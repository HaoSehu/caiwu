<?php

declare(strict_types=1);

namespace App\Services\Ticket;

use App\Exceptions\BusinessException;
use App\Models\AdminUser;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketDepartmentStaff;
use App\Models\TicketPresetReply;
use App\Models\TicketPresetReplyCategory;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Support\Facades\DB;

/**
 * M2 工单预设回复库：模板/分类 CRUD、占位符渲染兜底、使用计数。
 */
class TicketPresetReplyService
{
    /**
     * 模板列表（管理端）。
     *
     * @param  array<string, mixed>  $filters  category_id / department_id / keyword / is_enabled
     */
    public function listReplies(array $filters = []): array
    {
        $query = TicketPresetReply::query()
            ->with(['category:id,name', 'department:id,name', 'creator:id,username,nickname'])
            ->orderBy('sort_order')
            ->orderBy('id');

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }

        if (! empty($filters['keyword'])) {
            $keyword = (string) $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('content', 'like', "%{$keyword}%");
            });
        }

        if (isset($filters['is_enabled']) && $filters['is_enabled'] !== '') {
            $query->where('is_enabled', (int) $filters['is_enabled']);
        }

        return $query->get()->all();
    }

    /**
     * 客服使用侧栏列表（ticket.reply 持有者）：按操作者部门过滤——
     * 有部门归属的客服可见「通用（NULL）+ 本部门」模板；无归属客服与 super_admin 可见全部。
     *
     * @param  array<string, mixed>  $filters
     */
    public function listRepliesForStaff(AdminUser $operator, array $filters = []): array
    {
        $items = collect($this->listReplies($filters));

        $permissions = $operator->resolvedPermissions();
        if (in_array(AdminPermissions::ALL, $permissions, true)) {
            return $items->all();
        }

        $departmentIds = TicketDepartmentStaff::query()
            ->where('admin_user_id', (int) $operator->id)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($departmentIds === []) {
            return $items->all();
        }

        return $items
            ->filter(fn (TicketPresetReply $reply) => $reply->department_id === null
                || in_array((int) $reply->department_id, $departmentIds, true))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createReply(array $data, int $operatorId): TicketPresetReply
    {
        $this->assertCategoryUsable($data['category_id'] ?? null);

        return TicketPresetReply::create([
            'category_id' => $data['category_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'title' => trim((string) ($data['title'] ?? '')),
            'content' => (string) ($data['content'] ?? ''),
            'is_enabled' => (int) ($data['is_enabled'] ?? 1),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'created_by' => $operatorId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateReply(TicketPresetReply $reply, array $data): TicketPresetReply
    {
        $this->assertCategoryUsable($data['category_id'] ?? null);

        $reply->update([
            'category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : $reply->category_id,
            'department_id' => array_key_exists('department_id', $data) ? $data['department_id'] : $reply->department_id,
            'title' => trim((string) ($data['title'] ?? $reply->title)),
            // 部分更新：payload 缺省键（null）保留原值，不得用默认值覆盖既有数据
            'content' => ($data['content'] ?? null) !== null ? (string) $data['content'] : $reply->content,
            'is_enabled' => ($data['is_enabled'] ?? null) !== null ? (int) $data['is_enabled'] : $reply->is_enabled,
            'sort_order' => ($data['sort_order'] ?? null) !== null ? (int) $data['sort_order'] : $reply->sort_order,
        ]);

        return $reply->refresh();
    }

    public function deleteReply(TicketPresetReply $reply): void
    {
        $reply->delete();
    }

    /**
     * 渲染占位符兜底（前端插入时优先本地替换）：{{user_email}}/{{user_nickname}}/
     * {{ticket_subject}}/{{ticket_id}}/{{service_name}}；未知占位符原样保留。
     */
    public function render(TicketPresetReply $reply, Ticket $ticket): string
    {
        $ticket->loadMissing(['user:id,email,nickname', 'service:id,name,domain']);

        /** @var User|null $user */
        $user = $ticket->user;

        $replacements = [
            '{{user_email}}' => (string) ($user->email ?? ''),
            '{{user_nickname}}' => (string) ($user->nickname ?? ''),
            '{{ticket_subject}}' => (string) $ticket->subject,
            '{{ticket_id}}' => (string) $ticket->id,
            '{{service_name}}' => $this->resolveServiceName($ticket),
        ];

        return (string) strtr($reply->content, $replacements);
    }

    /**
     * 客服插入模板入口：渲染 + usage_count 原子自增。
     */
    public function renderForTicket(TicketPresetReply $reply, Ticket $ticket): string
    {
        $rendered = $this->render($reply, $ticket);
        $reply->incrementUsage();

        return $rendered;
    }

    /**
     * @return array<int, TicketPresetReplyCategory>
     */
    public function listCategories(): array
    {
        return TicketPresetReplyCategory::query()
            ->withCount('replies')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCategory(array $data): TicketPresetReplyCategory
    {
        $name = trim((string) ($data['name'] ?? ''));
        throw_if($name === '', new BusinessException('分类名称不能为空'));
        throw_if(
            TicketPresetReplyCategory::withTrashed()->where('name', $name)->exists(),
            new BusinessException('分类名称已存在')
        );

        return TicketPresetReplyCategory::create([
            'name' => $name,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_enabled' => (int) ($data['is_enabled'] ?? 1),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCategory(TicketPresetReplyCategory $category, array $data): TicketPresetReplyCategory
    {
        $name = trim((string) ($data['name'] ?? $category->name));
        throw_if($name === '', new BusinessException('分类名称不能为空'));
        throw_if(
            TicketPresetReplyCategory::withTrashed()
                ->where('name', $name)
                ->where('id', '!=', (int) $category->id)
                ->exists(),
            new BusinessException('分类名称已存在')
        );

        $category->update([
            'name' => $name,
            'sort_order' => (int) ($data['sort_order'] ?? $category->sort_order),
            'is_enabled' => (int) ($data['is_enabled'] ?? $category->is_enabled),
        ]);

        return $category->refresh();
    }

    /**
     * 删除分类（软删）：其下模板 category_id 置 NULL 而非级联删。
     */
    public function deleteCategory(TicketPresetReplyCategory $category): void
    {
        DB::transaction(function () use ($category): void {
            TicketPresetReply::query()
                ->where('category_id', (int) $category->id)
                ->update(['category_id' => null]);

            $category->delete();
        });
    }

    private function assertCategoryUsable(mixed $categoryId): void
    {
        if ($categoryId === null || (int) $categoryId <= 0) {
            return;
        }

        $category = TicketPresetReplyCategory::query()->find((int) $categoryId);
        throw_if(! $category, new BusinessException('所选分类不存在'));
    }

    private function resolveServiceName(Ticket $ticket): string
    {
        /** @var Service|null $service */
        $service = $ticket->service;
        if ($service === null) {
            return '';
        }

        $name = trim((string) $service->name);
        if ($name !== '') {
            return $name;
        }

        $domain = trim((string) ($service->domain ?? ''));
        if ($domain !== '') {
            return $domain;
        }

        return '服务 #'.$service->id;
    }
}
