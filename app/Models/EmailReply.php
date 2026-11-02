<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An email that came back in answer to an outreach email we sent.
 */
class EmailReply extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'contact_id',
        'email_draft_id',
        'message_id',
        'from_email',
        'from_name',
        'subject',
        'body',
        'received_at',
        'read_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function emailDraft(): BelongsTo
    {
        return $this->belongsTo(EmailDraft::class);
    }

    /**
     * Only what they wrote: drops the quoted copy of our email that mail apps add below a reply.
     */
    public function newText(): string
    {
        $text = str_replace("\r\n", "\n", (string) $this->body);
        $text = preg_split('/^(On .{0,300}wrote:|-{2,}\s*Original Message\s*-{2,}|From: .+\n(Sent|Date): )/mi', $text, 2)[0];
        $lines = array_filter(explode("\n", $text), fn (string $line) => ! str_starts_with(ltrim($line), '>'));

        return trim(implode("\n", $lines)) ?: trim((string) $this->body);
    }
}
