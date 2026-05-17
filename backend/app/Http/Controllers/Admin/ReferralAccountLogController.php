<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Referral\ReferralService;
use Illuminate\Http\Request;

class ReferralAccountLogController extends Controller
{
    public function __construct(private ReferralService $referralService) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'event_type' => ['nullable', 'string', 'max:30'],
            'type' => ['nullable', 'string', 'max:30'],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

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
