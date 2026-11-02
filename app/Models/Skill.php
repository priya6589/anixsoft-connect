<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A skill or area of expertise of the development team, used to personalise outreach emails.
 */
class Skill extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'details',
    ];

    /**
     * All skills as a bullet list for the AI prompt, or null when none were added.
     */
    public static function promptList(): ?string
    {
        $lines = self::orderBy('name')->get()
            ->map(fn (self $skill) => '- '.$skill->name.($skill->details ? ': '.$skill->details : ''));

        return $lines->isEmpty() ? null : $lines->implode("\n");
    }
}
