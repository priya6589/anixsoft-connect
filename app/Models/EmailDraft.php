<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailDraft extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /** Drafts that may still be edited, sent or replaced by a regenerated one. */
    public const UNSENT_STATUSES = [self::STATUS_DRAFT, self::STATUS_APPROVED, self::STATUS_FAILED];

    /**
     * Status => [label, Bootstrap badge colour].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const STATUSES = [
        self::STATUS_DRAFT => ['Waiting for review', 'info'],
        self::STATUS_APPROVED => ['Approved', 'warning'],
        self::STATUS_REJECTED => ['Rejected', 'secondary'],
        self::STATUS_SENT => ['Sent', 'success'],
        self::STATUS_FAILED => ['Failed', 'danger'],
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'contact_id',
        'subject',
        'body',
        'status',
        'model',
        'approved_at',
        'sent_at',
        'message_id',
        'error',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(EmailReply::class)->oldest('received_at');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst($this->status);
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::UNSENT_STATUSES, true);
    }

    /**
     * Waiting for review, or failed to send and can be retried.
     */
    public function canBeSent(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_FAILED], true);
    }
}
