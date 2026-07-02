<?php

namespace App\Http\Requests\Admin\Supplier;

use App\Http\Requests\Admin\Common\AdminFormRequest;
use App\Models\Supplier;
use App\Services\Upstream\ProviderKey;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use Closure;
use Illuminate\Validation\Rule;

class UpsertRequest extends AdminFormRequest
{
    protected function prepareForValidation(): void
    {
        $normalizedInterfaceType = app(ProviderResolver::class)->normalizeKey(
            (string) $this->input('interface_type', '')
        ) ?? '';

        $this->merge([
            'interface_type' => $normalizedInterfaceType,
        ]);
    }

    public function rules(): array
    {
        $supplier = $this->supplier();
        $hasExistingApiUrl = $supplier !== null && trim((string) $supplier->api_url) !== '';
        $hasExistingApiKey = $supplier !== null && trim((string) $supplier->api_key) !== '';

        return [
            'name' => ['required', 'string', 'max:120'],
            'interface_type' => ['required', Rule::in(app(ProviderRegistry::class)->keys())],
            'api_url' => $hasExistingApiUrl
                ? ['nullable', 'url:http,https', 'max:255', fn (string $attribute, mixed $value, Closure $fail) => $this->validateApiUrl($value, $fail)]
                : ['required', 'url:http,https', 'max:255', fn (string $attribute, mixed $value, Closure $fail) => $this->validateApiUrl($value, $fail)],
            'api_username' => ['required', 'string', 'max:100'],
            'api_key' => $hasExistingApiKey
                ? ['nullable', 'string', 'max:255']
                : ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:60'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:100'],
            'website' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:0,1'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function payload(): array
    {
        $supplier = $this->supplier();
        $validated = $this->validated();

        $validated['status'] = (int) ($validated['status'] ?? $this->input('status', 1));
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? $this->input('sort_order', 0));
        $validated['interface_type'] = (string) ($validated['interface_type'] ?? ProviderKey::HOSTING_PANEL_API);
        $validated['api_url'] = $this->normalizeApiUrl((string) ($validated['api_url'] ?? ''));
        $validated['api_username'] = trim((string) ($validated['api_username'] ?? ''));
        $validated['api_key'] = trim((string) ($validated['api_key'] ?? ''));

        if ($supplier !== null) {
            if ($validated['api_url'] === '') {
                $validated['api_url'] = trim((string) $supplier->api_url);
            }

            if ($validated['api_key'] === '') {
                $validated['api_key'] = trim((string) $supplier->api_key);
            }
        }

        $validated['contact_name'] = null;
        $validated['contact_phone'] = null;
        $validated['contact_email'] = null;
        $validated['website'] = null;
        $validated['notes'] = trim((string) ($validated['notes'] ?? '')) ?: null;
        $validated['interface_type'] = app(ProviderResolver::class)->normalizeKey(
            (string) ($validated['interface_type'] ?? '')
        ) ?? ProviderKey::HOSTING_PANEL_API;

        return $validated;
    }

    private function supplier(): ?Supplier
    {
        $supplier = $this->route('supplier');

        return $supplier instanceof Supplier ? $supplier : null;
    }

    private function validateApiUrl(mixed $value, Closure $fail): void
    {
        $url = trim((string) $value);
        if ($url === '') {
            return;
        }

        $parsed = parse_url($url);
        if (! is_array($parsed)) {
            $fail('上游接口地址格式不正确');

            return;
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parsed['host'] ?? '')));

        if ($scheme === '' || $host === '') {
            $fail('上游接口地址格式不正确');

            return;
        }

        if ($scheme !== 'https' && ! app()->environment('local')) {
            $fail('上游接口地址必须使用 HTTPS');

            return;
        }

        if (isset($parsed['user']) || isset($parsed['pass'])) {
            $fail('上游接口地址禁止包含账号信息');

            return;
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            $fail('上游接口地址禁止使用本机地址');

            return;
        }

        $allowedHosts = array_values(array_filter(array_map(
            static fn (string $item): string => strtolower(trim($item)),
            explode(',', (string) config('idc.hosting_panel_api.allowed_hosts', ''))
        )));

        if ($allowedHosts !== []) {
            $matched = collect($allowedHosts)->contains(function (string $allowedHost) use ($host): bool {
                return $host === $allowedHost || str_ends_with($host, '.'.$allowedHost);
            });

            if (! $matched) {
                $fail('上游接口域名不在允许范围内');

                return;
            }
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $publicIp = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($publicIp === false) {
                $fail('上游接口地址禁止使用内网或保留地址');
            }
        }
    }

    private function normalizeApiUrl(string $url): string
    {
        $url = trim($url);

        return $url !== '' ? rtrim($url, '/') : '';
    }
}
