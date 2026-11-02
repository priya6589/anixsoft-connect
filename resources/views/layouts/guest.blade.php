<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Log in') · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
    <main class="container d-flex flex-column justify-content-center align-items-center min-vh-100 py-4">
        <div class="text-center mb-4">
            <i class="bi bi-people-fill fs-1 text-primary"></i>
            <div class="h4 fw-semibold mb-0">{{ config('app.name') }}</div>
        </div>

        <div class="card shadow-sm w-100" style="max-width: 420px;">
            <div class="card-body p-4">
                @if (session('status'))
                    <div class="alert alert-success small" role="alert">{{ session('status') }}</div>
                @endif

                @yield('content')
            </div>
        </div>
    </main>
</body>
</html>
