<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * 入口文件请求收尾契约（2026-10-21 审查 P1）：
 * public/index.php 自己拆开了 send / finish_request / terminate 三段，
 * 因此必须显式调用 Kernel::terminate() 而不是 Application::terminate()。
 * 后者不执行 terminateMiddleware，框架全局中间件 InvokeDeferredCallbacks 的
 * terminate() 就永远不跑，defer() 注册的延迟任务（如 LogOperation 落 activity_logs）
 * 会在响应发出后被静默丢弃——线上表现为写请求审计全丢。
 */
class PublicEntryLifecycleTest extends TestCase
{
    public function test_entry_uses_kernel_terminate_contract(): void
    {
        $code = $this->entryCode();

        $this->assertStringContainsString(
            '$kernel->terminate($request, $response)',
            $code,
            '入口必须经 Kernel::terminate 收尾，否则 terminateMiddleware 不执行、defer 回调丢失'
        );
        // 注释里可以写「不能用 $app->terminate(...)」作为说明，但可执行代码里绝不能出现。
        $this->assertStringNotContainsString(
            '$app->terminate(',
            $code,
            'Application::terminate 不跑 terminateMiddleware，会把延迟回调静默丢掉'
        );
    }

    public function test_entry_keeps_early_finish_request_ordering(): void
    {
        $source = (string) file_get_contents(base_path('public/index.php'));

        // 三个语义缺一不可：客户端断开不中断脚本、响应先发出、FPM 进程先空出。
        $this->assertStringContainsString('ignore_user_abort(true)', $source);
        $this->assertStringContainsString('fastcgi_finish_request()', $source);

        $sendPosition = strpos($source, '$response->send();');
        $terminatePosition = strpos($source, '$kernel->terminate($request, $response);');

        $this->assertNotFalse($sendPosition);
        $this->assertNotFalse($terminatePosition);
        $this->assertLessThan(
            $terminatePosition,
            $sendPosition,
            'terminate 必须在响应发出之后执行（terminating 回调本就要跑在响应之后）'
        );
    }

    public function test_kernel_terminate_flushes_deferred_callbacks(): void
    {
        $marker = sys_get_temp_dir().DIRECTORY_SEPARATOR.'defer-kernel-'.bin2hex(random_bytes(6)).'.txt';

        $kernel = $this->app->make(HttpKernelContract::class);

        $this->app->make('router')->get('/__defer_kernel_probe', function () use ($marker) {
            defer(static function () use ($marker): void {
                file_put_contents($marker, 'ran');
            });

            return response('probe-ok');
        });

        try {
            $request = Request::create('/__defer_kernel_probe', 'GET');
            $response = $kernel->handle($request);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertFileDoesNotExist($marker, 'defer 回调必须在响应发出后执行，handle 阶段不能执行');

            $kernel->terminate($request, $response);

            $this->assertFileExists($marker, 'Kernel::terminate 必须触发 InvokeDeferredCallbacks 执行延迟任务');
        } finally {
            if (is_file($marker)) {
                unlink($marker);
            }
        }
    }

    /** 取入口文件的可执行代码：逐行剔除整行 // 注释，避免把说明文字里的反例当成真实调用。 */
    private function entryCode(): string
    {
        $source = (string) file_get_contents(base_path('public/index.php'));

        $kept = [];
        foreach (explode("\n", str_replace("\r\n", "\n", $source)) as $line) {
            if (str_starts_with(ltrim($line), '//')) {
                continue;
            }

            $kept[] = $line;
        }

        return implode("\n", $kept);
    }
}
