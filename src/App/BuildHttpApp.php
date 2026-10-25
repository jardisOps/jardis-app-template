<?php

declare(strict_types=1);

namespace App;

use JardisCore\App\App;
use JardisCore\App\Config\AppConfig;
use JardisCore\App\Routes;
use JardisCore\Kernel\Bootstrap\BuildDomainKernelFromEnv;
use JardisSupport\Contract\Kernel\DomainKernelInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Server\MiddlewareInterface;
use RuntimeException;

/**
 * Builds the HTTP application ONCE for every entry point: the front controller
 * (`public/index.php`) runs it, `bin/console system` reuses it. No second copy
 * of the wiring exists, so a web request and a system run pass the same
 * routes, the same middleware pipeline and the same session guard.
 *
 * Hand-written, never generated. The Builder writes `src/App/bootstrap.php` on
 * the first build and never touches it again. Until then this class runs on its
 * own, which is what makes a freshly cloned template answer without any
 * configuration.
 *
 * Throws on any startup error (unreadable .env, a bootstrap.php that returns
 * something unexpected, a failing middleware factory, a non-anonymous route
 * without session middleware). The caller owns the boundary: the front
 * controller answers the 500 envelope, the console prints the message.
 *
 * `BuildDomainKernelFromEnv` takes the project root and reads the ONE `.env`
 * there (process environment wins) — the generated bootstrap.php calls it the
 * same way, from wherever it lives under src/, so no path needs re-pointing
 * after the first build.
 */
final class BuildHttpApp
{
    /**
     * @return array{app: App, kernel: DomainKernelInterface}
     */
    public function __invoke(string $root): array
    {
        $bootstrap = $root . '/src/App/bootstrap.php';

        /** @var array<string, mixed> $bootstrapped */
        $bootstrapped = is_file($bootstrap)
            ? require $bootstrap
            : ['kernel' => (new BuildDomainKernelFromEnv())($root)];

        $kernel = $bootstrapped['kernel'] ?? null;

        if (!$kernel instanceof DomainKernelInterface) {
            throw new RuntimeException(sprintf(
                '%s must return an array carrying a "kernel" entry.',
                $bootstrap
            ));
        }

        /**
         * Versioned front door: every build writes src/Api/{Domain}/versions.php
         * (contract: returns `list<string>`, e.g. ['v2'], empty is allowed). The
         * union of all lists is what the gate accepts in `X-Jardis-Version`; the
         * header itself is read and checked by jardiscore/app, not here. Before
         * the first build no file exists and the list stays empty — only the
         * baseline (no header) is reachable then. A file that does not return a
         * list of strings is a startup error.
         *
         * @var list<string> $knownVersions
         */
        $knownVersions = [];

        foreach (glob($root . '/src/Api/*/versions.php') ?: [] as $versionsFile) {
            $versions = require $versionsFile;

            if (!is_array($versions) || !array_is_list($versions)) {
                throw new RuntimeException(sprintf('%s must return a list of version strings.', $versionsFile));
            }

            foreach ($versions as $version) {
                if (!is_string($version)) {
                    throw new RuntimeException(sprintf('%s must return a list of version strings.', $versionsFile));
                }

                if (!in_array($version, $knownVersions, true)) {
                    $knownVersions[] = $version;
                }
            }
        }

        $routes = new Routes(new Psr17Factory());
        $routes->health('/health');

        /**
         * App-wide middleware pipeline: the generated src/App/middleware.php,
         * returned by bootstrap.php as the "middleware" entry (contract:
         * `list<MiddlewareInterface>`, in registration order). Before the first
         * build there is no bootstrap.php, the entry is absent and the pipeline
         * stays empty. Registered before the route mount: a failing factory or
         * project class, or a wrong shape, is a startup error, never a stack
         * trace in the HTTP response.
         */
        $pipeline = $bootstrapped['middleware'] ?? [];

        if (!is_array($pipeline) || !array_is_list($pipeline)) {
            throw new RuntimeException(sprintf(
                '%s must return a "middleware" entry that is a list of PSR-15 middleware.',
                $bootstrap
            ));
        }

        foreach ($pipeline as $middleware) {
            if (!$middleware instanceof MiddlewareInterface) {
                throw new RuntimeException(sprintf(
                    '%s must return a "middleware" entry that is a list of PSR-15 middleware.',
                    $bootstrap
                ));
            }

            $routes->middleware($middleware);
        }

        /**
         * Session revocation for routes that end a session (logout, tenant
         * switch): the closure revokes exactly the session behind a session
         * reference through the kernel's auth services. Without `auth()` it is
         * `null` — a routes.php whose routes revoke a session then refuses to
         * mount (fail-closed), a routes.php without such routes ignores it.
         */
        $sessions = $kernel->auth()?->sessions();
        $revoke = $sessions === null ? null : $sessions->invalidateByReference(...); // `?->` cannot create a closure

        /**
         * Generic mount for Builder-emitted domain routes. Every build writes
         * src/Api/{Domain}/routes.php (contract: `return function (Routes $routes,
         * {Domain} $domain, string $prefix = '', ?Closure $revokeSession = null):
         * void` — the fourth parameter exists only in a domain with
         * session-revoking routes). The facade key in the bootstrap array is the
         * lcfirst'd domain name — the same name the Api/ directory carries, so
         * the mount derives it from the path. Zero matches before the first build
         * is fine: the app keeps running unconfigured and /health answers 200.
         *
         * The call is POSITIONAL on purpose: a named `revokeSession:` argument
         * breaks every routes.php that does not declare the parameter. To mount
         * everything under a common prefix, pass it as the third argument:
         *
         *   (require $routesFile)($routes, $facade, '/api', $revoke);
         *
         * Hand-written routes are still welcome right here, next to the loop; a
         * public one is `$routes->anonymous()->get(...)`.
         */
        foreach (glob($root . '/src/Api/*/routes.php') ?: [] as $routesFile) {
            $facade = $bootstrapped[lcfirst(basename(dirname($routesFile)))] ?? null;

            if (is_object($facade)) {
                (require $routesFile)($routes, $facade, '', $revoke);
            }
        }

        $config = new AppConfig(
            debug: (bool) $kernel->env('app_debug'),
            knownVersions: $knownVersions,
            requireSessionGuard: true, // a non-anonymous route without session middleware fails the start
        );

        // The start check (AssertSessionGuard) runs in the constructor.
        return ['app' => new App($routes, $kernel, $config), 'kernel' => $kernel];
    }
}
