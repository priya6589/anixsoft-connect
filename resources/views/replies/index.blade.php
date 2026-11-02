@extends('layouts.app')

@section('title', 'Replies')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-0">Replies</h1>
            <p class="text-body-secondary mb-0">Answers to your outreach emails. The mailbox is checked every 5 minutes.</p>
        </div>
        <form method="POST" action="{{ route('replies.fetch') }}" data-busy="Checking…">
            @csrf
            <button type="submit" class="btn btn-outline-primary">
                <i class="bi bi-arrow-clockwise me-1"></i> Check for replies now
            </button>
        </form>
    </div>

    @include('outreach._errors')

    @unless ($inboxConfigured)
        <div class="alert alert-warning small">
            <i class="bi bi-key me-1"></i> Add <code>IMAP_HOST</code> to <code>.env</code> (plus <code>MAIL_USERNAME</code> / <code>MAIL_PASSWORD</code>) so the app can read replies.
        </div>
    @endunless

    <div class="vstack gap-3">
        @forelse ($replies as $reply)
            @php($contact = $reply->contact)
            <div class="card shadow-sm @if ($newIds->contains($reply->id)) border-primary @endif">
                <div class="card-header bg-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <span class="fw-semibold">{{ $contact->full_name ?: $reply->from_name ?: $reply->from_email }}</span>
                        @if ($newIds->contains($reply->id))
                            <span class="badge text-bg-primary ms-1">New</span>
                        @endif
                        <div class="small text-body-secondary">
                            {{ $reply->from_email }}{{ $contact->company ? ' · '.$contact->company : '' }}
                        </div>
                    </div>
                    <span class="small text-body-secondary text-nowrap">{{ $reply->received_at?->format('d M Y, H:i') }}</span>
                </div>
                <div class="card-body">
                    @if ($reply->subject)
                        <div class="fw-semibold mb-2">{{ $reply->subject }}</div>
                    @endif
                    <div style="white-space: pre-line;">{{ $reply->newText() }}</div>

                    @if ($reply->emailDraft)
                        <details class="small mt-3">
                            <summary class="text-body-secondary">Your email: {{ $reply->emailDraft->subject }}</summary>
                            <div class="border rounded bg-body-tertiary p-3 mt-2" style="white-space: pre-line;">{{ $reply->emailDraft->body }}</div>
                        </details>
                    @endif
                </div>
                <div class="card-footer bg-body small d-flex flex-wrap gap-3">
                    <a href="mailto:{{ $reply->from_email }}?subject={{ rawurlencode('Re: '.($reply->subject ?? '')) }}">
                        <i class="bi bi-reply me-1"></i> Reply from your mail app
                    </a>
                    @if ($reply->emailDraft)
                        <a href="{{ route('drafts.edit', $reply->emailDraft) }}"><i class="bi bi-envelope me-1"></i> Open the sent email</a>
                    @endif
                    @if ($contact->linkedin_url)
                        <a href="{{ $contact->linkedin_url }}" target="_blank" rel="noopener"><i class="bi bi-linkedin me-1"></i> LinkedIn</a>
                    @endif
                </div>
            </div>
        @empty
            <div class="card shadow-sm">
                <div class="card-body text-center text-body-secondary py-5">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                    No replies yet. When someone answers one of your emails, it shows up here.
                </div>
            </div>
        @endforelse
    </div>

    @if ($replies->hasPages())
        <div class="mt-3">{{ $replies->links() }}</div>
    @endif
@endsection
