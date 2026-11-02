<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\EmailDraft;
use App\Models\EmailReply;
use App\Models\User;
use App\Services\ReplyInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReplyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        config(['mail.from.address' => 'team@anixsoft.test']);
    }

    private function sentDraft(string $email, ?string $messageId = null): EmailDraft
    {
        $contact = Contact::create(['first_name' => 'Priya', 'last_name' => 'Tripathi', 'email' => $email, 'company' => 'Tripathi Fashion House', 'status' => Contact::STATUS_SENT]);

        return $contact->emailDrafts()->create([
            'subject' => 'Your e-commerce site', 'body' => 'Hi Priya, our original email.',
            'status' => EmailDraft::STATUS_SENT, 'sent_at' => now()->subHour(), 'message_id' => $messageId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mail(array $overrides = []): array
    {
        return $overrides + [
            'message_id' => '<reply-1@mail.gmail.com>',
            'in_reply_to' => [],
            'from_email' => 'someone@example.com',
            'from_name' => 'Someone',
            'subject' => 'Re: Your e-commerce site',
            'body' => "Yes, let's talk on Friday!\n\nOn Tue, 28 Oct 2026 at 16:00, Anixsoft <team@anixsoft.test> wrote:\n> Hi Priya, our original email.",
            'received_at' => now()->toDateTimeString(),
        ];
    }

    public function test_replies_are_matched_by_message_id_or_sender_and_saved_once(): void
    {
        $inbox = app(ReplyInbox::class);
        $byHeader = $this->sentDraft('priya@example.com', 'abc123@anixsoft.test');

        // Matched through the In-Reply-To header, even from a different address.
        $reply = $inbox->store($this->mail(['in_reply_to' => ['<abc123@anixsoft.test>'], 'from_email' => 'Assistant@Example.com']));
        $this->assertSame($byHeader->id, $reply->email_draft_id);
        $this->assertSame("Yes, let's talk on Friday!", $reply->newText());
        $this->assertSame(Contact::STATUS_REPLIED, $byHeader->contact->fresh()->status);

        // The same message fetched again is not saved twice.
        $this->assertNull($inbox->store($this->mail(['in_reply_to' => ['abc123@anixsoft.test']])));

        // Matched by sender: a contact we emailed, without reply headers.
        $bySender = $this->sentDraft('rahul@example.com');
        $this->assertSame($bySender->id, $inbox->store($this->mail(['message_id' => 'r2', 'from_email' => 'RAHUL@example.com']))->email_draft_id);

        // Unrelated inbox mail and our own sent copies are ignored.
        $this->assertNull($inbox->store($this->mail(['message_id' => 'r3', 'from_email' => 'newsletter@shop.test'])));
        $this->assertNull($inbox->store($this->mail(['message_id' => 'r4', 'from_email' => 'team@anixsoft.test'])));

        $this->assertSame(2, EmailReply::count());
    }

    public function test_replies_are_shown_and_marked_read(): void
    {
        $draft = $this->sentDraft('priya@example.com');
        app(ReplyInbox::class)->store($this->mail(['from_email' => 'priya@example.com']));

        $this->get('/')->assertSee('Latest replies')->assertSee("Yes, let&#039;s talk on Friday!", false);
        $this->get(route('drafts.edit', $draft))->assertSee('Reply from Someone');

        $this->get(route('replies.index'))->assertOk()->assertSee('New')->assertSee('Your email: Your e-commerce site');
        $this->assertNotNull(EmailReply::sole()->read_at);

        // Replied contacts don't get another outreach email.
        $this->post(route('contacts.draft', $draft->contact))->assertForbidden();
    }

    public function test_checking_needs_mailbox_settings(): void
    {
        config(['outreach.imap.host' => null]);

        $this->post(route('replies.fetch'))->assertSessionHasErrors('ai');
        $this->get(route('replies.index'))->assertSee('IMAP_HOST');
    }
}
