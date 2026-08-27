<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\MemberLevel;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use App\Models\MarketingProductGroup;
use App\Models\MemberLevel;
use Illuminate\Validation\Validator;

class SyncLevelGroupDiscountsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'memberLevel' => ['required', 'integer', 'min:1'],
            'rules' => ['present', 'array'],
            'rules.*.marketing_product_group_id' => ['required', 'integer', 'min:1'],
            'rules.*.discount_type' => ['required', 'integer', 'in:1,2'],
            // type=1 为折后保留比例（bates 语义，90=九折）；type=2 为固定减免金额（0-999999）
            'rules.*.discount_value' => ['required', 'numeric', 'min:0.01', 'max:999999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $rules = (array) ($this->validationData()['rules'] ?? []);

            $groupIds = collect($rules)
                ->pluck('marketing_product_group_id')
                ->map(fn ($id) => (int) $id);
            if ($groupIds->count() !== $groupIds->unique()->count()) {
                $validator->errors()->add('rules', '同一营销组的折扣规则只能配置一条');

                return;
            }

            $existingIds = MarketingProductGroup::query()->whereIn('id', $groupIds)->pluck('id');
            if ($existingIds->count() !== $groupIds->unique()->count()) {
                $validator->errors()->add('rules', '折扣规则中的营销组不存在');
            }

            foreach ($rules as $index => $rule) {
                $type = (int) ($rule['discount_type'] ?? 0);
                $value = (float) ($rule['discount_value'] ?? 0);

                if ($type === 1 && $value > 100) {
                    $validator->errors()->add("rules.{$index}.discount_value", '折扣保留比例不能大于 100');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $level = $this->route('memberLevel');

        $this->merge([
            'memberLevel' => $level instanceof MemberLevel ? $level->getKey() : $level,
        ]);
    }

    /**
     * @return array<int, array{marketing_product_group_id: int, discount_type: int, discount_value: float}>
     */
    public function rulesPayload(): array
    {
        return collect((array) ($this->validated()['rules'] ?? []))
            ->map(fn (array $rule): array => [
                'marketing_product_group_id' => (int) $rule['marketing_product_group_id'],
                'discount_type' => (int) $rule['discount_type'],
                'discount_value' => round((float) $rule['discount_value'], 2),
            ])
            ->values()
            ->all();
    }
}
