<?php

declare(strict_types=1);

namespace App\Console;

use Closure;
use JardisCore\App\Handler\Response\MapDomainResponse;
use JardisSupport\Acl\Data\BackfillResult;
use JardisSupport\Acl\Exception\BackfillUnresolved;
use JardisSupport\Contract\Kernel\DomainKernelInterface;
use JardisSupport\Contract\Kernel\DomainResponseInterface;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * `bin/console access:backfill` — gives every record of a protected table that
 * holds no access entry yet its entry, through the same kernel as every other
 * console command.
 *
 * The pass itself is generated: the Builder writes `src/Api/{Domain}/backfill.php`
 * (contract: returns `static function (DomainKernelInterface $kernel, ?Closure
 * $progress = null): DomainResponseInterface|array<string, BackfillResult>`;
 * the file calls `$progress(string $table, BackfillResult $soFar)` after every
 * page). This class only finds that one file, calls it and prints the answer —
 * no query, no name from the access layer, no check of its own. EXACTLY ONE
 * such file must exist.
 *
 * Output: during the pass one line per page `<table>: <written> written,
 * <toFallback> to fallback, <skipped> skipped`; at the end one line per table
 * with the five numbers of the result. An answer of the prelude (status 400 or
 * above) prints the status on the first line and the body after it.
 * Exit codes: 0 for a finished pass; 1 for a prelude answer of 400 or above or
 * any exception of the pass (message on the error output); 2 for a usage or
 * setup error (argument, no or several backfill.php, file without a closure).
 */
final class AccessBackfill
{
    private const NEXT_STEP = 'Next step: check the prelude in the project settings or set a fallback group, '
        . 'then run access:backfill again.';

    /**
     * @param resource $out receives progress, results, status and body
     * @param resource $err receives every failure message
     */
    public function __construct(private $out = STDOUT, private $err = STDERR)
    {
    }

    /**
     * @param list<string> $arguments none expected
     */
    public function __invoke(string $projectRoot, array $arguments, DomainKernelInterface $kernel): int
    {
        if ($arguments !== []) {
            return $this->fail('access:backfill: takes no argument', 2);
        }

        $files = glob($projectRoot . '/src/Api/*/backfill.php') ?: [];
        if ($files === []) {
            return $this->fail(
                'access:backfill: no backfill found (src/Api/*/backfill.php): the domain has no protected table',
                2,
            );
        }
        if (count($files) > 1) {
            return $this->fail(
                sprintf(
                    'access:backfill: ambiguous backfill, expected exactly one src/Api/*/backfill.php, found %d',
                    count($files),
                ),
                2,
            );
        }

        try {
            $run = require $files[0];
        } catch (\Throwable $e) {
            return $this->fail(sprintf('access:backfill: backfill.php could not be loaded: %s', $e->getMessage()), 2);
        }
        if (!$run instanceof Closure) {
            return $this->fail(
                sprintf('access:backfill: %s/backfill.php must return a closure', basename(dirname($files[0]))),
                2,
            );
        }

        $progress = function (string $table, BackfillResult $soFar): void {
            fwrite($this->out, sprintf(
                "%s: %d written, %d to fallback, %d skipped\n",
                $table,
                $soFar->written(),
                $soFar->toFallback(),
                $soFar->skipped(),
            ));
        };

        try {
            /** @var DomainResponseInterface|array<string, BackfillResult> $answer */
            $answer = $run($kernel, $progress);

            if ($answer instanceof DomainResponseInterface) {
                return $this->printPreludeAnswer($answer);
            }

            $lines = [];
            foreach ($answer as $table => $result) {
                $lines[] = sprintf(
                    '%s: %d written, %d to fallback, %d skipped, %d with creator, %d resolved',
                    $table,
                    $result->written(),
                    $result->toFallback(),
                    $result->skipped(),
                    $result->withCreator(),
                    $result->resolved(),
                );
            }
        } catch (BackfillUnresolved $e) {
            return $this->fail(sprintf("%s: %s\n%s", $e::class, $e->getMessage(), self::NEXT_STEP), 1);
        } catch (\Throwable $e) {
            return $this->fail(sprintf('%s: %s', $e::class, $e->getMessage()), 1);
        }

        fwrite($this->out, implode("\n", $lines) . ($lines === [] ? '' : "\n"));

        return 0;
    }

    private function printPreludeAnswer(DomainResponseInterface $answer): int
    {
        $factory = new Psr17Factory();
        $mapped = (new MapDomainResponse($factory, $factory))($answer);
        $status = $mapped->getStatusCode();

        fwrite($this->out, $status . "\n" . (string) $mapped->getBody() . "\n");
        if ($status < 400) {
            return 0;
        }

        fwrite($this->err, self::NEXT_STEP . "\n");

        return 1;
    }

    private function fail(string $message, int $code): int
    {
        fwrite($this->err, $message . "\n");

        return $code;
    }
}
