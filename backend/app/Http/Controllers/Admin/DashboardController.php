<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\System\DashboardService;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService,
    ) {}

    public function index()
    {
        return $this->success($this->dashboardService->overview());
    }

    public function stats()
    {
        return $this->success($this->dashboardService->stats());
    }

    public function recentInvoices()
    {
        return $this->success([
            'recent_invoices' => $this->dashboardService->recentInvoices(),
        ]);
    }

    public function monthlyRevenue()
    {
        return $this->success($this->dashboardService->monthlyRevenue());
    }
}
