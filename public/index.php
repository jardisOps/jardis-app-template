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
 * brings its own error handling, but only from `new App(...)` onwards — an
 * unreadable .env or a bootstrap.php that returns something unexpected would
 * otherwise put a full stack trace into the HTTP response, since
 * display_errors is On in the dev profile.
 *
 * `BuildDomainKernelFromEnv` takes the project root and reads the ONE `.env`
 * there (process environment wins) — the generated bootstrap.php calls it the
 * same way, from wherever it lives under src/, so no path needs re-pointing
 * after the first build.
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
 * Generic mount for Builder-emitted domain routes. Every build writes
 * src/Api/{Domain}/routes.php (contract: `return function (Routes $routes,
 * {Domain} $domain, string $prefix = ''): void`). The facade key in the
 * bootstrap array is the lcfirst'd domain name — the same name the Api/
 * directory carries, so the mount derives it from the path. Zero matches
 * before the first build is fine: the app keeps running unconfigured and
 * /health answers 200.
 *
 * To mount everything under a common prefix, pass it as the third argument:
 *
 *   (require $routesFile)($routes, $facade, '/api');
 *
 * Hand-written routes are still welcome right here, next to the loop.
 */
foreach (glob($root . '/src/Api/*/routes.php') ?: [] as $routesFile) {
    $facade = $app[lcfirst(basename(dirname($routesFile)))] ?? null;

    if (is_object($facade)) {
        (require $routesFile)($routes, $facade);
    }
}

(new App($routes, $kernel, new AppConfig(debug: (bool) $kernel->env('app_debug'))))->run();
