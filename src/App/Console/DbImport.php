<?php

declare(strict_types=1);

namespace App\Console;

use JardisSupport\Contract\DbConnection\ConnectionPoolInterface;
use PDO;
use PDOException;

/**
 * Imports SQL schema into the database the kernel resolved from ENV.
 *
 * `bin/console db:import` without argument sets up the whole database: first
 * the SQL of every installed Jardis package (`vendor/jardis<kind>/<package>/resources/sql/<driver>.sql`,
 * read, never copied), then every `.jardis/<domain>/<subdomain>/<bc>/Schema.<dialect>.sql` of the
 * connection's driver (one per directory), each in sorted order. Rule over the
 * installed state — no list of packages, BCs or tables.
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

    /**
     * @param string $file project-relative schema file; empty = set up the whole database
     *                     (package SQL from vendor first, then every BC schema of the driver)
     */
    public function __invoke(string $projectRoot, string $file, ConnectionPoolInterface|PDO|null $connection): int
    {
        $path = null;
        if ($file !== '') {
            $path = $this->resolve($projectRoot, $file);
            if ($path === null) {
                return 1;
            }
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

        if ($path !== null) {
            $mismatch = $this->dialectMismatch($path, $driver);
            if ($mismatch !== null) {
                return $this->fail($mismatch);
            }

            return $this->importFile($pdo, $path, $file);
        }

        return $this->importAll($pdo, $projectRoot, $driver);
    }

    private function importAll(PDO $pdo, string $projectRoot, string $driver): int
    {
        $root = realpath($projectRoot);
        if ($root === false) {
            return $this->fail('project root not found');
        }

        try {
            $found = $this->discover($root, $driver);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage());
        }
        if ($found === null) {
            return $this->fail(sprintf('driver "%s" has no schema dialect', $driver));
        }

        foreach ($found['skipped'] as $directory) {
            fwrite($this->out, sprintf(
                "skipped %s: no %s schema\n",
                substr($directory, strlen($root) + 1),
                $driver,
            ));
        }

        if ($found['files'] === []) {
            return $this->fail(sprintf(
                'no schema found for driver "%s" (vendor/jardis*/<package>/resources/sql/%s.sql,'
                . ' .jardis/.../Schema.<dialect>.sql)',
                $driver,
                $driver,
            ));
        }

        foreach ($found['files'] as $file) {
            $result = $this->importFile($pdo, $file['path'], $file['label']);
            if ($result !== 0) {
                return $result;
            }
        }

        return 0;
    }

    /**
     * Package SQL first (one file per package: the one named after the PDO
     * driver), then the BC schemas below `.jardis/` (one file per directory:
     * the first matching dialect token), both sorted. Every hit is resolved
     * with realpath() and must stay inside the project (package SQL: inside
     * its package directory, which itself may be a symlink, e.g. a Composer
     * path repository). `skipped` lists directories that carry a `Schema.json`
     * but no schema of the driver's dialect.
     *
     * @return array{files: list<array{path: string, label: string}>, skipped: list<string>}|null
     *         null when the driver has no known dialect
     * @throws \RuntimeException on an unreadable directory or a path that escapes its boundary
     */
    private function discover(string $root, string $driver): ?array
    {
        $tokens = array_keys(array_filter(
            self::DIALECTS,
            static fn (array $drivers): bool => in_array($driver, $drivers, true),
        ));
        if ($tokens === []) {
            return null;
        }

        $files = $this->packageFiles($root, $driver);

        $perDirectory = [];
        $withSchemaJson = [];
        $jardis = $root . '/.jardis';
        if (is_dir($jardis)) {
            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($jardis, \FilesystemIterator::SKIP_DOTS),
                );
                foreach ($iterator as $entry) {
                    if (!$entry instanceof \SplFileInfo || !($entry->isFile() || $entry->isLink())) {
                        continue;
                    }
                    if ($entry->getFilename() === 'Schema.json') {
                        $withSchemaJson[$entry->getPath()] = true;
                        continue;
                    }
                    $matched = preg_match('/^Schema\.([A-Za-z]+)\.sql$/', $entry->getFilename(), $m) === 1;
                    $position = $matched ? array_search(strtolower($m[1]), $tokens, true) : false;
                    if ($position === false) {
                        continue;
                    }
                    $real = realpath($entry->getPathname());
                    if ($real === false || !str_starts_with($real, $root . '/') || !is_file($real)) {
                        throw new \RuntimeException(sprintf(
                            'schema file resolves outside the project: %s',
                            $entry->getPathname(),
                        ));
                    }
                    $directory = $entry->getPath();
                    if (!isset($perDirectory[$directory]) || $position < $perDirectory[$directory]['position']) {
                        $perDirectory[$directory] = [
                            'position' => $position,
                            'path' => $real,
                            'label' => substr($real, strlen($root) + 1),
                        ];
                    }
                }
            } catch (\UnexpectedValueException $e) {
                throw new \RuntimeException(
                    sprintf('cannot read directory below .jardis: %s', $e->getMessage()),
                    0,
                    $e,
                );
            }
        }

        $schemas = array_map(
            static fn (array $hit): array => ['path' => $hit['path'], 'label' => $hit['label']],
            array_values($perDirectory),
        );
        usort($schemas, static fn (array $a, array $b): int => $a['label'] <=> $b['label']);
        $skipped = array_keys(array_diff_key($withSchemaJson, $perDirectory));
        sort($skipped);

        return ['files' => array_merge($files, $schemas), 'skipped' => $skipped];
    }

    /**
     * @return list<array{path: string, label: string}> SQL files of the driver, one per installed package that
     *                                                  ships one, sorted by project-relative label
     * @throws \RuntimeException when a package SQL file is not a regular file inside its package directory
     */
    private function packageFiles(string $root, string $driver): array
    {
        $files = [];
        $vendor = $root . '/vendor';
        if (!is_dir($vendor)) {
            return [];
        }
        foreach (scandir($vendor) ?: [] as $kind) {
            if (!str_starts_with($kind, 'jardis') || !is_dir($vendor . '/' . $kind)) {
                continue;
            }
            foreach (scandir($vendor . '/' . $kind) ?: [] as $package) {
                $packageDir = $vendor . '/' . $kind . '/' . $package;
                $candidate = $packageDir . '/resources/sql/' . $driver . '.sql';
                if ($package === '.' || $package === '..' || (!is_file($candidate) && !is_link($candidate))) {
                    continue;
                }
                $realPackage = realpath($packageDir);
                $real = realpath($candidate);
                if (
                    $realPackage === false
                    || $real === false
                    || !str_starts_with($real, $realPackage . '/')
                    || !is_file($real)
                ) {
                    throw new \RuntimeException(sprintf(
                        'package SQL is not a regular file inside its package: %s',
                        $candidate,
                    ));
                }
                $files[] = ['path' => $real, 'label' => substr($candidate, strlen($root) + 1)];
            }
        }

        usort($files, static fn (array $a, array $b): int => $a['label'] <=> $b['label']);

        return $files;
    }

    private function importFile(PDO $pdo, string $path, string $label): int
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            return $this->fail(sprintf('cannot read "%s"', $label));
        }

        $statements = $this->split($sql);
        foreach ($statements as $index => $statement) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                return $this->fail(sprintf('%s: statement %d failed: %s', $label, $index + 1, $e->getMessage()));
            }
        }

        fwrite($this->out, sprintf("imported %d statement(s) from %s\n", count($statements), $label));

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
