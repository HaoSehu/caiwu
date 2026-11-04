<?php

declare(strict_types=1);

namespace App\Services\Messaging\Data;

use InvalidArgumentException;

/**
 * 单收件人短信 DTO（对齐 howstack SmsMessage）。
 *
 * 号码必须 E.164；模板号与变量名有格式上限；正文最长 2048 字符。
 * 禁止序列化：队列只保存 delivery_id，不把地址或正文写进队列载荷。
 */
final class SmsMessage
{
    /**
     * @param  array<string, string>  $parameters  模板变量，按名称提供
     */
    public function __construct(
        public readonly string $key,
        public readonly string $recipient,
        public readonly string $templateId = '',
        public readonly array $parameters = [],
        public readonly string $text = '',
    ) {
        if (! preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $key)
            || ! preg_match('/^\+[1-9][0-9]{7,14}$/D', $recipient)
            || ($templateId !== '' && ! preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $templateId))
            || count($parameters) > 32
            || mb_strlen($text) > 2048
            || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $text) === 1) {
            throw new InvalidArgumentException('短信输入无效');
        }

        foreach ($parameters as $name => $value) {
            if (! preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/D', (string) $name)
                || mb_strlen($value) > 256
                || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
                throw new InvalidArgumentException('短信模板变量无效');
            }
        }
    }
}
