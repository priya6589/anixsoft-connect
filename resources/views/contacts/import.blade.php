@extends('layouts.app')

@section('title', 'Import contacts')

@section('content')
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-1">Import LinkedIn contacts</h1>
                    <p class="text-body-secondary mb-4">
                        Upload the <code>Connections.csv</code> file from your LinkedIn data export.
                        You can upload a newer export at any time: existing contacts are updated, not duplicated.
                    </p>

                    <form method="POST" action="{{ route('contacts.import') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="file" class="form-label fw-semibold">CSV file</label>
                            <input type="file" name="file" id="file" accept=".csv,text/csv"
                                   class="form-control @error('file') is-invalid @enderror" required>
                            @error('file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Max 10 MB.</div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-1"></i> Upload &amp; save contacts
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h6 fw-semibold">How to get the file from LinkedIn</h2>
                    <ol class="small mb-0 ps-3">
                        <li>LinkedIn → <strong>Me</strong> → <strong>Settings &amp; Privacy</strong> → <strong>Data privacy</strong>.</li>
                        <li>Open <strong>Get a copy of your data</strong>, tick <strong>Connections</strong>, and request the archive.</li>
                        <li>Download the archive when LinkedIn emails you, usually within about 10 minutes.</li>
                        <li>Upload the <code>Connections.csv</code> file from it here.</li>
                    </ol>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-body fw-semibold">Recent imports</div>
                <ul class="list-group list-group-flush">
                    @forelse ($imports as $import)
                        <li class="list-group-item small">
                            <div class="d-flex justify-content-between">
                                <span class="text-truncate me-2">{{ $import->original_filename }}</span>
                                <span class="text-body-secondary text-nowrap">{{ $import->created_at->format('d M Y, H:i') }}</span>
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
    </div>
@endsection
