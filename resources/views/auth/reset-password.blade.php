@extends('layouts.guest')

@section('title', 'Reset password')

@section('content')
    <h1 class="h5 mb-4">Choose a new password</h1>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email', $email) }}"
                   class="form-control @error('email') is-invalid @enderror" required autocomplete="username">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">New password</label>
            <input type="password" name="password" id="password"
                   class="form-control @error('password') is-invalid @enderror" required autofocus autocomplete="new-password">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">Confirm new password</label>
            <input type="password" name="password_confirmation" id="password_confirmation"
                   class="form-control" required autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary w-100">Reset password</button>
    </form>
@endsection
