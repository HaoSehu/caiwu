<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Exceptions\BusinessException;
use App\Models\MessageConfiguration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * 消息场景配置仓储：整条配置加密存放，修订号乐观锁防覆盖。
 *
 * 写入口唯一（本类 save）；读取方一律经 all/get，
 * 避免各业务直接查 message_configurations 造成解密口径分裂。
 */
final class MessageConfigurationRepository
{
    /**
     * @return array<string, array{revision: int, configurations: array<string, mixed>}>
     */
    public function all(string $audience): array
    {
        $rows = MessageConfiguration::query()->where('audience', $audience)->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->message_type] = [
                'revision' => (int) $row->revision,
                'configurations' => $this->decode((string) $row->configuration_encrypted),
            ];
        }

        return $result;
    }

    /**
     * @return array{revision: int, configurations: array<string, mixed>}
     */
    public function get(string $audience, string $messageType): array
    {
        $row = MessageConfiguration::query()
            ->where('audience', $audience)
            ->where('message_type', $messageType)
            ->first();

        if (! $row instanceof MessageConfiguration) {
            return ['revision' => 0, 'configurations' => []];
        }

        return [
            'revision' => (int) $row->revision,
            'configurations' => $this->decode((string) $row->configuration_encrypted),
        ];
    }

    /**
     * 原子保存整条场景配置。
     *
     * @param  array<string, mixed>  $configurations
     *
     * @throws BusinessException 修订号落后于库内版本（409）
     */
    public function save(string $audience, string $messageType, int $revision, array $configurations): int
    {
        return DB::transaction(function () use ($audience, $messageType, $revision, $configurations): int {
            $row = MessageConfiguration::query()
                ->where('audience', $audience)
                ->where('message_type', $messageType)
                ->lockForUpdate()
                ->first();

            if (($row instanceof MessageConfiguration ? (int) $row->revision : 0) !== $revision) {
                throw new BusinessException('消息配置已变化，请重新打开后编辑', 40900, 409);
            }

            $row ??= new MessageConfiguration([
                'audience' => $audience,
                'message_type' => $messageType,
            ]);

            $row->configuration_encrypted = Crypt::encryptString(json_encode($configurations, JSON_THROW_ON_ERROR));
            $row->revision = $revision + 1;
            $row->save();

            return (int) $row->revision;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $payload): array
    {
        $decoded = json_decode(Crypt::decryptString($payload), true, 64, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
