<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SkillController extends Controller
{
    public function index(): View
    {
        return view('skills.index', ['skills' => Skill::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Skill::create($this->validated($request));

        return back()->with('success', 'Skill added. New emails will use it.');
    }

    public function update(Request $request, Skill $skill): RedirectResponse
    {
        $skill->update($this->validated($request, $skill));

        return back()->with('success', 'Skill updated.');
    }

    public function destroy(Skill $skill): RedirectResponse
    {
        $skill->delete();

        return back()->with('success', "Skill \"{$skill->name}\" removed.");
    }

    /**
     * Each row's form has its own error bag, so errors show next to the right skill.
     *
     * @return array{name: string, details: ?string}
     */
    private function validated(Request $request, ?Skill $skill = null): array
    {
        return $request->validateWithBag($skill ? "skill{$skill->id}" : 'default', [
            'name' => ['required', 'string', 'max:100'],
            'details' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
