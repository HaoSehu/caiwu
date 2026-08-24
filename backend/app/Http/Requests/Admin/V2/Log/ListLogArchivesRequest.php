<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Log;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListLogArchivesRequest extends AdminFormRequest
{
    private const MAX_PAGE = 500;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'table' => ['sometimes', 'string', Rule::in(array_keys((array) config('log_archive.tables', [])))],
            'status' => ['sometimes', 'string', Rule::in([
                'planned', 'staging', 'verified', 'published', 'purging', 'purged', 'failed', 'needs_recovery',
            ])],
            'batch_id' => ['sometimes', 'string', 'max:64'],
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
            if ((int) $this->input('page', 1) > self::MAX_PAGE) {
                $validator->errors()->add(
                    'page',
                    '归档批次列表最多支持第 '.self::MAX_PAGE.' 页，请缩小筛选范围。',
                );
            }
        });
    }
}
