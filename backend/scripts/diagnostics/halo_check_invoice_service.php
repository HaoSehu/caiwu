<?php

use App\Services\Finance\InvoiceService;
use App\Services\User\UserService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo 'InvoiceService::markPaidManually exists? ';
var_export(method_exists(InvoiceService::class, 'markPaidManually'));
echo PHP_EOL;

echo 'InvoiceService::refundByPaymentMethod exists? ';
var_export(method_exists(InvoiceService::class, 'refundByPaymentMethod'));
echo PHP_EOL;

try {
    $svc = $app->make(UserService::class);
    echo 'UserService resolved OK'.PHP_EOL;
} catch (Throwable $e) {
    echo 'UserService failed: '.$e->getMessage().PHP_EOL;
}

try {
    $svc = $app->make(InvoiceService::class);
    echo 'InvoiceService resolved OK'.PHP_EOL;
} catch (Throwable $e) {
    echo 'InvoiceService failed: '.$e->getMessage().PHP_EOL;
}
