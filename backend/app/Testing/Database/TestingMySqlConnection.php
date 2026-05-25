<?php

declare(strict_types=1);

namespace App\Testing\Database;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\MySqlSchemaState;
use Illuminate\Filesystem\Filesystem;

class TestingMySqlConnection extends MySqlConnection
{
    public function getSchemaState(?Filesystem $files = null, ?callable $processFactory = null): MySqlSchemaState
    {
        return new TestingMySqlSchemaState($this, $files, $processFactory);
    }
}
