<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\EmailReply;
use App\Services\ClaudeOutreachAi;
use App\Services\HunterEmailFinder;
use App\Services\OutreachQueue;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(ClaudeOutreachAi $ai, HunterEmailFinder $hunter, OutreachQueue $queue): View
    {
        return view('dashboard', [
            'stats' => Contact::stats(),
            'recentContacts' => Contact::latest('id')->take(8)->get(),
            // Everyone with an email, best AI score first, each with their latest email draft.
            'emailContacts' => Contact::whereNotNull('email')
                ->with('latestDraft')
                ->orderByDesc('ai_score')
                ->orderBy('id')
                ->paginate(10, pageName: 'emails'),
            'sendsLeftToday' => $queue->sendsLeftToday(),
            'latestReplies' => EmailReply::with('contact')->latest('received_at')->take(3)->get(),
            'imports' => ContactImport::latest()->take(5)->get(),
            'aiConfigured' => $ai->isConfigured(),
            'pendingJobs' => $queue->pendingJobs(),
            'draftsLeftToday' => $queue->draftsLeftToday(),
            'hunterConfigured' => $hunter->isConfigured(),
            'hunterRemaining' => $hunter->remainingSearches(),
            'lookupsLeftToday' => $queue->lookupsLeftToday(),
        ]);
    }
}
