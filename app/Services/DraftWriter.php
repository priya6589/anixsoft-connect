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

            $draft = $contact->emailDrafts()->create([
                'subject' => $email['subject'],
                'body' => $this->compose($contact, $email['body']),
                'status' => EmailDraft::STATUS_DRAFT,
                'model' => $this->ai->model(),
            ]);

            // Kept for the reviewer and the next regeneration; a known website is never wiped.
            $website = ($email['website_url'] ?? null)
                ? ['website_url' => $email['website_url'], 'website_tech' => $email['website_tech'] ?? null]
                : [];

            $contact->update($website + ['status' => Contact::STATUS_DRAFT_READY]);

            return $draft;
        });
    }

    /**
     * The same greeting and sign-off around every email, with Claude's text in the middle.
     */
    public function compose(Contact $contact, string $middle): string
    {
        $template = config('outreach.email');

        $greeting = match (true) {
            $contact->email_status === 'company' && filled($contact->company) => str_replace('{company}', $contact->company, $template['company_greeting']),
            filled($contact->first_name) => str_replace('{first_name}', $contact->first_name, $template['greeting']),
            default => 'Hello,',
        };

        $signature = implode("\n", array_filter([
            $template['sign_off'],
            config('outreach.sender_name'),
            config('outreach.signature_extra'),
        ]));

        return "{$greeting}\n\n".$this->stripGreetingAndSignOff($middle)."\n\n{$signature}\n\n{$template['opt_out']}";
    }

    /**
     * Claude is told to write only the middle; this removes a greeting or sign-off it added anyway.
     */
    private function stripGreetingAndSignOff(string $text): string
    {
        $lines = preg_split('/\R/', trim($text));

        if ($lines && preg_match('/^(dear|hi|hello|hey)\b[^.!?]{0,60},$/i', trim($lines[0]))) {
            array_shift($lines);
        }

        foreach ($lines as $i => $line) {
            if (preg_match('/^((best|kind|warm|thanks\s*(&|and))\s+)?regards,?$|^(sincerely|best|thanks|thank you|cheers),?$/i', trim($line))) {
                $lines = array_slice($lines, 0, $i);
                break;
            }
        }

        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    }
}
