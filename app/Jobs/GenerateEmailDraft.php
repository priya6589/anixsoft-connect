<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Services\DraftWriter;
use App\Services\OutreachQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateEmailDraft implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public int $contactId) {}

    public function handle(DraftWriter $writer, OutreachQueue $queue): void
    {
        $contact = Contact::find($this->contactId);

        // Re-check: the contact may have changed, or the daily limit been reached, since this job was queued.
        if (! $contact || ! $contact->email || $contact->status !== Contact::STATUS_QUALIFIED || $queue->draftsLeftToday() === 0) {
            return;
        }

        $writer->write($contact);
    }
}
