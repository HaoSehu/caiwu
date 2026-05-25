<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AccountLedger;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ServiceInstance;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\UserAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IdcModelLayerSmokeTest extends TestCase
{
    public function test_core_models_use_idc_connection(): void
    {
        $this->assertSame('idc', (new ServiceInstance)->getConnectionName());
        $this->assertSame('service_instances', (new ServiceInstance)->getTable());
        $this->assertSame('idc', (new AccountLedger)->getConnectionName());
        $this->assertSame('idc', (new Invoice)->getConnectionName());
        $this->assertSame('idc', (new Ticket)->getConnectionName());
    }

    public function test_core_relations_are_available(): void
    {
        $this->assertTrue(method_exists(Invoice::class, 'serviceInstance'));
        $this->assertTrue(method_exists(Invoice::class, 'refunds'));
        $this->assertTrue(method_exists(User::class, 'serviceInstances'));
        $this->assertTrue(method_exists(User::class, 'accountLedgers'));
        $this->assertTrue(method_exists(Product::class, 'pricingPlans'));
        $this->assertTrue(method_exists(Coupon::class, 'invoices'));
        $this->assertTrue(method_exists(Ticket::class, 'serviceInstance'));
    }

    public function test_user_account_model_exposes_new_reconciliation_fields(): void
    {
        $account = new UserAccount([
            'frozen_cash_balance' => '0.00',
            'last_reconciled_at' => '2026-05-18 01:39:01',
            'migrated_balance_diff' => '12.34',
        ]);

        $this->assertTrue(in_array('frozen_cash_balance', $account->getFillable(), true));
        $this->assertTrue(in_array('last_reconciled_at', $account->getFillable(), true));
        $this->assertTrue(in_array('migrated_balance_diff', $account->getFillable(), true));
        $this->assertSame('0.00', $account->frozen_cash_balance);
        $this->assertSame('12.34', $account->migrated_balance_diff);
        $this->assertNotNull($account->last_reconciled_at);
    }

    public function test_ticket_legacy_aliases_map_to_current_columns(): void
    {
        $ticket = new Ticket;
        $ticket->service_id = 12;
        $ticket->assignee_id = 8;

        $this->assertSame(12, $ticket->service_id);
        $this->assertSame(12, $ticket->service_instance_id);
        $this->assertSame(8, $ticket->assignee_id);
        $this->assertSame(8, $ticket->assignee_admin_id);
    }

    public function test_ticket_reply_legacy_aliases_map_to_current_columns(): void
    {
        $reply = new TicketReply;
        $reply->attachments = [['name' => 'a.png', 'path' => '/a.png']];
        $reply->is_staff = 1;

        $this->assertSame(1, $reply->is_staff);
        $this->assertSame('admin', $reply->sender_type);
        $this->assertIsArray($reply->attachments);
        $this->assertSame('a.png', $reply->attachments[0]['name'] ?? null);
    }

    public function test_service_instances_table_exposes_core_indexes(): void
    {
        $indexes = collect(DB::select("
            SELECT DISTINCT index_name AS index_name
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'service_instances'
        "))
            ->map(fn (object $row) => (string) $row->index_name)
            ->values()
            ->all();

        $this->assertContains('service_instances_service_no_unique', $indexes);
        $this->assertContains('service_instances_instance_identifier_unique', $indexes);
        $this->assertContains('service_instances_user_id_index', $indexes);
        $this->assertContains('service_instances_product_id_index', $indexes);
        $this->assertContains('service_instances_status_expires_at_index', $indexes);
    }

    public function test_service_instances_table_exposes_core_foreign_keys(): void
    {
        $foreignKeys = collect(DB::select("
            SELECT constraint_name AS constraint_name
            FROM information_schema.table_constraints
            WHERE table_schema = DATABASE()
              AND table_name = 'service_instances'
              AND constraint_type = 'FOREIGN KEY'
        "))
            ->map(fn (object $row) => (string) $row->constraint_name)
            ->values()
            ->all();

        $this->assertContains('fk_service_instances_user_id', $foreignKeys);
        $this->assertContains('fk_service_instances_product_id', $foreignKeys);
        $this->assertContains('fk_service_instances_source_invoice_id', $foreignKeys);
    }
}
