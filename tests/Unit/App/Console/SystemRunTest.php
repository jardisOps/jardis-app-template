<?php

declare(strict_types=1);

namespace Tests\Unit\App\Console;

use App\Console\SystemRun;
use Closure;
use JardisCore\App\App;
use JardisCore\App\Exception\SystemRunRefused;
use JardisSupport\Contract\Kernel\DomainKernelInterface;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * The console command only finds the one generated system.php, calls it and
 * prints the answer. What the run does (sign in, switch tenant, call the door)
 * belongs to jardiscore/app SignedInRun and the generated file — the system.php
 * files here are throw-away stand-ins with the same closure contract.
 */
final class SystemRunTest extends TestCase
{
    private string $root;

    /** @var resource */
    private $out;

    /** @var resource */
    private $err;

    private int $built = 0;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/systemrun-' . bin2hex(random_bytes(6));
        mkdir($this->root);
        $this->out = fopen('php://memory', 'w+') ?: throw new \RuntimeException('no stream');
        $this->err = fopen('php://memory', 'w+') ?: throw new \RuntimeException('no stream');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/src/Api/*/system.php') ?: [] as $file) {
            unlink($file);
            rmdir(dirname($file));
        }
        @rmdir($this->root . '/src/Api');
        @rmdir($this->root . '/src');
        rmdir($this->root);
    }

    private function systemFile(string $domain, string $body): void
    {
        mkdir($this->root . '/src/Api/' . $domain, 0777, true);
        file_put_contents($this->root . '/src/Api/' . $domain . '/system.php', "<?php\n\ndeclare(strict_types=1);\n\n" . $body);
    }

    /** @param list<string> $arguments */
    private function runCommand(array $arguments): int
    {
        $build = function (): array {
            $this->built++;

            return [
                'app' => (new \ReflectionClass(App::class))->newInstanceWithoutConstructor(),
                'kernel' => $this->createStub(DomainKernelInterface::class),
            ];
        };

        return (new SystemRun($this->out, $this->err))($this->root, $arguments, Closure::fromCallable($build));
    }

    /** @param resource $stream */
    private function read($stream): string
    {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    public function testPrintsStatusAndBodyAndPassesTheArgumentsToTheRun(): void
    {
        $this->systemFile('Shop', <<<'PHP'
return static fn ($app, $kernel, string $tenant, string $method, string $path, string $body = '') =>
    new Nyholm\Psr7\Response(200, [], json_encode([$tenant, $method, $path, $body], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
PHP);

        $code = $this->runCommand(['acme', 'post', '/orders', '{"a":1}']);

        self::assertSame(0, $code);
        self::assertSame("200\n[\"acme\",\"POST\",\"/orders\",\"{\\\"a\\\":1}\"]\n", $this->read($this->out));
        self::assertSame('', $this->read($this->err));
    }

    public function testStatusFromFourHundredOnIsExitOneButStillPrinted(): void
    {
        $this->systemFile('Shop', "return static fn () => new Nyholm\\Psr7\\Response(403, [], '{\"status\":403}');");

        self::assertSame(1, $this->runCommand(['acme', 'GET', '/orders']));
        self::assertSame("403\n{\"status\":403}\n", $this->read($this->out));
    }

    public function testARefusedRunIsExitOneWithTheReasonAndNoBody(): void
    {
        $this->systemFile('Shop', "return static fn () => throw new JardisCore\\App\\Exception\\SystemRunRefused('sign in failed', 401);");

        self::assertSame(1, $this->runCommand(['acme', 'GET', '/orders']));
        self::assertSame('', $this->read($this->out));
        self::assertStringContainsString((new SystemRunRefused('sign in failed', 401))->getMessage(), $this->read($this->err));
    }

    public function testMissingSystemFileIsASetupErrorAndNothingBoots(): void
    {
        self::assertSame(2, $this->runCommand(['acme', 'GET', '/orders']));
        self::assertStringContainsString('no system run found', $this->read($this->err));
        self::assertSame(0, $this->built);
    }

    public function testTwoSystemFilesAreAmbiguous(): void
    {
        $this->systemFile('Shop', 'return static fn () => new Nyholm\Psr7\Response(200);');
        $this->systemFile('Billing', 'return static fn () => new Nyholm\Psr7\Response(200);');

        self::assertSame(2, $this->runCommand(['acme', 'GET', '/orders']));
        self::assertStringContainsString('ambiguous', $this->read($this->err));
        self::assertSame(0, $this->built);
    }

    public function testWrongArgumentCountAndEmptyTenantAreUsageErrors(): void
    {
        $this->systemFile('Shop', 'return static fn () => new Nyholm\Psr7\Response(200);');

        self::assertSame(2, $this->runCommand(['acme', 'GET']));
        self::assertSame(2, $this->runCommand(['acme', 'GET', '/a', '{}', 'extra']));
        self::assertSame(2, $this->runCommand(['', 'GET', '/a']));
        self::assertSame(0, $this->built);
    }
}
