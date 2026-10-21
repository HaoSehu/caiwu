<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Integrations\Plugins\PluginFileLoader;
use App\Services\Integrations\Plugins\PluginScanner;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 短信模板本地语法翻译回归（2026-10-21 审查 P1-2）：
 * 运营商原文里的 ${var} 必须原样保留，不能被本地 {var} 规则二次包裹成 $${var}；
 * 同时本地 {{var}} / {var} 仍要正常翻译成 ${var}。
 * translateLocalSyntax 是插件私有方法，这里经 PluginFileLoader 正常加载插件后反射调用，
 * 覆盖真实运行时的两份实现（阿里云与 Demo 短信各一套，代码一致必须同时守住）。
 */
class SmsTranslateLocalSyntaxTest extends TestCase
{
    private const ALIYUN_CLIENT = 'Caiwu\\Plugins\\Sms\\Aliyun\\Lib\\AliyunSmsClient';

    private const DEMO_SERVICE = 'Caiwu\\Plugins\\Sms\\DemoSms\\Lib\\DemoSmsService';

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function translateCases(): array
    {
        $dollar = '$';

        return [
            '阿里云-单花括号翻译' => ['aliyun', '您有 {count} 条未读消息', '您有 '.$dollar.'{count} 条未读消息', self::ALIYUN_CLIENT],
            '阿里云-双花括号翻译' => ['aliyun', '您有 {{count}} 条未读消息', '您有 '.$dollar.'{count} 条未读消息', self::ALIYUN_CLIENT],
            '阿里云-既定语法原样保留' => ['aliyun', '验证码 '.$dollar.'{code}，5分钟内有效', '验证码 '.$dollar.'{code}，5分钟内有效', self::ALIYUN_CLIENT],
            '阿里云-混合语法不互相污染' => ['aliyun', $dollar.'{username}，您有 {count} 条未读', $dollar.'{username}，您有 '.$dollar.'{count} 条未读', self::ALIYUN_CLIENT],
            '演示-单花括号翻译' => ['demo_sms', '您有 {count} 条未读消息', '您有 '.$dollar.'{count} 条未读消息', self::DEMO_SERVICE],
            '演示-既定语法原样保留' => ['demo_sms', '您好 '.$dollar.'{username}，订单 '.$dollar.'{order_no} 已发货', '您好 '.$dollar.'{username}，订单 '.$dollar.'{order_no} 已发货', self::DEMO_SERVICE],
        ];
    }

    #[DataProvider('translateCases')]
    public function test_translate_local_syntax_keeps_provider_placeholders(string $slug, string $input, string $expected, string $class): void
    {
        $this->loadPlugin($slug);

        $method = new ReflectionMethod($class, 'translateLocalSyntax');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke($this->pluginInstance($class), $input));
    }

    public function test_extract_variables_sees_provider_placeholders_untouched(): void
    {
        $this->loadPlugin('aliyun');

        // 修复前：'验证码 ${code}' 被翻译成 '$${code}'，再交给 extractVariables 会得到带美元符的变量名，
        // 与运营商侧变量 code 对不上；修复后原文不再被翻译，提取结果必须是干净的 code。
        $content = '验证码 ${code}，5分钟内有效';

        $this->assertSame(['code'], self::ALIYUN_CLIENT::extractVariables($content));
    }

    private function loadPlugin(string $slug): void
    {
        $manifest = app(PluginScanner::class)->requireManifest('sms', $slug);

        app(PluginFileLoader::class)->ensureLoaded($manifest);
    }

    private function pluginInstance(string $class): object
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0) {
            return $reflection->newInstance();
        }

        // AliyunSmsClient 需要配置数组才完整，但 translateLocalSyntax 不读配置，
        // 这里绕过构造避免把无关凭据塞进单元测试。
        return $reflection->newInstanceWithoutConstructor();
    }
}
