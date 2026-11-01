<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Http\Resources\Concerns\StripsSensitiveResourceData;
use App\Models\ScheduleTaskRun;
use App\Support\SensitiveDataSanitizer;
use App\Support\TextSanitizer;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminScheduleTaskRunResource extends JsonResource
{
    use StripsSensitiveResourceData;

    private const MAX_DEPTH = 3;

    private const MAX_ITEMS = 20;

    private const MAX_TEXT = 2000;

    /**
     * 运行台账只允许返回可解释的状态字段，不暴露 Job payload、异常对象或第三方原始响应。
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $run = $this->resource;
        if (! $run instanceof ScheduleTaskRun) {
            return [];
        }

        $summary = $this->safeValue($run->summary, 0);
        // 2P0-C 紧凑引用呈现层翻译（审查 P2-④）：台账库里 summary 只存
        // {"__same_as_previous":true,"__previous_run_id":N} 两个内部哨兵键，
        // 直接透传会让管理端看到裸 JSON。就地翻译为语义结构，保留可跳转的
        // 引用运行 id，同时不改变库中原始 JSON（审计真源不受影响）。
        $summary = $this->translateDeduplicationMarkers($summary);

        return [
            'id' => (int) $run->id,
            'task_key' => (string) ($this->text($run->task_key) ?? ''),
            'task_name' => (string) ($this->text($run->task_name) ?? ''),
            'rule_description' => $this->text($run->rule_description),
            'source' => (string) ($this->text($run->source) ?? ''),
            'status' => (string) ($this->text($run->status) ?? ''),
            'status_label' => ScheduleTaskRun::statusLabel((string) ($run->status ?? '')),
            'attempt' => max(1, (int) ($run->attempt ?? 1)),
            'parent_run_id' => $run->parent_run_id !== null ? (int) $run->parent_run_id : null,
            'queue' => $this->text($run->queue),
            'duration_ms' => $run->duration_ms !== null ? (int) $run->duration_ms : null,
            'queued_at' => $this->date($run->queued_at),
            'started_at' => $this->date($run->started_at),
            'finished_at' => $this->date($run->finished_at),
            'manual_retry_at' => $this->date($run->manual_retry_at),
            'manual_retry_by' => $run->manual_retry_by !== null ? (int) $run->manual_retry_by : null,
            'summary' => $summary,
            'error_msg' => $this->text($run->error_msg),
        ];
    }

    /**
     * 紧凑引用呈现层翻译（2P0-C）：把台账内部的去重哨兵键翻译为语义结构，
     * 让管理端展示「与运行 #N 摘要相同」而不是裸 JSON 键。非紧凑引用原样返回。
     */
    private function translateDeduplicationMarkers(mixed $summary): mixed
    {
        if (! is_array($summary) || ! isset($summary['__same_as_previous'])) {
            return $summary;
        }

        $refRunId = isset($summary['__previous_run_id']) ? (int) $summary['__previous_run_id'] : null;

        return [
            'deduplicated' => true,
            'note' => $refRunId !== null ? "与运行 #{$refRunId} 的摘要相同" : '与上一次运行的摘要相同',
            'ref_run_id' => $refRunId,
        ];
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = SensitiveDataSanitizer::sanitizeText((string) $value);
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return TextSanitizer::truncateWithEllipsis($value, self::MAX_TEXT);
    }

    private function date(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i:s') : null;
    }

    private function safeValue(mixed $value, int $depth): mixed
    {
        if ($depth >= self::MAX_DEPTH) {
            if (is_array($value)) {
                return '[truncated]';
            }

            if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
                return $value;
            }

            return $this->text($value);
        }

        if (! is_array($value)) {
            if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
                return $value;
            }

            return $this->text($value);
        }

        $result = [];
        $count = 0;
        foreach ($value as $key => $item) {
            if ($count >= self::MAX_ITEMS) {
                break;
            }

            if (is_string($key) && $this->isSensitiveKey($key)) {
                continue;
            }

            $result[$key] = $this->safeValue($item, $depth + 1);
            $count++;
        }

        return array_is_list($value) ? array_values($result) : $result;
    }
}
