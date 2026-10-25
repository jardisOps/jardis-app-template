<?php

/**
 * Front controller — hand-written, never generated.
 *
 * The application itself is built in `App\BuildHttpApp` (src/App/), the one
 * place shared with `bin/console system`; this file only owns the HTTP
 * boundary around it. The Builder writes src/App/bootstrap.php on the first
 * build and never touches it again. Until then the app runs on its own, which
 * is what makes a freshly cloned template answer without any configuration.
 */

declare(strict_types=1);

use App\BuildHttpApp;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);

/**
 * Everything up to a constructed `App` runs inside this boundary: kernel,
 * versions, middleware pipeline, route mount and the session-guard start
 * check (all in `App\BuildHttpApp`, shared with `bin/console system`). The
 * framework brings its own error handling, but only once `run()` is reached —
 * an unreadable .env, a bootstrap.php that returns something unexpected, a
 * failing middleware factory or a non-anonymous route without session
 * middleware would otherwise put a full stack trace into the HTTP response,
 * since display_errors is On in the dev profile. `->run()` stays outside:
 * runtime errors go through `HandleThrowable` of jardiscore/app.
 */
try {
    $application = (new BuildHttpApp())($root)['app'];
} catch (Throwable $e) {
    error_log('[bootstrap] ' . $e::class . ': ' . $e->getMessage());

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

$application->run();
