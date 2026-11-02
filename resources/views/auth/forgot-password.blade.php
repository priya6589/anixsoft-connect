@extends('layouts.guest')

@section('title', 'Forgot password')

@section('content')
    <h1 class="h5 mb-1">Forgot your password?</h1>
    <p class="text-body-secondary small mb-4">Enter your email and we'll send you a link to choose a new one.</p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-4">
            <label for="email" class="form-label">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror" required autofocus>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100">Email reset link</button>
    </form>

    <div class="text-center mt-3">
        <a href="{{ route('login') }}" class="small"><i class="bi bi-arrow-left"></i> Back to log in</a>
    </div>
@endsection
