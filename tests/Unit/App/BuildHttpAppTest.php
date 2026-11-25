<?php

declare(strict_types=1);

namespace Tests\Unit\App;

use App\BuildHttpApp;
use JardisCore\App\App;
use JardisSupport\Contract\Auth\AuthServicesInterface;
use JardisSupport\Contract\Auth\SessionManagerInterface;
use JardisSupport\Contract\Kernel\DomainKernelInterface;
use PHPUnit\Framework\TestCase;

/**
 * The application is built once for the front controller and `bin/console
 * system`. Held here: the session revocation handed to a mounted routes.php
 * (positional fourth argument, `null` without auth()), the kernel handed as
 * positional fifth argument, and that a routes.php with three or four
 * parameters still mounts (PHP ignores the surplus argument).
 */
final class BuildHttpAppTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/buildhttpapp-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/src/App', 0777, true);
        mkdir($this->root . '/src/Api/Shop', 0777, true);
        $GLOBALS['__buildHttpAppSeen'] = null;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__buildHttpAppKernel'], $GLOBALS['__buildHttpAppSeen']);
        foreach (['src/App/bootstrap.php', 'src/Api/Shop/routes.php'] as $file) {
            @unlink($this->root . '/' . $file);
        }
        foreach (['src/App', 'src/Api/Shop', 'src/Api', 'src'] as $dir) {
            @rmdir($this->root . '/' . $dir);
        }
        @rmdir($this->root);
    }

    private function kernel(?SessionManagerInterface $sessions): DomainKernelInterface
    {
        $auth = null;
        if ($sessions !== null) {
            $auth = $this->createStub(AuthServicesInterface::class);
            $auth->method('sessions')->willReturn($sessions);
        }

        $kernel = $this->createStub(DomainKernelInterface::class);
        $kernel->method('auth')->willReturn($auth);

        return $kernel;
    }

    private function files(string $routesSignature): void
    {
        file_put_contents($this->root . '/src/App/bootstrap.php', <<<'PHP'
<?php

return ['kernel' => $GLOBALS['__buildHttpAppKernel'], 'shop' => new stdClass()];
PHP);
        file_put_contents($this->root . '/src/Api/Shop/routes.php', <<<PHP
<?php

return function ({$routesSignature}): void {
    \$GLOBALS['__buildHttpAppSeen'] = func_get_args();
};
PHP);
    }

    public function testRoutesAreMountedPositionallyWithTheSessionRevocation(): void
    {
        $sessions = $this->createMock(SessionManagerInterface::class);
        $sessions->expects(self::once())->method('invalidateByReference')->with('ref-1');
        $GLOBALS['__buildHttpAppKernel'] = $this->kernel($sessions);
        $this->files('JardisCore\App\Routes $routes, object $domain, string $prefix = \'\', ?Closure $revokeSession = null');

        $built = (new BuildHttpApp())($this->root);

        self::assertInstanceOf(App::class, $built['app']);
        self::assertSame($GLOBALS['__buildHttpAppKernel'], $built['kernel']);
        $seen = $GLOBALS['__buildHttpAppSeen'];
        self::assertIsArray($seen);
        self::assertSame('', $seen[2]);
        self::assertInstanceOf(\Closure::class, $seen[3]);
        $seen[3]('ref-1');
    }

    public function testWithoutAuthTheRevocationIsNull(): void
    {
        $GLOBALS['__buildHttpAppKernel'] = $this->kernel(null);
        $this->files('JardisCore\App\Routes $routes, object $domain, string $prefix = \'\', ?Closure $revokeSession = null');

        (new BuildHttpApp())($this->root);

        $seen = $GLOBALS['__buildHttpAppSeen'];
        self::assertIsArray($seen);
        // Five since the kernel became the fifth positional argument; a file
        // declaring four parameters ignores it, the call itself passes five.
        self::assertCount(5, $seen);
        self::assertNull($seen[3]);
        self::assertSame($GLOBALS['__buildHttpAppKernel'], $seen[4]);
    }

    public function testARoutesFileWithoutTheRevocationParameterStillMounts(): void
    {
        $GLOBALS['__buildHttpAppKernel'] = $this->kernel(null);
        $this->files('JardisCore\App\Routes $routes, object $domain, string $prefix = \'\'');

        $built = (new BuildHttpApp())($this->root);

        self::assertInstanceOf(App::class, $built['app']);
        self::assertIsArray($GLOBALS['__buildHttpAppSeen']);
    }

    public function testARoutesFileWithTheKernelParameterGetsTheAppKernel(): void
    {
        $sessions = $this->createStub(SessionManagerInterface::class);
        $GLOBALS['__buildHttpAppKernel'] = $this->kernel($sessions);
        $this->files('JardisCore\App\Routes $routes, object $domain, string $prefix = \'\', ?Closure $revokeSession = null, ?\JardisSupport\Contract\Kernel\DomainKernelInterface $kernel = null');

        $built = (new BuildHttpApp())($this->root);

        $seen = $GLOBALS['__buildHttpAppSeen'];
        self::assertIsArray($seen);
        self::assertCount(5, $seen);
        self::assertInstanceOf(\Closure::class, $seen[3]);
        self::assertSame($built['kernel'], $seen[4]);
    }

    public function testWithoutAuthTheFourthIsNullAndTheFifthIsTheKernel(): void
    {
        $GLOBALS['__buildHttpAppKernel'] = $this->kernel(null);
        $this->files('JardisCore\App\Routes $routes, object $domain, string $prefix = \'\', ?Closure $revokeSession = null, ?\JardisSupport\Contract\Kernel\DomainKernelInterface $kernel = null');

        $built = (new BuildHttpApp())($this->root);

        $seen = $GLOBALS['__buildHttpAppSeen'];
        self::assertIsArray($seen);
        self::assertNull($seen[3]);
        self::assertSame($built['kernel'], $seen[4]);
    }
}
