<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

// afterResponse / terminating 回调（支付后开通、返佣等）依赖脚本活到响应发出之后。
// PHP-FPM 下客户端提前断开时，ignore_user_abort 默认 Off 会让脚本在 send() 后中断，
// 这些回调就静默丢失；同时 FPM 默认要等脚本结束才释放进程。
// 因此这里显式忽略中断，并把「发送响应 → 结束请求 → 执行 terminating」拆开：
// finish_request 先把连接收尾并空出 FPM 进程，后续 terminating 仍然在本进程执行。
//
// 必须经 Kernel 契约收尾，不能用 $app->terminate($request, $response)：
// Application::terminate() 无参数、只跑 terminatingCallbacks，不调用 terminateMiddleware，
// 会让框架全局中间件 InvokeDeferredCallbacks 的 terminate() 永远不执行，defer() 注册的
// 延迟任务（如 LogOperation 的 activity_logs 审计落库）在响应发出后被静默丢弃。
// Kernel::terminate() 依次 dispatch(Terminating) → terminateMiddleware() → app->terminate()，
// 与 Laravel 默认 handleRequest 语义一致，同时保留上面拆开执行的顺序。
if (PHP_SAPI !== 'cli') {
    ignore_user_abort(true);
}

$kernel = $app->make(Kernel::class);

$request = Request::capture();
$response = $kernel->handle($request);
$response->send();

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

$kernel->terminate($request, $response);

return $response;
