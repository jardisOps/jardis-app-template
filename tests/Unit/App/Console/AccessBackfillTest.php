<?php

declare(strict_types=1);

namespace Tests\Unit\App\Console;

use App\Console\AccessBackfill;
use JardisSupport\Contract\Kernel\DomainKernelInterface;
use PHPUnit\Framework\TestCase;

/**
 * The console command only finds the one generated backfill.php, calls it and
 * prints the answer. What the pass does (creator group, fallback group, one
 * transaction, the verdict) belongs to jardissupport/acl and the generated file
 * - the backfill.php files here are throw-away stand-ins with the same contract:
 * `static function (DomainKernelInterface $kernel, ?Closure $progress = null)`.
 */
final class AccessBackfillTest extends TestCase
{
    private const NEXT_STEP = 'Next step: check the prelude in the project settings or set a fallback group, '
        . 'then run access:backfill again.';

    private string $root;

    /** @var resource */
    private $out;

    /** @var resource */
    private $err;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/accessbackfill-' . bin2hex(random_bytes(6));
        mkdir($this->root);
        $this->out = fopen('php://memory', 'w+') ?: throw new \RuntimeException('no stream');
        $this->err = fopen('php://memory', 'w+') ?: throw new \RuntimeException('no stream');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/src/Api/*/backfill.php') ?: [] as $file) {
            unlink($file);
            rmdir(dirname($file));
        }
        foreach (['/called', '/counter'] as $marker) {
            if (is_file($this->root . $marker)) {
                unlink($this->root . $marker);
            }
        }
        @rmdir($this->root . '/src/Api');
        @rmdir($this->root . '/src');
        rmdir($this->root);
    }

    private function backfillFile(string $domain, string $body): void
    {
        mkdir($this->root . '/src/Api/' . $domain, 0777, true);
        file_put_contents(
            $this->root . '/src/Api/' . $domain . '/backfill.php',
            "<?php\n\ndeclare(strict_types=1);\n\n" . $body,
        );
    }

    /** @param list<string> $arguments */
    private function runCommand(array $arguments = []): int
    {
        return (new AccessBackfill($this->out, $this->err))(
            $this->root,
            $arguments,
            $this->createStub(DomainKernelInterface::class),
        );
    }

    /** @param resource $stream */
    private function read($stream): string
    {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    public function testPrintsOneLineWithFiveNumbersPerTable(): void
    {
        $this->backfillFile('Depot', <<<'PHP'
return static fn ($kernel, $progress = null) => [
    'parcels' => new JardisSupport\Acl\Data\BackfillResult(5, 2, 1, 4, 2),
    'pickups' => new JardisSupport\Acl\Data\BackfillResult(0, 0, 0),
];
PHP);

        self::assertSame(0, $this->runCommand());
        self::assertSame(
            "parcels: 5 written, 2 to fallback, 1 skipped, 4 with creator, 2 resolved\n"
            . "pickups: 0 written, 0 to fallback, 0 skipped, 0 with creator, 0 resolved\n",
            $this->read($this->out),
        );
        self::assertSame('', $this->read($this->err));
    }

    public function testPassesTheKernelOfTheConsoleToTheFile(): void
    {
        $this->backfillFile('Depot', <<<'PHP'
return static fn (JardisSupport\Contract\Kernel\DomainKernelInterface $kernel) => [];
PHP);

        self::assertSame(0, $this->runCommand());
        self::assertSame('', $this->read($this->err));
    }

    public function testAPreludeAnswerFromFourHundredOnPrintsStatusAndBodyAndIsExitOne(): void
    {
        $this->backfillFile('Depot', <<<'PHP'
return static fn ($kernel, $progress = null) => new JardisSupport\Response\Data\Response\DomainResponse(
    JardisSupport\Contract\Kernel\ResponseStatus::Conflict,
    [],
    [],
    ['message' => 'prelude refused'],
    [],
);
PHP);

        self::assertSame(1, $this->runCommand());
        $out = $this->read($this->out);
        self::assertStringStartsWith("409\n", $out);
        self::assertStringContainsString('prelude refused', $out);
        self::assertStringContainsString('"status":409', $out);
        self::assertSame(self::NEXT_STEP . "\n", $this->read($this->err));
    }

    public function testBackfillUnresolvedIsExitOneWithTheMessageAndTheNextStep(): void
    {
        $this->backfillFile('Depot', 'return static fn () => throw new JardisSupport\Acl\Exception\BackfillUnresolved(7);');

        self::assertSame(1, $this->runCommand());
        self::assertSame('', $this->read($this->out));
        $err = $this->read($this->err);
        self::assertStringContainsString(
            'JardisSupport\Acl\Exception\BackfillUnresolved: Backfill refused: 7 record(s) with a creator',
            $err,
        );
        self::assertStringEndsWith(self::NEXT_STEP . "\n", $err);
    }

    public function testRepeatAbortedWithoutProgressIsExitOneWithTheMessage(): void
    {
        $this->backfillFile(
            'Depot',
            "return static fn () => throw new JardisCore\\App\\Exception\\RepeatAborted('no progress', 5);",
        );

        self::assertSame(1, $this->runCommand());
        self::assertSame('', $this->read($this->out));
        self::assertSame(
            "JardisCore\\App\\Exception\\RepeatAborted: Repeat aborted: no progress (open: 5)\n",
            $this->read($this->err),
        );
    }

    public function testRepeatAbortedWithoutANumberFieldIsExitOneWithTheMessage(): void
    {
        $this->backfillFile(
            'Depot',
            "return static fn () => throw new JardisCore\\App\\Exception\\RepeatAborted('answer carries no integer');",
        );

        self::assertSame(1, $this->runCommand());
        self::assertSame('', $this->read($this->out));
        self::assertSame(
            "JardisCore\\App\\Exception\\RepeatAborted: Repeat aborted: answer carries no integer\n",
            $this->read($this->err),
        );
    }

    public function testAnyOtherExceptionIsNeverSwallowed(): void
    {
        $this->backfillFile('Depot', "return static fn () => throw new RuntimeException('the backfill needs a database connection');");

        self::assertSame(1, $this->runCommand());
        self::assertSame('', $this->read($this->out));
        self::assertSame("RuntimeException: the backfill needs a database connection\n", $this->read($this->err));
    }

    public function testNoBackfillFileIsASetupError(): void
    {
        self::assertSame(2, $this->runCommand());
        self::assertStringContainsString('no backfill found', $this->read($this->err));
        self::assertSame('', $this->read($this->out));
    }

    public function testTwoBackfillFilesAreAmbiguousAndNeitherIsCalled(): void
    {
        $body = "file_put_contents(__DIR__ . '/../../../called', 'x');\nreturn static fn () => [];";
        $this->backfillFile('Depot', $body);
        $this->backfillFile('Billing', $body);

        self::assertSame(2, $this->runCommand());
        self::assertStringContainsString('ambiguous', $this->read($this->err));
        self::assertFileDoesNotExist($this->root . '/called');
    }

    public function testAFileWithoutAClosureIsASetupError(): void
    {
        $this->backfillFile('Depot', 'return [];');

        self::assertSame(2, $this->runCommand());
        self::assertStringContainsString('must return a closure', $this->read($this->err));
        self::assertSame('', $this->read($this->out));
    }

    public function testAnArgumentIsAUsageErrorAndTheFileIsNotCalled(): void
    {
        $this->backfillFile('Depot', <<<'PHP'
return static function () {
    file_put_contents(__DIR__ . '/../../../called', 'x');

    return [];
};
PHP);

        self::assertSame(2, $this->runCommand(['parcels']));
        self::assertStringContainsString('takes no argument', $this->read($this->err));
        self::assertSame('', $this->read($this->out));
        self::assertFileDoesNotExist($this->root . '/called');
    }

    public function testProgressLinesComeBeforeTheResultLinesInTheOrderOfTheCalls(): void
    {
        $this->backfillFile('Depot', <<<'PHP'
return static function ($kernel, ?Closure $progress = null): array {
    $progress('parcels', new JardisSupport\Acl\Data\BackfillResult(1000, 100, 0));
    $progress('parcels', new JardisSupport\Acl\Data\BackfillResult(2000, 150, 0));
    $progress('parcels', new JardisSupport\Acl\Data\BackfillResult(2500, 150, 3));
    $progress('pickups', new JardisSupport\Acl\Data\BackfillResult(40, 0, 0));

    return [
        'parcels' => new JardisSupport\Acl\Data\BackfillResult(2500, 150, 3, 2400, 2250),
        'pickups' => new JardisSupport\Acl\Data\BackfillResult(40, 0, 0),
    ];
};
PHP);

        self::assertSame(0, $this->runCommand());
        self::assertSame(
            "parcels: 1000 written, 100 to fallback, 0 skipped\n"
            . "parcels: 2000 written, 150 to fallback, 0 skipped\n"
            . "parcels: 2500 written, 150 to fallback, 3 skipped\n"
            . "pickups: 40 written, 0 to fallback, 0 skipped\n"
            . "parcels: 2500 written, 150 to fallback, 3 skipped, 2400 with creator, 2250 resolved\n"
            . "pickups: 40 written, 0 to fallback, 0 skipped, 0 with creator, 0 resolved\n",
            $this->read($this->out),
        );
        self::assertSame('', $this->read($this->err));
    }

    public function testAFileOfTheOldContractWithOnlyTheKernelRunsWithoutProgressLines(): void
    {
        $this->backfillFile('Depot', <<<'PHP'
return static fn (JardisSupport\Contract\Kernel\DomainKernelInterface $kernel): array => [
    'parcels' => new JardisSupport\Acl\Data\BackfillResult(3, 1, 0, 2, 1),
];
PHP);

        self::assertSame(0, $this->runCommand());
        self::assertSame("parcels: 3 written, 1 to fallback, 0 skipped, 2 with creator, 1 resolved\n", $this->read($this->out));
        self::assertSame('', $this->read($this->err));
    }

    public function testASecondRunThatIsRefusedIsExitOneAfterAFirstRunThatFinished(): void
    {
        // The state lives in a file of the root: every run requires the file anew.
        $this->backfillFile('Depot', <<<'PHP'
$counter = __DIR__ . '/../../../counter';
$run = is_file($counter) ? (int) file_get_contents($counter) : 0;
file_put_contents($counter, (string) ($run + 1));

return static function () use ($run): array {
    if ($run > 0) {
        throw new JardisSupport\Acl\Exception\BackfillUnresolved(1);
    }

    return ['parcels' => new JardisSupport\Acl\Data\BackfillResult(1, 1, 0, 0, 0)];
};
PHP);

        self::assertSame(0, $this->runCommand());
        self::assertSame("parcels: 1 written, 1 to fallback, 0 skipped, 0 with creator, 0 resolved\n", $this->read($this->out));
        self::assertSame('', $this->read($this->err));

        $this->out = fopen('php://memory', 'w+') ?: throw new \RuntimeException('no stream');
        self::assertSame(1, $this->runCommand());
        self::assertSame('', $this->read($this->out));
        $err = $this->read($this->err);
        self::assertStringContainsString('BackfillUnresolved: Backfill refused: 1 record(s) with a creator', $err);
        self::assertStringEndsWith(self::NEXT_STEP . "\n", $err);
    }
}
