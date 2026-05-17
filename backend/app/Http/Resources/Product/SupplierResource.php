<?php

namespace App\Http\Resources\Product;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Supplier */
class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'interface_type' => $this->interface_type,
            'api_url' => '',
            'has_api_url' => trim((string) $this->api_url) !== '',
            'api_username' => (string) $this->api_username,
            'api_key' => (string) $this->api_key,
            'has_api_key' => trim((string) $this->api_key) !== '',
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'website' => $this->website,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
