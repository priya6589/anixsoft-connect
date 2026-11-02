<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\EmailDraft;
use App\Models\User;
use App\Services\ClaudeOutreachAi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AiOutreachTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        config(['outreach.api_key' => 'test-key', 'outreach.daily_limit' => 10, 'outreach.min_score' => 50]);
    }

    /**
     * Fake Claude: scores come from a map of contact id => [category, score].
     *
     * @param  array<int, array{0: string, 1: int}>  $scores
     */
    private function fakeAi(array $scores = []): void
    {
        $fake = new class($scores) extends ClaudeOutreachAi
        {
            public int $draftCalls = 0;

            public function __construct(private array $scores) {}

            public function classify(Collection $contacts): array
            {
                return $contacts->mapWithKeys(fn (Contact $c) => [$c->id => [
                    'category' => $this->scores[$c->id][0] ?? 'potential_client',
                    'score' => $this->scores[$c->id][1] ?? 80,
                    'reason' => 'Test reason for '.$c->first_name,
                ]])->all();
            }

            public function draftEmail(Contact $contact, ?EmailDraft $previous = null): array
            {
                $this->draftCalls++;

                return $previous
                    ? ['subject' => "New angle after: {$previous->subject}", 'body' => "Hi {$contact->first_name}, a fresh version."]
                    : ['subject' => "Hello {$contact->first_name}", 'body' => "Hi {$contact->first_name}, we are connected on LinkedIn."];
            }
        };

        $this->app->instance(ClaudeOutreachAi::class, $fake);
    }

    private function contact(array $attributes = []): Contact
    {
        static $n = 0;
        $n++;

        return Contact::create($attributes + [
            'first_name' => "Person{$n}",
            'last_name' => 'Test',
            'linkedin_url' => "https://www.linkedin.com/in/person{$n}",
            'email' => "person{$n}@example.com",
            'company' => 'Acme',
            'position' => 'CTO',
            'status' => Contact::STATUS_IMPORTED,
        ]);
    }

    public function test_ai_buttons_need_an_api_key(): void
    {
        config(['outreach.api_key' => null]);
        $this->contact();

        $this->post(route('outreach.classify'))->assertSessionHasErrors('ai');
        $this->post(route('outreach.drafts'))->assertSessionHasErrors('ai');
        $this->get('/')->assertSee('ANTHROPIC_API_KEY');

        $this->assertNull(Contact::first()->classified_at);
    }

    public function test_sorting_sets_category_score_and_status(): void
    {
        $cto = $this->contact();
        $student = $this->contact(['position' => 'Student']);
        $lowScore = $this->contact(['position' => 'Developer']);
        $this->fakeAi([
            $student->id => ['not_relevant', 90],
            $lowScore->id => ['partner', 30],
        ]);

        $this->post(route('outreach.classify'))->assertSessionHas('success');

        $cto->refresh();
        $this->assertSame('potential_client', $cto->category);
        $this->assertSame(80, $cto->ai_score);
        $this->assertSame(Contact::STATUS_QUALIFIED, $cto->status);
        $this->assertNotNull($cto->classified_at);

        $this->assertSame(Contact::STATUS_IMPORTED, $student->fresh()->status, "not a fit: status unchanged");
        $this->assertSame("not_relevant", $student->fresh()->category);
        $this->assertSame(Contact::STATUS_IMPORTED, $lowScore->fresh()->status);

        $this->get(route('contacts.index', ['category' => 'potential_client']))
            ->assertSee($cto->first_name)
            ->assertDontSee($student->first_name);
    }

    public function test_drafts_only_for_qualified_contacts_with_email_best_first_within_daily_limit(): void
    {
        $this->fakeAi();
        config(['outreach.daily_limit' => 2]);

        $best = $this->contact(['status' => Contact::STATUS_QUALIFIED, 'ai_score' => 95]);
        $second = $this->contact(['status' => Contact::STATUS_QUALIFIED, 'ai_score' => 70]);
        $third = $this->contact(['status' => Contact::STATUS_QUALIFIED, 'ai_score' => 60]);
        $noEmail = $this->contact(['status' => Contact::STATUS_QUALIFIED, 'ai_score' => 99, 'email' => null]);
        $notRelevant = $this->contact(['status' => Contact::STATUS_NOT_RELEVANT, 'ai_score' => 99]);

        $this->post(route('outreach.drafts'))->assertSessionHas('success');

        $this->assertSame(2, EmailDraft::count());
        $this->assertSame(Contact::STATUS_DRAFT_READY, $best->fresh()->status);
        $this->assertSame(Contact::STATUS_DRAFT_READY, $second->fresh()->status);
        $this->assertSame(Contact::STATUS_QUALIFIED, $third->fresh()->status);
        $this->assertCount(0, $noEmail->emailDrafts);
        $this->assertCount(0, $notRelevant->emailDrafts);

        // Limit reached for today.
        $this->post(route('outreach.drafts'))->assertSessionHasErrors('ai');
        $this->assertSame(2, EmailDraft::count());
    }

    public function test_draft_can_be_edited_approved_and_rejected(): void
    {
        $this->fakeAi();
        $a = $this->contact(['status' => Contact::STATUS_QUALIFIED, 'ai_score' => 90, 'ai_reason' => 'Test reason: CTO at a growing company']);
        $b = $this->contact(['status' => Contact::STATUS_QUALIFIED, 'ai_score' => 80]);
        $this->post(route('outreach.drafts'));

        $draftA = $a->emailDrafts()->first();
        $draftB = $b->emailDrafts()->first();

        $this->get(route('drafts.index'))->assertOk()->assertSee($draftA->subject);
        $this->get(route('drafts.edit', $draftA))->assertOk()->assertSee('Test reason');

        $this->put(route('drafts.update', $draftA), ['subject' => 'Edited subject', 'body' => 'Edited body'])
            ->assertSessionHas('success');

        // Approving sends it and moves the reviewer on to the next waiting draft.
        $this->post(route('drafts.approve', $draftA))->assertRedirect(route('drafts.edit', $draftB));
        $this->assertSame(EmailDraft::STATUS_SENT, $draftA->fresh()->status);
        $this->assertSame('Edited subject', $draftA->fresh()->subject);
        $this->assertSame(Contact::STATUS_SENT, $a->fresh()->status);
        $this->assertSame(['Edited subject'], $this->sentSubjects());

        $this->post(route('drafts.reject', $draftB))->assertRedirect(route('drafts.index'));
        $this->assertSame(EmailDraft::STATUS_REJECTED, $draftB->fresh()->status);
        $this->assertSame(Contact::STATUS_QUALIFIED, $b->fresh()->status);

        // Rejected drafts are read-only.
        $this->put(route('drafts.update', $draftB), ['subject' => 'x', 'body' => 'y'])->assertForbidden();
    }

    public function test_email_can_be_generated_and_regenerated_for_one_contact(): void
    {
        $this->fakeAi();
        $contact = $this->contact(['first_name' => 'Priya']);

        $this->get('/')->assertSee('Generate email');

        $response = $this->post(route('contacts.draft', $contact));
        $first = $contact->emailDrafts()->sole();
        $response->assertRedirect(route('drafts.edit', $first));
        $this->assertSame(Contact::STATUS_DRAFT_READY, $contact->fresh()->status);

        $this->get('/')->assertSee('Hello Priya')->assertSee('Regenerate')->assertSee('Review &amp; send', false);

        // Regenerating replaces the unsent draft and doesn't use up another contact of the daily limit.
        $this->post(route('contacts.draft', $contact))->assertSessionHas('success');

        $this->assertSame(1, $contact->emailDrafts()->where('status', EmailDraft::STATUS_DRAFT)->count());
        $this->assertSame(EmailDraft::STATUS_REJECTED, $first->fresh()->status);
        // The AI was given the old version, so the subject is new too.
        $this->assertSame('New angle after: Hello Priya', $contact->latestDraft()->first()->subject);
        $this->assertSame(9, app(\App\Services\OutreachQueue::class)->draftsLeftToday());

        // Nothing to write without an email address.
        $this->post(route('contacts.draft', $this->contact(['email' => null])))->assertForbidden();
    }

    public function test_approve_sends_text_on_screen_within_daily_limit(): void
    {
        $this->fakeAi();
        config(['outreach.daily_limit' => 1]);
        $a = $this->contact();
        $b = $this->contact();
        $this->post(route('contacts.draft', $a));
        $this->post(route('contacts.draft', $b));
        [$draftA, $draftB] = [$a->emailDrafts()->sole(), $b->emailDrafts()->sole()];

        // Unsaved edits on the review page are what gets sent.
        $this->post(route('drafts.approve', $draftA), ['subject' => 'Typed subject', 'body' => 'Typed body'])
            ->assertSessionHas('success');
        $this->assertSame(EmailDraft::STATUS_SENT, $draftA->fresh()->status);
        $this->assertSame(['Typed subject'], $this->sentSubjects());

        // Limit reached: approved now, sent by the next day's run.
        $this->post(route('drafts.approve', $draftB));
        $this->assertSame(EmailDraft::STATUS_APPROVED, $draftB->fresh()->status);
        $this->assertCount(1, $this->sentSubjects());

        $this->travel(1)->days();
        $this->artisan('outreach:send')->assertSuccessful();
        $this->assertSame(EmailDraft::STATUS_SENT, $draftB->fresh()->status);
        $this->assertSame(Contact::STATUS_SENT, $b->fresh()->status);

        // Emailed contacts can't get another email.
        $this->post(route('contacts.draft', $a))->assertForbidden();
    }

    public function test_failed_send_is_recorded_and_can_be_retried(): void
    {
        $this->fakeAi();
        $contact = $this->contact();
        $this->post(route('contacts.draft', $contact));
        $draft = $contact->emailDrafts()->sole();
        $draft->update(['status' => EmailDraft::STATUS_APPROVED]);

        (new \App\Jobs\SendOutreachEmail($draft->id))->failed(new \RuntimeException('SMTP login failed'));

        $this->assertSame(EmailDraft::STATUS_FAILED, $draft->fresh()->status);
        $this->assertSame(Contact::STATUS_FAILED, $contact->fresh()->status);
        $this->get(route('drafts.edit', $draft))->assertSee('SMTP login failed')->assertSee('Retry sending');

        $this->post(route('drafts.approve', $draft));
        $this->assertSame(EmailDraft::STATUS_SENT, $draft->fresh()->status);
        $this->assertNull($draft->fresh()->error);
    }

    public function test_approve_sends_without_a_queue_worker_and_shows_smtp_errors(): void
    {
        $this->fakeAi();
        config(['queue.default' => 'database']);
        $contact = $this->contact();
        $this->post(route('contacts.draft', $contact));
        $draft = $contact->emailDrafts()->sole();

        \Illuminate\Support\Facades\Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('SMTP login failed'));

        $this->post(route('drafts.approve', $draft))->assertRedirect(route('drafts.edit', $draft));
        $this->assertSame(EmailDraft::STATUS_FAILED, $draft->fresh()->status);
        $this->assertSame('SMTP login failed', $draft->fresh()->error);
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('jobs')->count(), 'nothing left waiting for a worker');
    }

    /**
     * @return list<string> subjects of emails sent through the "array" test mailer
     */
    private function sentSubjects(): array
    {
        return collect(app('mailer')->getSymfonyTransport()->messages())
            ->map(fn ($sent) => $sent->getOriginalMessage()->getSubject())
            ->values()
            ->all();
    }

    public function test_resorting_does_not_undo_outreach_progress(): void
    {
        $contact = $this->contact(['status' => Contact::STATUS_APPROVED]);
        $this->fakeAi([$contact->id => ['not_relevant', 10]]);

        $this->post(route('outreach.classify'));

        $this->assertSame(Contact::STATUS_APPROVED, $contact->fresh()->status);
        $this->assertSame('not_relevant', $contact->fresh()->category);
    }
}
