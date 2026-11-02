<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\EmailDraft;
use App\Services\OutreachQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOutreachEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public int $draftId) {}

    public function handle(OutreachQueue $queue): void
    {
        $draft = EmailDraft::with('contact')->find($this->draftId);

        // Re-check: the draft may have been sent, rejected or regenerated, or today's limit
        // reached, since this job was queued. Approved drafts left over go out with `outreach:send`.
        if (! $draft || $draft->status !== EmailDraft::STATUS_APPROVED || ! $draft->contact->email || $queue->sendsLeftToday() === 0) {
            return;
        }

        $contact = $draft->contact;

        $sent = Mail::raw($draft->body, fn (Message $message) => $message
            ->to($contact->email, $contact->full_name)
            ->subject($draft->subject));

        $draft->update([
            'status' => EmailDraft::STATUS_SENT,
            'sent_at' => now(),
            'error' => null,
            // Replies quote this ID, which is how they are matched back to this email.
            'message_id' => $sent ? trim($sent->getMessageId(), '<>') : null,
        ]);
        $contact->update(['status' => Contact::STATUS_SENT]);
    }

    public function failed(?Throwable $exception): void
    {
        $draft = EmailDraft::with('contact')->find($this->draftId);

        if (! $draft || $draft->status !== EmailDraft::STATUS_APPROVED) {
            return;
        }

        $draft->update([
            'status' => EmailDraft::STATUS_FAILED,
            'error' => mb_substr($exception?->getMessage() ?: 'Sending failed.', 0, 1000),
        ]);
        $draft->contact->update(['status' => Contact::STATUS_FAILED]);
    }
}
