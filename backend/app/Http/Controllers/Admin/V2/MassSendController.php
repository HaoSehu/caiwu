<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\V2\AdminMassSendDetailResource;
use App\Http\Resources\Admin\V2\AdminMassSendListResource;
use App\Http\Resources\Admin\V2\AdminMassSendTargetResource;
use App\Services\MassSend\MassSendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MassSendController extends Controller
{
    public function __construct(
        private readonly MassSendService $massSends,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|integer|in:0,1,2,3,4',
            'keyword' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return $this->paginate(
            $this->massSends->adminList($validated, (int) ($validated['per_page'] ?? 20)),
            AdminMassSendListResource::class
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->success(
            AdminMassSendDetailResource::make($this->massSends->adminShow($id))->resolve()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $massSend = $this->massSends->createDraft($validated, (int) $request->user()?->getAuthIdentifier());

        return $this->success([
            'mass_send' => AdminMassSendDetailResource::make($massSend)->resolve(),
        ], '群发任务已创建');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $massSend = $this->massSends->updateDraft($this->massSends->adminShow($id), $validated);

        return $this->success([
            'mass_send' => AdminMassSendDetailResource::make($massSend)->resolve(),
        ], '群发任务已更新');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->massSends->deleteDraft($this->massSends->adminShow($id));

        return $this->success(null, '群发任务已删除');
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filter_json' => 'required|array',
        ]);

        return $this->success($this->massSends->preview($validated['filter_json']));
    }

    public function send(Request $request, int $id): JsonResponse
    {
        $massSend = $this->massSends->adminShow($id);

        // 定时任务：落 targets 与 total 后由 Dispatch Job 承接（scheduled_at 到点才实际发送）。
        $massSend = $this->massSends->start($massSend);

        return $this->success([
            'mass_send' => AdminMassSendDetailResource::make($massSend)->resolve(),
        ], '群发任务已启动');
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $massSend = $this->massSends->cancel($this->massSends->adminShow($id));

        return $this->success([
            'mass_send' => AdminMassSendDetailResource::make($massSend)->resolve(),
        ], '群发任务已取消');
    }

    public function targets(Request $request, int $id): JsonResponse
    {
        $massSend = $this->massSends->adminShow($id);

        $validated = $request->validate([
            'status' => 'nullable|integer|in:0,1,2',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return $this->paginate(
            $this->massSends->paginateTargets($massSend, $validated, (int) ($validated['per_page'] ?? 20)),
            AdminMassSendTargetResource::class
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:100',
            'channel' => 'required|integer|in:1,2,3',
            'is_marketing' => 'nullable|integer|in:0,1',
            'template_id' => 'nullable|integer|min:1',
            'subject' => 'nullable|string|max:200',
            'content' => 'nullable|string',
            'filter_json' => 'required|array',
            'scheduled_at' => 'nullable|date|after:now',
        ]);
    }
}
