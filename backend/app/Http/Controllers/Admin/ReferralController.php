<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Referral\AdminReferralOverviewService;

class ReferralController extends Controller
{
    public function __construct(
        private AdminReferralOverviewService $referralOverviewService,
    ) {}

    public function overview()
    {
        return $this->success($this->referralOverviewService->overview());
    }
}
