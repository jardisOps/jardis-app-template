<?php

declare(strict_types=1);

namespace Tests\Unit\App\Console;

use App\Console\DbImport;
use PDO;
use PHPUnit\Framework\TestCase;

final class DbImportTest extends TestCase
{
    private string $root;

    /** @var resource */
    private $out;

    /** @var resource */
    private $err;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/dbimport-' . bin2hex(random_bytes(6));
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

    /** Fake package SQL plus two BC schemas, each in the sqlite dialect, plus foreign-dialect files. */
    private function seedProject(): void
    {
        $this->writeNested('vendor/jardisx/demo/resources/sql/sqlite.sql', 'CREATE TABLE IF NOT EXISTS pkg_tokens (id INTEGER);');
        $this->writeNested('vendor/jardisx/demo/resources/sql/mysql.sql', 'CREATE TABLE foreign_pkg (id INTEGER) ENGINE=InnoDB;');
        $this->writeNested('.jardis/D/S/B1/Schema.sqlite.sql', 'CREATE TABLE IF NOT EXISTS b1_items (id INTEGER, pkg_ref INTEGER);');
        $this->writeNested('.jardis/D/S/B1/Schema.mariadb.sql', 'CREATE TABLE foreign_b1 (id INTEGER) ENGINE=InnoDB;');
        $this->writeNested('.jardis/D/S/B2/Schema.sqlite.sql', 'CREATE TABLE IF NOT EXISTS b2_items (id INTEGER);');
        $this->writeNested('.jardis/D/S/B2/Schema.postgres.sql', 'CREATE TABLE foreign_b2 (id SERIAL);');
    }

    private function progress(): string
    {
        rewind($this->out);

        return (string) stream_get_contents($this->out);
    }

    /** @return list<string> */
    private function tables(PDO $pdo): array
    {
        /** @var list<string> $names */
        $names = $this->column($pdo, "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");

        return $names;
    }

    private function pdo(): PDO
    {
        return new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    /** @return resource */
    private function memoryStream()
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        return $stream;
    }

    /** @return array<int, mixed> */
    private function column(PDO $pdo, string $sql): array
    {
        $statement = $pdo->query($sql);
        self::assertNotFalse($statement);

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    private function write(string $name, string $sql): void
    {
        file_put_contents($this->root . '/' . $name, $sql);
    }

    private function import(string $file, ?PDO $pdo): int
    {
        return (new DbImport($this->out, $this->err))($this->root, $file, $pdo);
    }

    private function errors(): string
    {
        rewind($this->err);

        return (string) stream_get_contents($this->err);
    }

    public function testImportsStatementsAndKeepsSemicolonInStringLiteral(): void
    {
        $pdo = $this->pdo();
        $this->write('s.sql', "-- header; comment\nCREATE TABLE IF NOT EXISTS a (id INTEGER, note TEXT);\n"
            . "CREATE TABLE IF NOT EXISTS b (id INTEGER); /* x; y */\nINSERT INTO a VALUES (1, 'x;y');\n");

        self::assertSame(0, $this->import('s.sql', $pdo));
        self::assertSame(['a', 'b'], $this->column($pdo, "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name"));
        self::assertSame(['x;y'], $this->column($pdo, 'SELECT note FROM a'));
    }

    public function testSecondRunIsIdempotent(): void
    {
        $pdo = $this->pdo();
        $this->write('s.sql', 'CREATE TABLE IF NOT EXISTS a (id INTEGER);');

        $first = $this->import('s.sql', $pdo);
        $second = $this->import('s.sql', $pdo);

        self::assertSame([0, 0], [$first, $second]);
    }

    public function testMissingFileFails(): void
    {
        self::assertSame(1, $this->import('nope.sql', $this->pdo()));
        self::assertStringContainsString('not found', $this->errors());
    }

    public function testPathOutsideProjectFails(): void
    {
        $outside = tempnam(sys_get_temp_dir(), 'out');
        self::assertIsString($outside);
        file_put_contents($outside, 'CREATE TABLE z (id INTEGER);');
        $pdo = $this->pdo();

        try {
            self::assertSame(1, $this->import($outside, $pdo));
            self::assertStringContainsString('outside project', $this->errors());
            self::assertSame(1, $this->import('../x.sql', $pdo));
        } finally {
            unlink($outside);
        }
    }

    public function testSqlErrorNamesStatementNumber(): void
    {
        $this->write('s.sql', 'CREATE TABLE a (id INTEGER); NOT VALID SQL; CREATE TABLE c (id INTEGER);');

        self::assertSame(1, $this->import('s.sql', $this->pdo()));
        self::assertStringContainsString('statement 2', $this->errors());
    }

    public function testNullConnectionFails(): void
    {
        $this->write('s.sql', 'CREATE TABLE a (id INTEGER);');

        self::assertSame(1, $this->import('s.sql', null));
        self::assertStringContainsString('no database configured', $this->errors());
    }

    public function testDialectMismatchFails(): void
    {
        $this->write('Schema.postgres.sql', 'CREATE TABLE a (id INTEGER);');

        self::assertSame(1, $this->import('Schema.postgres.sql', $this->pdo()));
        self::assertStringContainsString('dialect mismatch', $this->errors());
    }

    public function testWithoutFileTheWholeDatabaseIsSetUpPackageSqlFirst(): void
    {
        $this->seedProject();
        $pdo = $this->pdo();

        self::assertSame(0, $this->import('', $pdo));
        self::assertSame(['b1_items', 'b2_items', 'pkg_tokens'], $this->tables($pdo));

        $lines = explode("\n", trim($this->progress()));
        self::assertCount(3, $lines);
        self::assertStringContainsString('vendor/jardisx/demo/resources/sql/sqlite.sql', $lines[0]);
        self::assertStringContainsString('.jardis/D/S/B1/Schema.sqlite.sql', $lines[1]);
        self::assertStringContainsString('.jardis/D/S/B2/Schema.sqlite.sql', $lines[2]);
    }

    public function testSecondGenericRunChangesNothing(): void
    {
        $this->seedProject();
        $pdo = $this->pdo();
        $first = $this->import('', $pdo);
        $pdo->exec('INSERT INTO b1_items (id) VALUES (7)');

        $second = $this->import('', $pdo);
        self::assertSame([0, 0], [$first, $second]);
        self::assertSame(['7'], array_map('strval', $this->column($pdo, 'SELECT id FROM b1_items')));
        self::assertSame(['b1_items', 'b2_items', 'pkg_tokens'], $this->tables($pdo));
    }

    public function testForeignDialectFilesAreNotImported(): void
    {
        $this->seedProject();
        $pdo = $this->pdo();

        $this->import('', $pdo);

        self::assertNotContains('foreign_pkg', $this->tables($pdo));
        self::assertNotContains('foreign_b1', $this->tables($pdo));
        self::assertNotContains('foreign_b2', $this->tables($pdo));
        self::assertSame('', $this->errors());
    }

    /** SQLite-backed PDO that reports another driver name and records instead of executing. */
    private function pdoReporting(string $driver): PDO
    {
        return new class ($driver) extends PDO {
            /** @var list<string> */
            public array $statements = [];

            public function __construct(private readonly string $reported)
            {
                parent::__construct('sqlite::memory:');
            }

            public function getAttribute(int $attribute): mixed
            {
                return $attribute === PDO::ATTR_DRIVER_NAME ? $this->reported : parent::getAttribute($attribute);
            }

            public function exec(string $statement): int
            {
                $this->statements[] = $statement;

                return 0;
            }
        };
    }

    /** @return list<string> */
    private function progressLines(): array
    {
        return explode("\n", trim($this->progress()));
    }

    public function testOneFilePerDirectoryWhenSeveralDialectTokensMatchTheDriver(): void
    {
        $this->writeNested('.jardis/D/S/B/Schema.mysql.sql', 'CREATE TABLE b (id INTEGER);');
        $this->writeNested('.jardis/D/S/B/Schema.mariadb.sql', 'CREATE TABLE a (id INTEGER);');
        $this->writeNested('vendor/jardisx/demo/resources/sql/mysql.sql', 'CREATE TABLE p (id INTEGER);');
        $pdo = $this->pdoReporting('mysql');

        self::assertSame(0, $this->import('', $pdo));
        self::assertSame(['CREATE TABLE p (id INTEGER)', 'CREATE TABLE a (id INTEGER)'], $pdo->statements); // @phpstan-ignore property.notFound
        $lines = $this->progressLines();
        self::assertCount(2, $lines);
        self::assertStringContainsString('vendor/jardisx/demo/resources/sql/mysql.sql', $lines[0]);
        self::assertStringContainsString('.jardis/D/S/B/Schema.mariadb.sql', $lines[1]);
    }

    public function testPgsqlDriverPicksPgsqlPackageSqlAndEitherPostgresToken(): void
    {
        $this->writeNested('vendor/jardisx/demo/resources/sql/pgsql.sql', 'CREATE TABLE pkg (id INTEGER);');
        $this->writeNested('vendor/jardisx/demo/resources/sql/sqlite.sql', 'CREATE TABLE foreign_pkg (id INTEGER);');
        $this->writeNested('.jardis/D/S/A/Schema.postgres.sql', 'CREATE TABLE a (id INTEGER);');
        $this->writeNested('.jardis/D/S/B/Schema.pgsql.sql', 'CREATE TABLE b (id INTEGER);');
        $this->writeNested('.jardis/D/S/C/Schema.mysql.sql', 'CREATE TABLE foreign_c (id INTEGER);');
        $pdo = $this->pdoReporting('pgsql');

        self::assertSame(0, $this->import('', $pdo));
        self::assertSame(
            [
                'vendor/jardisx/demo/resources/sql/pgsql.sql',
                '.jardis/D/S/A/Schema.postgres.sql',
                '.jardis/D/S/B/Schema.pgsql.sql',
            ],
            array_map(static fn (string $line): string => substr($line, (int) strrpos($line, ' from ') + 6), $this->progressLines()),
        );
    }

    public function testFilesAreImportedInSortedOrderRegardlessOfCreationOrder(): void
    {
        $this->writeNested('.jardis/D/S/B2/Schema.sqlite.sql', 'CREATE TABLE IF NOT EXISTS b2 (id INTEGER);');
        $this->writeNested('.jardis/D/S/B1/Schema.sqlite.sql', 'CREATE TABLE IF NOT EXISTS b1 (id INTEGER);');
        $this->writeNested('vendor/jardisz/zeta/resources/sql/sqlite.sql', 'CREATE TABLE IF NOT EXISTS zeta (id INTEGER);');
        $this->writeNested('vendor/jardisa/alpha/resources/sql/sqlite.sql', 'CREATE TABLE IF NOT EXISTS alpha (id INTEGER);');

        self::assertSame(0, $this->import('', $this->pdo()));
        $lines = $this->progressLines();
        self::assertStringContainsString('jardisa/alpha', $lines[0]);
        self::assertStringContainsString('jardisz/zeta', $lines[1]);
        self::assertStringContainsString('B1/', $lines[2]);
        self::assertStringContainsString('B2/', $lines[3]);
    }

    public function testSchemaSymlinkPointingOutsideTheProjectFailsWithItsPath(): void
    {
        $outside = tempnam(sys_get_temp_dir(), 'out');
        self::assertIsString($outside);
        file_put_contents($outside, 'CREATE TABLE evil (id INTEGER);');
        mkdir($this->root . '/.jardis/D/S/B', 0777, true);
        symlink($outside, $this->root . '/.jardis/D/S/B/Schema.sqlite.sql');
        $pdo = $this->pdo();

        try {
            self::assertSame(1, $this->import('', $pdo));
            self::assertStringContainsString('outside the project', $this->errors());
            self::assertStringContainsString('.jardis/D/S/B/Schema.sqlite.sql', $this->errors());
            self::assertSame([], $this->tables($pdo));
        } finally {
            unlink($outside);
        }
    }

    public function testPackageSqlSymlinkedOutsideItsPackageFails(): void
    {
        $outside = tempnam(sys_get_temp_dir(), 'out');
        self::assertIsString($outside);
        file_put_contents($outside, 'CREATE TABLE evil (id INTEGER);');
        mkdir($this->root . '/vendor/jardisx/demo/resources/sql', 0777, true);
        symlink($outside, $this->root . '/vendor/jardisx/demo/resources/sql/sqlite.sql');

        try {
            self::assertSame(1, $this->import('', $this->pdo()));
            self::assertStringContainsString('not a regular file inside its package', $this->errors());
        } finally {
            unlink($outside);
        }
    }

    public function testSymlinkedPackageDirectoryIsAccepted(): void
    {
        $package = sys_get_temp_dir() . '/dbimport-pkg-' . bin2hex(random_bytes(6));
        mkdir($package . '/resources/sql', 0777, true);
        file_put_contents($package . '/resources/sql/sqlite.sql', 'CREATE TABLE IF NOT EXISTS linked_pkg (id INTEGER);');
        mkdir($this->root . '/vendor/jardisx', 0777, true);
        symlink($package, $this->root . '/vendor/jardisx/linked');
        $pdo = $this->pdo();

        try {
            self::assertSame(0, $this->import('', $pdo));
            self::assertSame(['linked_pkg'], $this->tables($pdo));
            self::assertStringContainsString('vendor/jardisx/linked/resources/sql/sqlite.sql', $this->progress());
        } finally {
            $this->remove($package);
        }
    }

    public function testProjectPathWithGlobCharactersStillFindsSchemas(): void
    {
        $this->remove($this->root);
        $this->root = sys_get_temp_dir() . '/dbimport-[x]*?{a,b}-' . bin2hex(random_bytes(4));
        mkdir($this->root);
        $this->writeNested('vendor/jardisx/demo/resources/sql/sqlite.sql', 'CREATE TABLE IF NOT EXISTS pkg (id INTEGER);');
        $pdo = $this->pdo();

        self::assertSame(0, $this->import('', $pdo));
        self::assertSame(['pkg'], $this->tables($pdo));
    }

    public function testDirectoryWithSchemaJsonButNoSchemaOfTheDialectIsReportedAsSkipped(): void
    {
        $this->writeNested('.jardis/D/S/B1/Schema.json', '{}');
        $this->writeNested('.jardis/D/S/B1/Schema.postgres.sql', 'CREATE TABLE foreign_b1 (id INTEGER);');
        $this->writeNested('.jardis/D/S/B2/Schema.json', '{}');
        $this->writeNested('.jardis/D/S/B2/Schema.sqlite.sql', 'CREATE TABLE IF NOT EXISTS b2 (id INTEGER);');
        $pdo = $this->pdo();

        self::assertSame(0, $this->import('', $pdo));
        self::assertStringContainsString("skipped .jardis/D/S/B1: no sqlite schema\n", $this->progress());
        self::assertStringNotContainsString('skipped .jardis/D/S/B2', $this->progress());
        self::assertSame(['b2'], $this->tables($pdo));
    }

    public function testUnreadableDirectoryIsReportedNotThrown(): void
    {
        $this->writeNested('.jardis/D/S/B/Schema.sqlite.sql', 'CREATE TABLE IF NOT EXISTS b (id INTEGER);');
        $this->writeNested('.jardis/D/Locked/x.txt', 'x');
        chmod($this->root . '/.jardis/D/Locked', 0000);

        try {
            if (is_readable($this->root . '/.jardis/D/Locked')) {
                self::markTestSkipped('directory permissions are not enforced for this user');
            }
            self::assertSame(1, $this->import('', $this->pdo()));
            self::assertStringContainsString('cannot read directory below .jardis', $this->errors());
        } finally {
            chmod($this->root . '/.jardis/D/Locked', 0755);
        }
    }

    public function testGenericRunWithoutAnyFileFailsWithPlainMessage(): void
    {
        self::assertSame(1, $this->import('', $this->pdo()));
        self::assertStringContainsString('no schema found for driver "sqlite"', $this->errors());
    }

    public function testNonIdempotentFileInGenericRunFailsLoudlyWithItsPath(): void
    {
        $this->writeNested('.jardis/D/S/B/Schema.sqlite.sql', 'CREATE TABLE plain (id INTEGER);');
        $pdo = $this->pdo();
        self::assertSame(0, $this->import('', $pdo));

        self::assertSame(1, $this->import('', $pdo));
        self::assertStringContainsString('Schema.sqlite.sql: statement 1 failed', $this->errors());
    }

    public function testSingleFileModeIgnoresTheRestOfTheProject(): void
    {
        $this->seedProject();
        $pdo = $this->pdo();

        self::assertSame(0, $this->import('.jardis/D/S/B2/Schema.sqlite.sql', $pdo));
        self::assertSame(['b2_items'], $this->tables($pdo));
    }
}
