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
        foreach (glob($this->root . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->root);
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
}
