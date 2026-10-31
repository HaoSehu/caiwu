<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 缓存 TTL 的单一真源。
 *
 * 这里只收「跨模块共用、且带安全约束」的 TTL：这些数字同时决定一次性凭证
 * 可被重放的时间窗口（MFA 挑战、代登录码、VNC token、结算报价），分散在各自
 * Service 里会让安全基线无法一次性审阅，改一次基线要全库搜索。
 *
 * 两条边界：
 * 1. 只在单个 Service 内部使用的 TTL 继续留在该类作私有常量，但禁止写裸字面量。
 * 2. 锁 TTL 不在此集中。锁的持有上限取决于被锁的临界区（一次上游调用、一次
 *    落库事务、一次批量同步），不同调用点的安全取值本就不同，集中成一套常量
 *    会诱导新代码复制一个错误取值；规矩是每个调用点用具名常量表达。
 */
final class CacheTtl
{
    /** 管理员双因素登录挑战凭证有效期：只够输完一次 6 位码。 */
    public const ADMIN_MFA_CHALLENGE_SECONDS = 300;

    /** 管理员代登录码有效期：一次点击到落地之间的窗口。 */
    public const ADMIN_LOGIN_AS_CODE_SECONDS = 120;

    /** 管理员登录失败计数窗口：与锁定阈值共同构成暴力破解防线。 */
    public const ADMIN_LOGIN_FAILED_WINDOW_SECONDS = 1800;

    /** 双因素绑定草稿有效期：够慢的人输完一次验证码，又不至于长期留一个未确认密钥。 */
    public const ADMIN_MFA_SETUP_SECONDS = 600;

    /** 公开 VNC token 有效期（换取一次控制台会话）。 */
    public const VNC_TOKEN_SECONDS = 600;

    /** VNC 中转句柄有效期：只够一次重连握手。 */
    public const VNC_RELAY_TOKEN_SECONDS = 60;

    /** 结算报价快照有效期：价格与库存只在短窗口内被信任。 */
    public const CHECKOUT_QUOTE_SECONDS = 600;

    /** 下单幂等键保留时长：覆盖「用户手滑重复提交」的最坏间隔。 */
    public const CHECKOUT_ORDER_IDEMPOTENCY_SECONDS = 900;

    /** 下单指纹窗口：只用于挡住同一动作的瞬时重复。 */
    public const CHECKOUT_ORDER_FINGERPRINT_SECONDS = 180;

    /** 支付会话有效期：收银台到回调之间的最长等待。 */
    public const CHECKOUT_PAYMENT_SESSION_SECONDS = 300;
}
