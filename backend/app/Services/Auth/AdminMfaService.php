<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\BusinessException;
use App\Models\AdminUser;
use App\Services\System\OperationLogService;
use App\Support\CacheKey;
use App\Support\Security\TotpService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * 管理员双因素认证（SEC-1）。
 *
 * 负责 TOTP 的绑定、确认、解绑与验证码校验；登录挑战凭证的签发与消费
 * 留在 AuthService，二者通过 admin_users 的三列交接。
 *
 * 三个刻意的取舍：
 * 1. 绑定分两步：先发草稿密钥（只进缓存），确认能算出正确验证码后才落库。
 *    否则管理员扫码失败或中途放弃，账号就被锁在双因素门外。
 * 2. 密钥用 Crypt 加密存储，不以明文落库；恢复码只存 Hash 结果，使用后即时移除。
 * 3. 所有异常路径都先记日志再抛业务异常，避免「绑定失败但没人知道为什么」。
 */
class AdminMfaService
{
    /** 绑定草稿有效期：够慢的人输完一次验证码，又不至于长期留一个未确认密钥。 */
    private const SETUP_TTL_SECONDS = 600;

    /** 恢复码数量与长度：8 条足够救急，10 位随机字符不可枚举。 */
    private const RECOVERY_CODE_COUNT = 8;

    private const RECOVERY_CODE_LENGTH = 10;

    public function __construct(
        private readonly TotpService $totpService,
        private readonly OperationLogService $operationLogService,
    ) {}

    /**
     * 第一步：生成绑定草稿。
     *
     * @return array{secret: string, otpauth_uri: string, issuer: string, expires_in: int}
     */
    public function beginSetup(AdminUser $admin): array
    {
        if ($admin->hasMfaEnabled()) {
            throw new BusinessException('该管理员已启用双因素认证，请先解绑后再重新绑定', 42200, 422);
        }

        $secret = $this->totpService->generateSecret();
        Cache::store('redis_volatile')->put(
            CacheKey::adminMfaSetup((int) $admin->id),
            ['secret' => $secret, 'issued_at' => now()->toIso8601String()],
            now()->addSeconds(self::SETUP_TTL_SECONDS),
        );

        $issuer = $this->issuer();

        return [
            'secret' => $secret,
            'otpauth_uri' => $this->totpService->provisioningUri($secret, (string) $admin->username, $issuer),
            'issuer' => $issuer,
            'expires_in' => self::SETUP_TTL_SECONDS,
        ];
    }

    /**
     * 第二步：校验一次动态码，确认后写库并返回明文恢复码（仅此一次可见）。
     *
     * @return array{recovery_codes: list<string>}
     */
    public function enable(AdminUser $admin, string $code, ?string $ipAddress = null): array
    {
        if ($admin->hasMfaEnabled()) {
            throw new BusinessException('该管理员已启用双因素认证，请先解绑后再重新绑定', 42200, 422);
        }

        $cacheKey = CacheKey::adminMfaSetup((int) $admin->id);
        $draft = Cache::store('redis_volatile')->get($cacheKey);
        $secret = is_array($draft) ? trim((string) ($draft['secret'] ?? '')) : '';

        if ($secret === '') {
            throw new BusinessException('绑定已过期，请重新发起绑定', 42200, 422);
        }

        if (! $this->totpService->verify($secret, $code)) {
            throw new BusinessException('验证码不正确，请检查认证器时间后重试', 42200, 422);
        }

        $recoveryCodes = $this->generateRecoveryCodes();
        $hashedCodes = array_map(static fn (string $plain): string => Hash::make($plain), $recoveryCodes);

        $admin->forceFill([
            'mfa_secret' => Crypt::encryptString($secret),
            'mfa_enabled_at' => now(),
            'mfa_recovery_codes' => $hashedCodes,
        ])->save();

        Cache::store('redis_volatile')->forget($cacheKey);

        $this->operationLogService->write(
            userId: (int) $admin->id,
            userType: 'admin',
            action: 'admin.auth.mfa.enable',
            module: 'auth',
            targetId: (int) $admin->id,
            detail: ['username' => (string) $admin->username],
            ipAddress: $ipAddress,
        );

        return ['recovery_codes' => $recoveryCodes];
    }

    /**
     * 解绑：必须同时提供当前密码与有效验证码（或一条恢复码）。
     *
     * 只验验证码不够——令牌被盗时攻击者同样能算码；只验密码也不够——
     * 密码泄漏就等于双因素白开。两个因子同时要求才符合「解绑是高危动作」。
     */
    public function disable(AdminUser $admin, string $password, string $code, ?string $ipAddress = null): void
    {
        if (! $admin->hasMfaEnabled()) {
            throw new BusinessException('该管理员未启用双因素认证', 42200, 422);
        }

        if (! Hash::check($password, (string) $admin->password)) {
            throw new BusinessException('当前密码不正确', 42200, 422);
        }

        if (! $this->verify($admin, $code)) {
            throw new BusinessException('验证码不正确，无法解绑双因素认证', 42200, 422);
        }

        $admin->forceFill([
            'mfa_secret' => null,
            'mfa_enabled_at' => null,
            'mfa_recovery_codes' => null,
        ])->save();

        $this->operationLogService->write(
            userId: (int) $admin->id,
            userType: 'admin',
            action: 'admin.auth.mfa.disable',
            module: 'auth',
            targetId: (int) $admin->id,
            detail: ['username' => (string) $admin->username],
            ipAddress: $ipAddress,
        );
    }

    /**
     * 校验动态码；动态码不匹配时回退到恢复码（命中即消耗）。
     *
     * 恢复码是「手机丢了」的逃生通道，只在动态码不通过时尝试，
     * 避免每次正常登录都去遍历恢复码哈希（Hash::check 是故意慢的）。
     */
    public function verify(AdminUser $admin, string $code): bool
    {
        if (! $admin->hasMfaEnabled()) {
            return false;
        }

        $secret = $this->decryptSecret($admin);
        if ($secret !== null && $this->totpService->verify($secret, $code)) {
            return true;
        }

        return $this->consumeRecoveryCode($admin, $code);
    }

    /**
     * 管理员当前是否已启用双因素认证。
     */
    public function isEnabled(AdminUser $admin): bool
    {
        return $admin->hasMfaEnabled();
    }

    /**
     * 应急清除（运维命令用）：不验密码与验证码，直接解绑。
     *
     * 只在服务器 shell 上可执行，属于「管理员把自己锁在门外」的兜底手段。
     */
    public function forceDisable(AdminUser $admin, ?string $operator = null): bool
    {
        if (! $admin->hasMfaEnabled()) {
            return false;
        }

        $admin->forceFill([
            'mfa_secret' => null,
            'mfa_enabled_at' => null,
            'mfa_recovery_codes' => null,
        ])->save();

        $this->operationLogService->write(
            userId: (int) $admin->id,
            userType: 'admin',
            action: 'admin.auth.mfa.force_disable',
            module: 'auth',
            targetId: (int) $admin->id,
            detail: ['username' => (string) $admin->username, 'operator' => $operator ?? 'system'],
            ipAddress: null,
        );

        return true;
    }

    /**
     * 解密 TOTP 密钥。密文损坏时记 error 并返回 null——调用方按验证失败处理，
     * 不让一个坏密钥把整个登录接口打成 500。
     */
    private function decryptSecret(AdminUser $admin): ?string
    {
        $ciphertext = trim((string) ($admin->mfa_secret ?? ''));
        if ($ciphertext === '') {
            return null;
        }

        try {
            $plain = Crypt::decryptString($ciphertext);
        } catch (Throwable $exception) {
            Log::error('[管理员双因素] 密钥解密失败，可能 APP_KEY 已轮换', [
                'admin_id' => (int) $admin->id,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        return $plain !== '' ? $plain : null;
    }

    /**
     * @return list<string>
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $codes[] = Str::upper(Str::random(self::RECOVERY_CODE_LENGTH));
        }

        return $codes;
    }

    /**
     * 逐个比对恢复码哈希，命中后立即从数组中移除并落库（一次性）。
     *
     * 恢复码是整数组覆盖写的：同一个恢复码并发提交两次时，两个请求都可能读到
     * 同一份旧数组、都 Hash::check 通过、都写回「删掉这一条」的结果，于是同一
     * 恢复码被用掉两次；若其中一次还带着别的并发消费，后写者会把对方删掉的
     * 恢复码重新写回来。用事务 + 行锁把「读数组 → 比对 → 写回」串行化，
     * 锁内重新读库拿到最新数组，第二个请求会看到该码已被移除而失败。
     */
    private function consumeRecoveryCode(AdminUser $admin, string $code): bool
    {
        $candidate = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        if (strlen($candidate) !== self::RECOVERY_CODE_LENGTH) {
            return false;
        }

        $adminId = (int) $admin->id;
        if ($adminId <= 0) {
            return false;
        }

        return DB::transaction(function () use ($admin, $adminId, $candidate): bool {
            // 锁内重读：调用方 $admin 可能是登录流程早期加载的实例，
            // mfa_recovery_codes 早已陈旧，直接拿它比对会让已用过的码复现。
            $locked = AdminUser::query()->lockForUpdate()->find($adminId);
            if (! $locked instanceof AdminUser) {
                return false;
            }

            // 模型属性类型由 larastan 按 json 列推断为 string|null，实际取值为
            // casts('array') 转换后的数组；这里以 mixed 承接再做运行时判断。
            /** @var mixed $raw */
            $raw = $locked->mfa_recovery_codes;
            if (! is_array($raw) || $raw === []) {
                return false;
            }

            $hashes = $raw;

            foreach ($hashes as $index => $hash) {
                if (! is_string($hash) || $hash === '') {
                    continue;
                }

                if (! Hash::check($candidate, $hash)) {
                    continue;
                }

                unset($hashes[$index]);
                $locked->forceFill(['mfa_recovery_codes' => array_values($hashes)])->save();
                // 回填调用方实例，避免同一请求后续读取到旧数组。
                $admin->setAttribute('mfa_recovery_codes', array_values($hashes));

                Log::info('[管理员双因素] 已使用恢复码登录', ['admin_id' => $adminId]);

                return true;
            }

            return false;
        });
    }

    private function issuer(): string
    {
        $name = trim((string) config('app.name', ''));

        return $name !== '' ? $name : 'Caiwu';
    }
}
