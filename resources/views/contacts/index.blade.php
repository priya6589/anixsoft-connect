@extends('layouts.app')

@section('title', 'Contacts')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="h3 mb-0">Contacts</h1>
        <a href="{{ route('contacts.import.form') }}" class="btn btn-primary">
            <i class="bi bi-upload me-1"></i> Import LinkedIn CSV
        </a>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([
            ['Total contacts', $stats['total'], 'people', 'primary'],
            ['With email', $stats['with_email'], 'envelope-check', 'success'],
            ['Without email', $stats['without_email'], 'envelope-x', 'warning'],
            ['Imports', $stats['imports'], 'file-earmark-arrow-up', 'secondary'],
        ] as [$label, $value, $icon, $color])
            <div class="col-6 col-lg-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <i class="bi bi-{{ $icon }} fs-3 text-{{ $color }}"></i>
                        <div>
                            <div class="fs-4 fw-semibold lh-1">{{ number_format($value) }}</div>
                            <div class="small text-body-secondary">{{ $label }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card shadow-sm">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('contacts.index') }}" class="row g-2">
                <div class="col-md-5">
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control"
                           placeholder="Search name, company, position, email…">
                </div>
                <div class="col-6 col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $value => [$label])
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
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
                    @if (request()->hasAny(['q', 'status', 'email']))
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
                                @else
                                    <span class="text-body-secondary">Not available</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge text-bg-{{ $contact->statusColor() }}">{{ $contact->statusLabel() }}</span>
                            </td>
                            <td class="small text-nowrap">{{ $contact->connected_on?->format('d M Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-body-secondary py-5">
                                @if (request()->hasAny(['q', 'status', 'email']))
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
