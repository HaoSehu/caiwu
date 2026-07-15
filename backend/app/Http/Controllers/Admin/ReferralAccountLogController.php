<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReferralAccountLog\IndexRequest;
use App\Services\Referral\ReferralService;

class ReferralAccountLogController extends Controller
{
    public function __construct(private ReferralService $referralService) {}

    public function index(IndexRequest $request)
    {
        $filters = $request->validated();

        if (empty($filters['event_type']) && ! empty($filters['type'])) {
            $filters['event_type'] = (string) $filters['type'];
        }

        unset($filters['type']);

        $perPage = max(1, min((int) ($filters['page_size'] ?? 20), 100));
        $paginator = $this->referralService->adminAccountLogs($filters, $perPage);

        return $this->success([
            'list' => collect($paginator->items())
                ->map(fn ($item) => $this->referralService->transformAccountLogRecord($item))
                ->values()
                ->all(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ]);
    }
}
