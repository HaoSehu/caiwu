<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 表注释补齐迁移（2026_10_25_120000）的落地结果：
 * 79 张业务表必须全部带中文表注释，避免再出现「表名能看懂、用途靠猜」的情况。
 */
class DatabaseTableCommentCoverageTest extends TestCase
{
    #[Test]
    public function every_base_table_has_a_chinese_comment(): void
    {
        $rows = DB::select("SELECT TABLE_NAME, TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");

        $this->assertNotEmpty($rows, '当前库应有业务表');

        $missing = [];
        foreach ($rows as $row) {
            $comment = trim((string) $row->TABLE_COMMENT);

            if ($comment === '') {
                $missing[] = $row->TABLE_NAME.'（空注释）';

                continue;
            }

            if (preg_match('/\p{Han}/u', $comment) !== 1) {
                $missing[] = $row->TABLE_NAME.'（非中文：'.$comment.'）';
            }
        }

        $this->assertSame([], $missing, '以下表缺少中文表注释：'.implode('、', $missing));
    }
}
