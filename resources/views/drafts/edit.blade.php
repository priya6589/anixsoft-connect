@extends('layouts.app')

@section('title', 'Review draft')

@section('content')
    @php($contact = $draft->contact)

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <a href="{{ route('drafts.index') }}" class="small"><i class="bi bi-arrow-left"></i> All drafts</a>
            <h1 class="h3 mb-0 mt-1">Review email</h1>
        </div>
        <span class="badge fs-6 text-bg-{{ $draft->statusColor() }}">{{ $draft->statusLabel() }}</span>
    </div>

    @include('outreach._errors')

    <div class="row g-4">
        <div class="col-lg-4 order-lg-2">
            <div class="card shadow-sm">
                <div class="card-header bg-body fw-semibold">Recipient</div>
                <div class="card-body small">
                    <div class="fw-semibold fs-6">
                        {{ $contact->full_name }}
                        @if ($contact->linkedin_url)
                            <a href="{{ $contact->linkedin_url }}" target="_blank" rel="noopener"><i class="bi bi-linkedin"></i></a>
                        @endif
                    </div>
                    <div class="text-body-secondary mb-2">{{ $contact->position }}{{ $contact->company ? ' at '.$contact->company : '' }}</div>
                    <div class="mb-2"><i class="bi bi-envelope me-1"></i> {{ $contact->email }}</div>
                    @if ($contact->website_url)
                        <div class="mb-2 text-break">
                            <i class="bi bi-globe me-1"></i>
                            <a href="{{ $contact->website_url }}" target="_blank" rel="noopener noreferrer">{{ preg_replace('#^https?://(www\.)?#i', '', rtrim($contact->website_url, '/')) }}</a>
                            @if ($contact->website_tech)
                                <span class="badge text-bg-light border ms-1" title="What the AI saw the website is built with">{{ $contact->website_tech }}</span>
                            @endif
                        </div>
                    @endif
                    <div class="mb-2">
                        <span class="badge text-bg-primary">{{ $contact->categoryLabel() }}</span>
                        <span class="badge text-bg-light border">Score {{ $contact->ai_score ?? '—' }}</span>
                    </div>
                    @if ($contact->ai_reason)
                        <div class="text-body-secondary"><i class="bi bi-stars me-1"></i>{{ $contact->ai_reason }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8 order-lg-1">
            <div class="card shadow-sm">
                <div class="card-body">
                    @if ($draft->error)
                        <div class="alert alert-danger small">
                            <i class="bi bi-exclamation-octagon me-1"></i> <strong>Sending failed:</strong> {{ $draft->error }}
                        </div>
                    @endif

                    @if ($draft->isEditable())
                        <form method="POST" action="{{ route('drafts.update', $draft) }}" id="draft-form">
                            @csrf
                            <div class="mb-3">
                                <label for="subject" class="form-label fw-semibold">Subject</label>
                                <input type="text" name="subject" id="subject" value="{{ old('subject', $draft->subject) }}"
                                       class="form-control @error('subject') is-invalid @enderror" required maxlength="255">
                                @error('subject')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="body" class="form-label fw-semibold">Email</label>
                                <textarea name="body" id="body" rows="14"
                                          class="form-control @error('body') is-invalid @enderror" required>{{ old('body', $draft->body) }}</textarea>
                                @error('body')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </form>

                        <div class="d-flex flex-wrap gap-2">
                            @if ($draft->canBeSent())
                                <button type="submit" form="draft-form" formaction="{{ route('drafts.approve', $draft) }}" class="btn btn-success">
                                    <i class="bi bi-send-check me-1"></i>
                                    {{ $draft->status === \App\Models\EmailDraft::STATUS_FAILED ? 'Retry sending' : 'Approve & send' }}
                                </button>
                            @endif
                            <form method="POST" action="{{ route('contacts.draft', $contact) }}" data-busy="Writing…">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary" title="Ask Claude for a new version">
                                    <i class="bi bi-arrow-repeat me-1"></i> Regenerate
                                </button>
                            </form>
                            <button type="submit" form="draft-form" class="btn btn-outline-secondary">
                                <i class="bi bi-save me-1"></i> Save changes
                            </button>
                            <form method="POST" action="{{ route('drafts.reject', $draft) }}" class="ms-auto">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="bi bi-x-circle me-1"></i> Reject
                                </button>
                            </form>
                        </div>
                        <div class="form-text">
                            @if ($draft->status === \App\Models\EmailDraft::STATUS_APPROVED && $sendsLeftToday > 0)
                                <i class="bi bi-hourglass-split me-1"></i> Approved and in the sending queue. It goes out within seconds while
                                <code>php artisan queue:work</code> is running. If it stays here, start the worker.
                            @elseif ($draft->status === \App\Models\EmailDraft::STATUS_APPROVED)
                                Approved. Today's limit of {{ config('outreach.daily_limit') }} emails is reached, so it goes out tomorrow at 10:00.
                                You can still edit, regenerate or reject it.
                            @else
                                <strong>Approve &amp; send</strong> sends the text exactly as shown above, including your edits, to {{ $contact->email }}.
                            @endif
                        </div>
                    @else
                        <div class="fw-semibold mb-2">{{ $draft->subject }}</div>
                        <div style="white-space: pre-line;">{{ $draft->body }}</div>
                        @if ($draft->sent_at)
                            <div class="small text-body-secondary mt-3"><i class="bi bi-send-check me-1"></i> Sent {{ $draft->sent_at->format('d M Y, h:i A') }}</div>
                        @endif
                    @endif
                </div>
            </div>

            @foreach ($draft->replies as $reply)
                <div class="card shadow-sm border-success mt-3">
                    <div class="card-header bg-body d-flex justify-content-between">
                        <span class="fw-semibold"><i class="bi bi-reply-fill me-1 text-success"></i> Reply from {{ $reply->from_name ?: $reply->from_email }}</span>
                        <span class="small text-body-secondary">{{ $reply->received_at?->format('d M Y, h:i A') }}</span>
                    </div>
                    <div class="card-body" style="white-space: pre-line;">{{ $reply->newText() }}</div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
