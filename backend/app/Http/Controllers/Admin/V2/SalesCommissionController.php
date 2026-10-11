<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalesCommission\V2\SalesCommissionResource;
use App\Models\SalesCommission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SalesCommissionController extends Controller
{
    /**
     * 计提记录列表：?status=&admin_user_id=&user_id=&date_from=&date_to=&per_page=
     */
    public function index(Request $request): JsonResponse
    {
        $query = SalesCommission::query()
            ->with(['adminUser:id,username,nickname,email', 'user:id,email,nickname,real_name,phone', 'invoice:id,invoice_no']);

        if ($request->filled('status')) {
            $query->where('status', (int) $request->input('status'));
        }

        if ($request->filled('admin_user_id')) {
            $query->where('admin_user_id', (int) $request->input('admin_user_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date('date_from')->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date('date_to')->endOfDay());
        }

        $perPage = max(1, min((int) $request->input('per_page', 15), 100));

        /** @var LengthAwarePaginator<SalesCommission> $paginator */
        $paginator = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginate($paginator, SalesCommissionResource::class);
    }
}
