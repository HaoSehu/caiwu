<?php

declare(strict_types=1);

use App\Services\ProductCatalog\ImportedProductGroupTreeNormalizer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(ImportedProductGroupTreeNormalizer::class)->normalize();
    }

    public function down(): void
    {
        // This data repair preserves original group IDs and is intentionally not reversed.
    }
};
