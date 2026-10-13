<?php

declare(strict_types=1);

namespace Tests\Unit\FrontController;

use PHPUnit\Framework\TestCase;

/**
 * The front controller collects `src/Api/{Domain}/versions.php` (written by
 * the Builder on every build) and hands the union to `AppConfig` as
 * `knownVersions`. Header reading and the 400 itself belong to
 * jardiscore/app — these tests only prove the wiring, end to end: the real
 * `public/index.php` runs in a subprocess against a throw-away project root.
 */
final class PublicIndexVersionsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/index-versions-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/public', 0777, true);
        mkdir($this->root . '/src/App', 0777, true);

        $projectRoot = dirname(__DIR__, 3);
        copy($projectRoot . '/public/index.php', $this->root . '/public/index.php');
        symlink($projectRoot . '/vendor', $this->root . '/vendor');

        // A generated bootstrap.php would carry the domain facade under the
        // lcfirst'd domain name; a plain object is enough for the mount.
        file_put_contents($this->root . '/src/App/bootstrap.php', <<<'PHP'
<?php

use JardisCore\Kernel\Bootstrap\BuildDomainKernelFromEnv;

return [
    'kernel' => (new BuildDomainKernelFromEnv())(dirname(__DIR__, 2)),
    'shop' => new stdClass(),
    'crm' => new stdClass(),
];
PHP);

        $this->domain('Shop', ['v2']);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    /** @param list<string>|null $versions null = write no versions.php */
    private function domain(string $name, ?array $versions): void
    {
        $dir = $this->root . '/src/Api/' . $name;
        @mkdir($dir, 0777, true);

        file_put_contents($dir . '/routes.php', <<<'PHP'
<?php

use JardisCore\App\Routes;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;

return function (Routes $routes, object $domain, string $prefix = ''): void {
    $routes->get($prefix . '/' . strtolower(basename(__DIR__)), static function (ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
        $factory = new Psr17Factory();

        return $factory->createResponse(200)->withBody($factory->createStream(
            json_encode(['version' => $request->getAttribute('jardisVersion')], JSON_THROW_ON_ERROR)
        ));
    });
};
PHP);

        if ($versions !== null) {
            file_put_contents($dir . '/versions.php', '<?php return ' . var_export($versions, true) . ';');
        }
    }

    private function writeVersions(string $domain, string $phpBody): void
    {
        file_put_contents($this->root . '/src/Api/' . $domain . '/versions.php', '<?php ' . $phpBody);
    }

    /** @return array{exit: int, body: string} */
    private function request(?string $versionHeader, string $path = '/shop'): array
    {
        $runner = $this->root . '/runner.php';
        file_put_contents($runner, <<<'PHP'
<?php

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = getenv('TEST_PATH');
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['HTTP_HOST'] = 'localhost';

if (getenv('TEST_VERSION') !== false) {
    $_SERVER['HTTP_X_JARDIS_VERSION'] = getenv('TEST_VERSION');
}

require $argv[1];
PHP);

        $env = ['TEST_PATH' => $path] + ($versionHeader === null ? [] : ['TEST_VERSION' => $versionHeader]);
        $process = proc_open(
            [PHP_BINARY, '-d', 'display_errors=0', $runner, $this->root . '/public/index.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->root,
            $env + ['PATH' => (string) getenv('PATH')]
        );
        self::assertIsResource($process);

        $body = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['exit' => proc_close($process), 'body' => $body];
    }

    /** @return array<string, mixed> */
    private function json(string $body): array
    {
        $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    public function testDeclaredVersionReachesTheHandlerAsRequestAttribute(): void
    {
        $result = $this->request('v2');

        self::assertSame(0, $result['exit']);
        self::assertSame(['version' => 'v2'], $this->json($result['body']));
    }

    public function testWithoutHeaderTheBaselineIsServed(): void
    {
        $result = $this->request(null);

        self::assertSame(['version' => ''], $this->json($result['body']));
    }

    public function testUnknownVersionIsRejectedWith400(): void
    {
        $envelope = $this->json($this->request('v9')['body']);

        self::assertSame(400, $envelope['status']);
        self::assertSame('request.versionUnknown', $envelope['data']['fields'][0]['messageKey']);
    }

    public function testBeforeTheFirstBuildNoVersionIsKnownAndTheAppStillRuns(): void
    {
        unlink($this->root . '/src/Api/Shop/versions.php');

        self::assertSame(['version' => ''], $this->json($this->request(null)['body']));
        self::assertSame(400, $this->json($this->request('v2')['body'])['status']);
    }

    public function testListsOfSeveralDomainsAreUnitedWithoutDuplicates(): void
    {
        $this->domain('Crm', ['v2', 'v3']);

        self::assertSame(['version' => 'v2'], $this->json($this->request('v2', '/shop')['body']));
        self::assertSame(['version' => 'v3'], $this->json($this->request('v3', '/crm')['body']));
        self::assertSame(['version' => 'v2'], $this->json($this->request('v2', '/crm')['body']));
        self::assertSame(400, $this->json($this->request('v4', '/crm')['body'])['status']);
    }

    public function testEmptyListIsAllowed(): void
    {
        $this->writeVersions('Shop', 'return [];');

        self::assertSame(['version' => ''], $this->json($this->request(null)['body']));
        self::assertSame(400, $this->json($this->request('v2')['body'])['status']);
    }

    /** @return array<string, array{string}> */
    public static function invalidVersionFiles(): array
    {
        return [
            'not an array' => ["return 'v2';"],
            'map instead of list' => ["return ['a' => 'v2'];"],
            'non-string entry' => ['return [2];'],
            'nothing returned' => ['$x = 1;'],
        ];
    }

    /** @dataProvider invalidVersionFiles */
    public function testInvalidVersionsFileIsAStartupErrorWithoutLeakingDetails(string $phpBody): void
    {
        $this->writeVersions('Shop', $phpBody);

        $result = $this->request('v2');
        $envelope = $this->json($result['body']);

        self::assertSame(1, $result['exit']);
        self::assertSame(500, $envelope['status']);
        self::assertSame(
            'The application could not be started. See the error log.',
            $envelope['errors']['bootstrap']
        );
        self::assertStringNotContainsString($this->root, $result['body']);
    }

    private function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }

        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $child) {
            $this->remove($path . '/' . $child);
        }

        rmdir($path);
    }
}
