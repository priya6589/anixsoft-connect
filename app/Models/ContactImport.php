<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactImport extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'original_filename',
        'total_rows',
        'created_count',
        'updated_count',
        'skipped_count',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }
}
