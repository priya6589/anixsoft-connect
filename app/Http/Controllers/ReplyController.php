<?php

namespace App\Http\Controllers;

use App\Models\EmailReply;
use App\Services\ReplyInbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ReplyController extends Controller
{
    public function index(Request $request, ReplyInbox $inbox): View
    {
        $term = $this->searchTerm($request);
        $unread = $request->boolean('unread');

        $replies = EmailReply::with(['contact', 'emailDraft'])
            ->when($term, fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('contact', fn ($q) => $q->search($term))
                ->orWhere('from_name', 'like', "%{$term}%")
                ->orWhere('from_email', 'like', "%{$term}%")
                ->orWhere('subject', 'like', "%{$term}%")
                ->orWhere('body', 'like', "%{$term}%")))
            ->when($unread, fn ($q) => $q->whereNull('read_at'))
            ->latest('received_at')
            ->paginate(20)
            ->withQueryString();

        // Highlighted as new on this visit, then marked as read.
        $newIds = $replies->whereNull('read_at')->pluck('id');
        EmailReply::whereIn('id', $newIds)->update(['read_at' => now()]);

        return view('replies.index', [
            'replies' => $replies,
            'newIds' => $newIds,
            'term' => $term,
            'unread' => $unread,
            'inboxConfigured' => $inbox->isConfigured(),
        ]);
    }

    public function fetch(ReplyInbox $inbox): RedirectResponse
    {
        if (! $inbox->isConfigured()) {
            return back()->withErrors(['ai' => 'Add IMAP_HOST (and MAIL_USERNAME / MAIL_PASSWORD) to the .env file to read replies.']);
        }

        try {
            $count = $inbox->fetch();
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['ai' => 'Could not read the mailbox: '.Str::limit($e->getMessage(), 200)]);
        }

        return back()->with('success', $count ? "{$count} new ".Str::plural('reply', $count).' found.' : 'No new replies.');
    }
}
