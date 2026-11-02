<?php

namespace App\Http\Controllers;

use App\Services\ClaudeOutreachAi;
use App\Services\HunterEmailFinder;
use App\Services\OutreachQueue;
use Illuminate\Http\RedirectResponse;

class OutreachController extends Controller
{
    public function __construct(private ClaudeOutreachAi $ai, private OutreachQueue $queue) {}

    public function findEmails(HunterEmailFinder $hunter): RedirectResponse
    {
        if (! $hunter->isConfigured() && ! $this->ai->isConfigured()) {
            return back()->withErrors(['ai' => 'Add ANTHROPIC_API_KEY (web search) or HUNTER_API_KEY to the .env file to find missing emails.']);
        }

        if ($this->queue->lookupsLeftToday() === 0) {
            return back()->withErrors(['ai' => 'Today\'s limit of '.config('outreach.lookup_daily_limit').' email lookups is reached. Try again tomorrow.']);
        }

        $count = $this->queue->queueEmailLookups();

        return back()->with('success', $count
            ? "Looking up emails for {$count} contacts. Only verified or publicly listed emails will be saved."
            : 'No qualified contacts are missing an email. Run AI sorting first if you haven\'t.');
    }

    public function classify(): RedirectResponse
    {
        if ($error = $this->missingKeyError()) {
            return $error;
        }

        $count = $this->queue->queueClassification();

        return back()->with('success', $count
            ? "AI sorting started for {$count} contacts. Results appear as each batch finishes."
            : 'All contacts are already sorted.');
    }

    public function generateDrafts(): RedirectResponse
    {
        if ($error = $this->missingKeyError()) {
            return $error;
        }

        if ($this->queue->draftsLeftToday() === 0) {
            return back()->withErrors(['ai' => 'Today\'s limit of '.config('outreach.daily_limit').' drafts is reached. Try again tomorrow.']);
        }

        $count = $this->queue->queueDrafts();

        return back()->with('success', $count
            ? "Writing {$count} email drafts. They will appear under Drafts for review."
            : 'No qualified contacts with an email address are waiting for a draft.');
    }

    private function missingKeyError(): ?RedirectResponse
    {
        return $this->ai->isConfigured()
            ? null
            : back()->withErrors(['ai' => 'Add ANTHROPIC_API_KEY to the .env file to use the AI features.']);
    }
}
