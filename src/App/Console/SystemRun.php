<?php

declare(strict_types=1);

namespace App\Console;

use Closure;
use JardisCore\App\App;
use JardisCore\App\Exception\SystemRunRefused;
use JardisSupport\Contract\Kernel\DomainKernelInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * `bin/console system <tenant> <METHOD> <path> [body]` — one door call as the
 * system user (cron, job, one-off command), through the same application a web
 * request goes through.
 *
 * The run itself is generated: the Builder writes `src/Api/{Domain}/system.php`
 * (contract: returns `function (App $app, DomainKernelInterface $kernel, string
 * $tenant, string $method, string $path, string $body = '', string $prefix =
 * ''): ResponseInterface`; sign-in secret from the ENV value
 * `SYSTEM_SIGN_IN_SECRET`). This class only finds that one file, calls it and
 * prints the answer — no sign-in logic, no second check. EXACTLY ONE such file
 * must exist: none means the domain has no sign-in door, several would make
 * "the system run" ambiguous.
 *
 * Output: the status code on the first line, the response body after it.
 * Exit codes: 0 for an answer below 400; 1 for a refused run
 * ({@see SystemRunRefused}), an answer of 400 or above, or any other failure of
 * the run; 2 for a usage or setup error (arguments, missing or ambiguous
 * system.php, application that does not start).
 */
final class SystemRun
{
    /**
     * @param resource $out receives status and body
     * @param resource $err receives every failure message
     */
    public function __construct(private $out = STDOUT, private $err = STDERR)
    {
    }

    /**
     * @param list<string> $arguments tenant, method, path, optional body
     * @param Closure(): array{app: App, kernel: DomainKernelInterface} $buildApp builds the application
     *                                                                           (only called once the arguments
     *                                                                           and the system.php are in order)
     */
    public function __invoke(string $projectRoot, array $arguments, Closure $buildApp): int
    {
        if (count($arguments) < 3 || count($arguments) > 4) {
            return $this->fail('usage: system <tenant> <METHOD> <path> [body]', 2);
        }

        [$tenant, $method, $path] = $arguments;
        $body = $arguments[3] ?? '';

        if ($tenant === '') {
            return $this->fail('tenant must not be empty', 2);
        }

        $files = glob($projectRoot . '/src/Api/*/system.php') ?: [];
        if ($files === []) {
            return $this->fail(
                'no system run found (src/Api/*/system.php): the domain has no sign-in door for a system user',
                2,
            );
        }
        if (count($files) > 1) {
            return $this->fail(
                sprintf('ambiguous system run, expected exactly one src/Api/*/system.php, found %d', count($files)),
                2,
            );
        }

        try {
            $run = require $files[0];
            if (!$run instanceof Closure) {
                return $this->fail(
                    sprintf('%s/system.php must return a closure', basename(dirname($files[0]))),
                    2,
                );
            }

            $built = $buildApp();
        } catch (\Throwable $e) {
            return $this->fail(sprintf('application could not be started: %s', $e->getMessage()), 2);
        }

        try {
            $response = $run($built['app'], $built['kernel'], $tenant, strtoupper($method), $path, $body);
        } catch (SystemRunRefused $e) {
            return $this->fail($e->getMessage(), 1);
        } catch (\Throwable $e) {
            return $this->fail(sprintf('%s: %s', $e::class, $e->getMessage()), 1);
        }

        if (!$response instanceof ResponseInterface) {
            return $this->fail('the system run did not return a response', 1);
        }

        $status = $response->getStatusCode();
        fwrite($this->out, $status . "\n" . (string) $response->getBody() . "\n");

        return $status >= 400 ? 1 : 0;
    }

    private function fail(string $message, int $code): int
    {
        fwrite($this->err, 'system: ' . $message . "\n");

        return $code;
    }
}
