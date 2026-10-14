<?php

declare(strict_types=1);

namespace Tests\Unit\FrontController;

use PHPUnit\Framework\TestCase;

/**
 * The front controller registers the `middleware` entry of the generated
 * `src/App/bootstrap.php` as the app-wide PSR-15 pipeline (before the route
 * mount, inside the bootstrap boundary) and starts the app with the session
 * guard check on. What a middleware does and what the guard check rejects
 * belongs to jardiscore/app — these tests only prove the wiring, end to end:
 * the real `public/index.php` runs in a subprocess against a throw-away
 * project root, in the style of {@see PublicIndexVersionsTest}.
 */
final class PublicIndexMiddlewareTest extends TestCase
{
    private const STARTUP_MESSAGE = 'The application could not be started. See the error log.';

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/index-middleware-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/public', 0777, true);

        $projectRoot = dirname(__DIR__, 3);
        copy($projectRoot . '/public/index.php', $this->root . '/public/index.php');
        symlink($projectRoot . '/vendor', $this->root . '/vendor');
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    /**
     * Writes src/App/bootstrap.php. `$middlewareEntry` is PHP source for the
     * "middleware" entry, `null` leaves the entry out.
     */
    private function bootstrap(?string $middlewareEntry): void
    {
        @mkdir($this->root . '/src/App', 0777, true);

        $entry = $middlewareEntry === null ? '' : "    'middleware' => " . $middlewareEntry . ",\n";

        file_put_contents($this->root . '/src/App/bootstrap.php', <<<PHP
<?php

use JardisCore\Kernel\Bootstrap\BuildDomainKernelFromEnv;

require_once __DIR__ . '/Recorder.php';

\$logFile = dirname(__DIR__, 2) . '/pipeline.log';

return [
    'kernel' => (new BuildDomainKernelFromEnv())(dirname(__DIR__, 2)),
    'shop' => new stdClass(),
{$entry}];
PHP);

        // A project middleware as the Builder-generated pipeline would carry it:
        // it logs the call and marks the request, the handler echoes the marks.
        file_put_contents($this->root . '/src/App/Recorder.php', <<<'PHP'
<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class Recorder implements MiddlewareInterface
{
    public function __construct(private readonly string $label, private readonly string $logFile)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        file_put_contents($this->logFile, $this->label . ' ' . $request->getUri()->getPath() . "\n", FILE_APPEND);

        $seen = (string) $request->getAttribute('recorded', '');

        return $handler->handle($request->withAttribute('recorded', $seen === '' ? $this->label : $seen . ',' . $this->label));
    }
}
PHP);
    }

    /** Writes src/Api/Shop/routes.php with one route `/shop`; the handler echoes the `recorded` attribute. */
    private function routes(bool $anonymous): void
    {
        $dir = $this->root . '/src/Api/Shop';
        @mkdir($dir, 0777, true);

        $register = $anonymous ? '$routes->anonymous()' : '$routes';

        file_put_contents($dir . '/routes.php', <<<PHP
<?php

use JardisCore\App\Routes;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

return function (Routes \$routes, object \$domain, string \$prefix = ''): void {
    {$register}->get(\$prefix . '/shop', static function (ServerRequestInterface \$request): ResponseInterface {
        \$factory = new Psr17Factory();

        return \$factory->createResponse(200)->withBody(\$factory->createStream(
            json_encode(['recorded' => \$request->getAttribute('recorded', '')], JSON_THROW_ON_ERROR)
        ));
    });
};
PHP);
    }

    /** @return array{exit: int, body: string, log: list<string>} */
    private function request(string $path): array
    {
        $runner = $this->root . '/runner.php';
        file_put_contents($runner, <<<'PHP'
<?php

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = getenv('TEST_PATH');
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['HTTP_HOST'] = 'localhost';

require $argv[1];
PHP);

        $process = proc_open(
            [PHP_BINARY, '-d', 'display_errors=0', $runner, $this->root . '/public/index.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->root,
            ['TEST_PATH' => $path, 'PATH' => (string) getenv('PATH')]
        );
        self::assertIsResource($process);

        $body = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $logFile = $this->root . '/pipeline.log';
        $log = is_file($logFile) ? file($logFile, FILE_IGNORE_NEW_LINES) : [];
        @unlink($logFile);

        return ['exit' => proc_close($process), 'body' => $body, 'log' => $log === false ? [] : $log];
    }

    /** @return array<string, mixed> */
    private function json(string $body): array
    {
        $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /** @param array{exit: int, body: string, log: list<string>} $result */
    private function assertStartupError(array $result): void
    {
        $envelope = $this->json($result['body']);

        self::assertSame(1, $result['exit']);
        self::assertSame(500, $envelope['status']);
        self::assertSame(self::STARTUP_MESSAGE, $envelope['errors']['bootstrap']);
        self::assertStringNotContainsString($this->root, $result['body']);
        self::assertStringNotContainsString('Stack trace', $result['body']);
    }

    // (a) Mount

    public function testEmptyPipelineMountsTheDomainRouteAndHealthStaysUp(): void
    {
        $this->bootstrap('[]');
        $this->routes(anonymous: true);

        $route = $this->request('/shop');
        $health = $this->request('/health');

        self::assertSame(0, $route['exit']);
        self::assertSame(['recorded' => ''], $this->json($route['body']));
        self::assertSame(200, $this->json($health['body'])['status']);
    }

    // (b) Pipeline

    public function testPipelineRunsInListOrderAroundTheDomainRoute(): void
    {
        $this->bootstrap("[new Recorder('first', \$logFile), new Recorder('second', \$logFile)]");
        $this->routes(anonymous: true);

        $result = $this->request('/shop');

        self::assertSame(['recorded' => 'first,second'], $this->json($result['body']));
        self::assertSame(['first /shop', 'second /shop'], $result['log']);
    }

    public function testForeignMiddlewareAlsoRunsOnTheAnonymousHealthRoute(): void
    {
        $this->bootstrap("[new Recorder('first', \$logFile)]");
        $this->routes(anonymous: true);

        $result = $this->request('/health');

        self::assertSame(200, $this->json($result['body'])['status']);
        self::assertSame(['first /health'], $result['log']);
    }

    // (c) 500 path

    public function testMiddlewareEntryOfTheWrongShapeIsAStartupError(): void
    {
        $this->bootstrap("'nope'");
        $this->routes(anonymous: true);

        $this->assertStartupError($this->request('/shop'));
    }

    public function testMiddlewareEntryWithAForeignObjectIsAStartupError(): void
    {
        $this->bootstrap('[new stdClass()]');
        $this->routes(anonymous: true);

        $this->assertStartupError($this->request('/shop'));
    }

    // (d) Session switch

    public function testWithoutSessionMiddlewareANonAnonymousRouteFailsTheStart(): void
    {
        $this->bootstrap('[]');
        $this->routes(anonymous: false);

        $this->assertStartupError($this->request('/shop'));
    }

    public function testTheSameSetupWithAnAnonymousRouteAnswers(): void
    {
        $this->bootstrap('[]');
        $this->routes(anonymous: true);

        $result = $this->request('/shop');

        self::assertSame(0, $result['exit']);
        self::assertSame(['recorded' => ''], $this->json($result['body']));
    }

    // (e) Before the first build

    public function testBeforeTheFirstBuildTheAppStartsWithoutPipelineAndHealthAnswers(): void
    {
        // No src/App/bootstrap.php, no src/Api/*/routes.php: the front controller's own default branch.
        $result = $this->request('/health');

        self::assertSame(0, $result['exit']);
        self::assertSame(200, $this->json($result['body'])['status']);
    }

    public function testBootstrapWithoutMiddlewareEntryRunsWithAnEmptyPipeline(): void
    {
        $this->bootstrap(null);
        $this->routes(anonymous: true);

        self::assertSame(['recorded' => ''], $this->json($this->request('/shop')['body']));
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
