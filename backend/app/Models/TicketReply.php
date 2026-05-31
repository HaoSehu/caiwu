<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TicketReply extends Model
{
    private static array $tableExistsCache = [];

    private static function tableExists(string $table): bool
    {
        return self::$tableExistsCache[$table] ??= Schema::hasTable($table);
    }

    public $timestamps = false;

    protected $fillable = ['ticket_id', 'user_id', 'content', 'is_staff', 'attachments', 'quote_reply_id', 'recalled_at', 'created_at'];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'created_at' => 'datetime',
            'recalled_at' => 'datetime',
        ];
    }

    public function getAttachmentsAttribute(mixed $value): array
    {
        if (self::tableExists('ticket_messages') && self::tableExists('ticket_attachments') && $this->exists) {
            $message = DB::table('ticket_messages')
                ->where('legacy_reply_id', (int) $this->id)
                ->first(['id']);

            if ($message) {
                $attachments = DB::table('ticket_attachments')
                    ->where('ticket_message_id', (int) $message->id)
                    ->orderBy('id')
                    ->get()
                    ->map(fn ($attachment) => [
                        'name' => (string) ($attachment->file_name ?? ''),
                        'path' => (string) ($attachment->file_path ?? ''),
                        'size' => (int) ($attachment->file_size ?? 0),
                        'mime_type' => (string) ($attachment->mime_type ?? ''),
                    ])
                    ->values()
                    ->all();

                if ($attachments !== []) {
                    return $attachments;
                }
            }
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected static function booted(): void
    {
        static::saved(function (self $reply): void {
            $reply->syncTicketMessageProjection();
        });
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function syncTicketMessageProjection(): void
    {
        if (! $this->exists || ! self::tableExists('ticket_messages') || ! self::tableExists('ticket_attachments')) {
            return;
        }

        DB::transaction(function (): void {
            $quoteMessageId = null;
            if (! empty($this->quote_reply_id)) {
                $quoteMessageId = DB::table('ticket_messages')
                    ->where('legacy_reply_id', (int) $this->quote_reply_id)
                    ->value('id');
            }

            DB::table('ticket_messages')->updateOrInsert(
                ['legacy_reply_id' => (int) $this->id],
                [
                    'ticket_id' => (int) $this->ticket_id,
                    'sender_type' => (int) ($this->is_staff ?? 0) === 1 ? 'admin' : 'user',
                    'sender_id' => (int) ($this->user_id ?? 0) ?: null,
                    'content' => (string) ($this->content ?? ''),
                    'quote_message_id' => $quoteMessageId ? (int) $quoteMessageId : null,
                    'recalled_at' => $this->recalled_at ? $this->recalled_at->format('Y-m-d H:i:s') : null,
                    'created_at' => $this->created_at ?? now(),
                    'updated_at' => $this->created_at ?? now(),
                ]
            );

            $messageId = (int) DB::table('ticket_messages')
                ->where('legacy_reply_id', (int) $this->id)
                ->value('id');

            DB::table('ticket_attachments')->where('ticket_message_id', $messageId)->delete();

            $attachments = $this->legacyAttachments();
            foreach ($attachments as $attachment) {
                if (! is_array($attachment)) {
                    continue;
                }

                DB::table('ticket_attachments')->insert([
                    'ticket_message_id' => $messageId,
                    'file_name' => trim((string) ($attachment['name'] ?? '附件')),
                    'file_path' => trim((string) ($attachment['path'] ?? '')),
                    'mime_type' => trim((string) ($attachment['mime_type'] ?? '')) ?: null,
                    'file_size' => is_numeric($attachment['size'] ?? null) ? (int) $attachment['size'] : 0,
                    'created_at' => $this->created_at ?? now(),
                    'updated_at' => $this->created_at ?? now(),
                ]);
            }
        });
    }

    private function legacyAttachments(): array
    {
        $raw = $this->getRawOriginal('attachments');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    }
}
