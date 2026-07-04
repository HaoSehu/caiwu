<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\System\SettingService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettingTableBindingTest extends TestCase
{
    public function test_site_config_endpoint_reads_basic_settings_from_settings_table(): void
    {
        $keys = ['site_name', 'browser_title', 'site_logo', 'site_favicon', 'client_console_icon', 'service_phone', 'service_email', 'service_hours', 'support_group_title', 'support_group_text', 'support_group_qr'];
        $originalRows = DB::table('settings')
            ->where('group_key', 'basic')
            ->whereIn('item_key', $keys)
            ->get(['group_key', 'item_key', 'item_value'])
            ->map(fn (object $row): array => [
                'group_key' => (string) $row->group_key,
                'item_key' => (string) $row->item_key,
                'item_value' => $row->item_value,
            ])
            ->all();

        $suffix = bin2hex(random_bytes(4));
        $siteName = '绔欑偣鍚嶇О-'.$suffix;
        $browserTitle = '娴忚鍣ㄦ爣棰?'.$suffix;
        $siteLogo = '/branding/test-logo-'.$suffix.'.png';
        $siteFavicon = '/branding/test-favicon-'.$suffix.'.png';
        $clientConsoleIcon = '/branding/test-client-console-icon-'.$suffix.'.png';
        $serviceQqGroup = 'qq-'.random_int(100000, 999999);
        $serviceEmail = 'support-'.$suffix.'@example.com';
        $serviceHours = '宸ヤ綔鏃?10:00 - 21:00';
        $supportGroupTitle = '官方群聊-'.$suffix;
        $supportGroupText = '扫码加入官方群聊，获取最新通知-'.$suffix;
        $supportGroupQr = '/uploads/support-group-'.$suffix.'.png';

        try {
            DB::table('settings')
                ->where('group_key', 'basic')
                ->whereIn('item_key', $keys)
                ->delete();

            Setting::setValue('basic', 'site_name', $siteName);
            Setting::setValue('basic', 'browser_title', $browserTitle);
            Setting::setValue('basic', 'site_logo', $siteLogo);
            Setting::setValue('basic', 'site_favicon', $siteFavicon);
            Setting::setValue('basic', 'client_console_icon', $clientConsoleIcon);
            Setting::setValue('basic', 'service_phone', $serviceQqGroup);
            Setting::setValue('basic', 'service_email', $serviceEmail);
            Setting::setValue('basic', 'service_hours', $serviceHours);
            Setting::setValue('basic', 'support_group_title', $supportGroupTitle);
            Setting::setValue('basic', 'support_group_text', $supportGroupText);
            Setting::setValue('basic', 'support_group_qr', $supportGroupQr);

            $this->getJson('/api/site/config')
                ->assertOk()
                ->assertJsonPath('data.site_name', $siteName)
                ->assertJsonPath('data.browser_title', $browserTitle)
                ->assertJsonPath('data.site_logo', $siteLogo)
                ->assertJsonPath('data.site_favicon', $siteFavicon)
                ->assertJsonPath('data.client_console_icon', $clientConsoleIcon)
                ->assertJsonPath('data.service_phone', $serviceQqGroup)
                ->assertJsonPath('data.service_qq_group', $serviceQqGroup)
                ->assertJsonPath('data.service_email', $serviceEmail)
                ->assertJsonPath('data.service_hours', $serviceHours)
                ->assertJsonPath('data.support_group_title', $supportGroupTitle)
                ->assertJsonPath('data.support_group_text', $supportGroupText)
                ->assertJsonPath('data.support_group_qr', $supportGroupQr);
        } finally {
            DB::table('settings')
                ->where('group_key', 'basic')
                ->whereIn('item_key', $keys)
                ->delete();

            if ($originalRows !== []) {
                DB::table('settings')->insert($originalRows);
            }
        }
    }

    public function test_site_product_types_endpoint_reads_current_product_group_tables(): void
    {
        $this->getJson('/api/site/product-types')
            ->assertOk()
            ->assertJsonStructure([
                'code',
                'message',
                'data' => ['list'],
                'timestamp',
            ]);
    }

    public function test_site_product_stock_endpoint_reads_current_product_group_tables(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $firstGroupId = null;
        $secondGroupId = null;
        $productId = null;

        try {
            $firstGroupId = $this->resolveVisibleFirstGroupId('stock-first-'.$suffix);

            $secondGroupId = (int) DB::table('second_product_groups')->insertGetId([
                'first_product_group_id' => $firstGroupId,
                'name' => '测试分组-'.$suffix,
                'slug' => 'stock-test-'.$suffix,
                'description' => '',
                'sort_order' => 0,
                'is_visible' => 1,
            ]);

            $productId = $this->insertProduct([
                'first_product_group_id' => $firstGroupId,
                'second_product_group_id' => $secondGroupId,
                'third_product_group_id' => null,
                'service_type_code' => 'vps',
                'product_type' => 'vps',
                'remark' => '测试商品-'.$suffix,
                'pricing' => json_encode(['monthly' => '9.90'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'setup_fee' => '0.00',
                'config_options' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'purchase_requires' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'stock' => 7,
                'status' => 1,
                'sort_order' => 0,
                'provision_module' => null,
                'auto_setup' => 0,
                'supplier_id' => null,
                'supplier_product_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->getJson('/api/site/products/'.$productId.'/stock')
                ->assertOk()
                ->assertJsonPath('data.product_id', $productId)
                ->assertJsonPath('data.stock', 7);
        } finally {
            if ($productId !== null) {
                DB::table('products')->where('id', $productId)->delete();
            }

            if ($secondGroupId !== null) {
                DB::table('second_product_groups')->where('id', $secondGroupId)->delete();
            }
        }
    }

    /**
     * 复用或创建一个可见的一级商品分组（code=vps），返回其 ID。
     * 一级分组按 code 唯一，且 saleProductQuery 仅放行可见商品种类，
     * 因此这里固定使用可见的 vps 种类，不在测试结束时删除共享行。
     */
    private function resolveVisibleFirstGroupId(string $slugSeed): int
    {
        $existingId = DB::table('first_product_groups')->where('code', 'vps')->value('id');
        if ($existingId !== null) {
            DB::table('first_product_groups')->where('id', (int) $existingId)->update(['is_visible' => 1]);

            return (int) $existingId;
        }

        return (int) DB::table('first_product_groups')->insertGetId([
            'code' => 'vps',
            'name' => 'VPS',
            'slug' => $slugSeed,
            'description' => '',
            'sort_order' => 0,
            'is_visible' => 1,
            'is_system' => 0,
            'legacy_product_type' => 'vps',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function insertProduct(array $payload): int
    {
        $columns = DB::getSchemaBuilder()->getColumnListing('products');
        $filteredPayload = array_intersect_key($payload, array_fill_keys($columns, true));

        return (int) DB::table('products')->insertGetId($filteredPayload);
    }

    public function test_site_product_detail_endpoint_returns_group_name_and_slogan(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $firstGroupId = null;
        $parentGroupId = null;
        $childGroupId = null;
        $productId = null;
        $parentSlogan = '父级标语-'.$suffix;
        $childSlogan = '分类标语-'.$suffix;

        try {
            $firstGroupId = $this->resolveVisibleFirstGroupId('detail-first-'.$suffix);

            $parentGroupId = (int) DB::table('second_product_groups')->insertGetId([
                'first_product_group_id' => $firstGroupId,
                'name' => '父级分类-'.$suffix,
                'slug' => 'detail-parent-'.$suffix,
                'description' => $parentSlogan,
                'sort_order' => 0,
                'is_visible' => 1,
            ]);

            $childGroupId = (int) DB::table('third_product_groups')->insertGetId([
                'second_product_group_id' => $parentGroupId,
                'name' => '子级分类-'.$suffix,
                'slug' => 'detail-child-'.$suffix,
                'description' => $childSlogan,
                'sort_order' => 0,
                'is_visible' => 1,
            ]);

            $productId = $this->insertProduct([
                'first_product_group_id' => $firstGroupId,
                'second_product_group_id' => $parentGroupId,
                'third_product_group_id' => $childGroupId,
                'service_type_code' => 'vps',
                'product_type' => 'vps',
                'remark' => '详情商品-'.$suffix,
                'pricing' => json_encode(['monthly' => '19.90'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'setup_fee' => '0.00',
                'config_options' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'purchase_requires' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'stock' => 9,
                'status' => 1,
                'sort_order' => 0,
                'provision_module' => null,
                'auto_setup' => 0,
                'supplier_id' => null,
                'supplier_product_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $response = $this->getJson('/api/site/products/'.$productId)
                ->assertOk()
                ->assertJsonPath('data.product.group.name', '子级分类-'.$suffix)
                ->assertJsonPath('data.product.group.slogan', $childSlogan)
                ->assertJsonPath('data.product.group.parent_name', '父级分类-'.$suffix)
                ->assertJsonPath('data.product.group.parent_slogan', $parentSlogan);

            $groupPayload = $response->json('data.product.group');

            $this->assertIsArray($groupPayload);
            $this->assertArrayNotHasKey('title', $groupPayload);
            $this->assertArrayNotHasKey('parent_title', $groupPayload);
        } finally {
            if ($productId !== null) {
                DB::table('products')->where('id', $productId)->delete();
            }

            if ($childGroupId !== null) {
                DB::table('third_product_groups')->where('id', $childGroupId)->delete();
            }

            if ($parentGroupId !== null) {
                DB::table('second_product_groups')->where('id', $parentGroupId)->delete();
            }
        }
    }

    public function test_site_product_quote_endpoint_reads_current_product_group_tables(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $firstGroupId = null;
        $secondGroupId = null;
        $productId = null;

        try {
            $firstGroupId = $this->resolveVisibleFirstGroupId('quote-first-'.$suffix);

            $secondGroupId = (int) DB::table('second_product_groups')->insertGetId([
                'first_product_group_id' => $firstGroupId,
                'name' => '报价分组-'.$suffix,
                'slug' => 'quote-test-'.$suffix,
                'description' => '',
                'sort_order' => 0,
                'is_visible' => 1,
            ]);

            $productId = $this->insertProduct([
                'first_product_group_id' => $firstGroupId,
                'second_product_group_id' => $secondGroupId,
                'third_product_group_id' => null,
                'service_type_code' => 'vps',
                'product_type' => 'vps',
                'remark' => '报价商品-'.$suffix,
                'pricing' => json_encode(['monthly' => '12.50'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'setup_fee' => '0.00',
                'config_options' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'purchase_requires' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'stock' => -1,
                'status' => 1,
                'sort_order' => 0,
                'provision_module' => null,
                'auto_setup' => 0,
                'supplier_id' => null,
                'supplier_product_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->postJson('/api/site/products/'.$productId.'/quote', [
                'billing_cycle' => 'monthly',
                'config' => [],
                'quantity' => 1,
            ])
                ->assertOk()
                ->assertJsonPath('data.product_id', $productId)
                ->assertJsonPath('data.total_amount', '12.50');
        } finally {
            if ($productId !== null) {
                DB::table('products')->where('id', $productId)->delete();
            }

            if ($secondGroupId !== null) {
                DB::table('second_product_groups')->where('id', $secondGroupId)->delete();
            }
        }
    }

    public function test_setting_model_reads_and_writes_through_settings_table(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $group = 'codex_runtime_'.$suffix;
        $key = 'sample_key';
        $value = 'sample-value-'.$suffix;

        Setting::setValue($group, $key, $value);

        $this->assertSame(
            $value,
            DB::table('settings')
                ->where('group_key', $group)
                ->where('item_key', $key)
                ->value('item_value')
        );
        $this->assertSame($value, Setting::getValue($group, $key));
    }

    public function test_setting_service_reads_dynamic_group_from_settings_table(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $group = 'codex_service_'.$suffix;
        $key = 'custom_key';
        $value = 'custom-value-'.$suffix;

        Setting::setValue($group, $key, $value);

        $settings = app(SettingService::class)
            ->getGroupSettings($group)
            ->keyBy('key');

        $this->assertTrue($settings->has($key));
        $this->assertSame($group, $settings[$key]['group']);
        $this->assertSame($value, $settings[$key]['value']);
    }
}
