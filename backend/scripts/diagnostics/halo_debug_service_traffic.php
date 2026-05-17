<?php

use App\Models\Service;
use App\Services\MofangFinanceClient;
use Illuminate\Contracts\Console\Kernel;

/**
 * 一次性诊断：指定 service_id 的流量使用值来自本地 provision_data 和上游魔方财务主机详情接口。
 * 用法：php _halo_debug_service_traffic.php [service_id]
 * 结果输出到 _halo_debug_service_traffic_result.txt
 */
$out = __DIR__.'/_halo_debug_service_traffic_result.txt';
$serviceId = (int) ($argv[1] ?? 91);

$lines = [];
$lines[] = '=== service traffic debug ===';
$lines[] = 'timestamp='.date('Y-m-d H:i:s');
$lines[] = 'service_id='.$serviceId;

try {
    require __DIR__.'/vendor/autoload.php';
    $app = require __DIR__.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    $service = Service::with(['product.supplier'])->find($serviceId);

    if (! $service) {
        $lines[] = 'result=service_not_found';
        file_put_contents($out, implode("\n", $lines)."\n");

        return;
    }

    $provision = is_array($service->provision_data) ? $service->provision_data : [];
    $hostId = (int) ($provision['upstream_host_id'] ?? 0);
    $product = $service->product;
    $supplier = $product?->supplier;

    $local = [
        'service_id' => (int) $service->id,
        'service_name' => (string) $service->name,
        'service_status' => (int) $service->status,
        'product_id' => (int) ($product->id ?? 0),
        'product_name' => (string) ($product->name ?? ''),
        'product_type' => (string) ($product->product_type ?? ''),
        'category_id' => (int) ($product->category_id ?? 0),
        'supplier_id' => (int) ($supplier->id ?? 0),
        'supplier_name' => (string) ($supplier->name ?? ''),
        'supplier_interface' => (string) ($supplier->interface_type ?? ''),
        'upstream_host_id' => $hostId,
        'upstream_product_id' => (int) ($provision['upstream_product_id'] ?? 0),
        'local_bw_usage' => $provision['bw_usage'] ?? null,
        'local_bw_limit' => $provision['bw_limit'] ?? null,
        'provision_keys' => array_keys($provision),
    ];

    $lines[] = 'LOCAL:';
    $lines[] = json_encode($local, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if (! $supplier) {
        $lines[] = 'result=supplier_missing';
        file_put_contents($out, implode("\n", $lines)."\n");

        return;
    }
    if ($hostId <= 0) {
        $lines[] = 'result=host_id_missing_in_provision_data';
        file_put_contents($out, implode("\n", $lines)."\n");

        return;
    }
    if (trim((string) $supplier->interface_type) !== 'mofang_finance_api') {
        $lines[] = 'result=supplier_not_mofang ('.$supplier->interface_type.')';
        file_put_contents($out, implode("\n", $lines)."\n");

        return;
    }

    $client = app(MofangFinanceClient::class);
    $resp = $client->getHostDetail($supplier, $hostId);

    $host = is_array(data_get($resp, 'data.host')) ? (array) data_get($resp, 'data.host') : [];

    $remote = [
        'status' => $resp['status'] ?? null,
        'msg' => $resp['msg'] ?? null,
        'bwusage' => $host['bwusage'] ?? null,
        'bwusage_type' => gettype($host['bwusage'] ?? null),
        'bwlimit' => $host['bwlimit'] ?? null,
        'bwlimit_type' => gettype($host['bwlimit'] ?? null),
        'host_keys_sample' => array_slice(array_keys($host), 0, 60),
    ];

    $lines[] = 'REMOTE:';
    $lines[] = json_encode($remote, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    $bwlimit = $host['bwlimit'] ?? null;
    $bwusage = $host['bwusage'] ?? null;
    $limited = is_numeric($bwlimit) && (int) $bwlimit > 0;
    $percent = $limited && is_numeric($bwusage)
        ? min(round(((float) $bwusage / (float) $bwlimit) * 100, 2), 100.0)
        : null;

    $lines[] = 'COMPUTED:';
    $lines[] = json_encode([
        'limited' => $limited,
        'usage_percent' => $percent,
        'frontend_branch' => $limited
            ? 'show progress bar (detail.traffic.limited=true)'
            : "fallback to findSpecValue(['流量','月流量','总流量'])",
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    $lines[] = 'result=ok';
} catch (Throwable $e) {
    $lines[] = 'ERROR: '.$e->getMessage();
    $lines[] = 'FILE:  '.$e->getFile().':'.$e->getLine();
    $lines[] = $e->getTraceAsString();
}

file_put_contents($out, implode("\n", $lines)."\n");
