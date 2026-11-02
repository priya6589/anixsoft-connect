@extends('layouts.app')

@section('title', 'Profile')

@section('content')
    <h1 class="h3 mb-4">Profile</h1>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-body fw-semibold">Account details</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        @method('PATCH')
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}"
                                   class="form-control @error('name') is-invalid @enderror" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-4">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}"
                                   class="form-control @error('email') is-invalid @enderror" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-body fw-semibold">Change password</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.password') }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current password</label>
                            <input type="password" name="current_password" id="current_password" autocomplete="current-password"
                                   class="form-control @error('current_password', 'password') is-invalid @enderror" required>
                            @error('current_password', 'password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">New password</label>
                            <input type="password" name="password" id="password" autocomplete="new-password"
                                   class="form-control @error('password', 'password') is-invalid @enderror" required>
                            @error('password', 'password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label">Confirm new password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation"
                                   class="form-control" required autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn btn-primary">Change password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
