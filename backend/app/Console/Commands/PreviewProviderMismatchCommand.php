<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PreviewProviderMismatchCommand extends Command
{
    protected $signature = 'upstream:preview-provider-mismatch';

    protected $description = '只读预览 services.provision_data.provider 与 suppliers.interface_type 不一致的记录';

    public function handle(): int
    {
        $rows = DB::select(<<<'SQL'
            SELECT
                s.id AS service_id,
                JSON_UNQUOTE(JSON_EXTRACT(s.provision_data, '$.provider')) AS old_provider,
                sup.interface_type AS new_provider,
                sup.id AS supplier_id,
                sup.interface_type AS supplier_interface_type,
                s.product_id,
                p.provision_module AS product_provision_module
            FROM idc.services s
            JOIN idc.suppliers sup
                ON sup.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(s.provision_data, '$.supplier_id')) AS UNSIGNED)
            LEFT JOIN idc.products p ON p.id = s.product_id
            WHERE JSON_UNQUOTE(JSON_EXTRACT(s.provision_data, '$.provider')) != sup.interface_type
              AND sup.interface_type IS NOT NULL
              AND sup.interface_type != ''
            ORDER BY s.id
        SQL);

        if (empty($rows)) {
            $this->info('未发现 provider 错配记录。');

            return self::SUCCESS;
        }

        $this->warn(sprintf('发现 %d 条 provider 错配记录（只读，未执行任何写库操作）：', count($rows)));
        $this->newLine();

        $headers = ['service_id', 'old_provider', 'new_provider', 'supplier_id', 'supplier_interface_type', 'product_id', 'product_provision_module'];
        $tableRows = array_map(fn ($row) => [
            $row->service_id,
            $row->old_provider,
            $row->new_provider,
            $row->supplier_id,
            $row->supplier_interface_type,
            $row->product_id,
            $row->product_provision_module,
        ], $rows);

        $this->table($headers, $tableRows);
        $this->newLine();
        $this->warn('以上为 dry-run 输出，未修改任何数据。如需执行修复，请另行确认。');

        return self::SUCCESS;
    }
}
