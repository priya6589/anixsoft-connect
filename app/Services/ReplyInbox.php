<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\EmailDraft;
use App\Models\EmailReply;
use Illuminate\Support\Carbon;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

/**
 * Reads the sending mailbox over IMAP and saves replies to our outreach emails.
 *
 * Only emails that answer something we sent are stored; the rest of the inbox is
 * ignored, and messages are read with PEEK so they stay unread in the mailbox.
 */
class ReplyInbox
{
    public function isConfigured(): bool
    {
        return filled(config('outreach.imap.host'))
            && filled(config('outreach.imap.username'))
            && filled(config('outreach.imap.password'));
    }

    /**
     * Check the inbox for new replies. Returns how many were saved.
     */
    public function fetch(): int
    {
        $firstSent = EmailDraft::whereNotNull('sent_at')->min('sent_at');

        if (! $firstSent || ! $this->isConfigured()) {
            return 0;
        }

        $client = (new ClientManager)->make([
            'host' => config('outreach.imap.host'),
            'port' => config('outreach.imap.port'),
            'encryption' => config('outreach.imap.encryption'),
            'validate_cert' => true,
            'username' => config('outreach.imap.username'),
            'password' => config('outreach.imap.password'),
            'protocol' => 'imap',
        ]);

        $client->connect();

        try {
            $messages = $client->getFolderByPath('INBOX')->query()
                ->whereSince(Carbon::parse($firstSent)->subDay())
                ->leaveUnread()
                ->get();

            $saved = 0;

            foreach ($messages as $message) {
                $saved += $this->store($this->toArray($message)) ? 1 : 0;
            }

            return $saved;
        } finally {
            $client->disconnect();
        }
    }

    /**
     * Save one incoming email if it answers an email we sent.
     *
     * @param  array{message_id: ?string, in_reply_to: list<string>, from_email: ?string, from_name: ?string, subject: ?string, body: ?string, received_at: mixed}  $mail
     */
    public function store(array $mail): ?EmailReply
    {
        $from = strtolower(trim((string) $mail['from_email']));
        $messageId = $this->cleanId($mail['message_id'])
            ?: sha1($from.'|'.$mail['subject'].'|'.$mail['received_at']);

        if ($from === '' || $from === strtolower((string) config('mail.from.address'))
            || EmailReply::where('message_id', $messageId)->exists()) {
            return null;
        }

        // Best match: the reply's headers point at the Message-ID of an email we sent.
        $ids = array_values(array_filter(array_map($this->cleanId(...), $mail['in_reply_to'])));
        $draft = $ids ? EmailDraft::whereIn('message_id', $ids)->first() : null;

        // Otherwise: the sender is a contact we have emailed.
        $draft ??= EmailDraft::where('status', EmailDraft::STATUS_SENT)
            ->whereHas('contact', fn ($q) => $q->where('email', $from))
            ->latest('sent_at')
            ->first();

        if (! $draft) {
            return null;
        }

        $reply = EmailReply::create([
            'contact_id' => $draft->contact_id,
            'email_draft_id' => $draft->id,
            'message_id' => $messageId,
            'from_email' => $from,
            'from_name' => mb_substr((string) $mail['from_name'], 0, 255) ?: null,
            'subject' => mb_substr((string) $mail['subject'], 0, 255) ?: null,
            'body' => $mail['body'],
            'received_at' => $mail['received_at'] ? Carbon::parse($mail['received_at']) : now(),
        ]);

        $draft->contact->update(['status' => Contact::STATUS_REPLIED]);

        return $reply;
    }

    /**
     * @return array{message_id: ?string, in_reply_to: list<string>, from_email: ?string, from_name: ?string, subject: ?string, body: ?string, received_at: mixed}
     */
    private function toArray(Message $message): array
    {
        $from = $message->getFrom()->first();
        $body = $message->hasTextBody()
            ? $message->getTextBody()
            : trim(html_entity_decode(strip_tags($message->getHTMLBody())));

        return [
            'message_id' => (string) $message->getMessageId(),
            'in_reply_to' => array_merge($message->getInReplyTo()->all(), $message->getReferences()->all()),
            'from_email' => $from?->mail,
            'from_name' => $from?->personal,
            'subject' => (string) $message->getSubject(),
            'body' => $body,
            'received_at' => $message->getDate()->first(),
        ];
    }

    private function cleanId(mixed $id): string
    {
        return trim((string) $id, " <>\t\r\n");
    }
}
