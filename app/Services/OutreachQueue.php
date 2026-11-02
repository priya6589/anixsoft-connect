<?php

namespace App\Services;

use App\Jobs\ClassifyContacts;
use App\Jobs\FindContactEmail;
use App\Jobs\GenerateEmailDraft;
use App\Jobs\SendOutreachEmail;
use App\Models\Contact;
use App\Models\EmailDraft;
use Illuminate\Support\Facades\DB;

/**
 * Picks which contacts need AI work and queues the jobs.
 */
class OutreachQueue
{
    /**
     * Queue every contact that has not been sorted yet. Returns how many were queued.
     */
    public function queueClassification(): int
    {
        $queued = 0;

        Contact::whereNull('classified_at')
            ->select('id')
            ->chunkById(config('outreach.classify_chunk'), function ($contacts) use (&$queued) {
                ClassifyContacts::dispatch($contacts->pluck('id')->all());
                $queued += $contacts->count();
            });

        return $queued;
    }

    /**
     * Queue drafts for the best qualified contacts that have an email, up to the daily limit.
     */
    public function queueDrafts(): int
    {
        $ids = Contact::where('status', Contact::STATUS_QUALIFIED)
            ->whereNotNull('email')
            ->orderByDesc('ai_score')
            ->limit($this->draftsLeftToday())
            ->pluck('id');

        $ids->each(fn (int $id) => GenerateEmailDraft::dispatch($id));

        return $ids->count();
    }

    /**
     * Queue Hunter lookups for the best qualified contacts without an email, up to the daily limit.
     */
    public function queueEmailLookups(): int
    {
        $ids = Contact::needsEmailLookup()
            ->orderByDesc('ai_score')
            ->limit($this->lookupsLeftToday())
            ->pluck('id');

        $ids->each(fn (int $id) => FindContactEmail::dispatch($id));

        return $ids->count();
    }

    public function lookupsLeftToday(): int
    {
        $today = Contact::whereDate('email_checked_at', today())->count();

        return max(0, config('outreach.lookup_daily_limit') - $today);
    }

    /**
     * Counts contacts, not drafts, so regenerating an email doesn't use up the daily limit.
     */
    public function draftsLeftToday(): int
    {
        $today = EmailDraft::whereDate('created_at', today())->distinct()->count('contact_id');

        return max(0, config('outreach.daily_limit') - $today);
    }

    public function sendsLeftToday(): int
    {
        $today = EmailDraft::whereDate('sent_at', today())->count();

        return max(0, config('outreach.daily_limit') - $today);
    }

    /**
     * Queue approved drafts that are still waiting (oldest approval first), up to today's sending limit.
     */
    public function queueApprovedSends(): int
    {
        $ids = EmailDraft::where('status', EmailDraft::STATUS_APPROVED)
            ->oldest('approved_at')
            ->limit($this->sendsLeftToday())
            ->pluck('id');

        $ids->each(fn (int $id) => SendOutreachEmail::dispatch($id));

        return $ids->count();
    }

    public function pendingJobs(): int
    {
        if (config('queue.default') !== 'database') {
            return 0;
        }

        return DB::table(config('queue.connections.database.table', 'jobs'))->count();
    }
}
