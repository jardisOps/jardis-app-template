<!-- BEGIN jardis/dev-skills — managed block, do not edit by hand -->

# Process router

Wissenspool: `.claude/wissen/INDEX.md` — vor Entscheiden lesen, Vermerk-Pflicht

Pick the lowest tier that fits a task and name it in one line — a present tier-3 criterion is never a matter of fit. Process is a means against size and risk, not a default: escalate only with a named reason. Two or more subtasks are never a single action. Tier 3 is not a judgement call: one tier-3 criterion in the task (an open decision, a public API or observable behaviour, data, migration, security) makes it tier 3 however small the change — start `process-concept`. Name tier 1 or 2 only after loading `process-choose-tier`. At tier 2 the main dialogue delegates every subtask to a sub-agent and verifies; it never implements itself. Skill first, then source code, then ask (`foundation-working-principles`). Before proposing anything new, ask what the environment already does (`process-check-existing`). Before acting in a phase, load its skill in full through the skill mechanism; reading parts of a skill file through the shell does not count. Before any write to a page of the knowledge pool, load `knowledge-record-decision`.

Merge and push are gates of the human: the session never merges or pushes on its own, it stops and asks. The session creates the branch and the commits itself: work happens on a `feature/*` or `fix/*` branch cut from `develop`, a hotfix on a `hotfix/*` branch cut from `main`, never directly on `develop` or `main`. It commits only after checking that every changed file belongs to the scope of the task, and reports a commit only once it stands in `git log`; no commit carries a `Co-Authored-By` line or any other tool attribution. A halt names exactly one git gate (merge or push), and the next one only once the step before is done; the `git-*` skills stay started by the human, the session uses plain git.

## Tiers

| Tier | Task | Skill |
|---|---|---|
| 0 Answer | question, explanation, typo | answer directly |
| 1 Single action | one thing, one place, no open decision | `process-choose-tier` |
| 2 Small assignment | one big or isolated thing, or several subtasks | `process-choose-tier` |
| 3 Undertaking | open decision, dependent steps, new architecture, public API, data, security | `process-concept` |

## Phase to skill

| Phase | Skill |
|---|---|
| Concept (stage 0) | `process-concept` |
| PRD (stage 1) | `process-write-prd` |
| Plan (stage 2) | `process-write-plan` |
| Build a stage | `process-run-stage` |
| Check a stage, accept the whole | `process-verify` |
| Close | `process-close` |
| Continue after an interruption | `process-resume` |
| Review board for PRD or plan | `process-review-board` |
| Review a change before a commit | `code-review-change` |
| Record a decision or a lesson | `knowledge-record-decision` |
| Keep the knowledge pool | `knowledge-maintain-pool` |
| Branch, commit, push, pull request | `git-start-branch`, `git-commit-change`, `git-push-and-open-pr` |
| Repository set-up and compliance | `git-setup-repository`, `git-check-compliance` |

## Jardis projects

Start with `start-orientation`: it walks packages, schema, design and code and names the skill for every question. Before hand-building a reusable component, check `packages-find-existing`. Rules: `foundation-architecture`, `foundation-patterns`, `foundation-testing`, `foundation-php`, `foundation-frontend-review`.

## Reviewer roles

Sources live in `process-review-board/reviewers/`; the main session picks roles with a reason and runs them blind, in parallel where the tool can, otherwise one after another with a fresh context each.

- PRD: skeptic, domain expert, strategic DDD, frontend UX
- Plan: architecture, DDD tactics, PHP, test strategy, packages, frontend architecture, frontend types, frontend a11y, frontend tests, frontend UX
- Stage: stage verifier, acceptance gate
- Support: existing-capability check, open-question gate, failure diagnosis

# Jardis packages — AI agent context

Aggregated by `jardis/dev-skills`. Run `composer install` to refresh.

Before hand-building a reusable building block, consult the `packages-find-existing` skill to check for an installable Jardis package. For the full workflow from schema to implementation, start with the `start-orientation` skill.

<!-- source: jardis/dev-skills -->
# jardis/dev-skills — Agent Notes

Composer plugin that distributes Jardis skills (into `.claude/skills` and `.agents/skills` of the consumer project) and aggregates `AGENTS.md` from Jardis vendor packages into the consumer project.

## What this package contributes

- **Discovery** of skills from `vendor/jardis*/*/.claude/skills/*/SKILL.md` and from this repo's own `skills/` directory.
- **Bundle skills** — 33 folders in `skills/`, listed in `src/Data/BundleSkills.php`. Each declares `profile: core` or `profile: jardis` in its frontmatter; the installed set is the skills of the resolved profile (`core` 25, `jardis` 33 — the eight `jardis` skills are `start-orientation`, `design-*`, `generated-code-*`), unless `extra."jardis/dev-skills"."bundled-skills"` narrows it (`false` or `[]` keeps only the mandatory groups `foundation-*` and `process-*`). Resolution (`Handler/Install/ResolveInstallProfile`): the key `extra."jardis/dev-skills"."profile"`, else an installation from before 1.7.0 (manifest without `profile`, or legacy folders) keeps `jardis` and is not marked, else detection of a `vendor/jardis*/*` package other than `jardis/dev-skills`. The router (`profile:` marker areas) and the reviewer shells (17 in `core`) follow the profile. By area prefix:
  - `start-orientation` — entry point and routing into the other skills
  - `packages-find-existing` — package catalog; generated from `catalog/manifest.json` (`make generate-catalog`), never edited by hand
  - `design-draft-schema`, `design-headless-mcp` — drafting a Schema.json; driving the Designer through `jardis mcp`
  - `generated-code-extend`, `-wire-transport`, `-versioning`, `-workflow-api`, `-recipes` — working with Designer-generated PHP code
  - `foundation-architecture`, `-patterns`, `-testing`, `-frontend-review`, `-php`, `-working-principles` — cross-cutting rules
  - `git-setup-repository`, `-start-branch`, `-commit-change`, `-push-and-open-pr`, `-check-compliance` — Gitflow workflow
  - `knowledge-maintain-pool`, `knowledge-record-decision` — the decision pool of a project
  - `process-*` (ten skills, reviewer sources in `skills/process-review-board/reviewers/`) and `code-review-change` — the development process
  - The 18 names of 1.3.x (`src/Data/RenamedSkills.php`) live on as redirect skills until 2.0.0.
- **Managed skills via manifest:** the plugin installs and removes only the skill folders listed in `.claude/skills/.jardis-managed.json` (`src/Data/Manifest.php`); folders of the user are never touched. Without a manifest, `BundleSkills::NAMES` plus the old names govern, never a name prefix. Locally changed folders are backed up to `.claude/.jardis-backup/`.
- **AGENTS.md aggregation** between markers `<!-- BEGIN jardis/dev-skills ... -->` / `<!-- END jardis/dev-skills -->`; the router text (`router/AGENTS-router.md`) opens the block. User content outside the markers is preserved. A source package's own managed block is stripped before embedding (`Handler/Install/StripManagedBlock`), so the result is always a single, non-nested block. An `AGENTS.md` that is a link, or lies behind one, is not written; a pre-existing `AGENTS.md` without markers is moved to `AGENTS.md.backup`.
- **`agents-md` key** (`src/Data/AgentsMdMode.php`, resolved in `Handler/Discovery/ResolveAgentsMdMode`): `aggregate` or `none`; without the key the default is `none` when the vendor part of the root package name begins with `jardis`, else `aggregate`. With `none` there is no block, no `CLAUDE.md` import and no Gemini entry, and `Handler/Install/RetireAgentsMd` removes what an earlier run left; skills, shells, manifest and exclude block run unchanged.
- **Add-ons** (each one only warns on failure): `CLAUDE.md` import block, `.gemini/settings.json` context entry, reviewer shells for five tools (`src/Data/ShellFormat.php`), Git exclude block (`process-docs`). The git rules of the router have three stances through `extra."jardis/dev-skills"."git-rules"`: `true` (default, branch, commit and merge are gates of the human), `"delegated"` (the session creates branch and commits itself, merge and push stay gates of the human), `false` (no git rules).
- **Tools for consumers:** `scripts/pool-check.php` (knowledge pool checker, linked as `vendor/bin/pool-check.php`), `scripts/commit-msg` and `scripts/install-commit-msg-hook` (the hook warns, it never rejects a commit), `scripts/check-commit-messages` (CI range check).

## Working in this repo

- **Architecture:** Closure-Orchestrator — `src/SkillInstaller.php` and `src/SkillUninstaller.php` compose the sub-orchestrators `src/InstallSkills.php`, `src/InstallAddons.php` and `src/UninstallAddons.php` and the handlers in `src/Handler/`. Data classes under `src/Data/`. No business logic in orchestrators.
- **Plugin entry:** `src/Plugin.php` (`Composer\Plugin\PluginInterface` + `EventSubscriberInterface`) wires `post-install-cmd`, `post-update-cmd`, `pre-package-uninstall`.
- **Tests:** Integration > Unit. New tests go under `tests/Integration/<area>/<ClassName>Test.php`. Use `tests/Support/TempProject` for filesystem fixtures.
- **Quality gates:** `make phpunit`, `make phpstan` (Level 8), `make phpcs` (PSR-12), `make validate-skills`, `make generate-catalog-check`, `make check-public-text`. All must be green. Before a release tag: `make check-changelog-top VERSION=<x.y.z>`.
- **Skill authoring:** Every bundled `SKILL.md` follows `docs/SKILL-FORMAT.md` v6 — frontmatter `name`/`description`/`zone`/`persona`/`profile`/`prerequisites`/`next`, single-line description (≤175 words hard limit, new skills ≤45), topical numbered body sections (`### 1. …`), per-zone line budget (`crosscut` 225, `pre`/`post-reference` 250, `process` 250, `discovery` 150, `post-active` 700). Long working artefacts live in a sibling `skills/<name>/examples/` directory and do not count against the body budget.

## Don'ts

- Do not add, rename or remove a bundle skill without updating `src/Data/BundleSkills.php` (and `src/Data/RenamedSkills.php` for a rename). Do not introduce a new area prefix without updating `docs/SKILL-FORMAT.md` §2.
- Do not edit a generated AGENTS.md block in a consumer project — the plugin overwrites it on next install.
- Do not bypass `TempProject` in tests with raw `tempnam()` / hardcoded paths.
- Do not duplicate content across bundle skills. Patterns live only in `foundation-patterns`, architecture only in `foundation-architecture`, frontend review rules only in `foundation-frontend-review`, test rules only in `foundation-testing`, generated-code layout only in `generated-code-extend` §1, transport wiring only in `generated-code-wire-transport`. Other skills link.
- Do not let the commit-msg hook or any other dev-skills tool block a commit: they warn and exit 0.

## Pointers

- README (consumer-facing): `README.md`
- Skill format spec: `docs/SKILL-FORMAT.md`
- Skill format validator: `bin/validate-skills.php` (run via `make validate-skills`)
- Router text of the managed block: `router/AGENTS-router.md`
- Package catalog source: `catalog/manifest.json`
- Release notes: `CHANGELOG.md`

<!-- source: jardiscore/app -->
# jardiscore/app

HTTP-delivery layer for Jardis-generated domains: FastRoute behind an own `Contract\RouterInterface`, a PSR-15 middleware pipeline, one canonical `DomainResponse` → PSR-7 mapper (`{status, data, errors, meta}` envelope), and a thin bootstrap bridge around the DomainKernel (`BuildDomainKernelFromEnv`). No Jardis domain ever imports this package (Wall Freedom) — a third-party framework can answer the same envelope contract without it.

## Usage essentials

- **Main classes (`JardisCore\App`):** `Routes` (registration: `get/post/put/patch/delete`, `middleware()`, `health()`), `Router` (dispatch, FastRoute never leaks past it), `App` (orchestrator: pure `handle(ServerRequestInterface)`, impure `run()`), `Config\AppConfig` (`debug` flag, injected by the bootstrap — never reads ENV itself).
- **Bootstrap recipe:** `BuildDomainKernelFromEnv` → `DomainKernel` → generated domain(s) `new {Domain}($kernel)` (not part of this package) → `Routes` + handlers → `App` → `run()`. Full runnable `public/index.php`: `docs/getting-started.md`.
- **Handlers return `DomainResponseInterface` or a PSR-7 `ResponseInterface`** — anything else throws `UnresolvableHandlerResult` and ends in the generic 500.
- **One envelope for every answer:** `MapDomainResponse` maps each `DomainResponseInterface` (`ResponseStatus` 1:1 to the HTTP code, e.g. `RuleViolation` = 422); 204 has no body; `BuildErrorResponse` is the only place assembling the envelope, also for 404 / 405 (with `Allow` header) / 500. `getEvents()` is never part of the client-facing envelope.
- **Errors:** `HandleThrowable` is the outermost boundary — `InvalidJsonBody` → 400, any other `Throwable` → generic 500 (details only with `AppConfig::$debug === true`), the full exception always goes to the PSR-3 logger.
- **Request body:** read from `php://input` exactly once into a seekable stream; `ParseJsonBody` is lazy and leaves `getBody()` byte-identical (webhook-HMAC case).
- **Don't:** read ENV inside `AppConfig`/handlers (inject `debug` from the bootstrap); hardwire PSR-17 factories inside handler/business code (receive the interfaces; `Nyholm\Psr7\Factory\Psr17Factory` is only `App`'s injectable default); expect this package to solve body-size limits, Trusted-Proxy/`X-Forwarded-*` or `display_errors=Off` (webserver/FPM/proxy responsibility); expect OPTIONS to be added automatically; import this package from a domain.
- **Skill:** `core-app` (`.claude/skills/core-app/SKILL.md`) — consult it before using the API.

## Full reference

https://docs.jardis.io/en/core/app

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

<!-- source: jardissupport/contracts -->
# jardissupport/contracts

All Jardis interface contracts in one package — ports for Auth, ClassVersion, Connection, Data, DbConnection, DbQuery, DotEnv, EventListener, Filesystem, Kernel, Mailer, Messaging, Repository, Scheduling, Secret, Validation and Workflow (88 contracts across 17 namespaces). Interfaces, enums, `final readonly` value objects and exception classes only — no implementation code.

## Usage essentials

- **Package name vs. namespace:** Composer package `jardissupport/contracts` (**plural**), namespace `JardisSupport\Contract\*` (**singular**) — package name ≠ namespace, that is not a contradiction. `jardissupport/contract` (singular) is the superseded predecessor; always `composer require jardissupport/contracts`.
- **Type-hint against the contract, never the implementation** — a domain/adapter package declares the port here and implements it in its own package; the dependency arrow points inward to the contract.
- **Enums you will import:** `Kernel\ResponseStatus` (int-backed, `Success` 200 … `RuleViolation` 422, `InternalError` 500), `Kernel\EventScope` (`Internal`/`Domain`), `Repository\PrimaryKey\PkStrategy`, `Auth\CredentialType`, `Auth\TokenType`.
- **Kernel contracts for generated code:** `DomainKernelInterface` (12 accessors incl. `eventListenerRegistry()` and `messaging()`; `projectRoot()` was renamed from `domainRoot()` in v2.0.0), `GeneratedContextInterface` (deliberately empty marker implemented by every generated `{Domain}Context`), `DomainResponseInterface`, `ContextResponseInterface`. Generated domains import these from here, not from `jardiscore/kernel`.
- **PSR boundary:** PSR interfaces (PSR-3, 11, 14, 16, 18) are not re-declared here; Jardis declares its own contract only where no PSR exists.
- **Don't:** add a method to a published interface casually — it is a breaking change for every implementor (coordinated fleet release); put implementation code or tests into this package; require the singular `jardissupport/contract`.
- **Skill:** `support-contracts` (`.claude/skills/support-contracts/SKILL.md`) — consult it before using the API.

## Full reference

https://docs.jardis.io/en/support/contracts

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

Object graph validation via Reflection — no annotations, no interfaces on domain classes, `ObjectValidator` + `ValidatorRegistry` + `CompositeFieldValidator` compose 22 stateless `ValueValidator` singletons.

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
- **Handler contract is fixed:** Every handler has `__invoke(WorkflowContextInterface $context): WorkflowResult` and MUST return `WorkflowResult` (otherwise `InvalidArgumentException`). Per-run input arrives via the handler factory — typically the factory wires `$data` into the handler's constructor. The handler reaches its predecessor's result via `$context->getPrevious()`, any handler's most recent result via `$context->getLatest(SomeHandler::class)`, or all invocations of a handler via `$context->getAll(SomeHandler::class)`. Mantle slots: `$context->reference()` / `setReference()` (pre-loaded data set by the flow's entry companion), `$context->response()` / `setResponse()` (final answer built by the final companion), `$context->getException()` / `setException()` (captured by the orchestrator before re-throw).
- **Transition resolution is a direct status lookup:** `determineNextHandler()` reads `config->getTransitions($currentHandler)`, looks up `transitions[$result->getStatus()]`, and returns it only if that target is itself a registered node (R5 routing-safety — prevents dispatch to a handler whose signature/role does not match the pipeline). No transitions configured, no entry for the status, or an unregistered target → the engine returns control to the caller. No status fallback chain. **Opt-in strict routing** (`new WorkflowConfig(strictRouting: true)`, default `false`) tightens the "no entry for the status" case: `'STATUS' => null` stays a legitimate, silent, declared terminal end, but a status with no transition key at all now raises `JardisSupport\Workflow\Exception\UnroutedStatusException` (`getNode()`/`getStatus()`) instead of silently stopping. The R5 hand-off case is unaffected by the flag either way; default `false` is byte-identical to pre-strict-routing behaviour.
- **`WorkflowResult` as routing VO:** `new WorkflowResult(WorkflowResult::ON_SUCCESS, $data)` or `WorkflowResult::ON_FAIL, $errors`. Constants (all seven, no others): `ON_SUCCESS`/`ON_FAIL`/`ON_TIMEOUT`/`ON_SKIP`/`ON_CANCEL`/`ON_EVENT`/`ON_EXIT`. Accessors: `getStatus()`, `getData()`, `getHandlerFqcn()` (stamped by the engine via `withHandler()` during `append()`).
- **`WorkflowContext` as flat execution log:** Mutable DTO. `append($fqcn, $result)` stamps the result via `withHandler()` and pushes it to the chain — re-invocations of the same handler (retry loops, cross-branch revisits) **never overwrite earlier entries**, so history is lossless. `getPrevious()` = immediate predecessor (null on first call); `getLatest($fqcn)` = most recent invocation of that handler; `getAll($fqcn)` = every invocation of that handler in execution order; `getChain()` = full ordered `list<WorkflowResultInterface>` where every result knows its producing handler. `WorkflowState<TPayload>` is the recommended typed alternative for process orchestrators — implements `WorkflowContextInterface` by delegating to an internal `WorkflowContext`, adding a typed `payload`/`original`/`modified` three-step; pass it as `$workflow($config, $data, $state)`.
- **Fluent Builder is the recommended config approach:** `(new WorkflowBuilder())->node(Class)->onSuccess(Next)->onFail(Other)->onTimeout(Self)->node(Next)->build()` returns `WorkflowConfigInterface`. `WorkflowNodeBuilder` has 7 transition methods (`onSuccess`/`onFail`/`onTimeout`/`onSkip`/`onCancel`/`onEvent`/`onExit`) plus `node()` and `build()`. Alternatively use `WorkflowConfig::addNode(Class, [ON_SUCCESS => Next, ON_FAIL => Other])` directly.
- **Contract and Layer rule:** Public interfaces from `jardissupport/contracts` (`WorkflowInterface`, `WorkflowConfigInterface`, `WorkflowContextInterface`, `WorkflowResultInterface`, `WorkflowBuilderInterface`, `WorkflowNodeBuilderInterface`). The Application Layer builds the config and starts the Workflow — **Domain never imports Workflow**. Handlers stay thin and delegate to domain services; `WorkflowResult` is a return type Contract, not a domain concept.

## Full reference

https://docs.jardis.io/en/support/workflow

<!-- END jardis/dev-skills -->
