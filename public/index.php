<?php

/**
 * Front controller — hand-written, never generated.
 *
 * The Builder writes src/App/bootstrap.php on the first build and never
 * touches it again. Until then this file runs on its own, which is what makes
 * a freshly cloned template answer without any configuration.
 */

declare(strict_types=1);

use JardisCore\App\App;
use JardisCore\App\Config\AppConfig;
use JardisCore\App\Routes;
use JardisCore\Kernel\Bootstrap\BuildDomainKernelFromEnv;
use JardisSupport\Contract\Kernel\DomainKernelInterface;
use Nyholm\Psr7\Factory\Psr17Factory;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$bootstrap = $root . '/src/App/bootstrap.php';

/**
 * Everything up to a usable kernel runs inside this boundary. The framework
 * brings its own error handling, but only from `new App(...)` onwards — a
 * missing config/env/.env.database or a bootstrap.php that returns something
 * unexpected would otherwise put a full stack trace into the HTTP response,
 * since display_errors is On in the dev profile.
 *
 * `BuildDomainKernelFromEnv` takes the project root and derives
 * `config/env/` itself (projekt-layout-konvention) — the generated
 * bootstrap.php calls it the same way, from wherever it lives under src/, so
 * no path needs re-pointing after the first build.
 */
try {
    /** @var array<string, mixed> $app */
    $app = is_file($bootstrap)
        ? require $bootstrap
        : ['kernel' => (new BuildDomainKernelFromEnv())($root)];

    $kernel = $app['kernel'] ?? null;

    if (!$kernel instanceof DomainKernelInterface) {
        throw new RuntimeException(sprintf(
            '%s must return an array carrying a "kernel" entry.',
            $bootstrap
        ));
    }
} catch (Throwable $e) {
    error_log('[bootstrap] ' . $e->getMessage());

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 500,
        'data' => new stdClass(),
        'errors' => ['bootstrap' => 'The application could not be started. See the error log.'],
        'meta' => new stdClass(),
    ], JSON_THROW_ON_ERROR);

    exit(1);
}

$routes = new Routes(new Psr17Factory());
$routes->health('/health');

/**
 * Domain routes go here. With a generated domain the facade comes out of the
 * array above:
 *
 *   $sales = $app['sales'];
 *   $routes->get('/orders/{id}', static fn ($request) =>
 *       $sales->order()->getOrderById((string) $request->getAttribute('id')));
 */

(new App($routes, $kernel, new AppConfig(debug: (bool) $kernel->env('app_debug'))))->run();
