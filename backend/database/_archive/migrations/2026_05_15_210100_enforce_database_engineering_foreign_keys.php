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
        Schema::table('invoice_items', function (Blueprint $table): void {
            if (! $this->hasForeign('invoice_items', 'fk_invoice_items_invoice_id')) {
                $table->foreign('invoice_id', 'fk_invoice_items_invoice_id')
                    ->references('id')->on('invoices')
                    ->cascadeOnDelete();
            }
        });

        Schema::table('payments', function (Blueprint $table): void {
            if (! $this->hasForeign('payments', 'fk_payments_invoice_id')) {
                $table->foreign('invoice_id', 'fk_payments_invoice_id')
                    ->references('id')->on('invoices')
                    ->restrictOnDelete();
            }

            if (! $this->hasForeign('payments', 'fk_payments_user_id')) {
                $table->foreign('user_id', 'fk_payments_user_id')
                    ->references('id')->on('users')
                    ->restrictOnDelete();
            }
        });

        Schema::table('payment_callbacks', function (Blueprint $table): void {
            if (! $this->hasForeign('payment_callbacks', 'fk_payment_callbacks_payment_id')) {
                $table->foreign('payment_id', 'fk_payment_callbacks_payment_id')
                    ->references('id')->on('payments')
                    ->cascadeOnDelete();
            }
        });

        Schema::table('user_accounts', function (Blueprint $table): void {
            if (! $this->hasForeign('user_accounts', 'fk_user_accounts_user_id')) {
                $table->foreign('user_id', 'fk_user_accounts_user_id')
                    ->references('id')->on('users')
                    ->restrictOnDelete();
            }
        });

        Schema::table('ticket_replies', function (Blueprint $table): void {
            if (! $this->hasForeign('ticket_replies', 'fk_ticket_replies_ticket_id')) {
                $table->foreign('ticket_id', 'fk_ticket_replies_ticket_id')
                    ->references('id')->on('tickets')
                    ->cascadeOnDelete();
            }
        });

        Schema::table('services', function (Blueprint $table): void {
            if (! $this->hasForeign('services', 'fk_services_user_id')) {
                $table->foreign('user_id', 'fk_services_user_id')
                    ->references('id')->on('users')
                    ->restrictOnDelete();
            }

            if (! $this->hasForeign('services', 'fk_services_product_id')) {
                $table->foreign('product_id', 'fk_services_product_id')
                    ->references('id')->on('products')
                    ->restrictOnDelete();
            }

            if (! $this->hasForeign('services', 'fk_services_invoice_id')) {
                $table->foreign('invoice_id', 'fk_services_invoice_id')
                    ->references('id')->on('invoices')
                    ->nullOnDelete();
            }
        });

        Schema::table('invoices', function (Blueprint $table): void {
            if (! $this->hasForeign('invoices', 'fk_invoices_user_id')) {
                $table->foreign('user_id', 'fk_invoices_user_id')
                    ->references('id')->on('users')
                    ->restrictOnDelete();
            }

            if (! $this->hasForeign('invoices', 'fk_invoices_product_id')) {
                $table->foreign('product_id', 'fk_invoices_product_id')
                    ->references('id')->on('products')
                    ->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        $drops = [
            'invoice_items' => ['fk_invoice_items_invoice_id'],
            'payments' => ['fk_payments_invoice_id', 'fk_payments_user_id'],
            'payment_callbacks' => ['fk_payment_callbacks_payment_id'],
            'user_accounts' => ['fk_user_accounts_user_id'],
            'ticket_replies' => ['fk_ticket_replies_ticket_id'],
            'services' => ['fk_services_user_id', 'fk_services_product_id', 'fk_services_invoice_id'],
            'invoices' => ['fk_invoices_user_id', 'fk_invoices_product_id'],
        ];

        foreach ($drops as $tableName => $constraints) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $constraints): void {
                foreach ($constraints as $constraint) {
                    if ($this->hasForeign($tableName, $constraint)) {
                        $table->dropForeign($constraint);
                    }
                }
            });
        }
    }

    private function hasForeign(string $tableName, string $constraintName): bool
    {
        return $this->foreignKeyExistsViaInformationSchema($tableName, $constraintName);
    }

    private function foreignKeyExistsViaInformationSchema(string $tableName, string $constraintName): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('table_schema', Schema::getConnection()->getDatabaseName())
            ->where('table_name', $tableName)
            ->where('constraint_name', $constraintName)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};
