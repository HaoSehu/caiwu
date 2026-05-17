<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$columns = Schema::getColumnListing('invoices');
echo 'invoices columns: '.implode(', ', $columns)."\n\n";

$hasProductId = Schema::hasColumn('invoices', 'product_id');
echo 'has product_id: '.($hasProductId ? 'YES' : 'NO')."\n";

$hasInvoiceIdOnRewards = Schema::hasColumn('referral_rewards', 'invoice_id');
echo 'referral_rewards has invoice_id: '.($hasInvoiceIdOnRewards ? 'YES' : 'NO')."\n";

// Check pending migrations
$migrator = $app->make('migrator');
$migrator->setConnection(null);
$ran = $migrator->getRepository()->getRan();
$all = $migrator->getMigrationFiles(__DIR__.'/database/migrations');
$pending = array_diff(array_keys($all), $ran);
echo "\npending migrations: ".(empty($pending) ? 'none' : implode(', ', $pending))."\n";
