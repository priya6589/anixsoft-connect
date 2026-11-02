<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\EmailDraft;
use Illuminate\Support\Facades\DB;

/**
 * Writes one outreach email for a contact with Claude. A contact has at most one
 * unsent draft: writing a new one (regenerating) retires the previous one.
 */
class DraftWriter
{
    public function __construct(private ClaudeOutreachAi $ai) {}

    public function write(Contact $contact): EmailDraft
    {
        // Regenerating: Claude sees the current version so the new one really differs.
        $previous = $contact->emailDrafts()->whereIn('status', EmailDraft::UNSENT_STATUSES)->latest('id')->first();

        // Ask Claude first: if it fails, nothing in the database has changed.
        $email = $this->ai->draftEmail($contact, $previous);

        return DB::transaction(function () use ($contact, $email) {
            $contact->emailDrafts()
                ->whereIn('status', EmailDraft::UNSENT_STATUSES)
                ->update(['status' => EmailDraft::STATUS_REJECTED, 'approved_at' => null]);

            $draft = $contact->emailDrafts()->create($email + [
                'status' => EmailDraft::STATUS_DRAFT,
                'model' => $this->ai->model(),
            ]);

            $contact->update(['status' => Contact::STATUS_DRAFT_READY]);

            return $draft;
        });
    }
}
