<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Integrations\Plugins\PluginConfigRepository;
use App\Services\Mail\SmtpMailTransport;
use Caiwu\Plugins\Mail\Smtp\Lib\SmtpMailService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/SmtpMailService.php';

/**
 * SMTP 邮件插件的配置校验与发送失败分支。
 *
 * 邮件是最容易「静默失败」的通道：配置不全时如果直接交给 Symfony Mailer，
 * 报错会变成连接超时或认证失败，运维只能看到「发不出去」。
 * 这里锁定两条契约：
 * 1. 配置不全必须在校验层就抛出「邮件接口配置不完整」，不能进入网络连接；
 * 2. 插件动作层的载荷校验失败要返回失败信封，而不是抛异常打断上层流程。
 */
class SmtpMailSendFailureTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function account(array $overrides = []): array
    {
        return array_merge([
            'host' => 'smtp.example.test',
            'port' => 465,
            'username' => 'no-reply@example.test',
            'password' => 'smtp-password',
            'from_name' => '测试站点',
            'encryption' => null,
            'timeout_seconds' => 8,
        ], $overrides);
    }

    public function test_incomplete_configuration_is_rejected_before_connecting(): void
    {
        $transport = new SmtpMailTransport;

        foreach ([
            '缺少 host' => ['host' => ''],
            '端口非正数' => ['port' => 0],
            '缺少账号' => ['username' => ''],
            '缺少口令' => ['password' => ''],
        ] as $reason => $overrides) {
            try {
                $transport->sendHtml($this->account($overrides), 'user@example.test', '主题', '<p>正文</p>');
                $this->fail('配置不全时应当拒绝发送：'.$reason);
            } catch (\RuntimeException $exception) {
                $this->assertSame('邮件接口配置不完整', $exception->getMessage(), $reason);
            }
        }
    }

    public function test_complete_configuration_applies_account_to_mailer(): void
    {
        // 用 facade 替身拦掉真实投递：本用例只校验配置注入结果，不发网络请求
        $this->fakeMailer(1);

        (new SmtpMailTransport)->sendHtml($this->account(['port' => 465]), 'user@example.test', '主题', '<p>正文</p>');

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.test', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('no-reply@example.test', config('mail.mailers.smtp.username'));
        $this->assertSame('no-reply@example.test', config('mail.from.address'));
        $this->assertSame('测试站点', config('mail.from.name'));
        // 465 且未显式配置加密方式时按隐式 SSL 处理
        $this->assertSame('ssl', config('mail.mailers.smtp.encryption'));
        $this->assertSame(8, config('mail.mailers.smtp.timeout'));
    }

    public function test_port_derived_encryption_and_explicit_override(): void
    {
        $this->fakeMailer(3);
        $transport = new SmtpMailTransport;

        $transport->sendHtml($this->account(['port' => 25, 'encryption' => null]), 'user@example.test', '主题', '<p>正文</p>');
        $this->assertNull(config('mail.mailers.smtp.encryption'));

        $transport->sendHtml($this->account(['port' => 587, 'encryption' => null]), 'user@example.test', '主题', '<p>正文</p>');
        $this->assertSame('tls', config('mail.mailers.smtp.encryption'));

        // 显式配置优先于端口推断，none 表示明文
        $transport->sendHtml($this->account(['port' => 465, 'encryption' => 'none']), 'user@example.test', '主题', '<p>正文</p>');
        $this->assertNull(config('mail.mailers.smtp.encryption'));
    }

    public function test_non_positive_timeout_falls_back_to_default(): void
    {
        $this->fakeMailer(1);

        (new SmtpMailTransport)->sendHtml($this->account(['timeout_seconds' => 0]), 'user@example.test', '主题', '<p>正文</p>');

        $this->assertSame(8, config('mail.mailers.smtp.timeout'));
    }

    public function test_send_html_action_rejects_invalid_recipient(): void
    {
        $service = $this->serviceWithConfig($this->account());

        $response = $service->execute([
            'action' => 'mail.send_html',
            'payload' => ['to' => 'not-an-email', 'subject' => '主题', 'html' => '<p>正文</p>'],
        ]);

        $this->assertFalse($response['success']);
        $this->assertSame('收件人邮箱地址无效', $response['message']);
        $this->assertSame([], $response['data']);
    }

    public function test_test_smtp_action_rejects_missing_subject(): void
    {
        $service = $this->serviceWithConfig($this->account());

        $response = $service->execute([
            'action' => 'mail.test_smtp',
            'payload' => ['to' => 'user@example.test', 'subject' => '   '],
        ]);

        $this->assertFalse($response['success']);
        $this->assertSame('请填写有效的收件邮箱和主题', $response['message']);
    }

    public function test_unknown_action_returns_failure_envelope(): void
    {
        $service = $this->serviceWithConfig($this->account());

        $response = $service->execute(['action' => 'mail.unknown', 'payload' => []]);

        $this->assertFalse($response['success']);
        $this->assertSame('不支持的插件动作', $response['message']);
        $this->assertSame([], $response['data']);
    }

    public function test_transport_failure_propagates_to_caller(): void
    {
        // 配置校验通过、网络层失败时异常必须上抛：调用方（通知服务）据此记录失败并重试，
        // 不能被插件吞掉变成「发送成功」
        $transport = new class extends SmtpMailTransport
        {
            public function sendHtml(array $account, string $to, string $subject, string $html): void
            {
                throw new \RuntimeException('连接 SMTP 服务器失败');
            }
        };

        $service = $this->serviceWithConfig($this->account(), $transport);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('连接 SMTP 服务器失败');

        $service->execute([
            'action' => 'mail.send_html',
            'payload' => ['to' => 'user@example.test', 'subject' => '主题', 'html' => '<p>正文</p>'],
        ]);
    }

    /**
     * 用 facade 替身拦掉真实投递。
     *
     * SMTP 传输内部会先调 app('mail.manager')->forgetMailers() 再调 Mail::html()，
     * 两者都落在同一个 facade 根实例上，所以必须一起声明期望，否则会报
     * 「Received Mockery_...::forgetMailers(), but no expectations were specified」。
     */
    private function fakeMailer(int $times): void
    {
        Mail::shouldReceive('forgetMailers')->andReturnNull();
        Mail::shouldReceive('html')->times($times)->andReturnNull();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function serviceWithConfig(array $config, ?SmtpMailTransport $transport = null): SmtpMailService
    {
        $repository = $this->createMock(PluginConfigRepository::class);
        $repository->method('resolvedConfigByDomainAndSlug')->willReturn($config);

        return new SmtpMailService($repository, $transport ?? new SmtpMailTransport);
    }
}
