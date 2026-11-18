<?php

declare(strict_types=1);

namespace Tests\Unit\App\Console;

use App\Console\DbImport;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * `db:import` without argument against the INSTALLED `vendor/` of this template (SQLite, temp project root):
 * what the lock delivers as package SQL — the access table of `jardissupport/acl` and the token table of
 * `jardissupport/auth` — is created by the generic rule, with no list of packages in `DbImport`.
 */
final class DbImportInstalledPackagesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $vendor = dirname(__DIR__, 4) . '/vendor';
        self::assertDirectoryExists($vendor, 'run `make install` first');

        $this->root = sys_get_temp_dir() . '/dbimport-vendor-' . bin2hex(random_bytes(6));
        mkdir($this->root);
        self::assertTrue(symlink($vendor, $this->root . '/vendor'));
    }

    protected function tearDown(): void
    {
        unlink($this->root . '/vendor');
        rmdir($this->root);
    }

    public function testImportReportsAclAndAuthPackageSqlAndCreatesTheirTables(): void
    {
        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');
        self::assertIsResource($out);
        self::assertIsResource($err);
        $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $exit = (new DbImport($out, $err))($this->root, '', $pdo);

        rewind($out);
        rewind($err);
        $progress = (string) stream_get_contents($out);
        self::assertSame(0, $exit, (string) stream_get_contents($err));
        self::assertStringContainsString('vendor/jardissupport/acl/resources/sql/sqlite.sql', $progress);
        self::assertStringContainsString('vendor/jardissupport/auth/resources/sql/sqlite.sql', $progress);

        $statement = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
        self::assertNotFalse($statement);
        $tables = $statement->fetchAll(PDO::FETCH_COLUMN);
        self::assertContains('acl_entries', $tables);
        self::assertContains('auth_tokens', $tables);
    }
}
