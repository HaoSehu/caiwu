<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Support\TextSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminLogSummaryResource extends JsonResource
{
    private const FIELD_KEYS = [
        'id',
        'source',
        'level',
        'status',
        'result_status',
        'method',
        'path',
        'module',
        'action',
        'user_type',
        'user_id',
        'actor_type',
        'actor_id',
        'actor_name',
        'admin_username',
        'admin_nickname',
        'ip_address',
        'role_name',
        'subject_type',
        'subject_id',
        'phone',
        'to_email',
        'template_code',
        'subject',
        'provider',
        'request_id',
        'gateway',
        'gateway_key',
        'driver_key',
        'plugin_key',
        'plugin_id',
        'trace_id',
        'out_trade_no',
        'trade_no',
        'invoice_id',
        'task_key',
        'task_title',
        'task_name',
        'duration_ms',
        'error_code',
        'created_at',
        'updated_at',
        'sent_at',
        'time',
        'started_at',
        'finished_at',
    ];

    // 管理端日志列表返回完整字段，不做脱敏（项目红线：管理员需真实审计信息）；摘要仅做长度截断
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $channel = (string) ($this->resource['channel'] ?? '');
        $row = is_array($this->resource['row'] ?? null) ? $this->resource['row'] : [];

        $payload = [
            'id' => (string) ($row['id'] ?? ''),
            'channel' => $channel,
        ];

        foreach (self::FIELD_KEYS as $key) {
            if (array_key_exists($key, $row)) {
                $payload[$key] = $row[$key];
            }
        }

        $payload['message_excerpt'] = $this->excerpt($this->messageText($row));
        $payload['context_excerpt'] = $this->excerpt($this->contextText($row), 240);
        $payload['error_excerpt'] = $this->excerpt((string) ($row['error_msg'] ?? $row['error_message'] ?? ''), 200);

        return $payload;
    }

    private function messageText(array $row): string
    {
        foreach (['message', 'content', 'description', 'action', 'error_msg', 'error_message', 'raw'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function contextText(array $row): string
    {
        $context = [];
        foreach (['detail', 'context', 'params', 'request_data', 'response_data', 'request_meta', 'response_meta', 'summary'] as $key) {
            if (array_key_exists($key, $row)) {
                $context[$key] = $row[$key];
            }
        }

        if ($context === []) {
            return '';
        }

        return (string) json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function excerpt(string $value, int $limit = 160): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return TextSanitizer::truncateWithEllipsis($value, $limit);
    }
}
