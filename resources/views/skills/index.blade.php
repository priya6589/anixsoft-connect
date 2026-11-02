@extends('layouts.app')

@section('title', 'Team skills')

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-0">Team skills</h1>
        <p class="text-body-secondary mb-0">
            Add what your developers are good at. For every email, the AI picks the one or two skills that fit that person best
            and explains them, using only what you write here.
        </p>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-body fw-semibold"><i class="bi bi-plus-circle me-1"></i> Add a skill</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('skills.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="name" class="form-label">Skill</label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" maxlength="100" required
                                   class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Laravel / PHP">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="details" class="form-label">Experience &amp; examples <span class="text-body-secondary">(optional)</span></label>
                            <textarea name="details" id="details" rows="3" maxlength="500"
                                      class="form-control @error('details') is-invalid @enderror"
                                      placeholder="e.g. 6 years, 40+ projects: e-commerce stores, SaaS dashboards, REST APIs">{{ old('details') }}</textarea>
                            @error('details')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Real numbers and project types make the emails more convincing.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i> Add skill</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-body fw-semibold">
                    Your skills <span class="badge text-bg-light border ms-1">{{ $skills->count() }}</span>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($skills as $skill)
                        @php($bag = $errors->getBag("skill{$skill->id}"))
                        <li class="list-group-item py-3">
                            <form method="POST" action="{{ route('skills.update', $skill) }}" id="skill-{{ $skill->id }}" class="row g-2">
                                @csrf
                                @method('PUT')
                                <div class="col-md-4">
                                    <input type="text" name="name" value="{{ $bag->any() ? old('name') : $skill->name }}" maxlength="100" required
                                           class="form-control fw-semibold @if ($bag->has('name')) is-invalid @endif" aria-label="Skill">
                                    @if ($bag->has('name'))
                                        <div class="invalid-feedback">{{ $bag->first('name') }}</div>
                                    @endif
                                </div>
                                <div class="col-md-8">
                                    <textarea name="details" rows="2" maxlength="500" aria-label="Experience and examples"
                                              class="form-control @if ($bag->has('details')) is-invalid @endif"
                                              placeholder="Experience & examples (optional)">{{ $bag->any() ? old('details') : $skill->details }}</textarea>
                                    @if ($bag->has('details'))
                                        <div class="invalid-feedback">{{ $bag->first('details') }}</div>
                                    @endif
                                </div>
                            </form>
                            <div class="d-flex justify-content-end gap-2 mt-2">
                                <button type="submit" form="skill-{{ $skill->id }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-save me-1"></i> Save
                                </button>
                                <form method="POST" action="{{ route('skills.destroy', $skill) }}"
                                      onsubmit="return confirm('Remove this skill?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash me-1"></i> Remove
                                    </button>
                                </form>
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-center text-body-secondary py-5">
                            <i class="bi bi-tools fs-2 d-block mb-2"></i>
                            No skills yet. Until you add some, emails describe your services from <code>OUTREACH_SERVICES</code> in <code>.env</code>.
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
