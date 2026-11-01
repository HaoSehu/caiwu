<?php

declare(strict_types=1);

namespace App\Services\Content\Storage;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * 媒体外链引用替换（settings.item_value 与 content_articles.cover_image）。
 *
 * 迁移（本地↔远程）与存量外链订正（media:storage-rewrite-urls）共用同一套语义：
 * 长键优先、命中即停、不做 str_replace 数组形式。抽成独立类是为了让两条链路
 * 不互相依赖——迁移服务带队列 Job 依赖，外链订正命令只需要这段纯替换逻辑。
 *
 * 做法与 MediaLibraryOrganizer::updateSettingReferences/updateContentArticleReferences 同形（已验收先例）。
 */
class MediaReferenceRewriter
{
    /**
     * 引用替换：settings.item_value 与 content_articles.cover_image。
     *
     * @param  array<string, string>  $pathMap
     */
    public function replaceReferences(array $pathMap): void
    {
        if ($pathMap === []) {
            return;
        }

        $settingRows = DB::table('settings')->select(['group_key', 'item_key', 'item_value'])->get();

        foreach ($settingRows as $settingRow) {
            $currentValue = $settingRow->item_value;
            if (! is_string($currentValue) || ! str_contains($currentValue, '/')) {
                continue;
            }

            $updatedValue = $this->applyPathMap($currentValue, $pathMap);
            if ($updatedValue === $currentValue) {
                continue;
            }

            Setting::setValue((string) $settingRow->group_key, (string) $settingRow->item_key, $updatedValue);
        }

        $articleRows = DB::table('content_articles')->whereNotNull('cover_image')->get(['id', 'cover_image']);

        foreach ($articleRows as $articleRow) {
            $currentValue = (string) ($articleRow->cover_image ?? '');
            if ($currentValue === '' || ! str_contains($currentValue, '/')) {
                continue;
            }

            $updatedValue = $this->applyPathMap($currentValue, $pathMap);
            if ($updatedValue === $currentValue) {
                continue;
            }

            DB::table('content_articles')->where('id', $articleRow->id)->update(['cover_image' => $updatedValue]);
        }
    }

    /**
     * 引用替换的执行体：长键（完整 URL）优先匹配，命中一个立即替换并停止。
     *
     * 不能用 str_replace 数组形式：它顺序执行且前一次替换的结果会被后续键再扫，
     * 而 newUrl 与 oldPath 同含 /media/ 段（互为子串），会产生双重前缀损坏。
     *
     * @param  array<string, string>  $pathMap
     */
    public function applyPathMap(string $value, array $pathMap): string
    {
        foreach ($pathMap as $old => $new) {
            if ($old !== '' && str_contains($value, $old)) {
                return str_replace($old, $new, $value);
            }
        }

        return $value;
    }

    /**
     * 键按长度降序排列：完整 URL（长键）必须先于相对路径（短键）被 applyPathMap 匹配，
     * 否则短键作为完整 URL 的子串先命中，把 URL 尾段替换成完整外链、域名前缀残留成双重前缀。
     *
     * @return array<string, string>
     */
    public function buildPathMap(string $oldPath, string $oldUrl, string $newUrl): array
    {
        $candidates = [];

        foreach ([$oldPath, $oldUrl] as $old) {
            $old = trim($old);
            if ($old !== '' && ! isset($candidates[$old])) {
                $candidates[$old] = $newUrl;
            }
        }

        uksort($candidates, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $candidates;
    }
}
