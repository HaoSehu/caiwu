<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->convertSmsTemplateContent(
            static fn (string $content): string => preg_replace('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/u', '{$1}', $content) ?? $content
        );
    }

    public function down(): void
    {
        $this->convertSmsTemplateContent(
            static fn (string $content): string => preg_replace('/(?<!\{)\{([a-zA-Z0-9_]+)\}(?!\})/u', '{{$1}}', $content) ?? $content
        );
    }

    /**
     * @param  callable(string): string  $converter
     */
    private function convertSmsTemplateContent(callable $converter): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        DB::table('notification_templates')
            ->where('channel', 'sms')
            ->orderBy('id')
            ->select(['id', 'content'])
            ->chunkById(100, function ($templates) use ($converter): void {
                foreach ($templates as $template) {
                    $content = (string) ($template->content ?? '');
                    $converted = $converter($content);

                    if ($converted === $content) {
                        continue;
                    }

                    DB::table('notification_templates')
                        ->where('id', $template->id)
                        ->update([
                            'content' => $converted,
                            'updated_at' => now(),
                        ]);
                }
            });
    }
};
