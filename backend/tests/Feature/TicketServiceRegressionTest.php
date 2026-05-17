<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Jobs\SendTicketNotificationEmailJob;
use App\Constants\ServiceStatus;
use App\Models\Role;
use App\Models\Product;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\ClientServiceConsole\ServiceTransformService;
use App\Services\System\NotificationService;
use App\Services\System\UploadedAssetReferenceService;
use App\Services\Ticket\TicketService;
use App\Support\AdminPermissions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketServiceRegressionTest extends TestCase
{
    public function test_service_status_labels_match_client_semantics(): void
    {
        $this->assertSame('开通中', ServiceStatus::$labels[ServiceStatus::PENDING] ?? null);
        $this->assertSame('已开通', ServiceStatus::$labels[ServiceStatus::ACTIVE] ?? null);
        $this->assertSame('已暂停', ServiceStatus::$labels[ServiceStatus::SUSPENDED] ?? null);
        $this->assertSame('已到期', ServiceStatus::$labels[ServiceStatus::EXPIRED] ?? null);
        $this->assertSame('已取消', ServiceStatus::$labels[ServiceStatus::CANCELLED] ?? null);
    }

    public function test_ticket_create_works_without_ticket_messages_table(): void
    {
        $this->runWithoutTicketMessagesTable(function (): void {
            $service = $this->makeTicketService();
            $user = $this->createClientUser('ticket-create');

            $ticket = $service->create((int) $user->id, [
                'department' => 'support',
                'subject' => 'Ticket Create Regression',
                'content' => 'Initial message',
                'priority' => 2,
            ]);

            $this->assertInstanceOf(Ticket::class, $ticket);
            $this->assertSame(TicketService::STATUS_OPEN, (int) $ticket->status);
            $this->assertSame(1, TicketReply::query()->where('ticket_id', (int) $ticket->id)->count());
        });
    }

    public function test_ticket_replies_work_without_ticket_messages_table(): void
    {
        $this->runWithoutTicketMessagesTable(function (): void {
            $service = $this->makeTicketService();
            $user = $this->createClientUser('ticket-reply');
            $staff = $this->createStaffUser();

            $ticket = $service->create((int) $user->id, [
                'department' => 'support',
                'subject' => 'Ticket Reply Regression',
                'content' => 'Initial message',
                'priority' => 2,
            ]);

            $clientReply = $service->clientReply($ticket->fresh(), (int) $user->id, 'Client follow-up', []);
            $staffReply = $service->staffReply($ticket->fresh(), (int) $staff->id, 'Staff response', []);

            $this->assertSame('Client follow-up', (string) ($clientReply['content'] ?? ''));
            $this->assertSame('Staff response', (string) ($staffReply['content'] ?? ''));
            $this->assertSame(3, TicketReply::query()->where('ticket_id', (int) $ticket->id)->count());
            $this->assertSame(
                TicketService::STATUS_STAFF_REPLY,
                (int) Ticket::query()->findOrFail((int) $ticket->id)->status
            );
        });
    }

    public function test_admin_list_ongoing_status_only_returns_unclosed_tickets(): void
    {
        $service = $this->makeTicketService();
        $user = $this->createClientUser('ticket-ongoing');

        $openTicket = Ticket::query()->create([
            'user_id' => (int) $user->id,
            'department' => 'support',
            'subject' => 'Open Ticket',
            'priority' => 2,
            'status' => TicketService::STATUS_OPEN,
        ]);

        $clientReplyTicket = Ticket::query()->create([
            'user_id' => (int) $user->id,
            'department' => 'support',
            'subject' => 'Client Reply Ticket',
            'priority' => 2,
            'status' => TicketService::STATUS_CLIENT_REPLY,
        ]);

        $staffReplyTicket = Ticket::query()->create([
            'user_id' => (int) $user->id,
            'department' => 'support',
            'subject' => 'Staff Reply Ticket',
            'priority' => 2,
            'status' => TicketService::STATUS_STAFF_REPLY,
        ]);

        $closedTicket = Ticket::query()->create([
            'user_id' => (int) $user->id,
            'department' => 'support',
            'subject' => 'Closed Ticket',
            'priority' => 2,
            'status' => TicketService::STATUS_CLOSED,
        ]);

        $result = $service->adminList(['status' => 'ongoing'], 20);
        $ticketIds = collect($result->items())->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains((int) $openTicket->id, $ticketIds);
        $this->assertContains((int) $clientReplyTicket->id, $ticketIds);
        $this->assertContains((int) $staffReplyTicket->id, $ticketIds);
        $this->assertNotContains((int) $closedTicket->id, $ticketIds);
    }

    public function test_ticket_detail_only_depends_on_service_id_when_product_name_column_is_missing(): void
    {
        $service = $this->makeTicketService();
        $user = $this->createClientUser('ticket-detail');

        $product = Product::query()->create([
            'product_type' => 'server',
            'pricing' => ['monthly' => '19.90'],
            'setup_fee' => '0.00',
            'stock' => 10,
            'status' => 1,
            'sort_order' => 0,
            'auto_setup' => 0,
        ]);

        $linkedService = Service::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'name' => '实例 A',
            'domain' => 'example.test',
            'billing_cycle' => 'monthly',
            'amount' => '19.90',
            'status' => 1,
            'provision_data' => [],
        ]);

        $ticket = Ticket::query()->create([
            'user_id' => (int) $user->id,
            'department' => 'support',
            'subject' => 'Ticket Detail Regression',
            'priority' => 2,
            'status' => TicketService::STATUS_OPEN,
            'service_id' => (int) $linkedService->id,
        ]);

        $detail = $service->detail($ticket);

        $this->assertSame((int) $linkedService->id, (int) ($detail['service']['id'] ?? 0));
        $this->assertSame('实例 A', (string) ($detail['service']['name'] ?? ''));
        $this->assertArrayNotHasKey('product_name', $detail['service'] ?? []);
    }

    public function test_client_ticket_detail_endpoint_does_not_require_product_name_column(): void
    {
        $user = $this->createClientUser('ticket-detail-endpoint');

        $product = Product::query()->create([
            'product_type' => 'server',
            'pricing' => ['monthly' => '29.90'],
            'setup_fee' => '0.00',
            'stock' => 10,
            'status' => 1,
            'sort_order' => 0,
            'auto_setup' => 0,
        ]);

        $linkedService = Service::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'name' => '实例 B',
            'domain' => 'detail-endpoint.test',
            'billing_cycle' => 'monthly',
            'amount' => '29.90',
            'status' => 1,
            'provision_data' => [],
        ]);

        $ticket = Ticket::query()->create([
            'user_id' => (int) $user->id,
            'department' => 'support',
            'subject' => 'Ticket Detail Endpoint Regression',
            'priority' => 2,
            'status' => TicketService::STATUS_OPEN,
            'service_id' => (int) $linkedService->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/client/tickets/'.$ticket->id);

        $response->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.service.id', (int) $linkedService->id)
            ->assertJsonPath('data.service.name', '实例 B');

        $this->assertArrayNotHasKey('product_name', $response->json('data.service') ?? []);
    }

    public function test_ticket_create_schedules_notification_email_without_direct_mail_send(): void
    {
        Bus::fake();

        config()->set('queue.default', 'sync');

        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->expects($this->never())->method('sendTemplateEmail');

        $service = new TicketService(
            $this->createMock(UploadedAssetReferenceService::class),
            $notificationService,
            $this->createMock(ServiceTransformService::class),
        );

        $user = $this->createClientUser('ticket-notify');
        $admin = $this->createTicketReplyAdmin();

        $ticket = $service->create((int) $user->id, [
            'department' => 'support',
            'subject' => 'Ticket Notification Regression',
            'content' => 'Initial message',
            'priority' => 2,
        ]);

        Bus::assertDispatchedAfterResponse(
            SendTicketNotificationEmailJob::class,
            fn (SendTicketNotificationEmailJob $job): bool => $job->to === (string) $admin->email
                && $job->templateCode === NotificationService::TEMPLATE_TICKET_CREATED
                && (int) ($job->context['ticket_id'] ?? 0) === (int) $ticket->id
        );
    }

    private function makeTicketService(): TicketService
    {
        return new TicketService(
            $this->createMock(UploadedAssetReferenceService::class),
            $this->createMock(NotificationService::class),
            $this->createMock(ServiceTransformService::class),
        );
    }

    private function createClientUser(string $prefix): User
    {
        $suffix = bin2hex(random_bytes(4));

        return User::query()->create([
            'email' => $prefix.'-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Ticket Regression',
            'real_name' => '',
            'id_card' => '',
            'verification_status' => 0,
            'verification_message' => '',
            'verification_certify_id' => null,
            'member_level_id' => null,
            'total_sales_amount' => '0.00',
            'referrer_user_id' => null,
            'verified_at' => null,
        ]);
    }

    private function createStaffUser(): AdminUser
    {
        $suffix = bin2hex(random_bytes(4));

        $role = Role::query()->create([
            'name' => 'ticket-regression-role-'.$suffix,
            'label' => 'Ticket Regression Role',
            'permissions' => [],
        ]);

        return AdminUser::query()->create([
            'username' => 'ticket-regression-admin-'.$suffix,
            'password' => 'Temp@123456',
            'role_id' => (int) $role->id,
            'nickname' => 'Ticket Staff',
            'email' => 'ticket-regression-admin-'.$suffix.'@example.com',
            'status' => 1,
        ]);
    }

    private function createTicketReplyAdmin(): AdminUser
    {
        $suffix = bin2hex(random_bytes(4));

        $role = Role::query()->create([
            'name' => 'ticket-notify-role-'.$suffix,
            'label' => 'Ticket Notify Role',
            'permissions' => [AdminPermissions::TICKET_REPLY],
        ]);

        return AdminUser::query()->create([
            'username' => 'ticket-notify-admin-'.$suffix,
            'password' => 'Temp@123456',
            'role_id' => (int) $role->id,
            'nickname' => 'Ticket Notify Admin',
            'email' => 'ticket-notify-admin-'.$suffix.'@example.com',
            'status' => 1,
        ]);
    }

    private function runWithoutTicketMessagesTable(callable $callback): mixed
    {
        $backupTable = null;

        if (Schema::hasTable('ticket_messages')) {
            $backupTable = 'ticket_messages_backup_'.bin2hex(random_bytes(4));
            Schema::rename('ticket_messages', $backupTable);
        }

        try {
            return $callback();
        } finally {
            if ($backupTable !== null && Schema::hasTable($backupTable)) {
                Schema::rename($backupTable, 'ticket_messages');
            }
        }
    }
}
