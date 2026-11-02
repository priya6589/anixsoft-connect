<?php

namespace App\Http\Controllers;

use App\Jobs\SendOutreachEmail;
use App\Models\Contact;
use App\Models\EmailDraft;
use App\Services\ClaudeOutreachAi;
use App\Services\DraftWriter;
use App\Services\OutreachQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class EmailDraftController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', EmailDraft::STATUS_DRAFT);

        $drafts = EmailDraft::with('contact')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('drafts.index', [
            'drafts' => $drafts,
            'status' => $status,
            'counts' => EmailDraft::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function edit(EmailDraft $draft, OutreachQueue $queue): View
    {
        return view('drafts.edit', [
            'draft' => $draft->load('contact'),
            'sendsLeftToday' => $queue->sendsLeftToday(),
        ]);
    }

    public function update(Request $request, EmailDraft $draft): RedirectResponse
    {
        abort_unless($draft->isEditable(), 403, 'This draft can no longer be edited.');

        $draft->update($this->validatedText($request));

        return back()->with('success', 'Draft saved.');
    }

    /**
     * Write (or rewrite) the email for one contact right away, then open it for review.
     */
    public function generate(Contact $contact, ClaudeOutreachAi $ai, DraftWriter $writer): RedirectResponse
    {
        abort_unless($contact->canWriteEmail(), 403, 'This contact has no email address, or was already emailed.');

        if (! $ai->isConfigured()) {
            return back()->withErrors(['ai' => 'Add ANTHROPIC_API_KEY to the .env file to use the AI features.']);
        }

        $rewrite = $contact->emailDrafts()->whereIn('status', EmailDraft::UNSENT_STATUSES)->exists();

        try {
            $draft = $writer->write($contact);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['ai' => 'Claude could not write this email: '.Str::limit($e->getMessage(), 200)]);
        }

        return redirect()->route('drafts.edit', $draft)
            ->with('success', $rewrite ? 'New version written. The previous one was replaced.' : "Email written for {$contact->full_name}.");
    }

    /**
     * Approve and send. The review page posts the text on screen, so unsaved edits are kept.
     */
    public function approve(Request $request, EmailDraft $draft, OutreachQueue $queue): RedirectResponse
    {
        abort_unless($draft->canBeSent(), 403, 'Only drafts waiting for review can be approved.');

        if ($request->hasAny(['subject', 'body'])) {
            $draft->fill($this->validatedText($request));
        }

        $draft->fill(['status' => EmailDraft::STATUS_APPROVED, 'approved_at' => now(), 'error' => null])->save();
        $draft->contact->update(['status' => Contact::STATUS_APPROVED]);

        if ($queue->sendsLeftToday() === 0) {
            return $this->toNextDraft('Approved. Today\'s limit of '.config('outreach.daily_limit').' emails is reached, so it will be sent tomorrow.');
        }

        // Sent in this request: shared hosting has no always-on queue worker to pick it up.
        try {
            SendOutreachEmail::dispatchSync($draft->id);
        } catch (Throwable) {
            // The job's failed() saved the error: the review page shows it with "Retry sending".
            return redirect()->route('drafts.edit', $draft);
        }

        return $this->toNextDraft("Sent to {$draft->contact->email}.");
    }

    public function reject(EmailDraft $draft): RedirectResponse
    {
        abort_unless($draft->isEditable(), 403, 'This draft can no longer be rejected.');

        $draft->update(['status' => EmailDraft::STATUS_REJECTED, 'approved_at' => null]);
        // Back to qualified so a fresh draft can be generated later.
        $draft->contact->update(['status' => Contact::STATUS_QUALIFIED]);

        return $this->toNextDraft('Draft rejected.');
    }

    /**
     * @return array{subject: string, body: string}
     */
    private function validatedText(Request $request): array
    {
        return $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ]);
    }

    private function toNextDraft(string $message): RedirectResponse
    {
        $next = EmailDraft::where('status', EmailDraft::STATUS_DRAFT)->oldest('id')->first();

        return ($next ? redirect()->route('drafts.edit', $next) : redirect()->route('drafts.index'))
            ->with('success', $message);
    }
}
