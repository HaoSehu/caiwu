<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * 上游以「库存不足」类文案拒绝开通/升级（加购/结算/余额支付）时的分类异常。
 * 业务语义与 BusinessException 完全一致（既有重试、requires_refund 等处理链路不变），
 * 仅供编排层识别：命中后需校准本地库存（快照归零 + 售罄覆盖），
 * 因为上游 prodetail.qty 是展示口径，与加购真实校验脱节（历史假库存事故同源）。
 * 词表与判定收敛于此（matches），供插件多个服务的上游失败出口共用，勿在调用方散落文案。
 */
class UpstreamStockShortageException extends BusinessException
{
    /**
     * 上游各失败出口的拒绝文案（home 路由中文 + 语言包键）：
     * - 「产品%s库存不足」：加购/结算库存校验（addToShop/settle，含上游代发缺货）
     * - 「商品缺货」：上游代发商品缺货（addToShop 写死）
     * - 「商品不存在」：上游代发商品 hidden=1 的加购拒绝（addToShop 写死）
     * - 「产品不存在」：上游商品被删除时 get_product_config 的拒绝（CART_PRO_CONF_NOTFOUND）
     * - 「超出库存」：升级换品目标商品库存 0 的拒绝（PRODUCT_OUT_OF_STOCK）
     * - 「out of stock」：openapi cartCheckout 结算复查（英文版本上游）
     *
     * @param  string  $message  上游原始 msg（映射为用户文案之前）
     */
    public static function matches(string $message): bool
    {
        if ($message === '') {
            return false;
        }

        foreach (['库存不足', '售罄', '售完', '缺货', '商品不存在', '产品不存在', '超出库存', 'out of stock'] as $needle) {
            if (mb_stripos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
