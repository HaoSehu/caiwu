<?php

declare(strict_types=1);

use App\Constants\CouponStatus;
use App\Models\ContentArticle;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 软删除后释放全局唯一键（D-01 / D-02 / D-03）。
 *
 * 覆盖三个真实事故点：文章 slug 唯一键、优惠券 code 唯一键、用户 referral_code
 * 唯一键。它们都启用了 SoftDeletes，应用层唯一性检查看不到软删行，若数据库层
 * 不释放就必然在「删除后重建同值」时抛 SQLSTATE 23000。
 */
class SoftDeleteUniqueKeyReleaseTest extends TestCase
{
    use DatabaseTransactions;

    private function makeArticle(string $slug): ContentArticle
    {
        return ContentArticle::query()->create([
            'content_type' => ContentArticle::TYPE_NOTICE,
            'title' => '标题'.$slug,
            'slug' => $slug,
            'content' => '正文',
            'status' => ContentArticle::STATUS_DRAFT,
        ]);
    }

    public function test_releases_content_article_slug_on_soft_delete(): void
    {
        $article = $this->makeArticle('release-slug-case');
        $article->delete();

        // 文章模型自带 deleting 钩子，占位格式为 {slug}_deleted_{id}（含原始 slug，可与原值互转）
        $this->assertSame('release-slug-case_deleted_'.$article->id, (string) ContentArticle::withTrashed()->find($article->id)->slug);

        // 重建同 slug 不应撞唯一键
        $rebuilt = $this->makeArticle('release-slug-case');
        $this->assertSame('release-slug-case', (string) $rebuilt->slug);
        $this->assertNotSame($article->id, $rebuilt->id);
    }

    public function test_released_placeholder_fits_narrow_referral_code_column(): void
    {
        $user = User::query()->create([
            'email' => 'narrow-'.uniqid().'@example.test',
            'phone' => '138'.substr((string) random_int(10000000, 99999999), 0, 8),
            'password' => 'secret-123456',
            'status' => 1,
        ]);
        $user->forceFill(['referral_code' => 'ABC'.random_int(100, 999)])->save();
        $user->delete();

        $released = (string) User::withTrashed()->find($user->id)->referral_code;
        $this->assertSame('del-'.$user->id, $released);
        $this->assertLessThanOrEqual(24, mb_strlen($released));
    }

    public function test_releases_coupon_code_on_soft_delete(): void
    {
        $code = 'SD'.random_int(100000, 999999);
        $coupon = Coupon::query()->create([
            'name' => '软删券'.$code,
            'code' => $code,
            'distribution_type' => 'manual',
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'status' => CouponStatus::ACTIVE,
            'discount_value' => '10.00',
        ]);
        $coupon->delete();

        $this->assertSame('del-'.$coupon->id, (string) Coupon::withTrashed()->find($coupon->id)->code);

        $rebuilt = Coupon::query()->create([
            'name' => '重建同名券'.$code,
            'code' => $code,
            'distribution_type' => 'manual',
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'status' => CouponStatus::ACTIVE,
            'discount_value' => '10.00',
        ]);
        $this->assertSame($code, (string) $rebuilt->code);
    }
}
