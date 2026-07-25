<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chat_conversations');

        if (Schema::hasTable('settings')) {
            DB::table('settings')
                ->where('group_key', 'ai_service')
                ->delete();

            DB::table('settings')
                ->whereIn('item_key', ['ai_api_key', 'deepseek_api_key', 'openai_api_key'])
                ->delete();
        }
    }

    public function down(): void
    {
        // AI customer service has been removed. No automatic restore is provided.
    }
};
