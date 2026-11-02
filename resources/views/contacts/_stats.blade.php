<div class="row g-3 mb-4">
    @foreach ([
        ['Total contacts', $stats['total'], 'people', 'primary', route('contacts.index')],
        ['With email', $stats['with_email'], 'envelope-check', 'success', route('contacts.index', ['email' => 'with'])],
        ['Without email', $stats['without_email'], 'envelope-x', 'warning', route('contacts.index', ['email' => 'without'])],
        ['Imports', $stats['imports'], 'file-earmark-arrow-up', 'secondary', route('contacts.import.form')],
    ] as [$label, $value, $icon, $color, $link])
        <div class="col-6 col-lg-3">
            <a href="{{ $link }}" class="card shadow-sm h-100 text-decoration-none">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi bi-{{ $icon }} fs-3 text-{{ $color }}"></i>
                    <div>
                        <div class="fs-4 fw-semibold lh-1">{{ number_format($value) }}</div>
                        <div class="small text-body-secondary">{{ $label }}</div>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>
