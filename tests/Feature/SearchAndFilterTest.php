<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\EmailDraft;
use App\Models\EmailReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every search box and filter on the Contacts, Drafts and Replies pages.
 * Contact names are unique, so assertSee / assertDontSee on a name tells which rows are listed.
 */
class SearchAndFilterTest extends TestCase
{
    use RefreshDatabase;

    private Contact $asha;   // potential client, qualified, Hunter-verified email

    private Contact $ravi;   // recruiter, sent, company inbox found online

    private Contact $karan;  // partner, not relevant status, email from the CSV

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->asha = Contact::create([
            'first_name' => 'Asha', 'last_name' => 'Verma', 'company' => 'Acme Ltd', 'position' => 'CTO',
            'email' => 'asha@acme.test', 'email_status' => 'valid', 'category' => 'potential_client',
            'classified_at' => now(), 'status' => Contact::STATUS_QUALIFIED,
        ]);
        $this->ravi = Contact::create([
            'first_name' => 'Ravi', 'last_name' => 'Kumar', 'company' => 'Hire Fast', 'position' => 'Talent Lead',
            'email' => 'hello@hirefast.test', 'email_status' => 'company', 'category' => 'recruiter',
            'classified_at' => now(), 'status' => Contact::STATUS_SENT,
        ]);
        // Meena: not sorted yet, no email (lookup found nothing).
        Contact::create([
            'first_name' => 'Meena', 'last_name' => 'Shah', 'company' => 'Shah Textiles', 'position' => 'Owner',
            'email_status' => 'not_found', 'status' => Contact::STATUS_IMPORTED,
        ]);
        $this->karan = Contact::create([
            'first_name' => 'Karan', 'last_name' => 'Mehta', 'company' => 'Pixel Studio', 'position' => 'Designer',
            'email' => 'karan_m@pixel.test', 'category' => 'partner',
            'classified_at' => now(), 'status' => Contact::STATUS_NOT_RELEVANT,
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  list<string>  $expected  first names that must be listed; the others must not
     */
    private function assertContacts(array $query, array $expected): void
    {
        $response = $this->get(route('contacts.index', $query))->assertOk();

        foreach (['Asha', 'Ravi', 'Meena', 'Karan'] as $name) {
            in_array($name, $expected, true)
                ? $response->assertSee($name.' ', false)
                : $response->assertDontSee($name.' ', false);
        }
    }

    public function test_contact_search_matches_name_company_position_and_email(): void
    {
        $this->assertContacts(['q' => 'asha'], ['Asha']);
        $this->assertContacts(['q' => 'Asha Verma'], ['Asha']);
        $this->assertContacts(['q' => 'pixel studio'], ['Karan']);
        $this->assertContacts(['q' => 'talent'], ['Ravi']);
        $this->assertContacts(['q' => 'karan_m@'], ['Karan']);
        $this->assertContacts(['q' => '  shah  '], ['Meena']);
        $this->assertContacts(['q' => 'nobody here'], []);
        $this->get(route('contacts.index', ['q' => 'nobody here']))->assertSee('No contacts match these filters.');
    }

    public function test_contact_status_filter(): void
    {
        $this->assertContacts(['status' => Contact::STATUS_QUALIFIED], ['Asha']);
        $this->assertContacts(['status' => Contact::STATUS_SENT], ['Ravi']);
        $this->assertContacts(['status' => Contact::STATUS_NOT_RELEVANT], ['Karan']);
        $this->assertContacts(['status' => Contact::STATUS_REPLIED], []);
    }

    public function test_contact_category_filter_including_not_sorted(): void
    {
        $this->assertContacts(['category' => 'potential_client'], ['Asha']);
        $this->assertContacts(['category' => 'recruiter'], ['Ravi']);
        $this->assertContacts(['category' => 'partner'], ['Karan']);
        $this->assertContacts(['category' => 'unsorted'], ['Meena']);
    }

    public function test_contact_email_filter_and_email_lookup_results(): void
    {
        $this->assertContacts(['email' => 'with'], ['Asha', 'Ravi', 'Karan']);
        $this->assertContacts(['email' => 'without'], ['Meena']);
        $this->assertContacts(['email' => 'valid'], ['Asha']);
        $this->assertContacts(['email' => 'company'], ['Ravi']);
        $this->assertContacts(['email' => 'not_found'], ['Meena']);
        $this->assertContacts(['email' => 'opted_out'], []);
    }

    public function test_contact_filters_combine(): void
    {
        $this->assertContacts(['email' => 'with', 'category' => 'recruiter'], ['Ravi']);
        $this->assertContacts(['q' => 'acme', 'status' => Contact::STATUS_SENT], []);
        $this->assertContacts(['q' => 'a', 'email' => 'with', 'status' => Contact::STATUS_QUALIFIED], ['Asha']);
    }

    public function test_unknown_or_malformed_contact_filters_are_ignored(): void
    {
        $all = ['Asha', 'Ravi', 'Meena', 'Karan'];

        $this->assertContacts(['status' => 'bogus', 'category' => 'bogus', 'email' => 'bogus'], $all);
        $this->assertContacts(['status' => ['qualified'], 'q' => ['asha']], $all);
        $this->assertContacts(['q' => '   '], $all);
    }

    public function test_contact_filters_show_their_selection_and_survive_pagination(): void
    {
        foreach (range(1, 30) as $i) {
            Contact::create(['first_name' => "Lead{$i}", 'last_name' => 'Test', 'email' => "lead{$i}@x.test", 'status' => Contact::STATUS_QUALIFIED]);
        }

        $this->get(route('contacts.index', ['status' => 'qualified', 'email' => 'with', 'category' => 'unsorted']))
            ->assertOk()
            ->assertSee('<option value="qualified" selected>', false)
            ->assertSee('<option value="with" selected>', false)
            ->assertSee('<option value="unsorted" selected>', false)
            ->assertSee('category=unsorted', false)
            ->assertSee('email=with', false)
            ->assertSee('page=2', false);

        $this->get(route('contacts.index', ['status' => 'qualified', 'email' => 'with', 'category' => 'unsorted', 'page' => 2]))
            ->assertOk()
            ->assertSee('Lead1 ', false)
            ->assertDontSee('Asha ', false);
    }

    public function test_dashboard_and_stat_cards_link_to_matching_filters(): void
    {
        $this->get(route('dashboard'))
            ->assertSee(route('contacts.index', ['category' => 'unsorted']), false)
            ->assertSee(route('contacts.index', ['status' => 'qualified']), false);

        $this->get(route('contacts.index'))
            ->assertSee(route('contacts.index', ['email' => 'with']), false)
            ->assertSee(route('contacts.index', ['email' => 'without']), false);
    }

    private function draft(Contact $contact, string $subject, string $status): EmailDraft
    {
        return $contact->emailDrafts()->create(['subject' => $subject, 'body' => 'Hello', 'status' => $status]);
    }

    public function test_draft_status_tabs_and_search(): void
    {
        $this->draft($this->asha, 'Your new web app', EmailDraft::STATUS_DRAFT);
        $this->draft($this->ravi, 'Hiring help for Hire Fast', EmailDraft::STATUS_SENT);
        $this->draft($this->karan, 'Design partnership', EmailDraft::STATUS_REJECTED);

        // Default tab: waiting for review.
        $this->get(route('drafts.index'))->assertSee('Your new web app')->assertDontSee('Hiring help');
        $this->get(route('drafts.index', ['status' => 'sent']))->assertSee('Hiring help')->assertDontSee('Your new web app');
        $this->get(route('drafts.index', ['status' => 'all']))->assertSee('Your new web app')->assertSee('Hiring help')->assertSee('Design partnership');
        $this->get(route('drafts.index', ['status' => 'bogus']))->assertSee('Your new web app')->assertDontSee('Hiring help');

        // Search by subject, contact name, company or email, within the chosen tab.
        $this->get(route('drafts.index', ['status' => 'all', 'q' => 'partnership']))->assertSee('Design partnership')->assertDontSee('Your new web app');
        $this->get(route('drafts.index', ['status' => 'all', 'q' => 'Ravi Kumar']))->assertSee('Hiring help')->assertDontSee('Design partnership');
        $this->get(route('drafts.index', ['status' => 'all', 'q' => 'acme']))->assertSee('Your new web app')->assertDontSee('Hiring help');
        $this->get(route('drafts.index', ['status' => 'draft', 'q' => 'Hire Fast']))->assertSee('No drafts match this search.');
        $this->get(route('drafts.index', ['status' => ['sent'], 'q' => ['x']]))->assertOk()->assertSee('Your new web app');

        // Switching tabs keeps the search.
        $this->get(route('drafts.index', ['q' => 'acme']))
            ->assertSee(route('drafts.index', ['status' => 'sent', 'q' => 'acme']));
    }

    private function reply(Contact $contact, string $subject, string $body, bool $read = false): EmailReply
    {
        static $n = 0;
        $n++;

        return EmailReply::create([
            'contact_id' => $contact->id, 'message_id' => "<r{$n}@mail.test>",
            'from_email' => $contact->email ?? 'x@mail.test', 'from_name' => $contact->full_name,
            'subject' => $subject, 'body' => $body, 'received_at' => now()->subMinutes($n),
            'read_at' => $read ? now() : null,
        ]);
    }

    public function test_reply_search_and_unread_filter(): void
    {
        $this->reply($this->asha, 'Re: Your new web app', 'Sounds good, call me Friday.');
        $this->reply($this->ravi, 'Re: Hiring help', 'Not interested, thanks.', read: true);

        $this->get(route('replies.index', ['unread' => 1]))
            ->assertSee('Sounds good')
            ->assertDontSee('Not interested')
            ->assertSee('id="unread" class="form-check-input" checked', false);

        // Opening the list marked Asha's reply read, so "Unread only" is now empty.
        $this->get(route('replies.index', ['unread' => 1]))->assertSee('No replies match these filters.');

        $this->get(route('replies.index', ['q' => 'friday']))->assertSee('Sounds good')->assertDontSee('Not interested');
        $this->get(route('replies.index', ['q' => 'hirefast.test']))->assertSee('Not interested')->assertDontSee('Sounds good');
        $this->get(route('replies.index', ['q' => 'Asha Verma']))->assertSee('Sounds good')->assertDontSee('Not interested');
        $this->get(route('replies.index', ['q' => 'Hiring help']))->assertSee('Not interested')->assertDontSee('Sounds good');
        $this->get(route('replies.index', ['q' => 'zzz']))->assertSee('No replies match these filters.');
        $this->get(route('replies.index', ['q' => ['bad']]))->assertOk()->assertSee('Sounds good');
    }
}
