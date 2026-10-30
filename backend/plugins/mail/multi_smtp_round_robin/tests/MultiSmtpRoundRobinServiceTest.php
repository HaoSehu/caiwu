<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\BusinessException;
use App\Services\Integrations\Plugins\PluginConfigRepository;
use App\Services\Mail\SmtpMailTransport;
use Caiwu\Plugins\Mail\MultiSmtpRoundRobin\Lib\MultiSmtpRoundRobinService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/MultiSmtpRoundRobinService.php';

/**
 * 多 SMTP 轮询插件契约回归。
 *
 * 该插件把「选号、冷却、游标」三件事叠在一起，出错时最典型的症状是：
 * 1. 某个账号认证失败后每次都先撞它（未写冷却），整站发信连坐；
 * 2. 轮询游标不前进，永远只用一个账号；
 * 3. 测试邮件直投与 send_html 轮询错用了彼此的参数口径。
 * 这里用替身把配置读取与传输层隔离，只钉住插件自己的判定逻辑。
 */
class MultiSmtpRoundRobinServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 游标与冷却是全局键：每个用例必须从干净缓存开始
        Cache::flush();
    }

    /**
     * 用匿名子类替掉配置读取与传输层：两者都不是 final，
     * 覆写最小方法即可，避免替身把「配置键名拼错」这类真实契约错误一起掩盖。
     *
     * @param  array<int, mixed>  $accounts
     * @param  array<int, array<string, mixed>>  $sent
     * @param  array<string, mixed>  $extra
     */
    private function service(array $accounts, array &$sent, array $extra = [], ?\Throwable $failure = null): MultiSmtpRoundRobinService
    {
        $config = array_merge(['accounts' => $accounts], $extra);

        $repository = new class($config) extends PluginConfigRepository
        {
            /** @param array<string, mixed> $config */
            public function __construct(private readonly array $config) {}

            public function resolvedConfigByDomainAndSlug(string $domain, string $slug): array
            {
                return $this->config;
            }
        };

        $transport = new class(static function (array $account, string $to, string $subject, string $html) use (&$sent, $failure): void {
            $sent[] = ['account' => $account, 'to' => $to, 'subject' => $subject, 'html' => $html];

            if ($failure !== null) {
                throw $failure;
            }
        }) extends SmtpMailTransport
        {

            /**
             * @param  \Closure(array<string, mixed>, string, string, string): void  $recorder
             */
            public function __construct(private readonly \Closure $recorder) {}

            public function sendHtml(array $account, string $to, string $subject, string $html): void
            {
                ($this->recorder)($account, $to, $subject, $html);
            }
        };

        return new MultiSmtpRoundRobinService($repository, $transport);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function accounts(): array
    {
        return [
            ['host' => 'smtp-a.test', 'port' => 465, 'username' => 'a@test', 'password' => 'secret-a', 'enabled' => true],
            ['host' => 'smtp-b.test', 'port' => 465, 'username' => 'b@test', 'password' => 'secret-b', 'enabled' => true],
        ];
    }

    public function test_key_and_label(): void
    {
        $sent = [];
        $service = $this->service($this->accounts(), $sent);

        $this->assertSame('multi_smtp_round_robin', $service->key());
        $this->assertSame('多 SMTP 轮询', $service->label());
    }

    public function test_send_html_without_accounts_is_rejected(): void
    {
        $sent = [];
        $service = $this->service([], $sent);

        try {
            $service->sendHtml('user@test.com', '主题', '<p>正文</p>');
            $this->fail('无可用账号时应当拒绝');
        } catch (BusinessException $exception) {
            $this->assertSame('多 SMTP 插件未配置可用账号', $exception->getMessage());
        }

        $this->assertSame([], $sent);
    }

    public function test_disabled_accounts_are_filtered_out(): void
    {
        $sent = [];
        // 字符串 "false" 必须被判为停用：早期用 (bool) 强转会让它通过
        $service = $this->service([
            ['host' => 'smtp-a.test', 'enabled' => 'false'],
            ['host' => 'smtp-b.test', 'enabled' => false],
        ], $sent);

        try {
            $service->sendHtml('user@test.com', '主题', '<p>正文</p>');
            $this->fail('全部账号停用时应当拒绝');
        } catch (BusinessException $exception) {
            $this->assertSame('多 SMTP 插件未配置可用账号', $exception->getMessage());
        }

        $this->assertSame([], $sent);
    }

    public function test_first_account_is_used_and_cursor_advances(): void
    {
        $sent = [];
        $service = $this->service($this->accounts(), $sent);

        $service->sendHtml('user@test.com', '主题', '<p>正文</p>');

        $this->assertCount(1, $sent);
        $this->assertSame('smtp-a.test', $sent[0]['account']['host']);
        $this->assertSame('user@test.com', $sent[0]['to']);
        $this->assertSame('主题', $sent[0]['subject']);
        $this->assertSame('<p>正文</p>', $sent[0]['html']);
        $this->assertSame(1, Cache::get('plugin:mail:multi_smtp_round_robin:cursor'));
    }

    public function test_cursor_selects_next_account_on_subsequent_send(): void
    {
        $sent = [];
        $service = $this->service($this->accounts(), $sent);

        $service->sendHtml('first@test.com', '主题', '<p>1</p>');
        $service->sendHtml('second@test.com', '主题', '<p>2</p>');

        $this->assertCount(2, $sent);
        $this->assertSame('smtp-a.test', $sent[0]['account']['host']);
        // 成功投递后写入 (0+1)%2 = 1，第二次必须换到第二个账号
        $this->assertSame('smtp-b.test', $sent[1]['account']['host']);
        $this->assertSame(0, Cache::get('plugin:mail:multi_smtp_round_robin:cursor'));
    }

    public function test_failed_account_enters_cooldown_and_next_one_is_tried(): void
    {
        $sent = [];
        $service = $this->service($this->accounts(), $sent, [], new \RuntimeException('认证失败'));

        try {
            $service->sendHtml('user@test.com', '主题', '<p>正文</p>');
            $this->fail('两个账号都失败时应当抛出');
        } catch (BusinessException $exception) {
            $this->assertSame('多 SMTP 轮询发送失败，已尝试 2 个账号', $exception->getMessage());
        }

        $this->assertCount(2, $sent);
        $this->assertSame('smtp-a.test', $sent[0]['account']['host']);
        $this->assertSame('smtp-b.test', $sent[1]['account']['host']);
        // 冷却到期时间必须写在失败的账号上
        $this->assertGreaterThan(time(), (int) Cache::get('plugin:mail:multi_smtp_round_robin:cooldown:0'));
        $this->assertGreaterThan(time(), (int) Cache::get('plugin:mail:multi_smtp_round_robin:cooldown:1'));
    }

    public function test_cooldown_account_is_skipped_from_cursor_position(): void
    {
        $sent = [];
        $service = $this->service($this->accounts(), $sent);
        Cache::put('plugin:mail:multi_smtp_round_robin:cooldown:0', time() + 60, 60);

        $service->sendHtml('user@test.com', '主题', '<p>正文</p>');

        $this->assertCount(1, $sent);
        // 游标指向 0，但 0 号仍在冷却，必须跳过投 1 号
        $this->assertSame('smtp-b.test', $sent[0]['account']['host']);
    }

    public function test_cooldown_seconds_are_honoured(): void
    {
        $sent = [];
        $service = $this->service($this->accounts(), $sent, ['cooldown_seconds' => 5], new \RuntimeException('失败'));

        try {
            $service->sendHtml('user@test.com', '主题', '<p>正文</p>');
        } catch (BusinessException) {
            // 断言冷却时长，异常本身由上一个用例覆盖
        }

        $until = (int) Cache::get('plugin:mail:multi_smtp_round_robin:cooldown:0');
        $this->assertGreaterThanOrEqual(time() + 3, $until);
        $this->assertLessThanOrEqual(time() + 5, $until);
    }

    public function test_cursor_uses_cache_value_as_start_index(): void
    {
        $sent = [];
        $service = $this->service($this->accounts(), $sent);
        Cache::put('plugin:mail:multi_smtp_round_robin:cursor', 1, 60);

        $service->sendHtml('user@test.com', '主题', '<p>正文</p>');

        $this->assertSame('smtp-b.test', $sent[0]['account']['host']);
        $this->assertSame(0, Cache::get('plugin:mail:multi_smtp_round_robin:cursor'));
    }

    public function test_unknown_action_returns_unsupported(): void
    {
        $sent = [];
        $service = $this->service($this->accounts(), $sent);

        $result = $service->execute(['action' => 'mail.unknown']);

        $this->assertFalse($result['success']);
        $this->assertSame('不支持的插件动作', $result['message']);
        $this->assertSame([], $sent);
    }

    public function test_execute_send_html_does_not_validate_recipient(): void
    {
        $sent = [];
        $service = $this->service($this->accounts(), $sent);

        // 历史契约：本插件不在插件侧校验收件人格式，交给传输层
        $result = $service->execute([
            'action' => 'mail.send_html',
            'payload' => ['to' => 'not-an-email', 'subject' => '主题', 'html' => '<p>正文</p>'],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(['sent' => true], $result['data']);
        $this->assertCount(1, $sent);
        $this->assertSame('not-an-email', $sent[0]['to']);
    }

    public function test_execute_test_smtp_requires_account_index(): void
    {
        $sent = [];
        $service = $this->service($this->accounts(), $sent);

        $result = $service->execute([
            'action' => 'mail.test_smtp',
            'payload' => ['to' => 'user@test.com', 'subject' => '主题'],
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('缺少必要参数：account_index、to、subject', $result['message']);
        $this->assertSame([], $sent);
    }

    public function test_execute_test_smtp_rejects_out_of_range_account_index(): void
    {
        $sent = [];
        $service = $this->service($this->accounts(), $sent);

        try {
            // 下标通过参数校验，但账号表里没有这一项：由投递钩子拒绝
            $service->execute([
                'action' => 'mail.test_smtp',
                'payload' => ['account_index' => 9, 'to' => 'user@test.com', 'subject' => '主题'],
            ]);
            $this->fail('账号下标越界时应当抛出');
        } catch (BusinessException $exception) {
            $this->assertSame('SMTP 账号不存在', $exception->getMessage());
        }

        $this->assertSame([], $sent);
    }

    public function test_execute_test_smtp_delivers_to_selected_account(): void
    {
        $sent = [];
        $service = $this->service($this->accounts(), $sent);

        $result = $service->execute([
            'action' => 'mail.test_smtp',
            'payload' => [
                'account_index' => 1,
                'to' => 'user@test.com',
                'subject' => '测试主题',
                'body' => '自定义正文',
                'template_code' => '100001',
            ],
        ]);

        $this->assertTrue($result['success']);
        // 回执只保留 sent 与 template_code，不带 to/subject
        $this->assertSame(['sent' => true, 'template_code' => '100001'], $result['data']);
        $this->assertSame('测试邮件发送成功', $result['message']);
        $this->assertCount(1, $sent);
        $this->assertSame('smtp-b.test', $sent[0]['account']['host']);
        $this->assertSame('测试主题', $sent[0]['subject']);
        // 正文经 nl2br + htmlspecialchars 处理
        $this->assertSame('自定义正文', $sent[0]['html']);
    }

    public function test_execute_test_smtp_escapes_custom_body(): void
    {
        $sent = [];
        $service = $this->service($this->accounts(), $sent);

        $service->execute([
            'action' => 'mail.test_smtp',
            'payload' => [
                'account_index' => 0,
                'to' => 'user@test.com',
                'subject' => '主题',
                'body' => "<b>加粗</b>\n第二行",
            ],
        ]);

        $this->assertSame("&lt;b&gt;加粗&lt;/b&gt;<br />\n第二行", $sent[0]['html']);
    }

    public function test_execute_test_smtp_ignores_html_field(): void
    {
        $sent = [];
        $service = $this->service($this->accounts(), $sent);

        // 本插件只认 body；html 字段被忽略，未带 body 时回落到内置验证码文案
        $service->execute([
            'action' => 'mail.test_smtp',
            'payload' => [
                'account_index' => 0,
                'to' => 'user@test.com',
                'subject' => '主题',
                'html' => '<p>这段不应被使用</p>',
                'code' => '123456',
            ],
        ]);

        $this->assertStringContainsString('123456', $sent[0]['html']);
        $this->assertStringNotContainsString('这段不应被使用', $sent[0]['html']);
    }

    public function test_execute_test_smtp_rejects_non_array_account(): void
    {
        $sent = [];
        $service = $this->service(['坏数据'], $sent);

        try {
            $service->execute([
                'action' => 'mail.test_smtp',
                'payload' => ['account_index' => 0, 'to' => 'user@test.com', 'subject' => '主题'],
            ]);
            $this->fail('账号配置非数组时应当抛出');
        } catch (BusinessException $exception) {
            $this->assertSame('SMTP 账号配置无效', $exception->getMessage());
        }

        $this->assertSame([], $sent);
    }
}
