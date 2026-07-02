<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Gateways\AliPay\Controller;

use Caiwu\Plugins\Gateways\AliPay\AliPayPlugin;
use Illuminate\Http\Response;

class IndexController
{
    public function __construct(
        private readonly AliPayPlugin $plugin,
    ) {}

    public function notifyHandle(array $payload): bool
    {
        return $this->plugin->verifyNotify($payload);
    }

    public function returnHandle(array $payload): bool
    {
        return $this->plugin->verifyNotify($payload);
    }

    public function buildNotifyResponse(bool $success): Response
    {
        return $this->plugin->buildNotifyResponse($success);
    }
}
