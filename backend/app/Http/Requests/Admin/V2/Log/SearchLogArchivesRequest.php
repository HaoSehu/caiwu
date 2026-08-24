<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Log;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use App\Services\System\LogArchiveV2Service;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SearchLogArchivesRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'table' => ['required', 'string', Rule::in(array_keys((array) config('log_archive.tables', [])))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function pageNumber(): int
    {
        return max(1, (int) $this->integer('page', 1));
    }

    public function perPage(int $default = 20, int $max = 100): int
    {
        return max(1, min((int) $this->integer('page_size', $default), $max));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $page = max(1, (int) $this->input('page', 1));
            $pageSize = max(1, min((int) $this->input('page_size', 20), 100));
            $maxPage = (int) ceil(LogArchiveV2Service::COLD_SEARCH_MAX_ROWS / $pageSize);

            if ($page > $maxPage) {
                $validator->errors()->add(
                    'page',
                    '归档冷检索单次最多扫描 '.LogArchiveV2Service::COLD_SEARCH_MAX_ROWS.' 行，请缩小时间范围或分页范围。',
                );
            }
        });
    }
}
