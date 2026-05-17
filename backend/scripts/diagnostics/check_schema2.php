<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$out = [];

$columns = Schema::getColumnListing('invoices');
$out[] = 'invoices columns: '.implode(', ', $columns);

$hasProductId = Schema::hasColumn('invoices', 'product_id');
$out[] = 'has product_id: '.($hasProductId ? 'YES' : 'NO');

$hasInvoiceIdOnRewards = Schema::hasColumn('referral_rewards', 'invoice_id');
$out[] = 'referral_rewards has invoice_id: '.($hasInvoiceIdOnRewards ? 'YES' : 'NO');

$migrator = $app->make('migrator');
$migrator->setConnection(null);
$ran = $migrator->getRepository()->getRan();
$all = $migrator->getMigrationFiles(__DIR__.'/database/migrations');
$pending = array_diff(array_keys($all), $ran);
$out[] = 'pending migrations: '.(empty($pending) ? 'none' : implode(', ', $pending));

file_put_contents(__DIR__.'/storage/_schema_check.txt', implode("\n", $out));
