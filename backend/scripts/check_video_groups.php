<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MediaFile;

$videos = MediaFile::where('mime_type', 'LIKE', 'video/%')->get();
echo "Found {$videos->count()} video records:\n";
foreach ($videos as $v) {
    echo "  {$v->filename}  group={$v->group}  url={$v->url}\n";
}
