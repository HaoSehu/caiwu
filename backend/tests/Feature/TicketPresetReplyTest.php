<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Product;
use App\Models\Role;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketDepartmentStaff;
use App\Models\TicketPresetReply;
use App\Models\TicketPresetReplyCategory;
use App\Models\User;
use App\Services\Ticket\TicketPresetReplyService;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * M2 预设回复库：CRUD / 占位符渲染 / usage_count 原子自增 / 软删分类模板置 NULL。
 */
class TicketPresetReplyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_category_and_reply_crud_via_admin_api(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::TICKET_MANAGE]));

        // 分类创建 + 重名拒绝
        $category = $this->postJson('/api/v2/admin/ticket-preset-reply-categories', [
            'name' => '常见问题分类',
            'sort_order' => 1,
        ])->assertOk()->assertJsonPath('data.category.name', '常见问题分类');

        $categoryId = (int) $category->json('data.category.id');

        // 重名拒绝：分类唯一校验抛 BusinessException，其 render() 契约为
        // code=42200 + HTTP 422（附带 message 原样透出）。
        $this->postJson('/api/v2/admin/ticket-preset-reply-categories', ['name' => '常见问题分类'])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200)
            ->assertJsonPath('message', '分类名称已存在');

        // 模板创建
        $reply = $this->postJson('/api/v2/admin/ticket-preset-replies', [
            'category_id' => $categoryId,
            'title' => '重启指南',
            'content' => '请重启 {{ticket_subject}} 相关服务。',
        ])->assertOk()->assertJsonPath('data.reply.title', '重启指南');

        $replyId = (int) $reply->json('data.reply.id');
        $this->assertSame($categoryId, (int) $reply->json('data.reply.category_id'));

        // 更新
        $this->putJson('/api/v2/admin/ticket-preset-replies/'.$replyId, [
            'title' => '重启指南 V2',
            'is_enabled' => false,
        ])->assertOk()->assertJsonPath('data.reply.is_enabled', 0);

        // 删除
        $this->deleteJson('/api/v2/admin/ticket-preset-replies/'.$replyId)->assertOk();
        $this->assertSoftDeleted('ticket_preset_replies', ['id' => $replyId]);
    }

    public function test_preset_reply_list_requires_ticket_reply_permission(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::DASHBOARD_VIEW]));

        $this->getJson('/api/v2/admin/ticket-preset-replies')
            ->assertStatus(403)
            ->assertJsonPath('code', 40300);
    }

    public function test_staff_list_is_filtered_by_department_membership(): void
    {
        $department = TicketDepartment::create(['name' => '模板过滤部门']);
        $foreignDepartment = TicketDepartment::create(['name' => '模板过滤部门B']);

        $staff = $this->makeAdmin([AdminPermissions::TICKET_REPLY]);
        TicketDepartmentStaff::create([
            'department_id' => (int) $department->id,
            'admin_user_id' => (int) $staff->id,
            'is_leader' => 0,
        ]);

        $common = TicketPresetReply::create(['title' => '通用模板', 'content' => 'x']);
        $own = TicketPresetReply::create(['title' => '本部门模板', 'content' => 'x', 'department_id' => (int) $department->id]);
        $foreign = TicketPresetReply::create(['title' => '其他部门模板', 'content' => 'x', 'department_id' => (int) $foreignDepartment->id]);

        Sanctum::actingAs($staff);

        $titles = collect($this->getJson('/api/v2/admin/ticket-preset-replies')->assertOk()->json('data.list'))
            ->pluck('title')
            ->all();

        $this->assertContains('通用模板', $titles);
        $this->assertContains('本部门模板', $titles);
        $this->assertNotContains('其他部门模板', $titles);
        $this->assertNotNull($common->id);
        $this->assertNotNull($own->id);
    }

    public function test_render_replaces_placeholders_and_increments_usage_count(): void
    {
        $operator = $this->makeAdmin([AdminPermissions::TICKET_REPLY]);
        Sanctum::actingAs($operator);

        $reply = TicketPresetReply::create([
            'title' => '渲染模板',
            'content' => '您好 {{user_nickname}}（{{user_email}}），工单 {{ticket_id}}「{{ticket_subject}}」关于 {{service_name}} 已收到。',
        ]);

        $user = User::query()->create([
            'email' => 'render_user_'.uniqid().'@test.local',
            'password' => 'secret123',
            'nickname' => '渲染用户',
            'status' => 1,
        ]);
        // services.product_id NOT NULL 且带 FK → 先造父行商品
        $product = Product::query()->create([
            'name' => '渲染测试商品'.uniqid(),
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '0.00'],
        ]);
        $service = Service::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'name' => '渲染服务',
            'billing_cycle' => 'monthly',
            'domain' => 'render.example.com',
            'status' => 1,
            'amount' => 0,
        ]);
        $ticket = Ticket::create([
            'user_id' => (int) $user->id,
            'department' => 'support',
            'subject' => '渲染测试工单',
            'status' => 0,
            'service_id' => (int) $service->id,
        ]);

        $this->getJson('/api/v2/admin/ticket-preset-replies/'.$reply->id.'/render?ticket_id='.$ticket->id)
            ->assertOk()
            ->assertJsonPath('data.content', '您好 渲染用户（'.$user->email.'），工单 '.$ticket->id.'「渲染测试工单」关于 渲染服务 已收到。');

        $this->assertSame(1, (int) $reply->refresh()->usage_count);

        // 再次调用原子自增到 2
        $this->getJson('/api/v2/admin/ticket-preset-replies/'.$reply->id.'/render?ticket_id='.$ticket->id)->assertOk();
        $this->assertSame(2, (int) $reply->refresh()->usage_count);
    }

    public function test_soft_deleted_category_nullifies_reply_category_id(): void
    {
        $service = app(TicketPresetReplyService::class);

        $category = TicketPresetReplyCategory::create(['name' => '待删分类']);
        $reply = TicketPresetReply::create(['category_id' => (int) $category->id, 'title' => '孤儿模板', 'content' => 'x']);

        $service->deleteCategory($category);

        $this->assertSoftDeleted('ticket_preset_reply_categories', ['id' => $category->id]);
        $this->assertNull($reply->refresh()->category_id);
        $this->assertNotNull($reply->refresh()->id);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeAdmin(array $permissions): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'preset_role_'.uniqid(),
            'label' => '预设回复测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'preset_staff_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '预设回复测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }
}
