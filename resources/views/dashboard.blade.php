@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-0">Dashboard</h1>
            <p class="text-body-secondary mb-0">Upload your LinkedIn contacts and track outreach.</p>
        </div>
        <a href="{{ route('contacts.index') }}" class="btn btn-outline-primary">
            <i class="bi bi-people me-1"></i> All contacts
        </a>
    </div>

    @include('contacts._stats')
    @include('outreach._errors')

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card shadow-sm mb-4 border-primary-subtle">
                <div class="card-header bg-body fw-semibold">
                    <i class="bi bi-stars me-1 text-primary"></i> AI outreach
                    <span class="badge text-bg-light border float-end fw-normal">{{ config('outreach.model') }}</span>
                </div>
                <div class="card-body">
                    @unless ($aiConfigured)
                        <div class="alert alert-warning small">
                            <i class="bi bi-key me-1"></i> Add <code>ANTHROPIC_API_KEY</code> to <code>.env</code> to turn on AI sorting and email writing.
                        </div>
                    @endunless

                    <div class="row text-center g-2 mb-3">
                        @foreach ([
                            ['Not sorted yet', $stats['unclassified'], route('contacts.index', ['category' => 'unsorted'])],
                            ['Qualified', $stats['qualified'], route('contacts.index', ['status' => 'qualified'])],
                            ['Drafts to review', $stats['drafts_pending'], route('drafts.index')],
                        ] as [$label, $value, $link])
                            <div class="col-4">
                                <a href="{{ $link }}" class="d-block border rounded py-2 text-decoration-none">
                                    <div class="fs-5 fw-semibold">{{ number_format($value) }}</div>
                                    <div class="small text-body-secondary">{{ $label }}</div>
                                </a>
                            </div>
                        @endforeach
                    </div>

                    @include('outreach._actions')

                    <div class="small text-body-secondary mt-2">
                        {{ $draftsLeftToday }} of {{ config('outreach.daily_limit') }} emails left to write today.
                    </div>

                    <div class="small border-top mt-3 pt-3">
                        <i class="bi bi-search me-1"></i> <strong>Email finder</strong>
                        <span class="text-body-secondary">({{ implode(' + ', array_filter([$hunterConfigured ? 'Hunter' : null, $aiConfigured ? 'web search' : null])) ?: 'off' }}):</span>
                        @if ($hunterConfigured || $aiConfigured)
                            {{ number_format($stats['qualified_no_email']) }} qualified contacts need an email ·
                            {{ $lookupsLeftToday }} of {{ config('outreach.lookup_daily_limit') }} lookups left today
                            @if ($hunterRemaining !== null)
                                · {{ number_format($hunterRemaining) }} Hunter credits left this month
                            @endif
                        @endif
                        @unless ($hunterConfigured)
                            <div class="text-warning-emphasis mt-1">
                                Add <code>HUNTER_API_KEY</code> to <code>.env</code> to also find and verify emails with Hunter.
                            </div>
                        @endunless
                    </div>

                    @if ($pendingJobs)
                        <div class="alert alert-info small mt-3 mb-0">
                            <i class="bi bi-hourglass-split me-1"></i> {{ $pendingJobs }} AI {{ Str::plural('job', $pendingJobs) }} waiting.
                            If this number doesn't go down, start the worker: <code>php artisan queue:work</code>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-body fw-semibold">
                    <i class="bi bi-cloud-upload me-1"></i> Upload LinkedIn contacts
                </div>
                <div class="card-body">
                    @include('contacts._upload-form')
                    <a href="{{ route('contacts.import.form') }}" class="d-inline-block small mt-3">
                        How do I download this file from LinkedIn?
                    </a>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-body fw-semibold">Recent imports</div>
                <ul class="list-group list-group-flush">
                    @forelse ($imports as $import)
                        <li class="list-group-item small">
                            <div class="d-flex justify-content-between">
                                <span class="text-truncate me-2">{{ $import->original_filename }}</span>
                                <span class="text-body-secondary text-nowrap">{{ $import->created_at->format('d M Y, h:i A') }}</span>
                            </div>
                            <span class="badge text-bg-success">{{ $import->created_count }} new</span>
                            <span class="badge text-bg-info">{{ $import->updated_count }} updated</span>
                            <span class="badge text-bg-light border">{{ $import->skipped_count }} skipped</span>
                        </li>
                    @empty
                        <li class="list-group-item small text-body-secondary">No imports yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-lg-7">
            @if ($latestReplies->isNotEmpty())
                <div class="card shadow-sm mb-4 border-success-subtle">
                    <div class="card-header bg-body d-flex justify-content-between align-items-center">
                        <span class="fw-semibold"><i class="bi bi-reply-fill me-1 text-success"></i> Latest replies</span>
                        <a href="{{ route('replies.index') }}" class="small">View all</a>
                    </div>
                    <div class="list-group list-group-flush">
                        @foreach ($latestReplies as $reply)
                            <a href="{{ route('replies.index') }}" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between gap-2">
                                    <span class="fw-semibold">
                                        {{ $reply->contact->full_name }}
                                        @unless ($reply->read_at)
                                            <span class="badge text-bg-primary ms-1">New</span>
                                        @endunless
                                    </span>
                                    <span class="small text-body-secondary text-nowrap">{{ $reply->received_at?->diffForHumans() }}</span>
                                </div>
                                <div class="small text-body-secondary text-truncate">{{ Str::limit($reply->newText(), 140) }}</div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="card shadow-sm mb-4" id="emails">
                <div class="card-header bg-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="fw-semibold"><i class="bi bi-envelope-paper me-1 text-primary"></i> Emails for each contact</span>
                    <span class="small text-body-secondary">{{ $sendsLeftToday }} of {{ config('outreach.daily_limit') }} emails left to send today</span>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($emailContacts as $contact)
                        @php($draft = $contact->latestDraft?->status === \App\Models\EmailDraft::STATUS_REJECTED ? null : $contact->latestDraft)
                        <li class="list-group-item py-3">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                <div class="me-auto">
                                    <div class="fw-semibold">{{ $contact->full_name ?: '—' }}</div>
                                    <div class="small text-body-secondary">
                                        {{ Str::limit($contact->position, 70) }}{{ $contact->company ? ' · '.$contact->company : '' }}
                                    </div>
                                    <div class="small mt-1">
                                        @if ($contact->classified_at)
                                            <span class="badge text-bg-light border">{{ $contact->categoryLabel() }} · {{ $contact->ai_score }}</span>
                                        @else
                                            <span class="badge text-bg-light border">Not sorted</span>
                                        @endif
                                        <span class="text-body-secondary">{{ $contact->email }}</span>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    @if ($draft)
                                        <span class="badge text-bg-{{ $draft->statusColor() }}">
                                            {{ $draft->statusLabel() }}{{ $draft->sent_at ? ' '.$draft->sent_at->format('d M') : '' }}
                                        </span>
                                        @if ($draft->isEditable())
                                            <a href="{{ route('drafts.edit', $draft) }}" class="btn btn-sm btn-primary">
                                                <i class="bi bi-send me-1"></i> Review &amp; send
                                            </a>
                                        @endif
                                    @endif
                                    @if ($contact->canWriteEmail())
                                        <form method="POST" action="{{ route('contacts.draft', $contact) }}" data-busy="Writing…">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                                @if ($draft)
                                                    <i class="bi bi-arrow-repeat me-1"></i> Regenerate
                                                @else
                                                    <i class="bi bi-stars me-1"></i> Generate email
                                                @endif
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                            @if ($draft)
                                <details class="small mt-2">
                                    <summary>
                                        <strong>{{ $draft->subject }}</strong>
                                        <span class="text-body-secondary">— {{ Str::limit($draft->body, 80) }}</span>
                                    </summary>
                                    <div class="border rounded bg-body-tertiary p-3 mt-2" style="white-space: pre-line;">{{ $draft->body }}</div>
                                </details>
                            @endif
                        </li>
                    @empty
                        <li class="list-group-item text-center text-body-secondary py-4">
                            No contacts with an email yet. Import contacts or run <strong>Find missing emails</strong>.
                        </li>
                    @endforelse
                </ul>
                @if ($emailContacts->hasPages())
                    <div class="card-footer bg-body">{{ $emailContacts->fragment('emails')->links() }}</div>
                @endif
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-body d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Recently added contacts</span>
                    <a href="{{ route('contacts.index') }}" class="small">View all</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Company</th>
                                <th>Email</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentContacts as $contact)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $contact->full_name ?: '—' }}</div>
                                        <div class="small text-body-secondary">{{ $contact->position }}</div>
                                    </td>
                                    <td class="small">{{ $contact->company ?: '—' }}</td>
                                    <td class="small">
                                        {!! $contact->email ? e($contact->email) : '<span class="text-body-secondary">Not available</span>' !!}
                                    </td>
                                    <td>
                                        <span class="badge text-bg-{{ $contact->statusColor() }}">{{ $contact->statusLabel() }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-body-secondary py-5">
                                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                        No contacts yet. Upload your LinkedIn CSV to get started.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
