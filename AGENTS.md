<!-- BEGIN jardis/dev-skills — managed block, do not edit by hand -->

# Jardis packages — AI agent context

Aggregated by `jardis/dev-skills`. Run `composer install` to refresh.

Before hand-building a reusable building block, consult the `jardis-catalog` skill to check for an installable Jardis package. For the full workflow from schema to implementation, start with the `jardis-start-here` skill.

<!-- source: jardis/dev-skills -->
# jardis/dev-skills — Agent Notes

Composer plugin that distributes Jardis skills (`<vendor>/.claude/skills/<name>/SKILL.md`) and aggregates `AGENTS.md` from Jardis vendor packages into the consumer project.

## What this package contributes

- **Discovery** of skills from `vendor/jardis*/*/.claude/skills/*/SKILL.md` and from this repo's own `skills/` directory.
- **Bundle skills** (opt-in via `extra."jardis/dev-skills"."bundled-skills"`) covering Jardis methodology:
  - `schema-authoring` — pre-Designer Schema.yaml authoring (companion `examples/Schema.yaml`)
  - `platform-implementation` — extending Designer-generated PHP code (Extensions/ layout, ClassVersion v2 override mechanics, V1–V12 prohibitions)
  - `platform-usage` — wiring Designer-generated Commands/Queries into a transport (HTTP / CLI / queue / worker), DomainResponse mapping
  - `platform-versioning` — ClassVersion resolution chain + the Versionierungs-Modell for Designer-generated code
  - `platform-workflow` — Workflow-Engine API consumed by FlowDesigner-generated Use-Case orchestrators
  - `platform-cookbook` — Phase-3 recipes, troubleshooting, and event transport for Designer-generated code
  - `rules-architecture` / `rules-frontend` / `rules-patterns` / `rules-testing` — cross-cutting rules (`rules-frontend` = stack-agnostic FE review constitution)
- **Managed prefixes:** `adapter-`, `core-`, `support-`, `tools-`, `schema-`, `plan-`, `platform-`, `rules-`. Skills with these prefixes are installed/removed by the plugin; skills without them belong to the user.
- **AGENTS.md aggregation** between markers `<!-- BEGIN jardis/dev-skills ... -->` / `<!-- END jardis/dev-skills -->`. User content outside the markers is preserved. A source package's own managed block is stripped before embedding (`Handler/Install/StripManagedBlock`), so the result is always a single, non-nested block. `*.backup` skill directories are skipped during discovery and never re-backed-up.

## Working in this repo

- **Architecture:** Closure-Orchestrator — `src/SkillInstaller.php` and `src/SkillUninstaller.php` compose handlers from `src/Handler/`. Data classes under `src/Data/`. No business logic in orchestrators.
- **Plugin entry:** `src/Plugin.php` (`Composer\Plugin\PluginInterface` + `EventSubscriberInterface`) wires `post-install-cmd`, `post-update-cmd`, `pre-package-uninstall`.
- **Tests:** Integration > Unit. New tests go under `tests/Integration/<area>/<ClassName>Test.php`. Use `tests/Support/TempProject` for filesystem fixtures.
- **Quality gates:** `make phpunit` (150+ tests), `make phpstan` (Level 8), `make phpcs` (PSR-12). All three must be green.
- **Skill authoring:** Every bundled `SKILL.md` follows `docs/SKILL-FORMAT.md` v3 — frontmatter `zone`/`prerequisites`/`next`, single-line description (≤60 words), topical numbered body sections (`### 1. …`), per-zone line budget (`post-active` = 550). Long working artefacts live in a sibling `skills/<name>/examples/` directory and do not count against the body budget. Reshape rationale in `docs/PRD-skill-overhaul.md`.

## Don'ts

- Do not introduce a new top-level skill prefix without updating `RemoveJardisSkills::MANAGED_PREFIXES` and `docs/SKILL-FORMAT.md` §2.
- Do not edit a generated AGENTS.md block in a consumer project — the plugin overwrites it on next install.
- Do not bypass `TempProject` in tests with raw `tempnam()` / hardcoded paths.
- Do not duplicate content across bundle skills. Patterns live only in `rules-patterns`, architecture only in `rules-architecture`, frontend review rules only in `rules-frontend`, test rules only in `rules-testing`, generated-code layout only in `platform-implementation` §1, transport wiring only in `platform-usage`. Designer YAML vocabulary (Aggregate / Source / FieldMap / Lists / Flow) lives in `tools-builder-engine` in the Builder repo — outside this bundle. Other skills link.

## Pointers

- README (consumer-facing): `README.md`
- Skill format spec: `docs/SKILL-FORMAT.md`
- Skill format validator: `bin/validate-skills.php` (run via `make validate-skills`)
- Bundle overhaul rationale: `docs/PRD-skill-overhaul.md`, `docs/PLAN-skill-overhaul.md`

<!-- source: jardiscore/kernel -->
# jardiscore/kernel

Application-layer offering (Kernel-Entkopplung 2026-07): the immutable service case `DomainKernel` (Constructor Injection, implements `JardisSupport\Contract\Kernel\DomainKernelInterface`) plus the ENV packer `Bootstrap\BuildDomainKernelFromEnv`. The former domain-side classes (`DomainApp`, `ServiceRegistry`, `BoundedContext`, `Response/*`) were removed — the Jardis Builder now generates the context body and the response trio into each domain (`{Domain}\Response\`); the shared vocabulary (`ResponseStatus`, `GeneratedContextInterface` marker) lives in `jardissupport/contracts`.

## Usage essentials

- **`DomainKernel` is a pure consumer** — immutable, builds nothing itself, loads no ENV. All 11 services come via constructor; `env(string $key)` is case-insensitive (private ENV > `$_ENV`, stored lowercase internally via `array_change_key_case`). `container()` always returns **`Factory`** (not just `ContainerInterface`) — Factory wraps an external container and provides `create()` in addition to PSR-11 `get()`/`has()`. Added with Kernel-Entkopplung: `eventListenerRegistry(): ?EventListenerRegistryInterface` — dispatcher + registry are handed in as a pair built from the same `ListenerProvider` instance.
- **The kernel core stays adapter-free** (Contract + PSR imports only). Adapter imports are legitimate exclusively inside the `Bootstrap\` sub-namespace — after the Kernel-Entkopplung, jardiscore/kernel is application layer, outside the inward-pointing hexagonal arrows (see README, constitutional note).
- **`Bootstrap\BuildDomainKernelFromEnv`** is ONE invokable class (`__invoke(string $configPath): DomainKernel`), no static factory. It loads the ENV cascade via `DotEnv::loadPrivate()` and composes 10 handler closures (cache, connection, dispatcher/listener-provider pair, filesystem, http, logger, mailer, redis, PDO extraction); the Redis fan-out (Redis feeds logger AND cache) is split into named sub-closures. Adapters are `suggest`/`require-dev` — missing adapters yield `null` services, never errors. `.env` templates: `docs/env-examples/`.
- **Consuming a generated domain:** `new {Domain}($kernel)` — the generated facade takes the case via constructor (`DomainKernelInterface`); one packer call per app bootstraps all domains.

## Full reference

https://docs.jardis.io/en/core/kernel

<!-- source: jardissupport/classversion -->
# jardissupport/classversion

Versioned classes via Namespace-Injection and/or Proxy-Registry. Entry point: `$classVersion(Class::class, $version)` via `__invoke` — Composite of `LoadClassFromProxy` (wins) and a configurable class finder (`LoadClassFromSubDirectory` or `LoadClassFromExtensions`, with fallback chain), configured through `ClassVersionConfig`.

## Source layout

- `src/ClassVersion.php` — orchestrator (implements `ClassVersionInterface`).
- `src/Data/` — `ClassVersionConfig`.
- `src/Reader/` — resolvers that implement `ClassVersionInterface`: `LoadClassFromSubDirectory`, `LoadClassFromExtensions`, `LoadClassFromProxy`.
- `src/Support/` — helpers that do **not** implement `ClassVersionInterface` and never take ClassVersion's place: `ClassResolutionCache`, `TracingClassVersion`.

## Usage essentials

- **Loader order fixed:** `ClassVersion::__invoke` checks `LoadClassFromProxy` first (returns `object|null`), then falls back to the configured class finder (returns `class-string`). Return type is `mixed` — Proxy returns object, class finders return class name for `new $class()` instantiation.
- **Two class finders, pick one per `ClassVersion` instance:**
  - `LoadClassFromSubDirectory` — injects version **before the class name**: `Acme\Domain\User` + `v2` → `Acme\Domain\v2\User`.
  - `LoadClassFromExtensions(depth, segmentNames, ?config)` — inserts one or more segments at position `depth` from the left; versioned subdir goes after each segment. `segmentNames: array<string>` (default `['Extensions']`); `''` is a legal entry meaning "no subdir inserted, probe the root directly". With `depth:3, segmentNames:['Extensions']`, `Acme\BC\Agg\Command\Handler\Foo` → `Acme\BC\Agg\Extensions\v2\Command\Handler\Foo` → baseline `Acme\BC\Agg\Extensions\Command\Handler\Foo` → generator base. Multi-segment example `segmentNames: ['', 'Platform']` walks **versions-first across all segments** before falling back to baselines: `…\v2\…` → `…\Platform\v2\…` → `…\…` (dev baseline) → `…\Platform\…` (platform baseline) → generator base. Classes shorter than `depth+1` skip override lookup. Pure string math, zero array allocations on the happy path.
- **Fallback chain in `ClassVersionConfig`** explicitly as `['v3' => ['v2', 'v1']]` — no recursive resolution, the order is the lookup path. **The base class (without version) is the implicit final fallback and is NOT in the `fallbackChain()` array.** Alias resolution (`'current'` → `'v2'`) happens before chain lookup.
- **Label validation in constructor:** Keys/values must be non-empty strings, trimming + dedup applied, otherwise `InvalidArgumentException`. `version($label)` returns the key (or passthrough for unknown), `version(null)` → `''`. Labels are case-sensitive.
- **`LoadClassFromProxy` fluent:** `addProxy(Logger::class, new FileLogger(), 'prod')->addProxy(...)`, `removeProxy(Logger::class, 'prod')` cleans up empty buckets. Data structure: `$cachedProxy[$version][$className] = $object`. Without config, proxy only trims `$version`, no alias resolving.
- **`ClassResolutionCache` (optional helper):** passed as `new ClassVersion($config, $finder, $proxy, cache: new ClassResolutionCache())`. Memoizes hits **and** misses per `(className, version)` key. Exception is cached and re-thrown without re-running the inner resolver. API: `remember(string $key, callable $producer): mixed`, `clear(): void`. **Never replaces `ClassVersion`** — consumer type stays `ClassVersion`.
- **`TracingClassVersion` Decorator for debug:** `$tracing->getTrace()` returns a list of `['requested', 'version', 'resolved', 'type' => 'class-string'|'proxy']`. Exceptions propagate **without** a trace entry. Layer rule: **Application Layer yes — Domain Layer never imports `ClassVersion`.**

## Full reference

https://docs.jardis.io/en/support/classversion

<!-- source: jardissupport/dotenv -->
# jardissupport/dotenv

`.env` loader with two modes (Public + Private), two-stage `APP_ENV` bootstrap, cascade includes (`load()`/`load?()`), `${VAR}`/`~` substitution via `VariableRegistry`, `_FILE` secret resolution, and cast chain (Value → UserHome → Numeric → Bool → JSON → Array).

## Usage essentials

- **`loadPublic($path)` vs. `loadPrivate($path)`:** Public writes `putenv()` + `$_ENV` + `$_SERVER` (bootstrap, once per request) and returns `void`. Private returns `array<string,mixed>` without globals — this is the default for domain configs (`Infrastructure/Config/*Config` classes). Inject values as primitives into the domain, never inject the `DotEnv` service itself.
- **Two-stage bootstrap is fixed:** Stage 1 loads `.env` + `.env.local`, then `APP_ENV` is resolved from `VariableRegistry`/`$_ENV`/`getenv()`, Stage 2 loads `.env.{APP_ENV}` + `.env.{APP_ENV}.local`. Later files override earlier ones — `*.local` always comes after the base/env counterpart.
- **Cast chain runs in strict order with early exit on non-string:** `CastStringToValue` → `CastUserHome` → `CastStringToNumeric` → `CastStringToBool` → `CastStringToJson` → `CastStringToArray`. Add custom handlers via `DotEnv::addHandler($invokable, prepend: true)` before substitution; never call `CastTypeHandler` directly. Note: `ENABLED=1` becomes `int(1)` (Numeric takes precedence over Bool) — write `true`/`false` explicitly for booleans.
- **`VariableRegistry` is the single source of truth** for `${VAR}` and `~` expansion in both modes; `LoadValuesFromFiles` populates it before every cast. Never use `getenv()` directly for values from `.env` in code — otherwise Private mode isolation does not apply.
- **Include system:** `load(path.env)` is required (throws `EnvFileNotFoundException`), `load?(path.env)` is optional (silent skip); relative paths are resolved from the directory of the including file; each include runs the full cascade (base → .local → .{APP_ENV} → .{APP_ENV}.local). Circular includes are detected via a `realpath()` stack and throw `CircularEnvIncludeException::getIncludeStack()`.
- **`_FILE` pattern + optional `jardissupport/secret`:** Keys with the `_FILE` suffix (`DB_PASSWORD_FILE=/run/secrets/db_pw`) are read by the loader, trimmed, passed through the cast chain, and stored under the key without the suffix (`DB_PASSWORD`). Combinable with `jardissupport/secret`: if the file contains `secret(aes:...)` it is decrypted in the same pass. Layer rule: `DotEnv` lives in `Infrastructure`, **never** in the domain.

## Full reference

https://docs.jardis.io/en/support/dotenv

<!-- source: jardissupport/factory -->
# jardissupport/factory

Minimal PSR-11 container: a single `Factory` class, no shared registry, no ClassVersion support, Reflection fallback only for parameterless constructors.

## Usage essentials

- **One class, two APIs:** `Factory` implements `Psr\Container\ContainerInterface` (`get()`, `has()`) and additionally provides `create(string $className, mixed ...$parameters): object`. `get()` is a lookup with a fallback chain, `create()` always returns a new instance with parameters — no cache, no container lookup.
- **`get()` resolution order is strict:** 1) pre-registered `$instances` (exact key match), 2) backend `ContainerInterface::has()/get()`, 3) `class_exists()` + Reflection `new $className()`, 4) `NotFoundException`. Step 3 applies **only** for parameterless constructors — classes with required params via `get()` throw `ContainerException`; use `create()` for those.
- **Immutable after construction:** `$instances` and `$container` are `readonly`. No `register*()`/`registerShared()` methods, no post-construction mutation. All instances must be passed in the constructor: `new Factory($backend, ['logger' => $logger])`.
- **No shared registry, no instance reuse:** Step 3 (Reflection) creates a new instance every time — if Singleton behavior is required, inject a backend container (e.g. PHP-DI) or pre-register the instance.
- **No ClassVersion support.** Versioned classes are resolved in the Kernel (`jardiscore/kernel`), not in the Factory. The Factory sees only the final class name.
- **Layer rule:** `Factory` lives in `Infrastructure/Support` and is consumed by the Application Layer — the **Domain never imports** `JardisSupport\Factory\Factory`. Exceptions: `NotFoundException` (`extends \InvalidArgumentException implements NotFoundExceptionInterface`) and `ContainerException` (`extends \RuntimeException implements ContainerExceptionInterface`).

## Full reference

https://docs.jardis.io/en/support/factory

<!-- source: jardissupport/secret -->
# jardissupport/secret

Secret resolution for `secret(...)`-markers in `.env` values — decrypts via AES-256-GCM (OpenSSL) or XSalsa20-Poly1305 (Sodium) as a DotEnv cast plugin. Encrypt offline, decrypt at boot, no runtime re-encryption.

## Usage Essentials

- **`SecretHandler` is the recommended entry point:** Wires `SecretResolverChain` (Sodium + AES) + `Secret` caster with a single `callable` key provider. As a DotEnv plugin, always use `addHandler($handler, prepend: true)` — `Secret` must run before `CastStringToValue`, otherwise `${VAR}` substitution and type casting already operate on the encrypted string.
- **Chain order is required:** Register `SodiumSecretResolver` before `AesSecretResolver` in `SecretResolverChain` — AES without prefix is the catch-all fallback (`supports()` matches when no `:` is in the value). Unknown prefixes remain free for future resolvers. `addResolver()` is **immutable** and returns a clone.
- **Encryption format is fixed per resolver:** AES → `[aes:]base64(nonce[12] + ciphertext + tag[16])` (32-byte key, 12-byte nonce). Sodium → `sodium:base64(nonce[24] + ciphertext_with_mac)` (32-byte key, 24-byte nonce, prefix is REQUIRED). Static `encrypt()` helpers are **for tooling/scripts only**, not for runtime calls.
- **Key providers are invokable and lazy:** `FileKeyProvider($path)` reads file contents (auto-detects base64 vs raw), `EnvKeyProvider($varname)` reads `getenv()` (also base64). Alternatively a direct `string` or `callable` (`fn() => file_get_contents('/run/secrets/key')`). **Never** store a key in code/repo/`.env`; `*.key` belongs in `.gitignore`.
- **Caster semantics are deterministic:** `new Secret(?SecretResolverInterface)` → `null`→`null`, `"plain"`→`"plain"` (no marker), `"secret(x)"`→`resolver->resolve("x")`. Without a configured resolver the marker is returned unchanged. The regex is `/^secret\((.+)\)$/`. Decrypted values then pass through the cast chain normally (bool, int, array, `${VAR}`).
- **Exceptions and Contract:** `SecretResolverInterface` (`supports()`/`resolve()`) and the base `SecretResolutionException` come from `jardissupport/contracts` — this package throws `SecretException` (extends `SecretResolutionException`) with sub-exceptions `InvalidKeyException` (missing/wrong length/not readable) and `DecryptionFailedException` (Base64 invalid, auth tag wrong). The domain imports **only** the Contract interface, never the package.

## Full Reference

https://docs.jardis.io/en/support/secret

<!-- END jardis/dev-skills -->
