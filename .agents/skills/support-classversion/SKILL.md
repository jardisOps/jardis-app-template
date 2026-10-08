---
name: support-classversion
description: Versioned class loading via namespace injection, proxy cache, fallback chain. Use for ClassVersion or namespace resolution.
user-invocable: false
zone: post-active
persona: C
prerequisites: [foundation-architecture, foundation-patterns]
next: [generated-code-versioning]
---

# CLASSVERSION_COMPONENT_SKILL
> jardissupport/classversion | NS: `JardisSupport\ClassVersion` | PHP 8.2+

## RESOLUTION FLOW
```
ClassVersion::__invoke($class, $version)
  0. (optional) ClassResolutionCache.remember(key=$class|$version) → hit: return
  1. LoadClassFromProxy       → object|null (registered proxy)
  2. null? ClassVersionConfig resolves label → subdirectory key
  3. Active class finder (pick ONE per instance):
       LoadClassFromSubDirectory → injects $version BEFORE class name
       LoadClassFromExtensions   → inserts segment at depth-N, tries versioned → baseline → base
     Both iterate fallback chain; first existing class wins.
     Base class = implicit final fallback (NOT in chain array).
  4. throws InvalidArgumentException when nothing found
  5. (optional) Cache.remember stores result OR exception
```

## CONSTRUCTOR
```php
use JardisSupport\ClassVersion\{ClassVersion, Data\ClassVersionConfig,
    Reader\LoadClassFromSubDirectory, Reader\LoadClassFromExtensions,
    Reader\LoadClassFromProxy, Support\ClassResolutionCache};

$config = new ClassVersionConfig(
    version: ['v1' => ['v1', 'legacy'], 'v2' => ['v2', 'current']],  // key => labels[]
    fallbacks: ['v3' => ['v2', 'v1'], 'v2' => ['v1']]                // key => chain[]
);
$cv = new ClassVersion(
    $config,
    new LoadClassFromSubDirectory($config),      // OR LoadClassFromExtensions(...)
    new LoadClassFromProxy($config),             // optional (default built internally)
    cache: new ClassResolutionCache(),           // optional — memoizes hits + misses
);
// Consumer type stays ClassVersion — cache does NOT wrap around or replace it.
// WITHOUT config: no fallback chain, no alias resolution
```

## INTERFACES (`JardisSupport\Contract\ClassVersion`)
```php
// ClassVersionInterface
public function __invoke(string $className, ?string $version = null): mixed;
// ClassVersionConfigInterface
public function version(?string $version = null): ?string;
public function fallbackChain(?string $version = null): array;  // WITHOUT base class (implicit final fallback)
```

## NAMESPACE INJECTION

### `LoadClassFromSubDirectory` — injects BEFORE class name
```
Acme\Domain\User + 'v2'                → Acme\Domain\v2\User
Acme\Deeply\Nested\Service + 'legacy'  → Acme\Deeply\Nested\legacy\Service
```

### `LoadClassFromExtensions` — inserts one or more segments at fixed depth from LEFT
```php
new LoadClassFromExtensions(depth: 3, segmentNames: ['Extensions'], versionConfig: $config);
```
```
Input : Acme\BC\Agg\Command\Handler\Foo,  version 'v2'
Tries : 1. Acme\BC\Agg\Extensions\v2\Command\Handler\Foo   (versioned override)
        2. Acme\BC\Agg\Extensions\Command\Handler\Foo      (versionless baseline override)
        3. Acme\BC\Agg\Command\Handler\Foo                 (generator base)
```

**Multi-segment lookup (versions-first across layers):**
```php
new LoadClassFromExtensions(depth: 3, segmentNames: ['', 'Platform'], versionConfig: $config);
```
```
Input : Acme\BC\Agg\Command\Handler\Foo,  version 'v2'
Tries : 1. Acme\BC\Agg\v2\Command\Handler\Foo                  (dev override v2,    segment='')
        2. Acme\BC\Agg\Platform\v2\Command\Handler\Foo         (platform v2)
        3. Acme\BC\Agg\Command\Handler\Foo                     (dev baseline,       segment='')
        4. Acme\BC\Agg\Platform\Command\Handler\Foo            (platform baseline)
        5. Acme\BC\Agg\Command\Handler\Foo                     (generator-base fallback)
```
- `depth` is **mandatory**; `segmentNames` defaults to `['Extensions']` for BC
- The empty string `''` is a legal segment value meaning "no subdir inserted, probe the root directly"
- Classes with fewer than `depth + 1` segments skip the override lookup entirely

## CLASSVERSIONCONFIG API
```php
$config->version('legacy');        // 'v1'       (label → key)
$config->version('unknown');       // 'unknown'  (passthrough)
$config->version(null);            // ''         (empty)
$config->fallbackChain('current'); // ['v2', 'v1']  (alias resolved first)
$config->fallbackChain('v1');      // ['v1']     (no fallback defined)
$config->fallbackChain(null);      // []
```
- Validation on ctor: keys/values = non-empty strings, labels trimmed + dedup, `InvalidArgumentException` on error
- Trimming: `trim($version ?? '', " \t\n\r\0\x0B")` | Labels case-sensitive | Unknown labels: passthrough

## RETURN TYPES
| Loader | Returns |
|--------|---------|
| `LoadClassFromProxy` | `object\|null` |
| `LoadClassFromSubDirectory` | `class-string` (throws when not found) |
| `LoadClassFromExtensions` | `class-string` (throws when not found) |
| `ClassVersion` | proxy object OR class-string (cache, if set, memoizes both results AND exceptions) |

## LOADCLASSFROMPROXY
```php
$proxy->addProxy(Logger::class, new FileLogger(), 'prod')->addProxy(...);  // fluent
$proxy->removeProxy(Logger::class, 'prod');   // cleans up empty buckets
// With config: version() for alias resolution; without: trim($version ?? '')
```

## USAGE
```php
// Subdirectory
$class = $cv(Payment::class, 'v2');   // 'App\Domain\v2\Payment'
$payment = new $class();

// Proxy
$proxyFinder->addProxy(Logger::class, new FileLogger(), 'prod');
$logger = $cv(Logger::class, 'prod');  // FileLogger instance
$proxyFinder->removeProxy(Logger::class, 'prod');
```

## CLASSRESOLUTIONCACHE (HELPER — `src/Support/`)
```php
use JardisSupport\ClassVersion\Support\ClassResolutionCache;

$cache = new ClassResolutionCache();
$cv    = new ClassVersion($config, $finder, $proxy, cache: $cache);

$cache->clear();  // test isolation / explicit invalidation
```
- **NOT** a `ClassVersionInterface` — cannot take `ClassVersion`'s place
- Memoizes hits (result, incl. `null`) AND misses (throws cached Throwable without re-running producer)
- Key: `"$className|$version"` (single string concat, `array_key_exists` null-safe)
- API: `remember(string $key, callable $producer): mixed`, `clear(): void` — nothing else

## TRACING (DEBUG DECORATOR — `src/Support/`)
```php
use JardisSupport\ClassVersion\Support\TracingClassVersion;

$tracing = new TracingClassVersion($cv);
$result = $tracing(Payment::class, 'v2');
$trace = $tracing->getTrace();
// [['requested' => '...', 'version' => 'v2', 'resolved' => '...', 'type' => 'class-string'|'proxy']]
$tracing->clearTrace();
// Exceptions propagate WITHOUT trace entry
```

## DIR CONVENTIONS

### SubDirectory reader
```
src/Domain/Payment.php     # base class (implicit final fallback)
src/Domain/v1/Payment.php  # v1
src/Domain/v2/Payment.php  # v2
```

### Extensions reader (depth: 3, segmentNames: ['Extensions'])
```
src/Domain/BC/Agg/Command/Handler/CreateOrder.php                # generator base
src/Domain/BC/Agg/Extensions/Command/Handler/CreateOrder.php     # baseline override (versionless)
src/Domain/BC/Agg/Extensions/v1/Command/Handler/CreateOrder.php  # v1 override
src/Domain/BC/Agg/Extensions/v2/Command/Handler/CreateOrder.php  # v2 override
```

### Multi-segment reader (depth: 3, segmentNames: ['', 'Platform'])
```
src/Domain/BC/Agg/Command/Handler/CreateOrder.php                # dev baseline (segment='')
src/Domain/BC/Agg/v1/Command/Handler/CreateOrder.php             # dev override v1
src/Domain/BC/Agg/v2/Command/Handler/CreateOrder.php             # dev override v2
src/Domain/BC/Agg/Platform/Command/Handler/CreateOrder.php       # platform baseline
src/Domain/BC/Agg/Platform/v1/Command/Handler/CreateOrder.php    # platform v1
src/Domain/BC/Agg/Platform/v2/Command/Handler/CreateOrder.php    # platform v2
```

## LAYER
- **Application:** yes — inject ClassVersion
- **Domain:** NEVER imports ClassVersion
