<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\Rbac\PermissionCatalogService;

class AdminPermissionCatalogController extends Controller
{
    public function __construct(private readonly PermissionCatalogService $permissionCatalogService) {}

    public function index()
    {
        return $this->success([
            'list' => $this->permissionCatalogService->list(),
        ]);
    }
}
