<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Upgrade;

use App\Support\Upgrade\MigrationLinter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 迁移 lint：命名规范与结构变更幂等守卫。
 */
class MigrationLinterTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'migration-lint-'.bin2hex(random_bytes(6));
        mkdir($this->root, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root.'/*.php') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->root);

        parent::tearDown();
    }

    #[Test]
    public function flags_structure_change_without_guard(): void
    {
        file_put_contents(
            $this->root.'/2026_10_21_000001_add_column.php',
            "<?php\nreturn new class { public function up(): void { Schema::table('t', fn (\$t) => \$t->string('x')); } };\n",
        );

        $violations = (new MigrationLinter)->lint($this->root, $this->root.'/exempt.json');

        $this->assertCount(1, $violations);
        $this->assertStringContainsString('守卫', $violations[0]['violations'][0]);
    }

    #[Test]
    public function accepts_structure_change_with_guard(): void
    {
        file_put_contents(
            $this->root.'/2026_10_21_000001_add_column.php',
            "<?php\nif (! Schema::hasColumn('t', 'x')) { Schema::table('t', fn (\$t) => \$t->string('x')); }\n",
        );

        $this->assertSame([], (new MigrationLinter)->lint($this->root, $this->root.'/exempt.json'));
    }

    #[Test]
    public function flags_bad_filename(): void
    {
        file_put_contents($this->root.'/bad-name.php', "<?php\n");

        $violations = (new MigrationLinter)->lint($this->root, $this->root.'/exempt.json');

        $this->assertCount(1, $violations);
        $this->assertStringContainsString('文件名', $violations[0]['violations'][0]);
    }

    #[Test]
    public function exemption_silences_violation(): void
    {
        file_put_contents(
            $this->root.'/2026_10_21_000001_add_column.php',
            "<?php\nSchema::table('t', fn (\$t) => \$t->string('x'));\n",
        );
        $exempt = $this->root.'/exempt.json';
        file_put_contents($exempt, json_encode([
            'exemptions' => [
                ['file' => '2026_10_21_000001_add_column.php', 'reason' => '已执行', 'recorded_at' => '2026-10-21'],
            ],
        ], JSON_UNESCAPED_UNICODE));

        $this->assertSame([], (new MigrationLinter)->lint($this->root, $exempt));
    }

    #[Test]
    public function real_repository_migration_lint_passes(): void
    {
        $violations = (new MigrationLinter)->lint(
            database_path('migrations'),
            MigrationLinter::defaultExemptPath(),
        );

        $this->assertSame([], $violations, '存在未豁免的迁移违规');
    }
}
