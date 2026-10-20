---
name: support-scheduling
description: Cron parsing, task scheduling, fluent API, tags, priority, validation, PSR-20 clock (SystemClock, FixedClock). Use for CronExpression, Schedule, ScheduledTask, ClockInterface, SystemClock, FixedClock, psr/clock.
user-invocable: false
zone: post-active
persona: C
prerequisites: [foundation-architecture, foundation-patterns]
next: []
---

# SCHEDULING_COMPONENT_SKILL
> jardissupport/scheduling | NS: `JardisSupport\Scheduling` | Pure-logic scheduling + PSR-20 clock | PHP 8.3+

## CONTRACTS (`JardisSupport\Contract\Scheduling\*`)
```php
CronExpressionInterface: isDue($now), nextRun($from), nextRuns($from, $count), previousRun($from), describe()
ScheduledTaskInterface:  name(), description(), expression(), isDue($now), nextRun($from), tags(), priority(), allowsOverlapping()
ScheduleInterface:       dueNow($now, $tags=[]), allTasks($tags=[]), validate()
ConstraintInterface:     __invoke(DateTimeInterface $now): bool
ScheduleViolation:       final readonly { taskName, message, severity = 'error' }
```

## API — Schedule (fluent)
```php
use JardisSupport\Scheduling\Schedule;

$schedule = Schedule::create('production')
    ->task('cleanup:expired')
        ->dailyAt('03:00')
        ->description('...')
        ->tag('maintenance')
        ->priority(10)
        ->withoutOverlapping()
    ->task('sync:inventory')
        ->everyFiveMinutes()
        ->between('08:00', '18:00')
        ->weekdays()
        ->tag('sync', 'erp')
        ->environments('production', 'staging');

$schedule->dueNow(new DateTimeImmutable());          // sorted by priority desc
$schedule->dueNow($now, ['sync']);                   // tag filter — OR semantics
$schedule->allTasks(['maintenance']);
$schedule->validate();                               // list<ScheduleViolation>
```

## API — CronExpression (standalone)
```php
use JardisSupport\Scheduling\CronExpression;

$cron = CronExpression::parse('*/5 9-17 * * 1-5');
$cron = CronExpression::parse('0 8 * * *', new DateTimeZone('Europe/Berlin'));

$cron->isDue($now);            // bool
$cron->nextRun($from);         // DateTimeInterface
$cron->nextRuns($from, 5);     // list<DateTimeInterface>
$cron->previousRun($from);     // DateTimeInterface
$cron->describe();             // human-readable string
```

## CLOCK (PSR-20, since 1.2.0)
`psr/clock` arrives via `jardissupport/contracts` >= 2.12 (no own require). Inject `Psr\Clock\ClockInterface` instead of `new DateTimeImmutable()`/`microtime()`.
```php
use JardisSupport\Scheduling\Clock\SystemClock;
use JardisSupport\Scheduling\Clock\FixedClock;

$clock = new SystemClock();                          // now(): DateTimeImmutable, timezone default UTC
$clock = new SystemClock(new DateTimeZone('Europe/Berlin'));
$fixed = new FixedClock(new DateTimeImmutable('2026-04-05 02:59:00')); // now() always the same instant
$later = $fixed->advance(new DateInterval('PT1M'));  // NEW FixedClock, original unchanged
$schedule->dueNow($clock->now());                    // Schedule still takes $now from outside
```
Both are `final readonly`, implement `ClockInterface`. `Schedule`/`TimeWindow` do not use the clock themselves.

## CRON SYNTAX
```
Fields (5):  min hour day month weekday
Fields (6):  sec min hour day month weekday
Fields (7):  sec min hour day month weekday year

Ranges:      0 9-17 * * *
Lists:       0,15,30,45 * * * *
Steps:       */5 * * * *
Combined:    1-10/3 * * * *
Predefined:  @daily @hourly @weekly @monthly @yearly @annually @midnight
```
- Weekday `7` is alias for `0` (Sunday).

## FLUENT TIME HELPERS
```
everyMinute()              * * * * *
everyFiveMinutes()         */5 * * * *
everyFifteenMinutes()      */15 * * * *
everyThirtyMinutes()       */30 * * * *
hourly()                   0 * * * *
hourlyAt(int $min)         $min * * * *
daily()                    0 0 * * *
dailyAt('08:00')           0 8 * * *
weekly()                   0 0 * * 0
weeklyOn(int $day, 'HH:MM')
monthly()                  0 0 1 * *
monthlyOn(int $day, 'HH:MM')
yearly()                   0 0 1 1 *
cron(string $expr)         direct expression
```

## CONSTRAINTS
```php
->between('08:00', '18:00')
->unlessBetween('02:00', '06:00')
->weekdays()                      // Mon–Fri
->weekends()                      // Sat–Sun
->days(2, 4)                      // 0=Sun … 6=Sat
->when(fn() => $flag)             // true = allow
->skip(fn() => $flag)             // true = skip
->environments('production')      // matches Schedule::create($env)
```

## DESCRIBE PATTERNS
| Expression | Output |
|------------|--------|
| `* * * * *` | `'Every minute'` |
| `*/N * * * *` | `'Every N minutes'` |
| `M H * * *` | `'Daily at HH:MM'` |
| `M H * * D` | `'Weekly on DayName at HH:MM'` |
| `M H D * *` | `'Monthly on day D at HH:MM'` |
| anything else | `'Custom schedule'` |

## EXCEPTIONS
```php
InvalidCronExpressionException::fromExpression(string $expr, string $reason)
InvalidScheduleException::missingName()
InvalidScheduleException::missingExpression(string $taskName)
InvalidScheduleException::invalidTime(string $time)
```

## CONVENTIONS
- Tags: OR semantics when filtering; deduplicated on merge.
- Priority: sorted descending (highest value first).
- `validate()` checks: empty schedule (warning), duplicate names (error), conflicting day constraints (warning).
