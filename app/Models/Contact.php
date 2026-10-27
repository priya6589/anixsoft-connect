<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    public const STATUS_IMPORTED = 'imported';

    /**
     * Status => [label, Bootstrap badge colour]. Later phases add AI/email statuses here.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const STATUSES = [
        self::STATUS_IMPORTED => ['Imported', 'secondary'],
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'contact_import_id',
        'first_name',
        'last_name',
        'linkedin_url',
        'email',
        'email_source',
        'company',
        'position',
        'connected_on',
        'category',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'connected_on' => 'date',
        ];
    }

    public function contactImport(): BelongsTo
    {
        return $this->belongsTo(ContactImport::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst($this->status);
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = trim($term);
        $like = '%'.$term.'%';
        [$first, $last] = array_pad(preg_split('/\s+/', $term, 2), 2, null);

        return $query->where(function (Builder $q) use ($like, $first, $last) {
            $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->when($last, fn (Builder $q) => $q->orWhere(fn (Builder $q) => $q
                    ->where('first_name', 'like', $first.'%')
                    ->where('last_name', 'like', $last.'%')))
                ->orWhere('company', 'like', $like)
                ->orWhere('position', 'like', $like)
                ->orWhere('email', 'like', $like);
        });
    }
}
