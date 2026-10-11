<?php

declare(strict_types=1);

namespace App\Http\Requests\V2\Content;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPublishedContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'keyword' => ['sometimes', 'string', 'max:100'],
            'category_id' => ['sometimes', 'integer', Rule::exists('content_categories', 'id')],
            'content_category_id' => ['sometimes', 'integer', Rule::exists('content_categories', 'id')],
            'is_recommended' => ['sometimes', 'integer', Rule::in([0, 1])],
            // M8 知识库标签检索：tag_ids 逗号分隔多值（如 1,2），tag_mode any=命中任一 / all=同时命中全部
            'tag_ids' => ['sometimes', 'string', 'regex:/^\d{1,10}(,\d{1,10})*$/'],
            'tag_mode' => ['sometimes', 'string', Rule::in(['any', 'all'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $filters = $this->safe()->only([
            'keyword',
            'category_id',
            'content_category_id',
            'is_recommended',
            'tag_ids',
            'tag_mode',
        ]);

        // M8：tag_ids 归一为 int 数组（空串视为未传），tag_mode 缺省 any
        if (isset($filters['tag_ids'])) {
            $tagIds = array_values(array_filter(array_map('intval', explode(',', (string) $filters['tag_ids']))));
            if ($tagIds === []) {
                unset($filters['tag_ids']);
            } else {
                $filters['tag_ids'] = $tagIds;
            }
        }
        if (isset($filters['tag_mode']) && ! in_array($filters['tag_mode'], ['any', 'all'], true)) {
            $filters['tag_mode'] = 'any';
        }

        if (empty($filters['category_id']) && ! empty($filters['content_category_id'])) {
            $filters['category_id'] = (int) $filters['content_category_id'];
        }

        return $filters;
    }

    public function perPage(int $default = 10): int
    {
        return max(1, min((int) $this->integer('page_size', $default), 50));
    }
}
