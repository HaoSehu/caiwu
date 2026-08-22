<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Http\Resources\Admin\V2\Concerns\StripsSensitiveResourceData;
use App\Models\ActivityLog;
use App\Support\AdminPrivacy;
use App\Support\SensitiveDataSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ActivityLog */
class AdminUserOperationLogResource extends JsonResource
{
    use StripsSensitiveResourceData;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $privacy = AdminPrivacy::fromRequest($request);
        $context = is_array($this->context ?? null) ? $this->context : [];

        return [
            'id' => (int) $this->id,
            'user_id' => $this->actor_id !== null ? (int) $this->actor_id : null,
            'user_type' => (string) ($this->actor_type ?? ''),
            'action' => (string) ($this->action ?? ''),
            'module' => (string) ($this->module ?? ''),
            'subject_id' => $this->subject_id !== null ? (int) $this->subject_id : null,
            'context' => $this->excerptContext($context, $privacy),
            'ip_address' => $privacy->ip($this->ip_address),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * 截断 context 为摘要（列表场景，限 240 字符）
     */
    private function excerptContext(array $context, AdminPrivacy $privacy): string
    {
        if ($context === []) {
            return '';
        }

        $json = (string) json_encode(
            $this->stripSensitiveKeys($privacy->payload($context)),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $text = SensitiveDataSanitizer::sanitizeText($json);
        $value = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if (mb_strlen($value) <= 240) {
            return $value;
        }

        return mb_substr($value, 0, 240).'...';
    }
}
