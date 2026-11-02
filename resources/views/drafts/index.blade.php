@extends('layouts.app')

@section('title', 'Email drafts')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="h3 mb-0">Email drafts</h1>
        @include('outreach._actions', ['only' => 'drafts'])
    </div>

    @include('outreach._errors')

    <ul class="nav nav-pills mb-3 flex-wrap">
        @foreach (['draft' => 'Waiting for review', 'approved' => 'Approved', 'sent' => 'Sent', 'rejected' => 'Rejected', 'failed' => 'Failed', 'all' => 'All'] as $value => $label)
            <li class="nav-item">
                <a class="nav-link @if ($status === $value) active @endif" href="{{ route('drafts.index', array_filter(['status' => $value, 'q' => $term])) }}">
                    {{ $label }}
                    @if ($value !== 'all')
                        <span class="badge text-bg-light border ms-1">{{ $counts[$value] ?? 0 }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>

    <div class="card shadow-sm">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('drafts.index') }}" class="d-flex gap-2">
                <input type="hidden" name="status" value="{{ $status }}">
                <input type="search" name="q" value="{{ $term }}" class="form-control"
                       placeholder="Search name, company, email or subject…">
                <button type="submit" class="btn btn-outline-primary">Search</button>
                @if ($term)
                    <a href="{{ route('drafts.index', ['status' => $status]) }}" class="btn btn-outline-secondary" title="Clear search">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </form>
        </div>

        <div class="list-group list-group-flush">
            @forelse ($drafts as $draft)
                <a href="{{ route('drafts.edit', $draft) }}" class="list-group-item list-group-item-action py-3">
                    <div class="d-flex justify-content-between gap-2">
                        <div class="text-truncate">
                            <span class="fw-semibold">{{ $draft->contact->full_name }}</span>
                            <span class="text-body-secondary small">· {{ $draft->contact->position }}{{ $draft->contact->company ? ' at '.$draft->contact->company : '' }}</span>
                        </div>
                        <span class="badge text-bg-{{ $draft->statusColor() }} align-self-start">{{ $draft->statusLabel() }}</span>
                    </div>
                    <div class="small mt-1"><strong>{{ $draft->subject }}</strong></div>
                    <div class="small text-body-secondary text-truncate">{{ Str::limit($draft->body, 160) }}</div>
                </a>
            @empty
                <div class="list-group-item text-center text-body-secondary py-5">
                    <i class="bi bi-envelope-paper fs-2 d-block mb-2"></i>
                    {{ $term ? 'No drafts match this search.' : 'No drafts here yet.' }}
                </div>
            @endforelse
        </div>

        @if ($drafts->hasPages())
            <div class="card-footer bg-body">{{ $drafts->links() }}</div>
        @endif
    </div>
@endsection
