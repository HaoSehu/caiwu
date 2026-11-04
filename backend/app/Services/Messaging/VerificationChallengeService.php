<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\VerificationChallenge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 验证码挑战：只保存 HMAC-SHA256 摘要，明文验证码不落库。
 *
 * 对齐 howstack SendRegistrationCodeService / MemberCodeAuthenticationService：
 * 挑战在发送前创建、按发送结果激活、校验时一次性消费。
 */
final class VerificationChallengeService
{
    private const ATTEMPT_TTL_MINUTES = 5;

    private const MAX_VERIFY_ATTEMPTS = 5;

    /**
     * @param  string  $secret  用于摘要的密钥（AppKey 或专用 secret）
     */
    public function __construct(private readonly ?string $secret = null) {}

    public function digest(string $value): string
    {
        $key = $this->secret ?? (string) config('app.key');
        if (strlen($key) < 32) {
            throw new \RuntimeException('验证码安全配置不可用');
        }

        return hash_hmac('sha256', $value, $key);
    }

    /**
     * 创建挑战（未激活）。
     *
     * @param  array{purpose: string, method: string, target: string, binding: string, id: string, code: string}  $context
     */
    public function issue(array $context): void
    {
        VerificationChallenge::query()->create([
            'challenge_id' => $context['id'],
            'purpose' => $context['purpose'],
            'method' => $context['method'],
            'target_hash' => $this->digest($context['purpose'].':'.$context['method'].':'.$context['target']),
            'binding_hash' => $this->digest($context['binding']),
            'code_hash' => $this->digest($context['id'].':'.$context['code']),
            'status' => VerificationChallenge::STATUS_PENDING,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::ATTEMPT_TTL_MINUTES),
            'created_at' => now(),
        ]);
    }

    /** 按发送结果激活或标记失效 */
    public function activate(string $challengeId, bool $accepted): void
    {
        VerificationChallenge::query()
            ->where('challenge_id', $challengeId)
            ->where('status', VerificationChallenge::STATUS_PENDING)
            ->update([
                'status' => $accepted ? VerificationChallenge::STATUS_ACTIVE : VerificationChallenge::STATUS_EXPIRED,
            ]);
    }

    /**
     * 一次性消费挑战：摘要全部匹配且未过期才成功。
     */
    public function consume(
        string $challengeId,
        string $purpose,
        string $method,
        string $target,
        string $binding,
        string $code,
    ): bool {
        return DB::transaction(function () use ($challengeId, $purpose, $method, $target, $binding, $code): bool {
            $challenge = VerificationChallenge::query()
                ->where('challenge_id', $challengeId)
                ->lockForUpdate()
                ->first();

            if (! $challenge instanceof VerificationChallenge
                || $challenge->status !== VerificationChallenge::STATUS_ACTIVE
                || $challenge->expires_at->isPast()
                || $challenge->attempts >= self::MAX_VERIFY_ATTEMPTS) {
                return false;
            }

            $challenge->attempts = $challenge->attempts + 1;

            $targetHash = $this->digest($purpose.':'.$method.':'.$target);
            $bindingHash = $this->digest($binding);
            $codeHash = $this->digest($challengeId.':'.$code);

            $matched = hash_equals((string) $challenge->target_hash, $targetHash)
                && hash_equals((string) $challenge->binding_hash, $bindingHash)
                && hash_equals((string) $challenge->code_hash, $codeHash);

            if ($matched) {
                $challenge->status = VerificationChallenge::STATUS_CONSUMED;
            }

            $challenge->save();

            return $matched;
        });
    }

    /** @return array{challenge_id: string, expires_in: int} */
    public function newChallengeId(): array
    {
        return ['challenge_id' => Str::random(32), 'expires_in' => self::ATTEMPT_TTL_MINUTES * 60];
    }
}
