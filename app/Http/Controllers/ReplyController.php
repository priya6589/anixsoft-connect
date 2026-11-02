<?php

namespace App\Http\Controllers;

use App\Models\EmailReply;
use App\Services\ReplyInbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ReplyController extends Controller
{
    public function index(ReplyInbox $inbox): View
    {
        $replies = EmailReply::with(['contact', 'emailDraft'])
            ->latest('received_at')
            ->paginate(20);

        // Highlighted as new on this visit, then marked as read.
        $newIds = $replies->whereNull('read_at')->pluck('id');
        EmailReply::whereIn('id', $newIds)->update(['read_at' => now()]);

        return view('replies.index', [
            'replies' => $replies,
            'newIds' => $newIds,
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
