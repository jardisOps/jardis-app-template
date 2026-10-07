<?php

declare(strict_types=1);

namespace App\Console;

use JardisSupport\Contract\DbConnection\ConnectionPoolInterface;
use PDO;
use PDOException;

/**
 * Imports a SQL schema file into the database the kernel resolved from ENV.
 *
 * `bin/console db:import <file>` — the file path is relative to the project
 * root and must stay inside it. The script is split into statements (a
 * semicolon inside a string literal, a quoted identifier or a comment does not
 * end one) and each statement is executed on its own; the first failure stops
 * the import. No migration or DROP behaviour: idempotence is the script's job
 * (`CREATE ... IF NOT EXISTS`). Limitation: no support for PostgreSQL
 * dollar-quoted bodies or MySQL backslash escapes inside string literals.
 */
final class DbImport
{
    /** Schema.<dialect>.sql file-name token => PDO driver names it targets. */
    private const DIALECTS = [
        'mariadb' => ['mysql'],
        'mysql' => ['mysql'],
        'postgres' => ['pgsql'],
        'pgsql' => ['pgsql'],
        'sqlite' => ['sqlite'],
    ];

    /**
     * @param resource $out receives progress and the result line
     * @param resource $err receives every failure message
     */
    public function __construct(private $out = STDOUT, private $err = STDERR)
    {
    }

    public function __invoke(string $projectRoot, string $file, ConnectionPoolInterface|PDO|null $connection): int
    {
        $path = $this->resolve($projectRoot, $file);
        if ($path === null) {
            return 1;
        }

        if ($connection === null) {
            return $this->fail('no database configured (DB_DRIVER/DB_HOST)');
        }

        try {
            $pdo = $connection instanceof ConnectionPoolInterface ? $connection->getWriter()->pdo() : $connection;
            $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (\Throwable $e) {
            return $this->fail(sprintf('database connection failed: %s', $e->getMessage()));
        }

        $mismatch = $this->dialectMismatch($path, $driver);
        if ($mismatch !== null) {
            return $this->fail($mismatch);
        }

        $sql = file_get_contents($path);
        if ($sql === false) {
            return $this->fail(sprintf('cannot read "%s"', $file));
        }

        $statements = $this->split($sql);
        foreach ($statements as $index => $statement) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                return $this->fail(sprintf('statement %d failed: %s', $index + 1, $e->getMessage()));
            }
        }

        fwrite($this->out, sprintf("imported %d statement(s) from %s\n", count($statements), $file));

        return 0;
    }

    private function resolve(string $projectRoot, string $file): ?string
    {
        $root = realpath($projectRoot);
        if ($root === false) {
            $this->fail('project root not found');

            return null;
        }

        $candidate = str_starts_with($file, '/') ? $file : $root . '/' . $file;
        $path = realpath($candidate);
        if ($path === false || !is_file($path)) {
            $this->fail(sprintf('file not found: %s', $file));

            return null;
        }

        if (!str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            $this->fail(sprintf('file is outside project: %s', $file));

            return null;
        }

        return $path;
    }

    private function dialectMismatch(string $path, string $driver): ?string
    {
        if (preg_match('/^Schema\.([A-Za-z]+)\.sql$/', basename($path), $m) !== 1) {
            return null;
        }

        $dialect = strtolower($m[1]);
        if (!isset(self::DIALECTS[$dialect]) || in_array($driver, self::DIALECTS[$dialect], true)) {
            return null;
        }

        return sprintf('dialect mismatch: file is "%s", connection driver is "%s"', $dialect, $driver);
    }

    /** @return list<string> */
    private function split(string $sql): array
    {
        $statements = [];
        $current = '';
        $length = strlen($sql);
        $quote = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($quote !== null) {
                $current .= $char;
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '-' && $next === '-') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end;
                $current .= "\n";
                continue;
            }

            if ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $length : $end + 1;
                $current .= ' ';
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $current .= $char;
                continue;
            }

            if ($char === ';') {
                $this->push($statements, $current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $this->push($statements, $current);

        return $statements;
    }

    /** @param list<string> $statements */
    private function push(array &$statements, string $statement): void
    {
        $statement = trim($statement);
        if ($statement !== '') {
            $statements[] = $statement;
        }
    }

    private function fail(string $message): int
    {
        fwrite($this->err, sprintf("db:import: %s\n", $message));

        return 1;
    }
}
