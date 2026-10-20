<?php

namespace App\Models;

use App\Exceptions\BusinessException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 用户账户投影（旧表）：现金真源为 finance_accounts，
 * cash_balance 仅为切换期热备列（T+7 由 000015 删除）。
 *
 * @property int    $user_id
 * @property string $cash_balance
 * @property string $credit_limit
 * @property string $referral_frozen_balance
 * @property string $referral_available_balance
 * @property string $referral_pending_withdrawal_balance
 * @property string $referral_withdrawn_balance
 * @property int    $version
 */
class UserAccount extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'cash_balance',
        'credit_limit',
        'referral_frozen_balance',
        'referral_available_balance',
        'referral_pending_withdrawal_balance',
        'referral_withdrawn_balance',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'cash_balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'referral_frozen_balance' => 'decimal:2',
            'referral_available_balance' => 'decimal:2',
            'referral_pending_withdrawal_balance' => 'decimal:2',
            'referral_withdrawn_balance' => 'decimal:2',
            'version' => 'integer',
        ];
    }

    /**
     * 账户写入必须比较读取时的版本，防止未持锁调用静默覆盖其他余额变更。
     */
    protected function performUpdate(Builder $query)
    {
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        $expectedVersion = (int) ($this->getRawOriginal('version') ?? 0);
        $this->setAttribute('version', $expectedVersion + 1);
        $dirty = $this->getDirtyForUpdate();

        if (count($dirty) > 0) {
            $updated = $this->setKeysForSaveQuery($query)
                ->where($this->qualifyColumn('version'), $expectedVersion)
                ->update($dirty);

            if ($updated !== 1) {
                throw new BusinessException('账户数据已被并发修改，请重试', 40900, 409);
            }

            $this->syncChanges();

            $this->fireModelEvent('updated', false);
        }

        return true;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
