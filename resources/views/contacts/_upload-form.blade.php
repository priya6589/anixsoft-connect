<form method="POST" action="{{ route('contacts.import') }}" enctype="multipart/form-data">
    @csrf
    <div class="mb-3">
        <label for="file" class="form-label fw-semibold">CSV file</label>
        <input type="file" name="file" id="file" accept=".csv,text/csv"
               class="form-control @error('file') is-invalid @enderror" required>
        @error('file')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">LinkedIn <code>Connections.csv</code>, max 10 MB.</div>
    </div>
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-upload me-1"></i> Upload &amp; save contacts
    </button>
</form>
