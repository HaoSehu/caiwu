<?php

namespace Tests;

use App\Services\Mail\MailDriverManager;
use App\Support\Cache\VolatileStore;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fixtures\Mail\FakeMailDriver;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->swapVolatileCacheStoreToArray();
        $this->swapMailDriverToFake();
    }

    /**
     * volatile 库（config/cache.php 的 redis_volatile）在生产的唯一驱动是 redis，
     * 但测试不应该依赖一台真实的 Redis。phpunit.xml.dist 只把 CACHE_STORE 改成
     * array，改不到这个独立 store，于是曾需要真实 Redis 的用例分散在多个测试类里
     * 各自 setUp 覆盖，谁漏了谁就静默连本机 6379。
     *
     * 统一在基类置为 array：任何用例（含间接调用 VolatileStore 的业务链路）
     * 都无需关心 Redis 是否可用；需要验证 redis 连接本身行为的用例可以按需
     * 覆盖回 redis。
     *
     * 用 VolatileStore::name() 取 store 名而不是写字面量：store 改名时这里自动跟上，
     * 不会因为「测试还在覆盖旧名字」而让新名字去连真实 Redis；
     * 用 array_merge 保留 config 里的 connection 等原生字段，
     * 由 VolatileStoreConfigurationTest 断言连接名与配置文件仍然对得上。
     */
    private function swapVolatileCacheStoreToArray(): void
    {
        $storeName = VolatileStore::name();

        config(["cache.stores.{$storeName}" => array_merge(
            (array) config("cache.stores.{$storeName}", []),
            ['driver' => 'array', 'serialize' => false],
        )]);
    }

    /**
     * 邮件驱动换成内存替身：测试环境不允许真的连 SMTP。
     *
     * 测试库若装着真实的 multi_smtp_round_robin 插件（本机 idc_test 就有），
     * 走真实驱动的链路会尝试连接外部邮件服务器：网络不可达或凭据失效时
     * 抛出「多 SMTP 轮询发送失败」，把与邮件无关的用例（如结账库存闸门）
     * 一起拖红；网络可达时更糟——测试会真的往外发信。
     *
     * 默认替身注册在 'smtp' 这个兜底 key 上：绑定候选里真实插件 key 找不到时，
     * MailDriverManager 会回落 config('integrations.mail.default', 'smtp')。
     * 需要断言具体驱动解析顺序的用例可以自己 new MailDriverManager 覆盖。
     */
    private function swapMailDriverToFake(): void
    {
        $this->app?->instance(MailDriverManager::class, new MailDriverManager([new FakeMailDriver]));
    }
}
