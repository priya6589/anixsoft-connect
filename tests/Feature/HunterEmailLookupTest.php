<?php

namespace Tests\Feature;

use App\Jobs\FindContactEmail;
use App\Models\Contact;
use App\Models\User;
use App\Services\ClaudeOutreachAi;
use App\Services\HunterEmailFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HunterEmailLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        // Web search (Claude) is off unless a test turns it on with fakeWebSearch().
        config(['outreach.hunter_key' => 'test-hunter-key', 'outreach.api_key' => null, 'outreach.lookup_daily_limit' => 10]);
        Http::preventStrayRequests();
    }

    /**
     * Fake Claude web search returning $result for every contact; returns the fake to count calls.
     *
     * @param  array{email: string, source_url: string, type: string}|null  $result
     */
    private function fakeWebSearch(?array $result): ClaudeOutreachAi
    {
        config(['outreach.api_key' => 'test-key']);

        $fake = new class($result) extends ClaudeOutreachAi
        {
            public int $calls = 0;

            public function __construct(private ?array $result) {}

            public function findEmail(Contact $contact): ?array
            {
                $this->calls++;

                return $this->result;
            }
        };

        $this->app->instance(ClaudeOutreachAi::class, $fake);

        return $fake;
    }

    public function test_web_search_is_used_when_hunter_finds_nothing(): void
    {
        $contact = $this->qualified();
        $this->fakeHunter(['email' => null]);
        $this->fakeWebSearch(['email' => 'asha@acme.com', 'source_url' => 'https://acme.com/team', 'type' => 'person']);

        $this->post(route('outreach.emails'))->assertSessionHas('success');

        $contact->refresh();
        $this->assertSame('asha@acme.com', $contact->email);
        $this->assertSame('web', $contact->email_source);
        $this->assertSame('found', $contact->email_status);
        $this->assertSame('https://acme.com/team', $contact->email_source_url);

        $this->get(route('contacts.index'))->assertSee('https://acme.com/team')->assertSee('Web');
    }

    public function test_company_website_inbox_is_used_once_per_company(): void
    {
        config(['outreach.hunter_key' => null]);
        $first = $this->qualified(['ai_score' => 90]);
        $second = $this->qualified(['ai_score' => 80]);
        $this->fakeWebSearch(['email' => 'contact@acme.com', 'source_url' => 'https://acme.com/contact', 'type' => 'company']);

        $this->post(route('outreach.emails'));

        $first->refresh();
        $this->assertSame('contact@acme.com', $first->email);
        $this->assertSame('company', $first->email_status);
        $this->assertNull($second->fresh()->email, 'same company inbox is not reused');
        $this->assertSame('shared', $second->fresh()->email_status);

        $this->get(route('contacts.index'))->assertSee('https://acme.com/contact')->assertSee('Company email already used');
    }

    public function test_web_search_works_without_hunter_and_respects_opt_outs(): void
    {
        config(['outreach.hunter_key' => null]);
        $web = $this->fakeWebSearch(null);
        $nothing = $this->qualified();

        $this->post(route('outreach.emails'))->assertSessionHas('success');

        $this->assertSame(1, $web->calls);
        $this->assertSame('not_found', $nothing->fresh()->email_status);
        $this->assertNull($nothing->fresh()->email);
        Http::assertNothingSent();

        // Someone who opted out with Hunter is never searched for on the web.
        config(['outreach.hunter_key' => 'test-hunter-key']);
        $optedOut = $this->qualified();
        $this->fakeHunter(451);
        FindContactEmail::dispatchSync($optedOut->id);

        $this->assertSame('opted_out', $optedOut->fresh()->email_status);
        $this->assertSame(1, $web->calls);
    }

    private function qualified(array $attributes = []): Contact
    {
        static $n = 0;
        $n++;

        return Contact::create($attributes + [
            'first_name' => "Asha{$n}",
            'last_name' => 'Verma',
            'company' => 'Acme Ltd',
            'position' => 'CTO',
            'status' => Contact::STATUS_QUALIFIED,
            'ai_score' => 80,
        ]);
    }

    /**
     * @param  array<string, mixed>|int  $finder  JSON body for a 200, or a bare status code
     * @param  array<string, mixed>|int|null  $verifier
     */
    private function fakeHunter(array|int $finder, array|int|null $verifier = null): void
    {
        $respond = fn ($spec) => is_int($spec) ? Http::response([], $spec) : Http::response(['data' => $spec]);

        // Fresh fake each call: Http::fake() otherwise keeps earlier stubs first in line.
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            'api.hunter.io/v2/email-finder*' => $respond($finder),
            'api.hunter.io/v2/email-verifier*' => $respond($verifier ?? 500),
            'api.hunter.io/v2/account*' => Http::response(['data' => ['requests' => ['searches' => ['remaining' => 42]]]]),
        ]);
    }

    public function test_verified_email_from_finder_is_saved_without_extra_verification(): void
    {
        $contact = $this->qualified();
        $this->fakeHunter(['email' => 'Asha@Acme.com', 'verification' => ['status' => 'valid']]);

        $this->post(route('outreach.emails'))->assertSessionHas('success');

        $contact->refresh();
        $this->assertSame('asha@acme.com', $contact->email);
        $this->assertSame('hunter', $contact->email_source);
        $this->assertSame('valid', $contact->email_status);
        $this->assertNotNull($contact->email_checked_at);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'email-finder')
            && $r['company'] === 'Acme Ltd'
            && $r['first_name'] === $contact->first_name
            && $r->header('X-API-KEY')[0] === 'test-hunter-key');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'email-verifier'));
    }

    public function test_unverified_email_is_checked_and_only_saved_when_valid(): void
    {
        $valid = $this->qualified();
        $this->fakeHunter(['email' => 'a@acme.com', 'verification' => null], ['status' => 'valid']);
        FindContactEmail::dispatchSync($valid->id);
        $this->assertSame('a@acme.com', $valid->fresh()->email);

        foreach (['accept_all' => 'risky', 'webmail' => 'risky', 'unknown' => 'risky', 'invalid' => 'invalid', 'disposable' => 'invalid'] as $hunterStatus => $expected) {
            $contact = $this->qualified();
            $this->fakeHunter(['email' => 'x@acme.com', 'verification' => null], ['status' => $hunterStatus]);

            FindContactEmail::dispatchSync($contact->id);

            $contact->refresh();
            $this->assertNull($contact->email, "{$hunterStatus} email must not be saved");
            $this->assertSame($expected, $contact->email_status);
        }
    }

    public function test_not_found_and_opted_out(): void
    {
        $notFound = $this->qualified();
        $this->fakeHunter(['email' => null]);
        FindContactEmail::dispatchSync($notFound->id);
        $this->assertSame('not_found', $notFound->fresh()->email_status);

        $optedOut = $this->qualified();
        $this->fakeHunter(451);
        FindContactEmail::dispatchSync($optedOut->id);
        $this->assertSame('opted_out', $optedOut->fresh()->email_status);
        $this->assertNull($optedOut->fresh()->email);

        $noCompany = $this->qualified(['company' => null]);
        FindContactEmail::dispatchSync($noCompany->id);
        $this->assertSame('not_found', $noCompany->fresh()->email_status);
    }

    public function test_still_verifying_rechecks_later_without_repeating_the_paid_search(): void
    {
        $contact = $this->qualified();
        $this->fakeHunter(['email' => 'a@acme.com', 'verification' => null], 202);
        Queue::fake();

        (new FindContactEmail($contact->id))->handle(app(HunterEmailFinder::class), app(ClaudeOutreachAi::class));

        Queue::assertPushed(FindContactEmail::class, fn ($job) => $job->foundEmail === 'a@acme.com' && $job->verifyChecks === 1);
        $this->assertNull($contact->fresh()->email_checked_at);

        // Last allowed check gives up as "risky" and never calls the finder again.
        $this->fakeHunter(['email' => 'a@acme.com', 'verification' => null], 202);
        (new FindContactEmail($contact->id, 'a@acme.com', 2))->handle(app(HunterEmailFinder::class), app(ClaudeOutreachAi::class));

        $this->assertSame('risky', $contact->fresh()->email_status);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'email-finder'));
    }

    public function test_only_qualified_contacts_without_email_are_looked_up_within_daily_limit(): void
    {
        config(['outreach.lookup_daily_limit' => 1]);
        $best = $this->qualified(['ai_score' => 95]);
        $second = $this->qualified(['ai_score' => 70]);
        $hasEmail = $this->qualified(['ai_score' => 99, 'email' => 'known@acme.com']);
        $notRelevant = $this->qualified(['ai_score' => 99, 'status' => Contact::STATUS_NOT_RELEVANT]);
        $this->fakeHunter(['email' => null]);

        $this->post(route('outreach.emails'));

        $this->assertSame('not_found', $best->fresh()->email_status);
        $this->assertNull($second->fresh()->email_checked_at);
        $this->assertNull($hasEmail->fresh()->email_checked_at);
        $this->assertNull($notRelevant->fresh()->email_checked_at);

        $this->post(route('outreach.emails'))->assertSessionHasErrors('ai');
    }

    public function test_needs_a_key_and_dashboard_shows_hunter_status(): void
    {
        config(['outreach.hunter_key' => null]);
        $this->qualified();

        $this->post(route('outreach.emails'))->assertSessionHasErrors('ai');
        $this->get('/')->assertSee('HUNTER_API_KEY');

        config(['outreach.hunter_key' => 'test-hunter-key']);
        $this->fakeHunter(['email' => null]);
        $this->get('/')->assertSee('42 Hunter credits left');
    }

    public function test_dashboard_survives_hunter_being_down(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('down'));

        $this->get('/')->assertOk()->assertSee('lookups left today');
    }

    public function test_contacts_list_shows_lookup_results(): void
    {
        $this->qualified(['email' => 'found@acme.com', 'email_source' => 'hunter', 'email_status' => 'valid']);
        $this->qualified(['email_status' => 'risky', 'email_checked_at' => now()]);

        $this->get(route('contacts.index'))
            ->assertSee('found@acme.com')
            ->assertSee('Hunter')
            ->assertSee('Risky – not used');
    }
}
