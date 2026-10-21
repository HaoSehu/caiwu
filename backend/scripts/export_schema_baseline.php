<?php

declare(strict_types=1);

use App\Support\Upgrade\MigrationInventory;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

define('LARAVEL_START', microtime(true));

$basePath = dirname(__DIR__);

require $basePath.'/vendor/autoload.php';

$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$options = getopt('', ['dry-run', 'output:', 'force']);
$dryRun = array_key_exists('dry-run', $options);
$force = array_key_exists('force', $options);
$output = isset($options['output']) && trim((string) $options['output']) !== ''
    ? (string) $options['output']
    : $basePath.'/database/schema/mysql-schema.sql';
$metaPath = dirname($output).'/baseline-meta.json';

$connection = DB::connection((string) config('database.default', 'mysql'));
$database = (string) $connection->getDatabaseName();

// 护栏：schema baseline 一旦在同一次发布里既导出又部署，半程结构会被固化成「新基线」，
// 之后所有人都以为结构已就绪。因此导出前必须确认没有待执行迁移。
// 只有明确知道自己在做什么（例如刻意重建基线）时才允许 --force 绕过。
$pendingMigrations = MigrationInventory::build()->pending;
if ($pendingMigrations !== [] && ! $force) {
    fwrite(STDERR, sprintf(
        '拒绝导出 schema baseline：数据库还有 %d 个待执行迁移。%s请先执行 php artisan db:upgrade，或确认要强制导出时追加 --force。%s待执行清单：%s%s',
        count($pendingMigrations),
        PHP_EOL,
        PHP_EOL,
        implode(', ', $pendingMigrations),
        PHP_EOL,
    ));
    exit(1);
}

$tables = $connection->select(<<<'SQL'
    SELECT TABLE_NAME AS table_name
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_TYPE = 'BASE TABLE'
    ORDER BY TABLE_NAME
SQL);

$lines = [
    '/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;',
    "/*!40103 SET TIME_ZONE='+00:00' */;",
    '/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;',
    '/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;',
    "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;",
    '/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;',
    '',
];

$tableNames = [];

foreach ($tables as $table) {
    $tableName = (string) $table->table_name;
    $tableNames[] = $tableName;
    $escapedTable = str_replace('`', '``', $tableName);
    $createRow = $connection->selectOne("SHOW CREATE TABLE `{$escapedTable}`");
    $createSql = showCreateTableSql($createRow);

    if ($createSql === '') {
        throw new RuntimeException("Unable to read SHOW CREATE TABLE for {$tableName}");
    }

    $lines[] = "DROP TABLE IF EXISTS `{$escapedTable}`;";
    $lines[] = '/*!40101 SET @saved_cs_client     = @@character_set_client */;';
    $lines[] = '/*!50503 SET character_set_client = utf8mb4 */;';
    $lines[] = rtrim($createSql, ';').';';
    $lines[] = '/*!40101 SET character_set_client = @saved_cs_client */;';
    $lines[] = '';
}

if (in_array('migrations', $tableNames, true)) {
    $migrationRows = $connection->table('migrations')
        ->select(['id', 'migration', 'batch'])
        ->orderBy('id')
        ->get();

    foreach ($migrationRows as $migration) {
        $lines[] = sprintf(
            'INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (%d,%s,%d);',
            (int) $migration->id,
            quoteSqlString((string) $migration->migration),
            (int) $migration->batch
        );
    }

    if ($migrationRows->isNotEmpty()) {
        $lines[] = '';
    }
}

$lines[] = '/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;';
$lines[] = '/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;';
$lines[] = '/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;';
$lines[] = '/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;';
$lines[] = '/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;';

$payload = implode("\n", $lines)."\n";

$migrationCount = in_array('migrations', $tableNames, true) ? (int) ($migrationRows ?? collect())->count() : 0;

if (! $dryRun) {
    $directory = dirname($output);
    if (! is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    file_put_contents($output, $payload);

    // 基线元数据：让 db:upgrade-status 与一致性测试能验证「基线是否与当前实库同步」，
    // 而不是靠人肉比对 4000 行 SQL。
    $meta = [
        'description' => 'schema baseline 导出元数据，用于检测基线是否滞后于实库。由 export_schema_baseline.php 生成，禁止手工编辑。',
        'generated_by' => 'backend/scripts/export_schema_baseline.php',
        'exported_at' => date('Y-m-d H:i:s'),
        'database' => $database,
        'table_count' => count($tableNames),
        'migration_count' => $migrationCount,
        'source_commit' => trim((string) @shell_exec('git rev-parse HEAD 2>&1')) ?: null,
    ];
    file_put_contents(
        $metaPath,
        json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL,
    );
}

fwrite(STDOUT, sprintf(
    '%s schema baseline: %s, database: %s, tables: %d, migrations: %d%s',
    $dryRun ? 'Dry-run' : 'Generated',
    $output,
    $database,
    count($tableNames),
    $migrationCount,
    PHP_EOL
));

function showCreateTableSql(?object $row): string
{
    if (! $row) {
        return '';
    }

    foreach ((array) $row as $key => $value) {
        if (strtolower((string) $key) === 'create table') {
            return (string) $value;
        }
    }

    return '';
}

function quoteSqlString(string $value): string
{
    return "'".str_replace(['\\', "'"], ['\\\\', "''"], $value)."'";
}
