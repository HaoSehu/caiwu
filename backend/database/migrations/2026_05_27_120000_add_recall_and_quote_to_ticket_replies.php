<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_replies', function (Blueprint $table): void {
            if (! Schema::hasColumn('ticket_replies', 'quote_reply_id')) {
                $table->unsignedBigInteger('quote_reply_id')->nullable()->after('attachments');
            }
            if (! Schema::hasColumn('ticket_replies', 'recalled_at')) {
                $table->timestamp('recalled_at')->nullable()->after('quote_reply_id');
            }
        });

        if (Schema::hasTable('ticket_messages')) {
            Schema::table('ticket_messages', function (Blueprint $table): void {
                if (! Schema::hasColumn('ticket_messages', 'quote_message_id')) {
                    $table->unsignedBigInteger('quote_message_id')->nullable()->after('content');
                }
                if (! Schema::hasColumn('ticket_messages', 'recalled_at')) {
                    $table->timestamp('recalled_at')->nullable()->after('quote_message_id');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('ticket_replies', function (Blueprint $table): void {
            if (Schema::hasColumn('ticket_replies', 'quote_reply_id')) {
                $table->dropColumn('quote_reply_id');
            }
            if (Schema::hasColumn('ticket_replies', 'recalled_at')) {
                $table->dropColumn('recalled_at');
            }
        });

        if (Schema::hasTable('ticket_messages')) {
            Schema::table('ticket_messages', function (Blueprint $table): void {
                if (Schema::hasColumn('ticket_messages', 'quote_message_id')) {
                    $table->dropColumn('quote_message_id');
                }
                if (Schema::hasColumn('ticket_messages', 'recalled_at')) {
                    $table->dropColumn('recalled_at');
                }
            });
        }
    }
};
