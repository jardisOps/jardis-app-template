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

Application-layer offering (Kernel-Entkopplung 2026-07): the immutable `DomainKernel` (Constructor Injection, implements `JardisSupport\Contract\Kernel\DomainKernelInterface`) plus the ENV packer `Bootstrap\BuildDomainKernelFromEnv`. The former domain-side classes (`DomainApp`, `ServiceRegistry`, `BoundedContext`, `Response/*`) were removed — the Jardis Builder now generates the context body and the response trio into each domain (`{Domain}\Response\`); the shared vocabulary (`ResponseStatus`, `GeneratedContextInterface` marker) lives in `jardissupport/contracts`.

## Usage essentials

- **`DomainKernel` is a pure consumer** — immutable, builds nothing itself, loads no ENV. All services come via constructor (11 nullable/typed accessors plus `container()`); `env(string $key)` is case-insensitive (stored lowercase internally via `array_change_key_case`) and **file-pure**: no `$_ENV`/process-environment fallback (dropped in v2.0.0, R3), an explicitly empty value counts as missing. `projectRoot()` (renamed from `domainRoot()`, contracts v2.0.0) returns the project root passed in. `container()` always returns **`Factory`** (not just `ContainerInterface`) — Factory wraps an external container and provides `create()` in addition to PSR-11 `get()`/`has()`. Added with Kernel-Entkopplung: `eventListenerRegistry(): ?EventListenerRegistryInterface` — dispatcher + registry are handed in as a pair built from the same `ListenerProvider` instance. Added with env-konfiguration (v2.0.0): `messaging(): ?MessagingServiceInterface` — publish/consume via `jardisadapter/messaging` (kafka/rabbitmq/redis, selected by `MESSAGING_TRANSPORT`).
- **The kernel core stays adapter-free** (Contract + PSR imports only). Adapter imports are legitimate exclusively inside the `Bootstrap\` sub-namespace — after the Kernel-Entkopplung, jardiscore/kernel is application layer, outside the inward-pointing hexagonal arrows (see README, constitutional note).
- **`Bootstrap\BuildDomainKernelFromEnv`** is ONE invokable class (`__invoke(string $projectRoot, ?string $envContent = null): DomainKernel`), no static factory. It takes the **project root** (the git-clone target) and reads the ONE `.env` that lives there, plus DotEnv's cascade (`.env` → `.env.local` → `.env.{APP_ENV}`) — no `config/` layer and no directory creation since v2.4.0. Hand it an `.env`-formatted string instead (`''` included, for containers with no file) and the file is not read at all; the process environment always wins over both (dotenv >= 1.4.0). It composes the handler closures (ENV chain incl. the secret key chain `APP_SECRET_KEY` → `<projectRoot>/support/secret.key`, then cache, connection, dispatcher/listener-provider pair, filesystem, http, logger, mailer, messaging, redis, PDO extraction); the Redis fan-out (Redis feeds logger AND cache) is split into named sub-closures. Adapters are `suggest`/`require-dev` — missing adapters yield `null` services, never errors. An unresolvable `secret(...)` value is the one thing that is loud: `InvalidEnvConfigurationException`, naming the key, never the value. `.env` template: `docs/.env.example`.
- **Consuming a generated domain:** `new {Domain}($kernel)` — the generated facade takes the DomainKernel via constructor (`DomainKernelInterface`); one packer call per app bootstraps all domains.

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

<!-- source: jardissupport/data -->
# jardissupport/data

Entity hydration, change tracking, deep clone, field mapping, identity generation — all reflection-based, no ORM. Three service classes: `Hydration`, `Identity`, `FieldMapper` implement Contracts from `jardissupport/contracts`.

## Usage essentials

- **Entity convention required:** `private array $__snapshot = [];` must exist on every hydrated entity — `getChanges()`, `toArray()`, and `aggregateToArray()` depend on it. Getter resolution order: `get{Name}()` > `is{Name}()` > `has{Name}()` > Reflection fallback; setter `set{Name}()` > Reflection + `TypeCaster`. Snake→Camel on column mapping (`user_name` → `userName`).
- **Value-Based Detection** separates DB columns from relations without a `#[Relation]` attribute: DB column = `null|scalar|DateTimeInterface|BackedEnum|plain array`, relation = objects or arrays of objects. `HydrateEntity` additionally checks the property type (array property + flat scalar array → hydrate as JSON column; array property + indexed array of assoc → skip as MANY-relation data). The `#[Relation]` attribute is NOT evaluated by this package — metadata only, for the Builder.
- **`hydrate()` vs `apply()`:** both set properties, but `hydrate()` merges into `__snapshot` (DB load, no changes), `apply()` leaves the snapshot untouched → `getChanges()` detects the modifications. Snapshot is **MERGE, not REPLACE** — multiple `hydrate()` calls accumulate. Snapshot holds only **scalars**: `DateTime` → `'Y-m-d H:i:s'`, `BackedEnum` → `->value`, no objects.
- **`toArray()` vs `aggregateToArray()`:** `toArray()` is flat (DB-column properties only, for `Repository::insert()`), `aggregateToArray()` serializes the full graph (recursive incl. relations, relation property names stay camelCase). Both read **keys from `__snapshot`** (real DB column names), **values from current properties**. Round-trip safe: `hydrate(['order_number' => 'X']) → aggregateToArray() → ['order_number' => 'X']`.
- **Identity generators:** `generateUuid7()` recommended (time-ordered, RFC 9562, monotonic counter for batch ordering and cross-instance collision avoidance); `generateUuid5()` deterministic (namespace + name, same input → same UUID); `generateUuid4()` for compatibility only; `generateNanoId(21, alphabet)` compact URL-safe. Use case: `identifier` = UUID v7 CHAR(36) public-facing, PK = autoincrement INT internal for FKs.
- **FieldMapper asymmetry:** `toColumns` is flat (Command-DTOs are flat), `fromColumns` recursive (Query responses are nested); `fromAggregate($array, $mapProvider, $entityName)` has a per-entity provider and **omits unmapped keys** (implicit PK/FK filtering). Empty-map shortcut: returns `$data` unchanged. Symmetry: `fromColumns(toColumns($data, $map), $map) === $data`. Layer rule: Domain defines entities, Infrastructure/Repository uses `Hydration`+`FieldMapper`, Application never directly.

## Full reference

https://docs.jardis.io/en/support/data

<!-- source: jardissupport/dbquery -->
# jardissupport/dbquery

Fluent SQL query builder for MySQL/MariaDB/PostgreSQL/SQLite — four builders (`DbQuery` SELECT + CTE + Window, `DbInsert`, `DbUpdate`, `DbDelete`), state-separated (Builder → State → dialect-specific generator → Prepared SQL).

## Usage essentials

- **Dialect via Enum:** `Dialect::MySQL|MariaDB|PostgreSQL|SQLite` with `value`, `defaultVersion()` (8.0 / 10.6 / 14 / 3.39) and `supportsVersion()`. `sql($dialect, prepared: true, version: '...')` is the only output path — string dialects are parsed internally via `Dialect::tryFromString()`. **Always use `prepared: true`**, no raw concatenation.
- **Prepared output via `DbPreparedQuery`:** `->sql()` returns SQL with `?` placeholders, `->bindings()` returns the matching parameter array, `->type()` returns the dialect string; `(string)$prepared` equals `->sql()`. Ready to use as-is with `PDO::prepare()`/`execute($bindings)`.
- **Dialect limits are hard-validated:** `FULL JOIN` throws `InvalidArgumentException` on MySQL/SQLite (PostgreSQL only). `UPDATE`/`DELETE` + `JOIN`/`ORDER BY`/`LIMIT` only on MySQL/MariaDB — PostgreSQL and SQLite throw `InvalidArgumentException`. No silent fallback behavior.
- **Conflict handling is dialect-specific:** MySQL/MariaDB `->onDuplicateKeyUpdate('field', $value)`, PostgreSQL `->onConflict('email')->doUpdate([...])` or `->doNothing()`, SQLite `->orIgnore()` / `->replace()`. `DbInsert::fromSelect($selectQuery)` for `INSERT...SELECT`.
- **Raw SQL only via `Expression::raw()`** (not escaped, not validated) — usable in WHERE, SET, JSON paths. JSON ops are dialect-aware: `->whereJson('settings')->extract('$.theme')->equals('dark')`, `->length()`, `->contains/notContains`. Condition chaining with `->and()`/`->or()` + optional bracket param `('(' / ')')` for grouping.
- **Version-aware SQL via `BuilderRegistry`** (instance-based, **not static** — multi-dialect usage in parallel within the same request is possible). Pattern: `namespace\method\mysql\v80\FullJoin` (dots removed from version), fallback to base class. Layer rule: builders live in the Infrastructure/Repository Layer, **Domain never imports** the builders.

## Full reference

https://docs.jardis.io/en/support/dbquery

<!-- source: jardissupport/dotenv -->
# jardissupport/dotenv

`.env` loader with two modes (Public + Private), two-stage `APP_ENV` bootstrap, cascade includes (`load()`/`load?()`), `${VAR}`/`~` substitution via `VariableRegistry`, `_FILE` secret resolution, and cast chain (Value → UserHome → Numeric → Bool → JSON → Array).

## Usage essentials

- **`loadPublic($path)` vs. `loadPrivate($path)`:** Public writes `putenv()` + `$_ENV` + `$_SERVER` (bootstrap, once per request) and returns `void`. Private returns `array<string,mixed>` without globals — this is the default for domain configs (`Infrastructure/Config/*Config` classes). Inject values as primitives into the domain, never inject the `DotEnv` service itself.
- **Two-stage bootstrap is fixed:** Stage 1 loads `.env` + `.env.local`, then `APP_ENV` is resolved from `VariableRegistry`/`$_ENV`/`getenv()`, Stage 2 loads `.env.{APP_ENV}` + `.env.{APP_ENV}.local`. Later files override earlier ones — `*.local` always comes after the base/env counterpart.
- **Cast chain runs in strict order with early exit on non-string:** `CastStringToValue` → `CastUserHome` → `CastStringToNumeric` → `CastStringToBool` → `CastStringToJson` → `CastStringToArray`. Add custom handlers via `DotEnv::addHandler($invokable, prepend: true)` before substitution; never call `CastTypeHandler` directly. Note: `ENABLED=1` becomes `int(1)` (Numeric takes precedence over Bool) — write `true`/`false` explicitly for booleans.
- **`VariableRegistry` is the single source of truth** for `${VAR}` and `~` expansion in both modes; `LoadValuesFromFiles` populates it before every cast. Never use `getenv()` directly for values from `.env` in code — otherwise Private mode isolation does not apply.
- **Include system:** `load(path.env)` is required (throws `EnvFileNotFoundException`), `load?(path.env)` is optional (silent skip); relative paths are resolved from the directory of the including file; each include runs the full cascade (base → .local → .{APP_ENV} → .{APP_ENV}.local). Circular includes are detected via a `realpath()` stack and throw `CircularEnvIncludeException::getIncludeStack()`.
- **v1.2 class-API additions** (`DotEnvInterface` unchanged): `addRawKeys(array $keysOrSuffixes)` registers case-insensitive keys/suffixes (e.g. `_PASSWORD`) that skip the whole cast chain, so a credential like `DB_PASSWORD=false` survives as the string `'false'`. `loadPublicFromString($content, ?$baseDir)` / `loadPrivateFromString($content, ?$baseDir)` parse `.env`-formatted content that never touched disk (e.g. a secrets-manager payload) with the same cast chain, substitution and `_FILE` resolution — no file cascade.
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

<!-- source: jardissupport/repository -->
# jardissupport/repository

Generic CRUD Repository for raw DB-access array data (no Entities, no Hydration), with Read/Write Splitting via `ConnectionPoolInterface|PDO`, three PK strategies, and consistent `PDOException → PersistException` wrapping.

## Usage essentials

- **Facade `Repository` is the only entry point** — constructor accepts `ConnectionPoolInterface` (real Read/Write Splitting via `getReader()`/`getWriter()`) or plain `PDO` (wrapped internally via `PdoConnectionPool` → same connection for reader and writer). Handlers (`InsertHandler`, `UpdateHandler`, `DeleteHandler`, `DeleteAllHandler`, `FindByIdHandler`, `ExistsHandler`, `QueryExecutor`) are instantiated lazily via `??=`.
- **Raw Data, not Entities:** `insert()/update()/delete()` take `array<string,mixed>`, `findById()/findByQuery()` return `?array`/`array<int,array>`. Hydration and Change-Tracking are the responsibility of `jardissupport/data` — the layer above, not here. `QueryExecutor` forces `PDO::FETCH_ASSOC` explicitly (regardless of PDO default).
- **PK strategies via Enum `PkStrategy` from `jardissupport/contracts`:** `AUTOINCREMENT` (default, `lastInsertId()` → `int`), `INTEGER` (MAX+1 with 3 retries on Duplicate Key → `int`, duplicate detection via SQLSTATE `23000` or SQLite string match), `NONE` (caller provides PK in `$values` → `int|string`). Empty `$values` → `PersistException` (including NONE without PK).
- **`findByQuery()` expects a `DbQueryBuilderInterface`** from `jardissupport/dbquery` (no criteria arrays!) — returns full query power (JOINs, Aggregation, Window Functions) with guaranteed `prepared: true`. For COUNT/Aggregation simply use `->select('COUNT(*) AS total')` — result is `[['total' => 42]]`.
- **Consistent Exception wrapping:** All write Handlers (`Insert`, `Update`, `Delete`, `DeleteAll`) catch `PDOException` and throw `PersistException` (from `jardissupport/contracts`). `RecordNotFoundException` is defined but not thrown by the Repository itself — only for custom implementations. `$repo->update(..., [])` is a no-op and returns `true` (bool); `$repo->deleteAll(..., [])` is a no-op and returns `void` (no return value).
- **Layer rule:** Repository is a Secondary Port (Hexagonal). The Domain imports **only** `RepositoryInterface` from `jardissupport/contracts` — **never** `JardisSupport\Repository\Repository` directly. The implementation lives in `Infrastructure`/Composition Root; `PdoConnection::getDatabaseName()` supports MySQL, PostgreSQL, and SQLite.

## Full reference

https://docs.jardis.io/en/support/repository

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

<!-- source: jardissupport/validation -->
# jardissupport/validation

Object graph validation via Reflection — no annotations, no interfaces on domain classes, `ObjectValidator` + `ValidatorRegistry` + `CompositeFieldValidator` compose 21 stateless `ValueValidator` singletons.

## Usage essentials

- **Three-class entry point:** `new ObjectValidator(ValidatorRegistry)` → `validate($root)` traverses the graph recursively (exception-safe via `try/finally`). `ValidatorRegistry::register($class, $validator)` matches by class string with parent/interface fallback, `CompositeFieldValidator` builds rules via fluent API `->field('x')->validates(Class, $options)`. `ValidationContext` protects against circular refs with `spl_object_id()` + `maxDepth: 100`.
- **Field resolution in strict order:** `get{Field}()` → `is{Field}()` → `has{Field}()` → `{Field}()` (ucfirst) → Reflection on property. PSR getters always first, Reflection only as last-resort fallback — no `__get`/magic method support.
- **Null-safe convention (with exactly 2 exceptions):** All `ValueValidator`s return `null` when the value is `null` — **except** `NotBlank` and `NotEmpty`, which explicitly validate against `null`. Custom validators with `implements ValueValidatorInterface` (`jardissupport/contracts`, `validateValue(mixed, array $options = []): ?string`) must include `if ($value === null) return null;` at the top.
- **Custom message pattern is required for every error return:** `$hasCustomMessage = array_key_exists('message', $options); $message = $options['message'] ?? 'Default';` — on every error return `$hasCustomMessage ? $message : 'Detail-Message'`. When `message` is set in the `$options` array, it takes precedence over any detail message, regardless of which rule fails.
- **Factory methods return `$options` arrays, parameter is named `$options` (not `$args`):** `Email::strict()`, `Uuid::v4()`, `Range::between(18, 120)`, `Length::zipCode()`. In `FieldBuilder`, `validates($class, $options)` and `breaksOn($class, $options)` are the two public methods — `breaksOn()` automatically finalizes pending `validates()` calls before registration.
- **`excludeFields()` distinguishes Create vs. Update:** When `id === null` (Create), listed fields are skipped; when `id` is set (Update), ALL fields are validated — including excluded ones. `withIdentityField('customId')` changes the identity field name (default `'id'`). `breaksOn()` respects `excludeFields()` — excluded fields are not evaluated for break conditions. The domain layer **never** imports Validation (Application validates Commands/DTOs before Domain).

## Full reference

https://docs.jardis.io/en/support/validation

<!-- source: jardissupport/workflow -->
# jardissupport/workflow

Multi-step orchestration: Handler chains via `WorkflowConfig`, status and named transitions, typed `WorkflowContext` propagated through the chain.

## Usage essentials

- **Two-class execution:** `$workflow = new Workflow();` or `new Workflow(fn(string $class, mixed $data) => $container->get($class))` (Factory for DI); call `$workflow($config, $data = null)` returns a `WorkflowContextInterface` carrying every handler invocation as a flat handler-stamped entry. Always starts at the first `addNode()` entry — the order of node registration determines the entry point. The engine is stateless and single-shot: iteration over inputs and aggregation across multiple runs are the caller's job.
- **Handler contract is fixed:** Every handler has `__invoke(WorkflowContextInterface $context): WorkflowResult` and MUST return `WorkflowResult` (otherwise `InvalidArgumentException`). Per-run input arrives via the handler factory — typically the factory wires `$data` into the handler's constructor or spawns a fresh BoundedContext with `$data` as payload so the handler can read it via `$this->payload()`. The handler reaches its predecessor's result via `$context->getPrevious()`, any handler's most recent result via `$context->getLatest(SomeHandler::class)`, or all invocations of a handler via `$context->getAll(SomeHandler::class)`. Mantle slots: `$context->reference()` / `setReference()` (pre-loaded data set by the flow's entry companion), `$context->response()` / `setResponse()` (final answer built by the final companion), `$context->getException()` / `setException()` (captured by the orchestrator before re-throw).
- **Transition resolution is a direct status lookup:** `determineNextHandler()` reads `config->getTransitions($currentHandler)`, looks up `transitions[$result->getStatus()]`, and returns it only if that target is itself a registered node (R5 routing-safety — prevents dispatch to a handler whose signature/role does not match the pipeline). No transitions configured, no entry for the status, or an unregistered target → the engine returns control to the caller. No status fallback chain. **Opt-in strict routing** (`new WorkflowConfig(strictRouting: true)`, default `false`) tightens the "no entry for the status" case: `'STATUS' => null` stays a legitimate, silent, declared terminal end, but a status with no transition key at all now raises `JardisSupport\Workflow\Exception\UnroutedStatusException` (`getNode()`/`getStatus()`) instead of silently stopping. The R5 hand-off case is unaffected by the flag either way; default `false` is byte-identical to pre-strict-routing behaviour.
- **`WorkflowResult` as routing VO:** `new WorkflowResult(WorkflowResult::ON_SUCCESS, $data)` or `WorkflowResult::ON_FAIL, $errors`. Constants (all seven, no others): `ON_SUCCESS`/`ON_FAIL`/`ON_TIMEOUT`/`ON_SKIP`/`ON_CANCEL`/`ON_EVENT`/`ON_EXIT`. Accessors: `getStatus()`, `getData()`, `getHandlerFqcn()` (stamped by the engine via `withHandler()` during `append()`).
- **`WorkflowContext` as flat execution log:** Mutable DTO. `append($fqcn, $result)` stamps the result via `withHandler()` and pushes it to the chain — re-invocations of the same handler (retry loops, cross-branch revisits) **never overwrite earlier entries**, so history is lossless. `getPrevious()` = immediate predecessor (null on first call); `getLatest($fqcn)` = most recent invocation of that handler; `getAll($fqcn)` = every invocation of that handler in execution order; `getChain()` = full ordered `list<WorkflowResultInterface>` where every result knows its producing handler. `WorkflowState<TPayload>` is the recommended typed alternative for process orchestrators — implements `WorkflowContextInterface` by delegating to an internal `WorkflowContext`, adding a typed `payload`/`original`/`modified` three-step; pass it as `$workflow($config, $data, $state)`.
- **Fluent Builder is the recommended config approach:** `(new WorkflowBuilder())->node(Class)->onSuccess(Next)->onFail(Other)->onTimeout(Self)->node(Next)->build()` returns `WorkflowConfigInterface`. `WorkflowNodeBuilder` has 7 transition methods (`onSuccess`/`onFail`/`onTimeout`/`onSkip`/`onCancel`/`onEvent`/`onExit`) plus `node()` and `build()`. Alternatively use `WorkflowConfig::addNode(Class, [ON_SUCCESS => Next, ON_FAIL => Other])` directly.
- **Contract and Layer rule:** Public interfaces from `jardissupport/contracts` (`WorkflowInterface`, `WorkflowConfigInterface`, `WorkflowContextInterface`, `WorkflowResultInterface`, `WorkflowBuilderInterface`, `WorkflowNodeBuilderInterface`). The Application Layer builds the config and starts the Workflow — **Domain never imports Workflow**. Handlers stay thin and delegate to domain services; `WorkflowResult` is a return type Contract, not a domain concept.

## Full reference

https://docs.jardis.io/en/support/workflow

<!-- END jardis/dev-skills -->
