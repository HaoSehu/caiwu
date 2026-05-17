<?php

namespace App\Constants;

use App\Models\Setting;
use Illuminate\Support\Str;

class ProductType
{
    const VPS = 'vps';

    const DEDICATED = 'dedicated';

    const HOSTING = 'hosting';

    const DOMAIN = 'domain';

    const OTHER = 'other';

    public const SETTING_GROUP = 'product';

    public const SETTING_KEY = 'product_types';

    private static ?array $cachedItems = null;

    private const DEFAULT_INTERNAL_IDS = [
        self::VPS => 1,
        self::DEDICATED => 2,
        self::HOSTING => 3,
        self::DOMAIN => 4,
        self::OTHER => 5,
    ];

    public static array $labels = [
        self::VPS => '云服务器',
        self::DEDICATED => '独立服务器',
        self::HOSTING => '虚拟主机',
        self::DOMAIN => '域名',
        self::OTHER => '其他',
    ];

    public static function defaultItems(): array
    {
        return [
            ['internal_id' => self::DEFAULT_INTERNAL_IDS[self::VPS], 'value' => self::VPS, 'label' => '云服务器', 'icon' => 'Platform', 'is_builtin' => true, 'is_hidden' => false],
            ['internal_id' => self::DEFAULT_INTERNAL_IDS[self::DEDICATED], 'value' => self::DEDICATED, 'label' => '独立服务器', 'icon' => 'OfficeBuilding', 'is_builtin' => true, 'is_hidden' => false],
            ['internal_id' => self::DEFAULT_INTERNAL_IDS[self::HOSTING], 'value' => self::HOSTING, 'label' => '虚拟主机', 'icon' => 'Monitor', 'is_builtin' => true, 'is_hidden' => false],
            ['internal_id' => self::DEFAULT_INTERNAL_IDS[self::DOMAIN], 'value' => self::DOMAIN, 'label' => '域名', 'icon' => 'Link', 'is_builtin' => true, 'is_hidden' => false],
            ['internal_id' => self::DEFAULT_INTERNAL_IDS[self::OTHER], 'value' => self::OTHER, 'label' => '其他', 'icon' => 'Grid', 'is_builtin' => true, 'is_hidden' => false],
        ];
    }

    public static function items(): array
    {
        if (self::$cachedItems !== null) {
            return self::$cachedItems;
        }

        $storedValue = Setting::getValue(self::SETTING_GROUP, self::SETTING_KEY, '');
        $decoded = json_decode((string) $storedValue, true);

        if (! is_array($decoded) || $decoded === []) {
            return self::$cachedItems = self::defaultItems();
        }

        [$items, $changed] = self::normalizeItems($decoded);

        if ($items === []) {
            return self::$cachedItems = self::defaultItems();
        }

        if ($changed) {
            self::persistItems($items);
        }

        return self::$cachedItems = $items;
    }

    public static function allowedValues(): array
    {
        return array_values(array_map(
            fn (array $item) => (string) $item['value'],
            self::items()
        ));
    }

    public static function visibleItems(): array
    {
        return array_values(array_filter(
            self::items(),
            fn (array $item) => ! (bool) ($item['is_hidden'] ?? false)
        ));
    }

    public static function visibleValues(): array
    {
        return array_values(array_map(
            fn (array $item) => (string) $item['value'],
            self::visibleItems()
        ));
    }

    public static function isVisible(?string $value): bool
    {
        $normalizedValue = trim((string) $value);

        if ($normalizedValue === '') {
            return false;
        }

        foreach (self::items() as $item) {
            if ((string) ($item['value'] ?? '') !== $normalizedValue) {
                continue;
            }

            return ! (bool) ($item['is_hidden'] ?? false);
        }

        return false;
    }

    public static function labelOf(?string $value): string
    {
        $normalizedValue = trim((string) $value);
        if ($normalizedValue === '') {
            return '-';
        }

        foreach (self::items() as $item) {
            if ((string) $item['value'] === $normalizedValue) {
                return (string) $item['label'];
            }
        }

        return self::$labels[$normalizedValue] ?? $normalizedValue;
    }

    public static function iconOf(?string $value): string
    {
        $normalizedValue = trim((string) $value);
        if ($normalizedValue === '') {
            return '';
        }

        foreach (self::items() as $item) {
            if ((string) ($item['value'] ?? '') === $normalizedValue) {
                return trim((string) ($item['icon'] ?? ''));
            }
        }

        return '';
    }

    public static function routeIdOf(?string $value): int
    {
        return self::internalIdOf($value);
    }

    public static function internalIdOf(?string $value): int
    {
        $normalizedValue = trim((string) $value);
        if ($normalizedValue === '') {
            return 0;
        }

        foreach (self::items() as $item) {
            if ((string) ($item['value'] ?? '') === $normalizedValue) {
                return (int) ($item['internal_id'] ?? 0);
            }
        }

        return 0;
    }

    public static function saveItems(array $items): array
    {
        [$normalizedItems] = self::normalizeItems($items);

        if ($normalizedItems === []) {
            $normalizedItems = self::defaultItems();
        }

        self::persistItems($normalizedItems);

        return $normalizedItems;
    }

    public static function resetCache(): void
    {
        self::$cachedItems = null;
    }

    public static function normalizeValue(string $value): string
    {
        $slug = Str::slug(trim($value), '_');

        if ($slug === '') {
            $slug = 'type_'.Str::lower(Str::random(6));
        }

        return Str::limit($slug, 50, '');
    }

    private static function persistItems(array $items): void
    {
        Setting::setValue(
            self::SETTING_GROUP,
            self::SETTING_KEY,
            json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        self::$cachedItems = $items;
    }

    private static function normalizeItems(array $items): array
    {
        $normalized = [];
        $seenValues = [];
        $usedInternalIds = [];
        $changed = false;

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                $changed = true;

                continue;
            }

            $value = self::normalizeValue((string) ($item['value'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));

            if ($label === '') {
                $changed = true;

                continue;
            }

            if (isset($seenValues[$value])) {
                $changed = true;

                continue;
            }

            $internalId = self::normalizeInternalId($item['internal_id'] ?? null);
            $preferredInternalId = self::preferredInternalId($value, $index);

            if ($internalId <= 0 || isset($usedInternalIds[$internalId])) {
                $internalId = self::nextAvailableInternalId($usedInternalIds, $preferredInternalId);
                $changed = true;
            }

            $label = Str::limit($label, 30, '');
            $normalized[] = [
                'internal_id' => $internalId,
                'value' => $value,
                'label' => $label,
                'icon' => self::normalizeIcon($item['icon'] ?? ''),
                'is_builtin' => (bool) ($item['is_builtin'] ?? false),
                'is_hidden' => (bool) ($item['is_hidden'] ?? false),
            ];

            $usedInternalIds[$internalId] = true;
            $seenValues[$value] = true;
        }

        return [$normalized, $changed];
    }

    private static function normalizeInternalId(mixed $value): int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : 0;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        if (is_float($value) && $value > 0 && floor($value) === $value) {
            return (int) $value;
        }

        return 0;
    }

    private static function preferredInternalId(string $value, int $index): int
    {
        return self::DEFAULT_INTERNAL_IDS[$value] ?? ($index + 1);
    }

    private static function nextAvailableInternalId(array $usedInternalIds, int $preferredInternalId): int
    {
        $candidate = max(1, $preferredInternalId);

        while (isset($usedInternalIds[$candidate])) {
            $candidate++;
        }

        return $candidate;
    }

    private static function normalizeIcon(mixed $icon): string
    {
        return Str::limit(trim((string) $icon), 50, '');
    }
}
