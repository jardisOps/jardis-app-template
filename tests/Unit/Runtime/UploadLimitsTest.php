<?php

declare(strict_types=1);

namespace Tests\Unit\Runtime;

use PHPUnit\Framework\TestCase;

/**
 * The stack's upload limits come from support/php/60-upload.ini, mounted into
 * conf.d of every PHP container (compose). The test runs inside such a
 * container (`make phpunit`), so it proves the mount, not just the file.
 */
final class UploadLimitsTest extends TestCase
{
    public function testUploadAndPostLimitsAre12M(): void
    {
        self::assertSame('12M', ini_get('upload_max_filesize'));
        self::assertSame('12M', ini_get('post_max_size'));
    }

    public function testIniFileAndMountStayInSync(): void
    {
        $root = dirname(__DIR__, 3);
        $ini = (string) file_get_contents($root . '/support/php/60-upload.ini');
        $compose = (string) file_get_contents($root . '/support/docker-compose.yml');

        self::assertMatchesRegularExpression('/^upload_max_filesize = 12M$/m', $ini);
        self::assertMatchesRegularExpression('/^post_max_size = 12M$/m', $ini);
        // app (fpm), phpcli and worker all read the same file.
        self::assertSame(3, substr_count($compose, './php/60-upload.ini:/usr/local/etc/php/conf.d/60-upload.ini:ro'));
    }
}
