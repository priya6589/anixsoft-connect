@extends('layouts.guest')

@section('title', 'Log in')

@section('content')
    <h1 class="h5 mb-1">Log in</h1>
    <p class="text-body-secondary small mb-4">Sign in to manage your contacts.</p>

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="username">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <div class="d-flex justify-content-between">
                <label for="password" class="form-label">Password</label>
                <a href="{{ route('password.request') }}" class="small">Forgot password?</a>
            </div>
            <input type="password" name="password" id="password"
                   class="form-control @error('password') is-invalid @enderror" required autocomplete="current-password">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-check mb-4">
            <input type="checkbox" name="remember" id="remember" class="form-check-input" @checked(old('remember'))>
            <label for="remember" class="form-check-label">Remember me</label>
        </div>

        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-box-arrow-in-right me-1"></i> Log in
        </button>
    </form>
@endsection
