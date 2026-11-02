@extends('layouts.app')

@section('title', 'Contacts')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="h3 mb-0">Contacts</h1>
        <div class="d-flex flex-wrap gap-2">
            @include('outreach._actions')
            <a href="{{ route('contacts.import.form') }}" class="btn btn-outline-secondary">
                <i class="bi bi-upload me-1"></i> Import LinkedIn CSV
            </a>
        </div>
    </div>

    @include('outreach._errors')

    @include('contacts._stats')

    <div class="card shadow-sm">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('contacts.index') }}" class="row g-2">
                <div class="col-md-4">
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control"
                           placeholder="Search name, company, position, email…">
                </div>
                <div class="col-6 col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $value => [$label])
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="category" class="form-select">
                        <option value="">All categories</option>
                        @foreach ($categories as $value => $label)
                            <option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="email" class="form-select">
                        <option value="">Any email</option>
                        <option value="with" @selected(request('email') === 'with')>Has email</option>
                        <option value="without" @selected(request('email') === 'without')>No email</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-fill">Filter</button>
                    @if (request()->hasAny(['q', 'status', 'category', 'email']))
                        <a href="{{ route('contacts.index') }}" class="btn btn-outline-secondary" title="Clear filters">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Company</th>
                        <th>Email</th>
                        <th>AI category</th>
                        <th>Status</th>
                        <th class="text-nowrap">Connected on</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contacts as $contact)
                        <tr>
                            <td class="fw-semibold text-nowrap">
                                {{ $contact->full_name ?: '—' }}
                                @if ($contact->linkedin_url)
                                    <a href="{{ $contact->linkedin_url }}" target="_blank" rel="noopener" class="ms-1" title="LinkedIn profile">
                                        <i class="bi bi-linkedin"></i>
                                    </a>
                                @endif
                            </td>
                            <td class="small">{{ $contact->position ?: '—' }}</td>
                            <td class="small">{{ $contact->company ?: '—' }}</td>
                            <td class="small">
                                @if ($contact->email)
                                    <a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>
                                    @if ($contact->email_source === 'hunter')
                                        <span class="badge text-bg-success" title="Found and verified by Hunter">Hunter</span>
                                    @elseif ($contact->email_source === 'web')
                                        <a href="{{ $contact->email_source_url }}" target="_blank" rel="noopener noreferrer"
                                           class="badge text-bg-info text-decoration-none"
                                           title="{{ $contact->email_status === 'company' ? 'General inbox on the company website' : 'Found on a public web page' }} – click to see where">
                                            {{ $contact->email_status === 'company' ? 'Company' : 'Web' }} <i class="bi bi-box-arrow-up-right"></i>
                                        </a>
                                    @endif
                                @elseif ($emailStatus = $contact->emailStatusBadge())
                                    <span class="badge text-bg-{{ $emailStatus[1] }}">{{ $emailStatus[0] }}</span>
                                @else
                                    <span class="text-body-secondary">Not available</span>
                                @endif
                            </td>
                            <td class="small text-nowrap" @if ($contact->ai_reason) title="{{ $contact->ai_reason }}" @endif>
                                @if ($contact->classified_at)
                                    {{ $contact->categoryLabel() }}
                                    <span class="badge text-bg-light border">{{ $contact->ai_score }}</span>
                                @else
                                    <span class="text-body-secondary">Not sorted</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge text-bg-{{ $contact->statusColor() }}">{{ $contact->statusLabel() }}</span>
                            </td>
                            <td class="small text-nowrap">{{ $contact->connected_on?->format('d M Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-body-secondary py-5">
                                @if (request()->hasAny(['q', 'status', 'category', 'email']))
                                    No contacts match these filters.
                                @else
                                    No contacts yet. <a href="{{ route('contacts.import.form') }}">Import your LinkedIn CSV</a> to get started.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($contacts->hasPages())
            <div class="card-footer bg-body">
                {{ $contacts->links() }}
            </div>
        @endif
    </div>
@endsection
