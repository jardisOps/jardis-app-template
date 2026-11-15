<?php

declare(strict_types=1);

namespace App\Console;

use JardisSupport\Contract\DbConnection\ConnectionPoolInterface;
use PDO;

/**
 * Empties the database the kernel resolved from ENV and sets it up again from the schema.
 *
 * `bin/console db:reset` drops every table and view of the connection's own database
 * (SQLite: the main database, MySQL/MariaDB: the current database, PostgreSQL: the
 * current schema — extension members and partitions of a dropped parent are left to
 * the database), then runs exactly `db:import` without argument (package SQL, then
 * all BC schemas — App\Console\DbImport, reused, not copied). ALL DATA IS LOST; there
 * is no confirmation — the caller decides.
 *
 * Nothing is dropped unless the import is certain to find a schema: driver and schema
 * discovery (App\Console\DbImport::canImportAll) are checked before the first DROP.
 *
 * Foreign keys: SQLite `PRAGMA foreign_keys` and MySQL `FOREIGN_KEY_CHECKS` are switched
 * off around the drops and set back to their previous value. PostgreSQL drops without
 * `CASCADE` inside one transaction (dependents first, all tables in one statement), so
 * an object of another schema that depends on one of ours makes the reset fail and roll
 * back instead of being dropped. MySQL/MariaDB DDL commits implicitly: a failing drop
 * leaves the earlier drops in place. No driver other than sqlite, mysql and pgsql is touched.
 */
final class DbReset
{
    /**
     * @param resource $out receives progress and the result line
     * @param resource $err receives every failure message
     */
    public function __construct(private $out = STDOUT, private $err = STDERR)
    {
    }

    public function __invoke(string $projectRoot, ConnectionPoolInterface|PDO|null $connection): int
    {
        if ($connection === null) {
            return $this->fail('no database configured (DB_DRIVER/DB_HOST)');
        }

        try {
            $pdo = $connection instanceof ConnectionPoolInterface ? $connection->getWriter()->pdo() : $connection;
            $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (\Throwable $e) {
            return $this->fail(sprintf('database connection failed: %s', $e->getMessage()));
        }

        $drop = match ($driver) {
            'sqlite' => $this->dropSqlite(...),
            'mysql' => $this->dropMysql(...),
            'pgsql' => $this->dropPgsql(...),
            default => null,
        };
        if ($drop === null) {
            return $this->fail(sprintf('driver "%s" is not supported (sqlite, mysql, pgsql)', $driver));
        }

        $import = new DbImport($this->out, $this->err);
        if (!$import->canImportAll($projectRoot, $driver)) {
            return 1;
        }

        try {
            $objects = $drop($pdo);
        } catch (\Throwable $e) {
            return $this->fail(sprintf('dropping failed: %s', $e->getMessage()));
        }

        $views = count(array_filter($objects, static fn (string $kind): bool => $kind !== 'TABLE'));
        fwrite($this->out, sprintf(
            "dropped %d table(s), %d view(s)\n",
            count($objects) - $views,
            $views,
        ));

        $result = $import($projectRoot, '', $connection);
        if ($result !== 0) {
            $this->fail('the database was emptied, the schema import did not complete');
        }

        return $result;
    }

    /** @return list<string> kind of every dropped object */
    private function dropSqlite(PDO $pdo): array
    {
        $foreignKeys = $pdo->query('PRAGMA foreign_keys');
        $wasOn = $foreignKeys !== false && (int) $foreignKeys->fetchColumn() === 1;
        $pdo->exec('PRAGMA foreign_keys = OFF');

        try {
            // type DESC: views ('view') before tables ('table'); sqlite_* are internal.
            $rows = $this->rows($pdo, "SELECT upper(type), name FROM sqlite_master"
                . " WHERE type IN ('table', 'view') AND substr(name, 1, 7) <> 'sqlite_' ORDER BY type DESC");

            return $this->drop($pdo, $rows, '"', '', false);
        } finally {
            $pdo->exec('PRAGMA foreign_keys = ' . ($wasOn ? 'ON' : 'OFF'));
        }
    }

    /** @return list<string> */
    private function dropMysql(PDO $pdo): array
    {
        if (($this->rows($pdo, 'SELECT DATABASE(), 1')[0][0] ?? null) === null) {
            throw new \RuntimeException('no database selected');
        }

        $previous = (int) ($this->rows($pdo, 'SELECT @@FOREIGN_KEY_CHECKS, 1')[0][0] ?? 1);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $rows = $this->rows($pdo, "SELECT IF(TABLE_TYPE = 'VIEW', 'VIEW', 'TABLE'), TABLE_NAME"
                . ' FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_TYPE DESC');

            return $this->drop($pdo, $rows, '`', '', false);
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = ' . $previous);
        }
    }

    /**
     * One transaction, no CASCADE: views, materialized views and foreign tables go first
     * (newest first, so a dependent precedes what it reads), then every table in a single
     * statement, which lets tables that reference each other go together.
     *
     * @return list<string>
     */
    private function dropPgsql(PDO $pdo): array
    {
        $schema = $this->rows($pdo, 'SELECT current_schema(), 1')[0][0] ?? null;
        if ($schema === null) {
            throw new \RuntimeException('no schema selected');
        }

        $rows = $this->rows($pdo, "SELECT CASE c.relkind WHEN 'v' THEN 'VIEW' WHEN 'm' THEN 'MATERIALIZED VIEW'"
            . " WHEN 'f' THEN 'FOREIGN TABLE' ELSE 'TABLE' END, c.relname"
            . ' FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace'
            . " WHERE n.nspname = current_schema() AND c.relkind IN ('r', 'p', 'v', 'm', 'f')"
            . ' AND NOT c.relispartition'
            . " AND NOT EXISTS (SELECT 1 FROM pg_depend d WHERE d.classid = 'pg_class'::regclass"
            . " AND d.objid = c.oid AND d.deptype = 'e') ORDER BY (c.relkind IN ('r', 'p')), c.oid DESC");

        $pdo->beginTransaction();
        try {
            $kinds = $this->drop($pdo, $rows, '"', '"' . str_replace('"', '""', $schema) . '".', true);
            $pdo->commit();

            return $kinds;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * @param list<array{0: string, 1: string}> $rows kind and name of each object, dependents first
     * @param string $qualifier already quoted schema prefix, or ''
     * @param bool $batchTables drop all TABLE rows in one statement (after the other kinds)
     * @return list<string> kind of every object dropped
     */
    private function drop(PDO $pdo, array $rows, string $quote, string $qualifier, bool $batchTables): array
    {
        $kinds = [];
        $tables = [];
        foreach ($rows as [$kind, $name]) {
            $identifier = $qualifier . $quote . str_replace($quote, $quote . $quote, $name) . $quote;
            $kinds[] = $kind;
            if ($batchTables && $kind === 'TABLE') {
                $tables[] = $identifier;
                continue;
            }
            $pdo->exec(sprintf('DROP %s IF EXISTS %s', $kind, $identifier));
        }
        if ($tables !== []) {
            $pdo->exec('DROP TABLE IF EXISTS ' . implode(', ', $tables));
        }

        return $kinds;
    }

    /** @return list<array{0: string, 1: string}> */
    private function rows(PDO $pdo, string $sql): array
    {
        $statement = $pdo->query($sql);
        if ($statement === false) {
            throw new \RuntimeException('query failed: ' . $sql);
        }

        /** @var list<array{0: string, 1: string}> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_NUM);

        return $rows;
    }

    private function fail(string $message): int
    {
        fwrite($this->err, sprintf("db:reset: %s\n", $message));

        return 1;
    }
}
