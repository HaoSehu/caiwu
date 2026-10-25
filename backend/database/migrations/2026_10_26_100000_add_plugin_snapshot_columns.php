<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 插件数据链快照列（P-01 / P-02 / P-08 / P-10 / V-01）。
 *
 * 背景：插件卸载会把 integration_plugin_bindings 的绑定记录硬删，并把
 * payments.plugin_id 外键置空（nullOnDelete），导致历史资金数据的来源插件
 * 无法追溯；同时支付创建时只落 gateway_key（alipay/yipay），丢失了「具体是
 * 哪个插件、哪个通道」的信息（例如「易支付-微信」）。
 *
 * 策略：在所有需要长期留存的业务表上增加「插件身份文本快照」列。快照列是
 * 冗余字段，写入时由业务层从当前插件上下文复制，读侧优先使用；plugin_id
 * 仅作在线关联，插件被删后快照仍可独立还原展示文案。
 *
 * 列宽统一 varchar(120)：与 integration_plugins.plugin_key / name 一致，
 * 避免未来插件名超长在快照列被截断。
 *
 * 回填视为一次性口径：payments / finance_refunds / gateway_logs 依据现存
 * plugin_id 关联回填，属于事实映射；verification_histories 没有历史插件归
 * 属，按「当前唯一启用的实名认证插件」推断回填（单启用约束见
 * PluginDomain::singleEnabledDomains），该推断已在 TODO 清单 D5 中确认。
 *
 * 幂等：MySQL DDL 无法随事务回滚，每一列、每一个索引都单独判断存在性后
 * 再执行，保证部分生效后重试不会因「已存在」失败。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments')) {
            $this->addPaymentsColumns();
        }

        if (Schema::hasTable('finance_refunds')) {
            Schema::table('finance_refunds', function (Blueprint $table): void {
                if (! Schema::hasColumn('finance_refunds', 'plugin_id')) {
                    $table->unsignedBigInteger('plugin_id')->nullable()->after('payment_id')->comment('原支付单的支付插件ID快照，余额退款为空');
                }

                if (! Schema::hasColumn('finance_refunds', 'plugin_key')) {
                    $table->string('plugin_key', 120)->nullable()->after('plugin_id')->comment('原支付单的支付插件标识快照');
                }

                if (! Schema::hasColumn('finance_refunds', 'plugin_name')) {
                    $table->string('plugin_name', 120)->nullable()->after('plugin_key')->comment('原支付单的支付插件名称快照');
                }

                if (! Schema::hasColumn('finance_refunds', 'gateway_key')) {
                    $table->string('gateway_key', 120)->nullable()->after('plugin_name')->comment('原支付单的网关标识快照');
                }

                if (! Schema::hasColumn('finance_refunds', 'payment_type')) {
                    $table->string('payment_type', 20)->nullable()->after('gateway_key')->comment('原支付单的网关内支付类型快照');
                }
            });
        }

        if (Schema::hasTable('verification_histories')) {
            Schema::table('verification_histories', function (Blueprint $table): void {
                if (! Schema::hasColumn('verification_histories', 'plugin_id')) {
                    $table->unsignedBigInteger('plugin_id')->nullable()->after('verification_type')->comment('本次认证使用的实名认证插件ID快照');
                }

                if (! Schema::hasColumn('verification_histories', 'plugin_key')) {
                    $table->string('plugin_key', 120)->nullable()->after('plugin_id')->comment('本次认证使用的实名认证插件标识快照');
                }

                if (! Schema::hasColumn('verification_histories', 'plugin_name')) {
                    $table->string('plugin_name', 120)->nullable()->after('plugin_key')->comment('本次认证使用的实名认证插件名称快照，如 百度智能云人脸实名认证');
                }

                if (! Schema::hasColumn('verification_histories', 'driver_key')) {
                    $table->string('driver_key', 120)->nullable()->after('plugin_name')->comment('本次认证使用的驱动标识快照，渠道排障用');
                }
            });

            if (! Schema::hasIndex('verification_histories', 'verification_histories_plugin_submitted_idx')) {
                Schema::table('verification_histories', function (Blueprint $table): void {
                    $table->index(['plugin_id', 'submitted_at'], 'verification_histories_plugin_submitted_idx');
                });
            }
        }

        if (Schema::hasTable('gateway_logs')) {
            Schema::table('gateway_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('gateway_logs', 'plugin_key')) {
                    $table->string('plugin_key', 120)->nullable()->after('plugin_id')->comment('网关请求时的支付插件标识快照；gateway 列仍保留插件上报的原始网关串以便排障');
                }
            });

            // 与既有 gateway_logs_gateway_key_idx (gateway_key, created_at) 对称，支撑按插件聚合日志（P-10）。
            if (! Schema::hasIndex('gateway_logs', 'gateway_logs_plugin_key_created_idx')) {
                Schema::table('gateway_logs', function (Blueprint $table): void {
                    $table->index(['plugin_key', 'created_at'], 'gateway_logs_plugin_key_created_idx');
                });
            }
        }

        $this->backfillSnapshots();
    }

    public function down(): void
    {
        if (Schema::hasTable('verification_histories')
            && Schema::hasIndex('verification_histories', 'verification_histories_plugin_submitted_idx')) {
            Schema::table('verification_histories', function (Blueprint $table): void {
                $table->dropIndex('verification_histories_plugin_submitted_idx');
            });
        }

        if (Schema::hasTable('gateway_logs')
            && Schema::hasIndex('gateway_logs', 'gateway_logs_plugin_key_created_idx')) {
            Schema::table('gateway_logs', function (Blueprint $table): void {
                $table->dropIndex('gateway_logs_plugin_key_created_idx');
            });
        }

        $this->dropColumns('gateway_logs', ['plugin_key']);
        $this->dropColumns('verification_histories', ['driver_key', 'plugin_name', 'plugin_key', 'plugin_id']);
        $this->dropColumns('finance_refunds', ['payment_type', 'gateway_key', 'plugin_name', 'plugin_key', 'plugin_id']);
        $this->dropColumns('payments', ['option_name', 'option_key', 'payment_type', 'plugin_name', 'plugin_key']);
    }

    /**
     * payments 是新旧两代支付链共用的主表，列数最多，单列一个闭包便于逐列幂等判断。
     */
    private function addPaymentsColumns(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'plugin_key')) {
                $table->string('plugin_key', 120)->nullable()->after('plugin_id')->comment('来源支付插件标识快照，卸载插件后仍可追溯');
            }

            if (! Schema::hasColumn('payments', 'plugin_name')) {
                $table->string('plugin_name', 120)->nullable()->after('plugin_key')->comment('来源支付插件名称快照，用于展示与对账');
            }

            if (! Schema::hasColumn('payments', 'payment_type')) {
                $table->string('payment_type', 20)->nullable()->after('gateway_key')->comment('网关内支付类型快照，如 wxpay/alipay，区分同插件下的多条通道');
            }

            if (! Schema::hasColumn('payments', 'option_key')) {
                $table->string('option_key', 120)->nullable()->after('payment_type')->comment('下单所选通道标识快照，如 yipay:wxpay');
            }

            if (! Schema::hasColumn('payments', 'option_name')) {
                $table->string('option_name', 120)->nullable()->after('option_key')->comment('下单所选通道名称快照，如 易支付 - 微信');
            }
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropColumns(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->dropColumn($column);
            });
        }
    }

    private function backfillSnapshots(): void
    {
        if (! Schema::hasTable('integration_plugins')) {
            return;
        }

        if (Schema::hasTable('payments')
            && Schema::hasColumn('payments', 'plugin_key')
            && Schema::hasColumn('payments', 'plugin_name')) {
            DB::statement(<<<'SQL'
                UPDATE payments
                INNER JOIN integration_plugins ON integration_plugins.id = payments.plugin_id
                SET payments.plugin_key = integration_plugins.plugin_key,
                    payments.plugin_name = integration_plugins.name
                WHERE payments.plugin_id IS NOT NULL
                  AND (payments.plugin_key IS NULL OR payments.plugin_name IS NULL)
            SQL);
        }

        if (Schema::hasTable('finance_refunds')
            && Schema::hasColumn('finance_refunds', 'plugin_key')
            && Schema::hasColumn('finance_refunds', 'gateway_key')) {
            DB::statement(<<<'SQL'
                UPDATE finance_refunds
                INNER JOIN payments ON payments.id = finance_refunds.payment_id
                SET finance_refunds.plugin_id = payments.plugin_id,
                    finance_refunds.plugin_key = payments.plugin_key,
                    finance_refunds.plugin_name = payments.plugin_name,
                    finance_refunds.gateway_key = payments.gateway_key,
                    finance_refunds.payment_type = payments.payment_type
                WHERE finance_refunds.payment_id IS NOT NULL
                  AND finance_refunds.plugin_key IS NULL
            SQL);
        }

        if (Schema::hasTable('verification_histories')
            && Schema::hasColumn('verification_histories', 'plugin_key')) {
            // 历史实名记录没有插件归属，按当前启用的实名认证插件推断回填（D5）。
            DB::statement(<<<'SQL'
                UPDATE verification_histories
                SET plugin_id = (
                        SELECT integration_plugins.id
                        FROM integration_plugins
                        WHERE integration_plugins.domain = 'verification'
                          AND integration_plugins.status = 1
                        ORDER BY integration_plugins.id ASC
                        LIMIT 1
                    ),
                    plugin_key = (
                        SELECT integration_plugins.plugin_key
                        FROM integration_plugins
                        WHERE integration_plugins.domain = 'verification'
                          AND integration_plugins.status = 1
                        ORDER BY integration_plugins.id ASC
                        LIMIT 1
                    ),
                    plugin_name = (
                        SELECT integration_plugins.name
                        FROM integration_plugins
                        WHERE integration_plugins.domain = 'verification'
                          AND integration_plugins.status = 1
                        ORDER BY integration_plugins.id ASC
                        LIMIT 1
                    ),
                    driver_key = (
                        SELECT COALESCE(
                            (
                                SELECT bindings.provider_key
                                FROM integration_plugin_bindings AS bindings
                                WHERE bindings.plugin_id = plugins.id
                                  AND bindings.domain = 'verification'
                                  AND bindings.bindable_type = 'setting'
                                  AND bindings.bindable_id = 0
                                  AND bindings.binding_key = 'verification_driver'
                                  AND bindings.status = 1
                                ORDER BY bindings.priority ASC, bindings.id DESC
                                LIMIT 1
                            ),
                            plugins.plugin_key
                        )
                        FROM integration_plugins AS plugins
                        WHERE plugins.domain = 'verification'
                          AND plugins.status = 1
                        ORDER BY plugins.id ASC
                        LIMIT 1
                    )
                WHERE plugin_id IS NULL
                  AND plugin_key IS NULL
            SQL);
        }

        if (Schema::hasTable('gateway_logs')
            && Schema::hasColumn('gateway_logs', 'plugin_key')) {
            DB::statement(<<<'SQL'
                UPDATE gateway_logs
                INNER JOIN integration_plugins ON integration_plugins.id = gateway_logs.plugin_id
                SET gateway_logs.plugin_key = integration_plugins.plugin_key
                WHERE gateway_logs.plugin_id IS NOT NULL
                  AND gateway_logs.plugin_key IS NULL
            SQL);
        }
    }
};
