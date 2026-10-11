<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\ContentArticle;
use App\Models\ContentTag;
use App\Models\Role;
use App\Services\Content\ContentArticleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * M8 标签管理端 API：鉴权/权限/CRUD/重名/删除解绑守卫。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class ContentTagCrudTest extends TestCase
{
    use DatabaseTransactions;

    private function makeAdmin(array $permissions = ['content.list', 'content.manage']): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '管理员',
            'status' => 1,
            'role_id' => $role->id,
        ]);
    }

    private function makeTag(string $name = '帮助中心', int $sortOrder = 0): ContentTag
    {
        return ContentTag::query()->create([
            'name' => $name.uniqid(),
            'sort_order' => $sortOrder,
        ]);
    }

    public function test_requires_admin_authentication(): void
    {
        $this->getJson('/api/v2/admin/content-tags')
            ->assertStatus(401);
    }

    public function test_requires_permission_for_read(): void
    {
        $admin = $this->makeAdmin(permissions: []);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v2/admin/content-tags')
            ->assertStatus(403)
            ->assertJson(fn (AssertableJson $json) => $json->where('code', 40300)->etc());
    }

    public function test_requires_permission_for_write(): void
    {
        $admin = $this->makeAdmin(permissions: ['content.list']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v2/admin/content-tags', ['name' => '只读岗建标签'])
            ->assertStatus(403)
            ->assertJson(fn (AssertableJson $json) => $json->where('code', 40300)->etc());
    }

    public function test_index_lists_tags_with_contract_fields(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);
        $tag = $this->makeTag();

        $this->getJson('/api/v2/admin/content-tags')
            ->assertStatus(200)
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.list', fn ($list) => collect($list)->contains(
                    fn (array $item) => (int) $item['id'] === (int) $tag->id
                        && $item['name'] === (string) $tag->name
                        && isset($item['usage_count'], $item['sort_order'])
                ))
                ->etc());
    }

    public function test_store_creates_tag(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $name = '建标签'.uniqid();
        $this->postJson('/api/v2/admin/content-tags', [
            'name' => $name,
            'sort_order' => 3,
        ])
            ->assertStatus(200)
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.name', $name)
                ->where('data.sort_order', 3)
                ->etc());

        $this->assertDatabaseHas('content_tags', ['name' => $name]);
    }

    public function test_store_rejects_duplicate_name_with_422(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);
        $existing = $this->makeTag();

        $this->postJson('/api/v2/admin/content-tags', ['name' => (string) $existing->name])
            ->assertStatus(422)
            ->assertJson(fn (AssertableJson $json) => $json->where('code', 42200)->etc());
    }

    public function test_update_renames_tag(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);
        $tag = $this->makeTag();

        $name = '改名标签'.uniqid();
        $this->putJson('/api/v2/admin/content-tags/'.$tag->id, [
            'name' => $name,
            'sort_order' => 9,
        ])
            ->assertStatus(200)
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.name', $name)
                ->where('data.sort_order', 9)
                ->etc());
    }

    public function test_destroy_rejects_tag_bound_to_articles(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);
        $tag = $this->makeTag();
        $articleId = $this->insertArticle();

        DB::table('content_article_tags')->insert([
            'article_id' => $articleId,
            'tag_id' => $tag->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteJson('/api/v2/admin/content-tags/'.$tag->id)
            ->assertStatus(422)
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('code', 42200)
                ->etc());

        $this->assertDatabaseHas('content_tags', ['id' => $tag->id]);
    }

    public function test_destroy_deletes_unbound_tag(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);
        $tag = $this->makeTag();

        $this->deleteJson('/api/v2/admin/content-tags/'.$tag->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('content_tags', ['id' => $tag->id]);
    }

    /**
     * 经文章服务创建一篇已发布帮助文章（保证 payload 完整性），返回 id。
     */
    private function insertArticle(): int
    {
        $categoryId = (int) DB::table('content_categories')->insertGetId([
            'content_type' => 'help',
            'name' => '测试分类'.uniqid(),
            'slug' => 'cat-'.uniqid(),
            'status' => 1,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var ContentArticle $article */
        $article = app(ContentArticleService::class)->create([
            'content_type' => 'help',
            'category_id' => $categoryId,
            'title' => '解绑守卫文章'.uniqid(),
            'content' => '正文内容',
            'status' => 1,
        ], adminId: 1);

        return (int) $article->id;
    }
}
