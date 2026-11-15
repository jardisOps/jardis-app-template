<?php

declare(strict_types=1);

namespace Tests\Unit\App\Console;

use PDO;
use PDOException;

/**
 * A recording stand-in for a server connection: reports $driver, answers the catalog
 * queries of DbReset with one table (`we"ird` / `we``ird`) and one view `v1`, records
 * every executed statement and never executes anything.
 */
final class RecordingServerPdo extends PDO
{
    /** @var list<string> */
    public array $statements = [];

    private bool $transaction = false;

    public function __construct(private string $driver, private ?string $failOn = null)
    {
        parent::__construct('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    public function getAttribute(int $attribute): mixed
    {
        return $attribute === PDO::ATTR_DRIVER_NAME ? $this->driver : parent::getAttribute($attribute);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        $name = $this->driver === 'mysql' ? 'we`ird' : 'we"ird';
        $answer = match (true) {
            str_contains($query, 'DATABASE()') && !str_contains($query, 'information_schema') => "SELECT 'app', 1",
            str_contains($query, '@@FOREIGN_KEY_CHECKS') => 'SELECT 0, 1',
            str_contains($query, 'current_schema(), 1') => "SELECT 'pub', 1",
            default => $this->driver === 'pgsql'
                ? "SELECT 'VIEW', 'v1' UNION ALL SELECT 'TABLE', '" . $name . "'"
                : "SELECT 'TABLE', '" . $name . "' UNION ALL SELECT 'VIEW', 'v1'",
        };

        return parent::query($answer);
    }

    public function exec(string $statement): int
    {
        $this->statements[] = $statement;
        if ($this->failOn !== null && str_starts_with($statement, $this->failOn)) {
            throw new PDOException('boom');
        }

        return 0;
    }

    public function beginTransaction(): bool
    {
        $this->statements[] = 'BEGIN';
        $this->transaction = true;

        return true;
    }

    public function commit(): bool
    {
        $this->statements[] = 'COMMIT';
        $this->transaction = false;

        return true;
    }

    public function rollBack(): bool
    {
        $this->statements[] = 'ROLLBACK';
        $this->transaction = false;

        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }
}
