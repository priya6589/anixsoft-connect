{{-- AI action buttons, in pipeline order. Pass ['only' => 'sort'|'emails'|'drafts'] to show just one. --}}
<div class="d-flex flex-wrap gap-2">
    @foreach ([
        'sort' => ['outreach.classify', 'stars', 'Run AI sorting', 'btn-outline-primary'],
        'emails' => ['outreach.emails', 'search', 'Find missing emails', 'btn-outline-primary'],
        'drafts' => ['outreach.drafts', 'pencil-square', "Write today's emails", 'btn-primary'],
    ] as $key => [$route, $icon, $label, $class])
        @if (! isset($only) || $only === $key)
            <form method="POST" action="{{ route($route) }}">
                @csrf
                <button type="submit" class="btn {{ $class }}">
                    <i class="bi bi-{{ $icon }} me-1"></i> {{ $label }}
                </button>
            </form>
        @endif
    @endforeach
</div>
