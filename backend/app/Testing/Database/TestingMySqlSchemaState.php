<?php

declare(strict_types=1);

namespace App\Testing\Database;

use Illuminate\Database\Schema\MySqlSchemaState;
use Symfony\Component\Process\Exception\ProcessFailedException;

class TestingMySqlSchemaState extends MySqlSchemaState
{
    public function load($path)
    {
        try {
            parent::load($path);
        } catch (ProcessFailedException $exception) {
            if (! $this->shouldFallbackToPdo($exception)) {
                throw $exception;
            }

            ($this->output)('out', "mysql cli unavailable, loading schema via PDO: {$path}".PHP_EOL);

            $this->loadSchemaViaPdo((string) $path);
        }
    }

    private function shouldFallbackToPdo(ProcessFailedException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        if (! str_contains($message, 'mysql')) {
            return false;
        }

        foreach ([
            'is not recognized as an internal or external command',
            'command not found',
            'no such file or directory',
            'the system cannot find the file specified',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function loadSchemaViaPdo(string $path): void
    {
        $contents = $this->files->get($path);

        foreach ($this->splitStatements($contents) as $statement) {
            $statement = trim($statement);

            if ($statement === '') {
                continue;
            }

            $this->connection->unprepared($statement);
        }
    }

    /**
     * Split a MySQL schema dump into executable statements.
     *
     * @return list<string>
     */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $inLineComment = false;
        $inBlockComment = false;
        $length = strlen($sql);

        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];
            $next = $index + 1 < $length ? $sql[$index + 1] : null;
            $prev = $index > 0 ? $sql[$index - 1] : null;

            if ($inLineComment) {
                $buffer .= $char;

                if ($char === "\n") {
                    $inLineComment = false;
                }

                continue;
            }

            if ($inBlockComment) {
                $buffer .= $char;

                if ($char === '*' && $next === '/') {
                    $buffer .= '/';
                    $index++;
                    $inBlockComment = false;
                }

                continue;
            }

            if ($quote !== null) {
                $buffer .= $char;

                if (($quote === '\'' || $quote === '"') && $char === '\\' && $next !== null) {
                    $buffer .= $next;
                    $index++;
                    continue;
                }

                if ($char === $quote && $prev !== '\\') {
                    $quote = null;
                }

                continue;
            }

            if (($char === '\'' || $char === '"' || $char === '`')) {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === '#' || ($char === '-' && $next === '-' && preg_match('/\s/', (string) ($sql[$index + 2] ?? '')) === 1)) {
                $inLineComment = true;
                $buffer .= $char;
                continue;
            }

            if ($char === '/' && $next === '*') {
                $inBlockComment = true;
                $buffer .= '/*';
                $index++;
                continue;
            }

            if ($char === ';') {
                $statements[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }
}
