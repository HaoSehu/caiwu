<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Ticket extends Model
{
    private static array $tableExistsCache = [];

    private static function tableExists(string $table): bool
    {
        return self::$tableExistsCache[$table] ??= Schema::hasTable($table);
    }

    protected $fillable = [
        'user_id', 'department', 'subject', 'priority',
        'status', 'service_id', 'assignee_id', 'close_reason',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $ticket): void {
            if (! $ticket->exists || ! self::tableExists('ticket_departments')) {
                return;
            }

            $department = trim((string) ($ticket->department ?? 'support'));
            if ($department === '') {
                $department = 'support';
            }

            DB::table('ticket_departments')->updateOrInsert(
                ['code' => $department],
                [
                    'name' => $department,
                    'status' => 1,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'assignee_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [0, 1, 2]);
    }
}
