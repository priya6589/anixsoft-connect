<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Contacts') · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-expand-md bg-dark" data-bs-theme="dark">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="{{ route('dashboard') }}">
                <i class="bi bi-people-fill me-1"></i> {{ config('app.name') }}
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('dashboard')) active @endif" href="{{ route('dashboard') }}">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('contacts.index')) active @endif" href="{{ route('contacts.index') }}">Contacts</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('drafts.*')) active @endif" href="{{ route('drafts.index') }}">Drafts</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('contacts.import*')) active @endif" href="{{ route('contacts.import.form') }}">Import</a>
                    </li>
                    <li class="nav-item">
                        @php($unreadReplies = \App\Models\EmailReply::whereNull('read_at')->count())
                        <a class="nav-link @if (request()->routeIs('replies.*')) active @endif" href="{{ route('replies.index') }}">
                            Replies
                            @if ($unreadReplies)
                                <span class="badge rounded-pill text-bg-danger">{{ $unreadReplies }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('skills.*')) active @endif" href="{{ route('skills.index') }}">Skills</a>
                    </li>
                    <li class="nav-item dropdown ms-md-2">
                        <a class="nav-link dropdown-toggle @if (request()->routeIs('profile.*')) active @endif" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> {{ auth()->user()->name }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-gear me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Log out</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Slow actions (Claude writing an email): show progress and block double clicks.
        document.querySelectorAll('form[data-busy]').forEach((form) => form.addEventListener('submit', () => {
            const button = form.querySelector('[type=submit]');
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> ' + form.dataset.busy;
        }));
    </script>
</body>
</html>
