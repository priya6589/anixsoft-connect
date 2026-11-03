<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contact extends Model
{
    public const STATUS_IMPORTED = 'imported';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_NOT_RELEVANT = 'not_relevant';

    public const STATUS_DRAFT_READY = 'draft_ready';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REPLIED = 'replied';

    /**
     * Status => [label, Bootstrap badge colour], in pipeline order.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const STATUSES = [
        self::STATUS_IMPORTED => ['Imported', 'secondary'],
        self::STATUS_QUALIFIED => ['Qualified', 'primary'],
        self::STATUS_NOT_RELEVANT => ['Not relevant', 'light'],
        self::STATUS_DRAFT_READY => ['Draft ready', 'info'],
        self::STATUS_APPROVED => ['Approved', 'warning'],
        self::STATUS_SENT => ['Sent', 'success'],
        self::STATUS_FAILED => ['Failed', 'danger'],
        self::STATUS_REPLIED => ['Replied', 'success'],
    ];

    /**
     * AI category => label. Keys are also the values Claude must return.
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'potential_client' => 'Potential client',
        'recruiter' => 'Recruiter / hiring',
        'partner' => 'Partner / referral',
        'not_relevant' => 'Not relevant',
    ];

    /**
     * Email lookup result => [label, Bootstrap badge colour]. Emails are saved only when
     * Hunter verifies them ("valid") or Claude saw them on a public web page ("found", or
     * "company" for the general inbox on the company's website).
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const EMAIL_STATUSES = [
        'valid' => ['Verified', 'success'],
        'found' => ['Found online', 'info'],
        'company' => ['Company email', 'info'],
        'shared' => ['Company email already used', 'secondary'],
        'risky' => ['Risky – not used', 'warning'],
        'invalid' => ['Invalid', 'danger'],
        'not_found' => ['Not found', 'secondary'],
        'opted_out' => ['Opted out', 'dark'],
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
        'email_status',
        'email_source_url',
        'email_checked_at',
        'company',
        'website_url',
        'website_tech',
        'position',
        'connected_on',
        'category',
        'ai_score',
        'ai_reason',
        'classified_at',
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
            'classified_at' => 'datetime',
            'email_checked_at' => 'datetime',
        ];
    }

    /**
     * @return array{total: int, with_email: int, without_email: int, imports: int, unclassified: int, qualified: int, qualified_no_email: int, drafts_pending: int}
     */
    public static function stats(): array
    {
        $total = self::count();
        $withEmail = self::whereNotNull('email')->count();

        return [
            'total' => $total,
            'with_email' => $withEmail,
            'without_email' => $total - $withEmail,
            'imports' => ContactImport::count(),
            'unclassified' => self::whereNull('classified_at')->count(),
            'qualified' => self::where('status', self::STATUS_QUALIFIED)->count(),
            'qualified_no_email' => self::needsEmailLookup()->count(),
            'drafts_pending' => EmailDraft::where('status', EmailDraft::STATUS_DRAFT)->count(),
        ];
    }

    public function contactImport(): BelongsTo
    {
        return $this->belongsTo(ContactImport::class);
    }

    public function emailDrafts(): HasMany
    {
        return $this->hasMany(EmailDraft::class);
    }

    public function latestDraft(): HasOne
    {
        return $this->hasOne(EmailDraft::class)->latestOfMany();
    }

    /**
     * An email can be written (or rewritten) for anyone with an address who hasn't been emailed yet.
     */
    public function canWriteEmail(): bool
    {
        return filled($this->email) && ! in_array($this->status, [self::STATUS_SENT, self::STATUS_REPLIED], true);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(EmailReply::class);
    }

    /**
     * @return array{0: string, 1: string}|null [label, colour] of the Hunter lookup result
     */
    public function emailStatusBadge(): ?array
    {
        return self::EMAIL_STATUSES[$this->email_status] ?? null;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Not sorted';
    }

    /**
     * True until a draft exists, so re-running AI sorting can't undo outreach progress.
     */
    public function isBeforeOutreach(): bool
    {
        return in_array($this->status, [self::STATUS_IMPORTED, self::STATUS_QUALIFIED, self::STATUS_NOT_RELEVANT], true);
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

    /**
     * Qualified contacts with no email that Hunter has not looked up yet.
     */
    public function scopeNeedsEmailLookup(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_QUALIFIED)
            ->whereNull('email')
            ->whereNull('email_checked_at');
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
