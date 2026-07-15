<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo '=== ProductType::visibleValues() ===' . PHP_EOL;
var_dump(\App\Constants\ProductType::visibleValues());
echo PHP_EOL;

echo '=== ProductType::visibleItems() ===' . PHP_EOL;
var_dump(\App\Constants\ProductType::visibleItems());
echo PHP_EOL;

echo '=== first_product_groups ===' . PHP_EOL;
$firstGroups = Illuminate\Support\Facades\DB::table('first_product_groups')->get();
echo 'Count: ' . count($firstGroups) . PHP_EOL;
foreach ($firstGroups as $g) {
    echo json_encode($g, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
echo PHP_EOL;

echo 'second_product_groups: ' . Illuminate\Support\Facades\DB::table('second_product_groups')->count() . PHP_EOL;
echo 'products on_sale: ' . Illuminate\Support\Facades\DB::table('products')->where('lifecycle_status', 'on_sale')->count() . PHP_EOL;
echo 'products total: ' . Illuminate\Support\Facades\DB::table('products')->count() . PHP_EOL;

