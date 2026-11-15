<?php

declare(strict_types=1);

namespace Tests\Unit\App\Console;

use App\Console\DbReset;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class DbResetTest extends TestCase
{
    private string $root;

    /** @var resource */
    private $out;

    /** @var resource */
    private $err;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/dbreset-' . bin2hex(random_bytes(6));
        mkdir($this->root);
        $this->out = $this->memoryStream();
        $this->err = $this->memoryStream();
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    private function remove(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->remove($path . '/' . $entry);
                }
            }
            rmdir($path);

            return;
        }

        unlink($path);
    }

    private function writeNested(string $relative, string $sql): void
    {
        $target = $this->root . '/' . $relative;
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }
        file_put_contents($target, $sql);
    }

    /** Package SQL plus one BC schema (parent/child with a foreign key), sqlite dialect, plus a foreign-dialect file. */
    private function seedProject(): void
    {
        $this->writeNested('vendor/jardisx/demo/resources/sql/sqlite.sql', 'CREATE TABLE IF NOT EXISTS pkg_tokens (id INTEGER);');
        $this->writeNested(
            '.jardis/D/S/B1/Schema.sqlite.sql',
            'CREATE TABLE IF NOT EXISTS b1_parents (id INTEGER PRIMARY KEY);'
            . 'CREATE TABLE IF NOT EXISTS b1_children (id INTEGER PRIMARY KEY, parent_id INTEGER NOT NULL REFERENCES b1_parents (id));',
        );
        $this->writeNested('.jardis/D/S/B1/Schema.mariadb.sql', 'CREATE TABLE foreign_b1 (id INTEGER) ENGINE=InnoDB;');
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }

    /** @return resource */
    private function memoryStream()
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        return $stream;
    }

    /** @return list<string> */
    private function tables(PDO $pdo, string $type = 'table'): array
    {
        $statement = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = ? AND name NOT LIKE 'sqlite!_%' ESCAPE '!' ORDER BY name");
        $statement->execute([$type]);
        /** @var list<string> $names */
        $names = $statement->fetchAll(PDO::FETCH_COLUMN);

        return $names;
    }

    private function scalar(PDO $pdo, string $sql): int
    {
        $statement = $pdo->query($sql);
        self::assertNotFalse($statement);

        return (int) $statement->fetchColumn();
    }

    private function rowCount(PDO $pdo, string $table): int
    {
        return $this->scalar($pdo, 'SELECT COUNT(*) FROM ' . $table);
    }

    private function reset(?PDO $pdo): int
    {
        return (new DbReset($this->out, $this->err))($this->root, $pdo);
    }

    private function progress(): string
    {
        rewind($this->out);

        return (string) stream_get_contents($this->out);
    }

    private function errors(): string
    {
        rewind($this->err);

        return (string) stream_get_contents($this->err);
    }

    public function testDropsEverythingAndSetsUpTheSchemaEmpty(): void
    {
        $this->seedProject();
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE b1_parents (id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE b1_children (id INTEGER PRIMARY KEY, parent_id INTEGER NOT NULL REFERENCES b1_parents (id))');
        $pdo->exec('CREATE TABLE pkg_tokens (id INTEGER)');
        $pdo->exec('CREATE TABLE "stray ""quoted"" table" (id INTEGER)');
        $pdo->exec('CREATE VIEW stray_view AS SELECT id FROM b1_parents');
        $pdo->exec('INSERT INTO b1_parents VALUES (1), (2)');
        $pdo->exec('INSERT INTO b1_children VALUES (10, 1), (11, 2)');
        $pdo->exec('INSERT INTO pkg_tokens VALUES (7)');

        self::assertSame(0, $this->reset($pdo));

        self::assertSame(['b1_children', 'b1_parents', 'pkg_tokens'], $this->tables($pdo));
        self::assertSame([], $this->tables($pdo, 'view'));
        self::assertSame(0, $this->rowCount($pdo, 'b1_parents'));
        self::assertSame(0, $this->rowCount($pdo, 'b1_children'));
        self::assertSame(0, $this->rowCount($pdo, 'pkg_tokens'));
        self::assertStringContainsString('dropped 4 table(s), 1 view(s)', $this->progress());
        self::assertStringContainsString('imported 1 statement(s) from vendor/jardisx/demo/resources/sql/sqlite.sql', $this->progress());
        self::assertSame('', $this->errors());
    }

    public function testRestoresTheForeignKeySwitch(): void
    {
        $this->seedProject();
        $pdo = $this->pdo();

        self::assertSame(0, $this->reset($pdo));
        self::assertSame(1, $this->scalar($pdo, 'PRAGMA foreign_keys'));

        $off = $this->pdo();
        $off->exec('PRAGMA foreign_keys = OFF');
        self::assertSame(0, $this->reset($off));
        self::assertSame(0, $this->scalar($off, 'PRAGMA foreign_keys'));
    }

    public function testEmptyDatabaseIsSetUpFromTheSchema(): void
    {
        $this->seedProject();
        $pdo = $this->pdo();

        self::assertSame(0, $this->reset($pdo));

        self::assertSame(['b1_children', 'b1_parents', 'pkg_tokens'], $this->tables($pdo));
        self::assertStringContainsString('dropped 0 table(s), 0 view(s)', $this->progress());
    }

    public function testNoConnectionFails(): void
    {
        self::assertSame(1, $this->reset(null));
        self::assertStringContainsString('db:reset: no database configured', $this->errors());
    }

    public function testUnsupportedDriverFailsAndTouchesNothing(): void
    {
        $pdo = new class ('sqlite::memory:') extends PDO {
            public function getAttribute(int $attribute): mixed
            {
                return $attribute === PDO::ATTR_DRIVER_NAME ? 'oci' : parent::getAttribute($attribute);
            }
        };
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE keep (id INTEGER)');

        self::assertSame(1, $this->reset($pdo));
        self::assertStringContainsString('db:reset: driver "oci" is not supported', $this->errors());
        self::assertSame(['keep'], $this->tables($pdo));
    }

    public function testFailingDropStopsWithMessageAndKeepsForeignKeysOn(): void
    {
        $this->seedProject();
        $pdo = new class ('sqlite::memory:') extends PDO {
            public function exec(string $statement): int|false
            {
                if (str_starts_with($statement, 'DROP TABLE')) {
                    throw new PDOException('disk I/O error');
                }

                return parent::exec($statement);
            }
        };
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE doomed (id INTEGER)');

        self::assertSame(1, $this->reset($pdo));
        self::assertStringContainsString('db:reset: dropping failed: disk I/O error', $this->errors());
        self::assertSame(1, $this->scalar($pdo, 'PRAGMA foreign_keys'));
        self::assertSame('', $this->progress());
    }

    public function testFailingSchemaImportIsReportedByDbImport(): void
    {
        $this->writeNested('.jardis/D/S/B1/Schema.sqlite.sql', 'CREATE TABLE broken (;');
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE stray (id INTEGER)');

        self::assertSame(1, $this->reset($pdo));
        self::assertStringContainsString('db:import: .jardis/D/S/B1/Schema.sqlite.sql: statement 1 failed', $this->errors());
        self::assertSame([], $this->tables($pdo));
        self::assertStringContainsString('db:reset: the database was emptied, the schema import did not complete', $this->errors());
    }

    public function testNoSchemaFailsBeforeAnyDrop(): void
    {
        // Only a foreign dialect and no package SQL: the import would find nothing.
        $this->writeNested('.jardis/D/S/B1/Schema.mariadb.sql', 'CREATE TABLE foreign_b1 (id INTEGER) ENGINE=InnoDB;');
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE precious (id INTEGER)');
        $pdo->exec('INSERT INTO precious VALUES (1)');

        self::assertSame(1, $this->reset($pdo));

        self::assertStringContainsString('db:import: no schema found for driver "sqlite"', $this->errors());
        self::assertSame(['precious'], $this->tables($pdo));
        self::assertSame(1, $this->rowCount($pdo, 'precious'));
        self::assertSame('', $this->progress());
    }

    public function testSchemaOutsideTheProjectFailsBeforeAnyDrop(): void
    {
        $outside = sys_get_temp_dir() . '/dbreset-outside-' . bin2hex(random_bytes(6)) . '.sql';
        file_put_contents($outside, 'CREATE TABLE evil (id INTEGER);');
        $this->writeNested('.jardis/D/S/B1/Schema.json', '{}');
        symlink($outside, $this->root . '/.jardis/D/S/B1/Schema.sqlite.sql');
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE precious (id INTEGER)');

        try {
            self::assertSame(1, $this->reset($pdo));
        } finally {
            unlink($outside);
        }

        self::assertStringContainsString('resolves outside the project', $this->errors());
        self::assertSame(['precious'], $this->tables($pdo));
    }

    public function testMysqlRestoresThePreviousForeignKeySwitchAndQuotesNames(): void
    {
        $this->writeNested('.jardis/D/S/B1/Schema.mysql.sql', 'CREATE TABLE IF NOT EXISTS b1 (id INT);');
        $pdo = new RecordingServerPdo('mysql');

        self::assertSame(0, $this->reset($pdo));

        self::assertSame([
            'SET FOREIGN_KEY_CHECKS = 0',
            'DROP TABLE IF EXISTS `we``ird`',
            'DROP VIEW IF EXISTS `v1`',
            'SET FOREIGN_KEY_CHECKS = 0', // previous value was 0: restored to 0, not forced to 1
            'CREATE TABLE IF NOT EXISTS b1 (id INT)',
        ], $pdo->statements);
        self::assertStringContainsString('dropped 1 table(s), 1 view(s)', $this->progress());
    }

    public function testMysqlRestoresTheForeignKeySwitchWhenADropFails(): void
    {
        $this->writeNested('.jardis/D/S/B1/Schema.mysql.sql', 'CREATE TABLE IF NOT EXISTS b1 (id INT);');
        $pdo = new RecordingServerPdo('mysql', 'DROP TABLE');

        self::assertSame(1, $this->reset($pdo));

        self::assertStringContainsString('db:reset: dropping failed: boom', $this->errors());
        self::assertSame('SET FOREIGN_KEY_CHECKS = 0', end($pdo->statements));
        self::assertCount(3, $pdo->statements);
    }

    public function testPostgresDropsInOneTransactionWithoutCascadeAndRollsBackOnFailure(): void
    {
        $this->writeNested('.jardis/D/S/B1/Schema.pgsql.sql', 'CREATE TABLE IF NOT EXISTS b1 (id INT);');
        $ok = new RecordingServerPdo('pgsql');
        self::assertSame(0, $this->reset($ok));

        self::assertSame([
            'BEGIN',
            'DROP VIEW IF EXISTS "pub"."v1"',
            'DROP TABLE IF EXISTS "pub"."we""ird"',
            'COMMIT',
            'CREATE TABLE IF NOT EXISTS b1 (id INT)',
        ], $ok->statements);
        foreach ($ok->statements as $statement) {
            self::assertStringNotContainsStringIgnoringCase('CASCADE', $statement);
        }

        $failing = new RecordingServerPdo('pgsql', 'DROP TABLE');
        self::assertSame(1, $this->reset($failing));
        self::assertSame(['BEGIN', 'DROP VIEW IF EXISTS "pub"."v1"', 'DROP TABLE IF EXISTS "pub"."we""ird"', 'ROLLBACK'], $failing->statements);
        self::assertStringContainsString('dropping failed: boom', $this->errors());
    }
}
