<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Services\ClaudeOutreachAi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ClassifyContacts implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    /**
     * @param  list<int>  $contactIds
     */
    public function __construct(public array $contactIds) {}

    public function handle(ClaudeOutreachAi $ai): void
    {
        $contacts = Contact::whereIn('id', $this->contactIds)->get();

        if ($contacts->isEmpty()) {
            return;
        }

        $results = $ai->classify($contacts);
        $minScore = config('outreach.min_score');

        foreach ($contacts as $contact) {
            $result = $results[$contact->id] ?? null;

            if (! $result) {
                continue;
            }

            $contact->fill([
                'category' => $result['category'],
                'ai_score' => $result['score'],
                'ai_reason' => $result['reason'],
                'classified_at' => now(),
            ]);

            // Only good freelance prospects change status; the rest stay "imported" (their
            // category still says why). Never moves a contact backwards once drafting/sending has started.
            if ($contact->isBeforeOutreach()) {
                $contact->status = $result['category'] !== 'not_relevant' && $result['score'] >= $minScore
                    ? Contact::STATUS_QUALIFIED
                    : Contact::STATUS_IMPORTED;
            }

            $contact->save();
        }
    }
}
