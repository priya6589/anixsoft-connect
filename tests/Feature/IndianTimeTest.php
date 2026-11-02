<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\EmailDraft;
use App\Models\User;
use App\Services\ReplyInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IndianTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function sentDraft(): EmailDraft
    {
        $contact = Contact::create(['first_name' => 'Priya', 'last_name' => 'Tripathi', 'email' => 'priya@example.com', 'status' => Contact::STATUS_SENT]);

        return $contact->emailDrafts()->create(['subject' => 'Hello', 'body' => 'Hi', 'status' => EmailDraft::STATUS_SENT, 'sent_at' => now()]);
    }

    public function test_app_runs_on_indian_time_and_shows_12_hour_times(): void
    {
        $this->assertSame('Asia/Kolkata', config('app.timezone'));

        // 11:23 UTC is 4:53 PM in India.
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-02 11:23:00', 'UTC'));
        $draft = $this->sentDraft();

        $this->assertSame('2026-11-02 16:53:00', $draft->fresh()->sent_at->toDateTimeString());
        $this->get(route('drafts.edit', $draft))->assertSee('Sent 02 Nov 2026, 04:53 PM');
    }

    public function test_reply_dates_are_converted_to_indian_time(): void
    {
        $draft = $this->sentDraft();

        $reply = app(ReplyInbox::class)->store([
            'message_id' => '<r1@mail.test>', 'in_reply_to' => [], 'from_email' => 'priya@example.com', 'from_name' => 'Priya',
            'subject' => 'Re: Hello', 'body' => 'Sure!', 'received_at' => 'Mon, 02 Nov 2026 11:23:00 +0000',
        ]);

        $this->assertSame('2026-11-02 16:53:00', $reply->fresh()->received_at->toDateTimeString());
        $this->get(route('replies.index'))->assertSee('02 Nov 2026, 04:53 PM');
        $this->assertSame($draft->id, $reply->email_draft_id);
    }

    public function test_migration_moves_old_utc_times_to_indian_time_but_not_plain_dates(): void
    {
        $contact = Contact::create(['first_name' => 'Old', 'last_name' => 'Record', 'connected_on' => '2026-01-15', 'status' => Contact::STATUS_IMPORTED]);
        DB::table('contacts')->where('id', $contact->id)->update(['classified_at' => '2026-11-02 11:23:00', 'email_checked_at' => null]);

        $migration = require database_path('migrations/2026_11_02_000009_shift_saved_times_to_indian_time.php');
        $migration->up();

        $row = DB::table('contacts')->find($contact->id);
        $this->assertSame('2026-11-02 16:53:00', $row->classified_at);
        $this->assertNull($row->email_checked_at);
        $this->assertStringStartsWith('2026-01-15', $row->connected_on);

        $migration->down();
        $this->assertSame('2026-11-02 11:23:00', DB::table('contacts')->find($contact->id)->classified_at);
    }
}
