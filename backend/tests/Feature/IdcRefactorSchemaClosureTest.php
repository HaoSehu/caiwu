<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IdcRefactorSchemaClosureTest extends TestCase
{
    public function test_idc_refactor_migrations_close_core_schema_gaps(): void
    {
        $this->markTestSkipped('database/migrations 已删除，当前数据库结构基线改为 database/schema.sql');
    }
}
