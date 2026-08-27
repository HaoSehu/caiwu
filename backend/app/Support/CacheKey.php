<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 统一的缓存键命名辅助类。
 *
 * 所有缓存键应该通过此类生成，确保格式统一、可维护。
 * 注意：键名前缀与 config/cache.php 中的 CACHE_PREFIX（idc_cache_）是两个层面，
 * Laravel Cache 门面会自动在键前追加 CACHE_PREFIX，所以这里的键名无需再手动加前缀。
 */
final class CacheKey
{
    /**
     * 余额统计缓存。
     */
    public static function userBalanceSummary(int $userId): string
    {
        return 'user:'.$userId.':balance_summary';
    }

    /**
     * 会员等级相关缓存。
     */
    public static function memberLevels(bool $enabledOnly): string
    {
        return 'member_levels:list:'.($enabledOnly ? 'enabled' : 'all');
    }

    /**
     * 推广大使档位缓存。
     */
    public static function promotionAmbassadors(bool $enabledOnly): string
    {
        return 'promotion_ambassadors:list:'.($enabledOnly ? 'enabled' : 'all');
    }

    /**
     * 会员折扣矩阵规则版本号：任何矩阵/营销组写入后自增，使旧键自然过期。
     */
    public static function memberGroupDiscountVersion(): string
    {
        return 'member_group_discounts:version';
    }

    /**
     * 单个等级的会员折扣矩阵规则表：member_group_discounts:<版本>:level:<等级ID>。
     */
    public static function memberGroupRules(int $version, int $memberLevelId): string
    {
        return sprintf('member_group_discounts:v%d:level:%d', $version, $memberLevelId);
    }

    /**
     * 首页 Hero 内容缓存。
     */
    public static function homeHero(): string
    {
        return 'site:home:hero';
    }

    /**
     * 内容发布版本缓存。
     */
    public static function contentPublishedVersion(): string
    {
        return 'content:published:version';
    }

    /**
     * 官网首页聚合缓存版本。
     */
    public static function siteHomeVersion(): string
    {
        return 'site:home:version';
    }

    /**
     * 官网首页聚合数据缓存：site:home:<分组段>:<公告数>:<帮助数>:v<内容版本>:b<首页版本>。
     *
     * $groupSegment 为根分组数量段：传 'all' 表示不限，否则传数字字符串。
     */
    public static function siteHome(string $groupSegment, int $noticeLimit, int $helpLimit, int $contentVersion, int $homeVersion): string
    {
        return sprintf('site:home:%s:%d:%d:v%d:b%d', $groupSegment, $noticeLimit, $helpLimit, $contentVersion, $homeVersion);
    }

    /**
     * 站点目录缓存。
     */
    public static function siteCatalog(): string
    {
        return 'catalog:site:v1';
    }

    /**
     * 站点目录拆分版本缓存。
     */
    public static function siteCatalogSplitVersion(): string
    {
        return 'catalog:site:split:version';
    }

    /**
     * VNC Token 缓存。
     */
    public static function vncToken(string $token): string
    {
        return 'vnc_token:'.$token;
    }

    /**
     * 验证码缓存（通用包装）。
     */
    public static function verificationCode(string $key): string
    {
        return 'verification_code:'.$key;
    }

    /**
     * 验证码本体目标键（不含 verification_code 统一前缀）：
     * channel 取 email/phone，identity 为该目标的不可读指纹，
     * 保证最终键与既有格式逐字一致。
     */
    public static function verificationTargetKey(string $channel, int|string $userId, string $identity): string
    {
        return $channel.'_code:'.$userId.':'.$identity;
    }

    /**
     * 验证码错误尝试计数键（历史上不带 verification_code 统一前缀，格式保持不变）。
     */
    public static function verificationAttemptKey(string $channel, int|string $userId, string $identity): string
    {
        return $channel.'_code_attempts:'.$userId.':'.$identity;
    }

    /**
     * 极验脚本缓存。
     */
    public static function geeTestScript(): string
    {
        return 'geetest:script';
    }

    /**
     * VAPTCHA 已通过 token 防复用缓存。
     */
    public static function vaptchaVerifiedToken(string $fingerprint): string
    {
        return 'vaptcha:verified_token:'.$fingerprint;
    }

    /**
     * 登录风控键族：login-risk:<维度>:<sha1 摘要>。
     *
     * 供 LoginRiskControlService 统一构造，避免摘要拼接散落。
     */
    public static function loginRiskAccountIp(string $account, string $ip): string
    {
        return 'login-risk:account-ip:'.sha1($account.'|'.$ip);
    }

    /**
     * 登录风控：账号维度失败计数。
     */
    public static function loginRiskAccount(string $account): string
    {
        return 'login-risk:account:'.sha1($account);
    }

    /**
     * 登录风控：IP 维度失败计数。
     */
    public static function loginRiskIp(string $ip): string
    {
        return 'login-risk:ip:'.sha1($ip);
    }

    /**
     * 登录风控：失败告警互斥锁（同一账号告警只发一次）。
     */
    public static function loginRiskFailureAlert(string $account): string
    {
        return 'login-risk:failure-alert:'.sha1($account);
    }

    /**
     * 登录风控：账号最近失败来源 IP 集合（多 IP 轮换攻击识别）。
     */
    public static function loginRiskFailedIps(string $account): string
    {
        return 'login-risk:failed-ips:'.sha1($account);
    }

    /**
     * 管理员代登录一次性码缓存：auth:admin_login_as:<sha256 摘要>。
     */
    public static function adminLoginAs(string $code): string
    {
        return 'auth:admin_login_as:'.hash('sha256', $code);
    }

    /**
     * 管理端登录失败计数：admin-login-fail:account:<sha1 摘要>。
     */
    public static function adminLoginFailure(string $normalizedUsername): string
    {
        return 'admin-login-fail:account:'.sha1($normalizedUsername);
    }

    /**
     * 官网商品库存查询缓存。
     */
    public static function siteProductStock(int $productId): string
    {
        return 'site_product_stock:'.$productId;
    }

    /**
     * 上游供应商库存缓存：按供应商与拉取的商品 ID 列表摘要区分。
     *
     * @param  list<int>  $supplierProductIds
     */
    public static function productRemoteStock(int $supplierId, array $supplierProductIds): string
    {
        return 'product_remote_stock:'.$supplierId.':'.sha1(implode(',', $supplierProductIds));
    }

    /**
     * 上游库存拉取失败日志节流键：stock_log:<原因>:<主体 ID>。
     */
    public static function stockLogThrottle(string $reason, int $subjectId): string
    {
        return 'stock_log:'.$reason.':'.$subjectId;
    }

    /**
     * 活动日志写入失败计数。
     */
    public static function activityLogWriteFailures(): string
    {
        return 'activity_log:write_failures';
    }

    /**
     * 分布式互斥锁键：顶层段 + 具体锁名。
     *
     * 现存调度器族锁以 scheduler 为顶层段（心跳槽位锁、队列 Worker 互斥锁），
     * 通过本方法统一拼接，保证与既有锁键字符串完全一致。
     */
    public static function lock(string $topSegment, string $name): string
    {
        return $topSegment.':'.$name;
    }
}
