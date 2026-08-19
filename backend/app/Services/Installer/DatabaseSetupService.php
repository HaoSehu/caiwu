<?php

declare(strict_types=1);

namespace App\Services\Installer;

use PDO;
use PDOException;
use RuntimeException;

class DatabaseSetupService
{
    public function verify(array $data): array
    {
        $host = (string) ($data['host'] ?? '127.0.0.1');
        $port = (int) ($data['port'] ?? 3306);
        $database = (string) ($data['database'] ?? '');
        $username = (string) ($data['username'] ?? '');
        $password = (string) ($data['password'] ?? '');
        if (! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new RuntimeException('数据库名称格式无效');
        }
        try {
            $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            if (version_compare(preg_replace('/[^0-9.].*/', '', $version) ?: '0', '8.0.0', '<')) {
                throw new RuntimeException('MySQL 版本必须为 8.0 或更高');
            }
            $exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?');
            $exists->execute([$database]);
            $databaseCreated = (int) $exists->fetchColumn() === 0;
            $quoted = '`'.str_replace('`', '``', $database).'`';
            $pdo->exec("CREATE DATABASE IF NOT EXISTS {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $db = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $statement = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?');
            $statement->execute([$database]);

            return ['version' => $version, 'database_created' => $databaseCreated, 'table_count' => (int) $statement->fetchColumn()];
        } catch (PDOException $e) {
            throw new RuntimeException('数据库连接失败：'.$e->getMessage(), 0, $e);
        }
    }
}
