<?php

declare(strict_types=1);

namespace App\Services\Integrations\Payments\Concerns;

/**
 * 支付网关的 PEM 密钥规范化。
 *
 * 管理员在配置里粘贴的通常是去掉头尾与换行的裸 base64 密钥，
 * openssl 系列函数要求标准 PEM 格式（每行 64 字符）。这里统一承载
 * 「裸 base64 → 标准 PEM」的包装规则，签名与验签两侧共用同一实现，
 * 避免各网关客户端各自维护 wordwrap/chunk_split 变体导致验签漂移。
 */
trait WrapsPemKeys
{
    /**
     * 把单个 base64 主体包成完整 PEM 文本（每行 64 字符，含 BEGIN/END 头尾）。
     */
    protected function wrapPemKey(string $base64Body, string $label): string
    {
        return "-----BEGIN {$label}-----\n".wordwrap($base64Body, 64, "\n", true)."\n-----END {$label}-----";
    }

    /**
     * 规范化商户/平台私钥：已是 PEM（含 -----BEGIN 头）或空串原样返回，
     * 裸 base64 统一按 PKCS8「PRIVATE KEY」标签包装。
     */
    protected function pemWrappedPrivateKey(string $key): string
    {
        $normalized = trim($key);

        if ($normalized === '' || str_contains($normalized, '-----BEGIN')) {
            return $normalized;
        }

        return $this->wrapPemKey($normalized, 'PRIVATE KEY');
    }

    /**
     * 规范化平台公钥：已是 PEM 或空串原样返回，裸 base64 按「PUBLIC KEY」标签包装。
     */
    protected function pemWrappedPublicKey(string $key): string
    {
        $normalized = trim($key);

        if ($normalized === '' || str_contains($normalized, '-----BEGIN')) {
            return $normalized;
        }

        return $this->wrapPemKey($normalized, 'PUBLIC KEY');
    }
}
