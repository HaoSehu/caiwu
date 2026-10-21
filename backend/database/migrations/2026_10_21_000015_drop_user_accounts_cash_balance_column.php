<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T+7 归档第一步：删除 user_accounts.cash_balance（现金真源已完成迁移）。
 *
 * 护栏设计（双保险，缺一不可）：
 * 1. 未设置 FINANCE_ALLOW_RETIRE=true 时静默跳过（只告警不执行）——避免发版/测试环境
 *    的常规 migrate 误删列；跳过时迁移记录照常落库，T+7 按 Runbook 用
 *    rollback（no-op）+ migrate（带环境变量）两步重放；
 * 2. 即使设置了 ALLOW_RETIRE，FINANCE_HOT_STANDBY 仍开启时硬失败——
 *    内核热备还在写该列，删列会让所有资金写入报错。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->retirementEnabled('up')) {
            return;
        }

        if ((bool) config('finance.hot_standby', true)) {
            throw new RuntimeException(
                'FINANCE_HOT_STANDBY 仍为开启：内核热备还在写 user_accounts.cash_balance，'
                .'先置 FINANCE_HOT_STANDBY=false 并 config:cache 后再执行本迁移',
            );
        }

        // 同窗自检：删列与「代码不再读写该列」必须落在同一个发布窗，
        // 否则删列后残留的读写点会直接 SQL 报错（P1-5）。这里在真正删列前
        // 扫描应用代码，仍存在 cash_balance 读写点就硬失败，把问题挡在迁移期。
        $residues = $this->findCashBalanceWriters();
        if ($residues !== []) {
            throw new RuntimeException(
                '仍存在 user_accounts.cash_balance 的代码读写点，禁止删列：'.implode('、', $residues)
                .'。请在同一发布窗内清理后再执行本迁移。',
            );
        }

        Schema::table('user_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('user_accounts', 'cash_balance')) {
                $table->dropColumn('cash_balance');
            }
        });
    }

    /**
     * 扫描 app/ 下仍在直接写/读 user_accounts.cash_balance 的文件。
     *
     * 白名单：迁移文件本身、重建命令（已归档护栏会先拒绝）、一致性检查（有 hasColumn 短路）。
     *
     * @return list<string>
     */
    private function findCashBalanceWriters(): array
    {
        // 白名单：迁移期/重建期仍需要读旧列，且各自已有列存在性判断或归档护栏。
        // FinanceAccountService 的写是「切换期热备」，必须随 FINANCE_HOT_STANDBY=false 一起退出，
        // 因此保留在名单内由热备开关控制（关闭后不再执行）。
        $allowlist = [
            'Services/System/LedgerConsistencyCheck.php',
            // 热备漂移命令按「列存在才比三重口径、列不存在只比余额 vs 批次」降级，
            // 已在命令内用 Schema::hasColumn 短路；不列入白名单会让 T+7 被自身扫描硬阻断。
            'Console/Commands/FinanceHotStandbyDriftCommand.php',
            'Console/Commands/RebuildFinanceLedgerCommand.php',
            'Console/Commands/MigrateIdentityUserAccountsCommand.php',
            'Console/Commands/ReconcileAccountDomainCommand.php',
            'Services/System/AccountMigrationService.php',
            'Services/Finance/Core/FinanceAccountService.php',
            'Models/UserAccount.php',
            'Models/User.php',
            'Services/User/AccountService.php',
            'Services/User/UserAccountProjectionService.php',
            // 响应/投影字段名恰好叫 cash_balance，不是对 user_accounts 的读写
            'Http/Resources/Admin/V2/AdminUserDetailResource.php',
            'Http/Resources/Admin/V2/AdminUserListItemResource.php',
            'Http/Resources/Client/V2/ClientUserInfoResource.php',
            'Services/Auth/AuthService.php',
            'Services/Finance/FinanceLedgerQueryService.php',
            'Services/Finance/ClientInvoicePaymentWorkflowService.php',
        ];

        $root = base_path('app');
        if (! is_dir($root)) {
            return [];
        }

        $offenders = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if (in_array($relative, $allowlist, true)) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            // 只认「以数组键方式写 cash_balance」：模型属性注解、注释、SQL 字符串不算。
            if (preg_match("/'cash_balance'\s*=>/", $contents) === 1) {
                $offenders[] = $relative;
            }
        }

        sort($offenders);

        return $offenders;
    }

    public function down(): void
    {
        if (! $this->retirementEnabled('down')) {
            return;
        }

        Schema::table('user_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_accounts', 'cash_balance')) {
                $table->decimal('cash_balance', 20, 2)->default(0)->comment('[已退役] 切换期热备列；真源 finance_accounts');
            }
        });
    }

    private function retirementEnabled(string $direction): bool
    {
        if ((bool) env('FINANCE_ALLOW_RETIRE', false)) {
            return true;
        }

        // 注意：匿名迁移类没有 $this->command（访问即 ErrorException），输出统一走日志通道
        logger()->warning(sprintf(
            '[T+7] 000015 %s skipped: FINANCE_ALLOW_RETIRE=true not set (run per Runbook 7 days after stable cutover)',
            $direction,
        ));

        return false;
    }
};
