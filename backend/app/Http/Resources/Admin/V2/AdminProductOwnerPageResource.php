<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Support\AdminPrivacy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminProductOwnerPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = is_array($this->resource) ? $this->resource : [];

        return [
            'list' => array_map(fn (mixed $item): array => $this->ownerItem($item), is_array($payload['list'] ?? null) ? $payload['list'] : []),
            'summary' => $this->summary($payload['summary'] ?? []),
            'total' => (int) ($payload['total'] ?? 0),
            'page' => (int) ($payload['page'] ?? 1),
            'page_size' => (int) ($payload['page_size'] ?? 20),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ownerItem(mixed $item): array
    {
        $item = is_array($item) ? $item : [];
        $privacy = AdminPrivacy::current();
        $email = (string) ($item['email'] ?? '');
        $phone = (string) ($item['phone'] ?? '');

        return [
            'id' => (int) ($item['id'] ?? 0),
            // display_name 在昵称不可读时会回落到 email/phone，必须与 email/phone 字段一起脱敏。
            'display_name' => $privacy->displayName($item['display_name'] ?? '', $email, $phone),
            'nickname' => (string) ($item['nickname'] ?? ''),
            'email' => $privacy->email($email),
            'phone' => $privacy->phone($phone),
            'status' => (int) ($item['status'] ?? 0),
            'status_label' => (string) ($item['status_label'] ?? ''),
            'product_services_count' => (int) ($item['product_services_count'] ?? 0),
            'active_product_services_count' => (int) ($item['active_product_services_count'] ?? 0),
            'latest_service_created_at' => $item['latest_service_created_at'] ?? null,
            'latest_service_expires_at' => $item['latest_service_expires_at'] ?? null,
            'created_at' => $item['created_at'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(mixed $summary): array
    {
        $summary = is_array($summary) ? $summary : [];

        return [
            'owners_total' => (int) ($summary['owners_total'] ?? 0),
            'services_total' => (int) ($summary['services_total'] ?? 0),
            'active_services_total' => (int) ($summary['active_services_total'] ?? 0),
            'latest_service_created_at' => $summary['latest_service_created_at'] ?? null,
        ];
    }
}
