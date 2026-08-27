<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Log;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use App\Services\System\AdminLogService;
use App\Services\System\AdminLogV2QueryService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListLogsRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::in(AdminLogV2QueryService::channels())],
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'include_summary' => ['sometimes', 'boolean'],
            // 复用基类 trait 的旧分页参数禁用规则，替代手抄的 per_page/pageSize prohibited。
            ...$this->legacyPaginationRules(),
            'keyword' => ['sometimes', 'string', 'max:120'],
            'actor_keyword' => ['sometimes', 'string', 'max:120'],
            'description_keyword' => ['sometimes', 'string', 'max:120'],
            'ip_address' => ['sometimes', 'string', 'max:45'],
            'level' => ['sometimes', 'in:DEBUG,INFO,NOTICE,WARNING,ERROR,CRITICAL,ALERT,EMERGENCY'],
            'module' => ['sometimes', 'string', 'max:60'],
            'method' => ['sometimes', 'in:GET,POST,PUT,PATCH,DELETE,OPTIONS,HEAD'],
            'status' => ['sometimes', 'string', 'max:20'],
            'task_key' => ['sometimes', 'string', 'max:60'],
            'task_name' => ['sometimes', 'string', 'max:120'],
            'user_type' => ['sometimes', 'in:admin,client,guest'],
            'phone' => ['sometimes', 'string', 'max:30'],
            'email' => ['sometimes', 'string', 'max:120'],
            'gateway' => ['sometimes', 'string', 'max:50'],
            'gateway_key' => ['sometimes', 'string', 'max:120'],
            'driver_key' => ['sometimes', 'string', 'max:120'],
            'plugin_id' => ['sometimes', 'integer', 'min:1'],
            'trace_id' => ['sometimes', 'string', 'max:64'],
            'action' => ['sometimes', 'string', 'max:100'],
            'result_status' => ['sometimes', 'in:success,failed,pending,unknown'],
            'actor_type' => ['sometimes', 'in:admin,client,system,sub_account'],
            'subject_type' => ['sometimes', 'string', 'max:50'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
        ];
    }

    public function validationData(): array
    {
        return array_merge(parent::validationData(), [
            'channel' => $this->route('channel'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ((string) $this->route('channel') !== 'api') {
                return;
            }

            $page = max(1, (int) $this->input('page', 1));
            $pageSize = max(1, min((int) $this->input('page_size', 20), 100));
            $maxPage = intdiv(
                AdminLogService::API_CANDIDATE_MAX_ROWS + $pageSize - 1,
                $pageSize,
            );

            if ($page > $maxPage) {
                $validator->errors()->add(
                    'page',
                    'API 日志单次查询最多浏览前 '.AdminLogService::API_CANDIDATE_MAX_ROWS.' 条，请缩小筛选范围。',
                );
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->safe()->only([
            'keyword',
            'actor_keyword',
            'description_keyword',
            'ip_address',
            'level',
            'module',
            'method',
            'status',
            'task_key',
            'task_name',
            'user_type',
            'phone',
            'email',
            'gateway',
            'gateway_key',
            'driver_key',
            'plugin_id',
            'trace_id',
            'action',
            'result_status',
            'actor_type',
            'subject_type',
            'start_date',
            'end_date',
        ]);
    }

    public function pageNumber(): int
    {
        return max(1, (int) $this->integer('page', 1));
    }

    public function includeSummary(): bool
    {
        return $this->boolean('include_summary', true);
    }
}
