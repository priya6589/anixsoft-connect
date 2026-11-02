<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Services\ClaudeOutreachAi;
use App\Services\HunterEmailFinder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FindContactEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    private const MAX_VERIFY_CHECKS = 3;

    /**
     * $foundEmail is set when re-checking an email Hunter was still verifying,
     * so the (paid) Email Finder search is not repeated.
     */
    public function __construct(
        public int $contactId,
        public ?string $foundEmail = null,
        public int $verifyChecks = 0,
    ) {}

    public function handle(HunterEmailFinder $hunter, ClaudeOutreachAi $ai): void
    {
        $contact = Contact::find($this->contactId);

        if (! $contact || $contact->email || $contact->email_checked_at) {
            return;
        }

        if (blank($contact->company)) {
            $this->record($contact, 'not_found');

            return;
        }

        $email = $this->foundEmail;
        $status = null;

        if (! $email) {
            $found = $hunter->isConfigured() ? $hunter->find($contact) : null;

            if (! $found || ! $found['email']) {
                // Hunter had nothing (or isn't set up): search the public web, unless the person opted out.
                if (($found['status'] ?? null) !== 'opted_out' && $ai->isConfigured()) {
                    $this->searchWeb($contact, $ai);

                    return;
                }

                $this->record($contact, $found['status'] ?? 'not_found');

                return;
            }

            $email = $found['email'];
            $status = $found['status'] === 'valid' ? 'valid' : null;
        }

        $status ??= $hunter->verify($email);

        // Hunter is still verifying: check again later, then give up as "unknown".
        if ($status === null) {
            if ($this->verifyChecks + 1 < self::MAX_VERIFY_CHECKS) {
                self::dispatch($contact->id, $email, $this->verifyChecks + 1)->delay(now()->addMinutes(2));

                return;
            }

            $status = 'unknown';
        }

        if ($status === 'valid') {
            $contact->fill(['email' => $email, 'email_source' => 'hunter']);
        }

        $this->record($contact, match ($status) {
            'valid' => 'valid',
            'opted_out' => 'opted_out',
            'invalid', 'disposable' => 'invalid',
            default => 'risky', // accept_all, webmail, unknown
        });
    }

    private function searchWeb(Contact $contact, ClaudeOutreachAi $ai): void
    {
        $found = $ai->findEmail($contact);

        if (! $found) {
            $this->record($contact, 'not_found');

            return;
        }

        // One company inbox is used for one contact only, so the company isn't emailed twice.
        if ($found['type'] === 'company' && Contact::where('email', $found['email'])->exists()) {
            $contact->email_source_url = $found['source_url'];
            $this->record($contact, 'shared');

            return;
        }

        $contact->fill([
            'email' => $found['email'],
            'email_source' => 'web',
            'email_source_url' => $found['source_url'],
        ]);

        $this->record($contact, $found['type'] === 'company' ? 'company' : 'found');
    }

    private function record(Contact $contact, string $emailStatus): void
    {
        $contact->fill([
            'email_status' => $emailStatus,
            'email_checked_at' => now(),
        ])->save();
    }
}
