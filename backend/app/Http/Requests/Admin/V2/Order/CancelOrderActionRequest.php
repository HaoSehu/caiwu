<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Order;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

/**
 * 管理端取消订单：不接收业务入参
 * （取消原因由服务端固定写 admin_order_cancel，避免前端传任意值污染审计口径）。
 *
 * 路由参数 order 由 Order 模型隐式绑定（控制器签名是 Order $order），
 * 订单不存在时绑定层直接 404，因此这里不重复校验 order，
 * 与 CancelInvoiceActionRequest 同口径。
 */
class CancelOrderActionRequest extends AdminFormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        // 分页禁用规则由基类的 RejectsLegacyPagination 提供（D-7），
        // 不要在动作类里重复硬写四条，否则基类加字段时这里会漏。
        return $this->allPaginationRules();
    }
}
