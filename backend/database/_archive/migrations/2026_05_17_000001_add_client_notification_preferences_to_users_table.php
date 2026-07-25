<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'login_notify')) {
                $table->boolean('login_notify')->default(true)->after('login_email_alert')->comment('账号登录提醒 0关闭 1开启');
            }

            if (! Schema::hasColumn('users', 'login_location_alert')) {
                $table->boolean('login_location_alert')->default(true)->after('login_notify')->comment('异地登录提醒 0关闭 1开启');
            }

            if (! Schema::hasColumn('users', 'password_change_alert')) {
                $table->boolean('password_change_alert')->default(true)->after('login_location_alert')->comment('密码变更提醒 0关闭 1开启');
            }

            if (! Schema::hasColumn('users', 'phone_change_alert')) {
                $table->boolean('phone_change_alert')->default(true)->after('password_change_alert')->comment('手机号变更提醒 0关闭 1开启');
            }

            if (! Schema::hasColumn('users', 'email_change_alert')) {
                $table->boolean('email_change_alert')->default(true)->after('phone_change_alert')->comment('邮箱变更提醒 0关闭 1开启');
            }

            if (! Schema::hasColumn('users', 'marketing_alert')) {
                $table->boolean('marketing_alert')->default(false)->after('email_change_alert')->comment('营销提醒接收 0关闭 1开启');
            }
        });

        $hasLegacyLoginAlert = Schema::hasColumn('users', 'login_email_alert');

        if (Schema::hasColumn('users', 'login_notify')) {
            DB::table('users')->update([
                'login_notify' => $hasLegacyLoginAlert ? DB::raw('COALESCE(login_email_alert, 1)') : 1,
            ]);
        }

        if (Schema::hasColumn('users', 'login_location_alert')) {
            DB::table('users')->whereNull('login_location_alert')->update(['login_location_alert' => 1]);
        }

        if (Schema::hasColumn('users', 'password_change_alert')) {
            DB::table('users')->whereNull('password_change_alert')->update(['password_change_alert' => 1]);
        }

        if (Schema::hasColumn('users', 'phone_change_alert')) {
            DB::table('users')->whereNull('phone_change_alert')->update(['phone_change_alert' => 1]);
        }

        if (Schema::hasColumn('users', 'email_change_alert')) {
            DB::table('users')->whereNull('email_change_alert')->update(['email_change_alert' => 1]);
        }

        if (Schema::hasColumn('users', 'marketing_alert')) {
            DB::table('users')->whereNull('marketing_alert')->update(['marketing_alert' => 0]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'marketing_alert',
                'email_change_alert',
                'phone_change_alert',
                'password_change_alert',
                'login_location_alert',
                'login_notify',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
