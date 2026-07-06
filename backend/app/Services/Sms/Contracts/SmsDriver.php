<?php

declare(strict_types=1);

namespace App\Services\Sms\Contracts;

use App\Services\Sms\Data\SmsSendRequest;
use App\Services\Sms\Data\SmsSendResult;
use App\Services\Sms\Data\SmsMessageRequest;

interface SmsDriver
{
    public function key(): string;

    public function label(): string;

    public function sendMessage(SmsMessageRequest $request): SmsSendResult;

    public function sendVerifyCode(SmsSendRequest $request): SmsSendResult;
}
