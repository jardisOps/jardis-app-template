# jardis-app-template

A Docker-based project template for Jardis domains. Clone it, point the Jardis
Builder at `src/`, and the generated code has a runtime around it: nginx plus
php-fpm, a CLI container for workers and tooling, and opt-in services for
database (MariaDB or PostgreSQL), cache, message broker, event stream, mail
and a supervised worker.

It builds no images. The PHP runtimes come from
[`php-image-builder`](https://github.com/jardisOps/php-image-builder) via Docker Hub — this template is
their first consumer on the fpm side.

## Requirements

Docker with compose. The PHP runtimes are pulled from Docker Hub
(`headgent/phpcli` / `headgent/phpfpm`, published since 2026-07-26); no local
image build is needed. The floating `:8.3` tag moves with the monthly
republish — real projects pin the immutable `:8.3-<date>` twin in `.env`.

## Getting started

```sh
make install     # composer install in the phpcli container
make start       # web + app — http://localhost:8080
make console ARGS=kernel   # which services the kernel resolved
make stop
```

The first `make` call creates `.env` from the versioned `.env.example` — the
Makefile remakes the missing include and restarts itself, so there is no
bootstrap step to forget. `.env` is yours from then on; it is gitignored.

If port 8080 is taken, change `HTTP_PORT` in `.env`. There is no second file
to look in: **one file, no overlays** — no `.env.local`, no `.env.{APP_ENV}`.
The guardrail rejects such a file so nobody builds an invisible second layer.
In PHPStorm, point the run configuration's EnvFile at `<root>/.env`.

The delivered state runs on SQLite and needs no database container. That is
possible because the Jardis kernel treats every adapter as optional: an unset
ENV means the kernel carries `null` for that service instead of failing.

## Opt-in services

Every service beyond web + app is a compose profile, selected through **one
line** in the root `.env` — no YAML editing, and the line is machine-writable
for tools that configure the stack:

```sh
COMPOSE_PROFILES=db-mariadb,cache,mail
```

| Profile | Service | Reachable inside the network |
|---|---|---|
| `db-mariadb` | MariaDB | `db:3306` — alias, pick one engine |
| `db-postgres` | PostgreSQL | `db:5432` — alias, pick one engine |
| `cache` | Redis | `cache:6379` |
| `rabbitmq` | RabbitMQ + management UI | `rabbitmq:5672`, UI on `:15672` |
| `kafka` | Kafka (single-node KRaft) | `kafka:9092` |
| `mail` | Mailpit (SMTP catcher) | `mail:1025`, UI on `:8025` |
| `worker` | supervised `bin/console` | set `WORKER_COMMAND` first |

`make start` picks the profiles up automatically. The application side is
switched on separately, in the same `.env` — one block per kernel adapter
(`database`, `redis`, `cache`, `logger`, `http`, `mail`, `messaging`), each
carrying commented, ready-to-uncomment values that point at the service names
above. The `messaging` block picks exactly one transport (`kafka`,
`rabbitmq`, `redis` or `database`) for the kernel's `messaging()` accessor —
its `redis` transport reuses the `redis` block's connection values (one
stack, one Redis). `database` is the simplest queue there is: it reuses the
project's own DB_* connection, no container and no compose profile — see
"Database queue schema" below.

The classic shortcut still works:

```sh
make start-full  # adds db (MariaDB), cache (Redis) and mail (Mailpit)
```

Before a release of the template itself, `make profiles-check` probes every
opt-in profile once — start, wait until healthy, remove — sequentially,
because the two db profiles share the network alias `db`. The worker probe
runs the real `WORKER_COMMAND` once and therefore needs a prior
`make install`.

### Schema einspielen

Table creation is not done by the kernel (Säule 1) — you import a SQL file
over the project's own DB connection (`DB_*` from `.env`, no DB client binary
needed):

1. Export in the Builder (MCP `export_schema_sql_files`, or the UI). It writes
   `.jardis/<Domain>/<Subdomain>/<BC>/Schema.<dialect>.sql`.
2. Import it:
   ```sh
   make db-import SCHEMA=.jardis/<Domain>/<Subdomain>/<BC>/Schema.mariadb.sql
   ```
   or per MCP `run_make_target {target: "db-import", params: {SCHEMA: "…"}}`
   (`run_make_target` has a 10-minute limit). `SCHEMA` is relative to the
   project root and must stay inside it. Underneath it is
   `bin/console db:import <file>`; the first failing statement stops the
   import and is reported by number.

| `DB_DRIVER` | File |
|---|---|
| `mysql` | `Schema.mariadb.sql` or `Schema.mysql.sql` |
| `pgsql` | `Schema.postgres.sql` |
| `sqlite` | `Schema.sqlite.sql` |

A `Schema.<dialect>.sql` that does not match the connection's driver is
refused. Notes:

- The MySQL/MariaDB file creates the database from the export
  (`CREATE DATABASE IF NOT EXISTS` + `USE`): `DB_DATABASE` must match that
  name and `DB_USER` needs the right to create it.
- PostgreSQL expects `DB_DATABASE` to exist already.
- A second run is idempotent (`CREATE … IF NOT EXISTS`). There is no
  migration and no DROP behaviour.

#### Database queue schema

`MESSAGING_TRANSPORT=database` needs the event tables in place first — the
kernel wires publisher and consumer, it does not create schema. They go the
same way:

- **MySQL/MariaDB** — `make db-import SCHEMA=support/sql/domain_events.sql`
  (also ships in `jardisadapter/messaging:src/Schema/domain_events.sql`).
- **SQLite** — save this DDL as a file in the project and import it the same
  way:
  ```sql
  CREATE TABLE IF NOT EXISTS domain_events (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      topic VARCHAR(255) NOT NULL,
      payload TEXT NOT NULL,
      created_at TEXT NOT NULL,
      processed_at TEXT NULL DEFAULT NULL,
      attempts INTEGER NOT NULL DEFAULT 0,
      last_error TEXT NULL DEFAULT NULL
  )
  ```
- **PostgreSQL** — adapt the MySQL schema (`AUTO_INCREMENT` → `SERIAL` /
  `GENERATED ALWAYS AS IDENTITY`, `DATETIME(6)` → `TIMESTAMP(6)`); no
  ready-made file ships for it.

The `domain_event_subscriptions` table (fan-out / consumer groups) is only
needed once you pass a `group` option to `messaging()->consume()` — see
`support/sql/domain_events.sql` for its shape.

## Where things live

| Path | What |
|---|---|
| `public/index.php` | front controller — hand-written, never generated |
| `bin/console` | CLI entry point for workers, cron, one-off commands |
| `src/` | the Builder's OutputDir. `src/App/bootstrap.php` and one folder per domain |
| `support/` | build tooling: compose file, Makefile modules, nginx template |
| `.env.example` | the delivered original — versioned, trivial defaults |
| `.env` | **the** configuration: stack, nginx and application, one block each. Yours, gitignored, created from `.env.example` by `make` |

The rule behind it: every configuration value of this project lives exactly
once, in the project root, in one file — the place every tool already looks
(`docker compose`, `make`, the kernel's DotEnv, your IDE). There is no
separate runtime configuration directory any more, and no overlay file.

## Where the ENV keys come from

**The reader names the key, the template mirrors it.** No key name is
invented here — each one belongs to the code that reads it, and renaming it
locally would only detach the two:

| Block | Named by |
|---|---|
| `stack` | this template — `support/docker-compose.yml` and the `Makefile` |
| `nginx` | `devops/php-image-builder`, `src/shared/nginx/nginx-defaults.env` |
| `app`, `database`, `redis`, `cache`, `logger`, `http`, `mail`, `messaging` | `jardis/core/kernel`, `docs/.env.example` — the keys its Bootstrap handlers read |

The `PHP_*`/`OPCACHE_*`/`XDEBUG_*`/`APCU_SHM_SIZE`/`PCOV_ENABLED`/`APP_ENV`
keys in the `stack` and `app` blocks are named by the image entrypoint
(`php-image-builder`, `src/shared/entrypoint/lib-phpini.sh`).

`.env.example` is a copy of those key sets with this template's own delivery
state (SQLite active, everything else commented out) and its own default
values — never a second, independently maintained list.

`bin/sync-env-from-kernel.sh --check` compares, **block by block**, against
`$JARDIS_KERNEL_DIR/docs/.env.example` (default: the sibling
`jardis/core/kernel` checkout) and exits `0` on parity, `1` if a Kernel key
is missing here or sits in the wrong block, `2` if the Kernel checkout isn't
found. The template's own `stack` and `nginx` blocks are skipped.

A key this template adds to a kernel block on purpose (e.g.
`DB_ROOT_PASSWORD`, which only docker compose reads) carries a **marker**: a
comment line starting with `# Template:` directly above the key — further
comment lines may sit between, a blank line ends the run. Marked extras are
known and stay silent; an unmarked extra is reported as
`ENV-PARITY.extra` (exit stays 0), so a key that arrived by accident still
shows up. `--print-missing` prints any gap as
ready-to-paste commented lines. `make env-parity-check` runs `--check`.

There is no write mode: which block a missing key belongs to is semantic
(a service is switched on as a unit), and only a human — or the Builder's own
generator — can place it correctly.

## Secrets

Credentials never go into the versioned `.env.example` in plaintext. Run
`make generate-key-file` once (creates `support/secret.key`, gitignored),
then `make encrypt VALUE="..."` and put the printed ciphertext into `.env`,
e.g. `MAIL_PASSWORD=secret(...)`. The key file is the only real secret: in
production it is mounted (volume or K8s secret) at the same path, or the key
comes from `APP_SECRET_KEY` in the process environment. The kernel resolves
`secret(...)` values during bootstrap — application code only ever sees
plaintext.

**One limit, enforced by the guardrail:** only keys the *kernel* reads may
carry `secret(...)`. A key that `docker compose` consumes (every `${KEY}` in
`support/docker-compose.yml` — `DB_USER`, `DB_PASSWORD`, `RABBITMQ_*`, the
`NGINX_*` set, …) must stay plaintext: compose has no decryption step and
would hand the ciphertext to the container verbatim. Keep the trivial dev
default there and inject the real value through the process environment,
which always wins over the file.

## Ownership

The project-root/`.env`/`src` layout is a Jardis-wide convention (see
the `projekt-layout-konvention` entry in the Jardis knowledge base) — this
template's part of it is which paths Jardis writes once and which stay
yours from the start:

| Path | Owned by |
|---|---|
| `.env` | you, from the first `make` on — except the `COMPOSE_PROFILES` line, which stays machine-writable for provisioning tools |
| `.env.example` | the template — the delivered original every clone starts from |
| `src/{BC}/Model/` | the Builder, hermetic — overwritten on every build |
| `src/App/bootstrap.php` | the Builder writes it **once** (`ForceOverwrite:false`); yours from that point on |
| `public/index.php`, `bin/console` | you — never generated |

## Wiring in a generated domain

1. Set the Builder's OutputDir to this project's `src/`.
2. Build. The Builder writes `src/App/bootstrap.php` **once** and never
   overwrites it — from then on the file is yours.
3. That file calls `BuildDomainKernelFromEnv` with the project root, the
   same way `public/index.php`/`bin/console` do — the packer reads the one
   `.env` there, so no path needs adjusting relative to where the generated
   file lives.
4. Routes need no wiring: every build also writes
   `src/Api/{Domain}/{openapi.yaml,routes.php}`, and `public/index.php`
   mounts each `routes.php` automatically with the matching facade from the
   `bootstrap.php` array. Hand-written routes still go next to that loop.
   The same loop-style mount collects `src/Api/*/versions.php` for the
   [versioned front door](#versioned-front-door).
5. For typed frontend access to the emitted `openapi.yaml` contract, follow
   the recipe in [`FRONTEND.md`](FRONTEND.md).

The runtime packages generated code imports (`jardissupport/data`, `dbquery`,
`repository`, `validation`, `workflow`) ship preinstalled — a fresh build
runs without any `composer require`. So do the packages of the
[session door](#sessions) (`jardiscore/app` with `RequireSession`,
`jardissupport/contracts`, `jardissupport/auth`) and the three adapters it
and the generated front door lean on: `jardisadapter/dbconnection` (the
`DbConnectionInterface` that `DbTokenStore` needs), `jardisadapter/http` and
`jardisadapter/filesystem`. They stay inert until configured — the kernel
still degrades an unconfigured adapter to `null`.

The other `jardisadapter/*` packages (`cache`, `eventdispatcher`, `logger`,
`mailer`, `messaging`) are optional and NOT preinstalled, matching "every
adapter degrades to `null` when unconfigured" above. When a stack selection
needs one, install it in the phpcli container — works even with the stack
stopped:

```sh
make composer-require PACKAGE=jardisadapter/cache
make composer-require PACKAGE=jardisadapter/mailer VERSION=^1.2
```

`PACKAGE` is required (the target fails fast without it); `VERSION` is an
optional Composer constraint. This is the same target the Jardis Builder
uses when a stack selection references an adapter not yet in
`composer.json`.

Autoloading needs no maintenance: generated domains carry top-level namespaces
(`namespace Sales;`), and `composer.json` maps `""` to `src/`. A second domain
works without touching any config — but note that `bootstrap.php` is not
regenerated, so its facade line has to be added by hand.

Composer warns that an empty PSR-4 prefix costs performance, and that is
correct. It is accepted here on purpose: a template cannot know the domain
names in advance, and a freshly generated class has to load without anyone
remembering to dump the autoloader. For deployment, `composer install
--optimize-autoloader` turns it into a classmap and the cost disappears.

## Versioned front door

A domain can serve more than one class version at its front door. Which
versions exist is declared where the domain is modelled — the `versions` list
in the domain manifest — never in this project.

- **Header:** a client selects a version with `X-Jardis-Version: v2`. Without
  the header the request is served by the baseline (the generated classes
  themselves).
- **`src/Api/{Domain}/versions.php`:** the Builder writes it on every build
  next to `routes.php` and returns the declared list, e.g. `return ['v2'];`
  (an empty list is valid). It is generated — do not edit it.
- **Wiring:** `public/index.php` collects every `src/Api/*/versions.php`, unites
  the lists (unique, stable order) and passes them to `jardiscore/app` as
  `AppConfig(knownVersions: …)`. The package reads and checks the header and
  exposes the result to handlers as the request attribute `jardisVersion`
  (`''` = baseline). Before the first build no file exists: the list is empty
  and the app runs unchanged.
- **Unknown version:** a value that is not in the list, an empty value or a
  repeated header is answered with `400` (validation form, field
  `X-Jardis-Version`, `messageKey` `request.versionUnknown`) before any
  middleware or handler runs. 404/405 are unaffected.
- **Broken file:** a `versions.php` that does not return a list of strings is a
  startup error — the bootstrap boundary answers `500` without details and
  logs the file name.

## Sessions

The template ships the packages for a token session but does **not** switch it
on — `public/index.php` stays open until you wire it. The parts, all from
Jardis packages (nothing is built here):

| Part | Package |
|---|---|
| `RequireSession` PSR-15 middleware (401 envelope, request attributes) | `jardiscore/app` >= 1.4 |
| `TokenAuthenticator`, `SessionManager`, `DbTokenStore`, `Credential` | `jardissupport/auth` >= 1.3 |
| `DbConnectionInterface` for the store | `jardisadapter/dbconnection` |
| `RequestAttribute::SUBJECT` / `::SUBJECT_ID` / `::TENANT` / `::SESSION` | `jardissupport/contracts` >= 2.9 |

**1. Create the token table once** (pick the file of your `DB_DRIVER`: `mysql.sql`,
`pgsql.sql` or `sqlite.sql`):

```sh
make db-import SCHEMA=vendor/jardissupport/auth/resources/sql/mysql.sql
```

The DDL is `CREATE … IF NOT EXISTS` (default table `auth_tokens`), so a second
run changes nothing. Only the SHA-256 hash of a token is stored.

**2. Register the middleware globally** in `public/index.php`, directly after
`$routes = new Routes(…)` and before the route mount loop. `health()` is
anonymous on its own; list further anonymous paths (exact, or a prefix ending in
`*`):

```php
use JardisAdapter\DbConnection\Factory\ConnectionFactory;
use JardisCore\App\Middleware\RequireSession;
use JardisSupport\Auth\Data\Credential;
use JardisSupport\Auth\DbTokenStore;
use JardisSupport\Auth\TokenAuthenticator;
use JardisSupport\Contract\DbConnection\ConnectionPoolInterface;

$db = $kernel->dbConnection(); // pool (read replicas) or plain PDO, null without DB_*
$connection = $db instanceof ConnectionPoolInterface
    ? $db->getWriter()
    : (new ConnectionFactory())->fromPdo($db);
$psr17 = new Psr17Factory();

$routes->middleware(new RequireSession(
    new TokenAuthenticator(new DbTokenStore($connection)),
    Credential::token(...),
    $psr17,
    $psr17,
    ['/auth/login', '/auth/refresh'],
));
```

(Guard `$db === null` first if the stack may run without a database.) The
middleware runs only on a matched route: unknown paths stay 404, wrong methods
405. A missing, malformed, unknown, expired or revoked token is answered
identically with `401` (`WWW-Authenticate: Bearer`); the handler is not called.

**3. Read the session** in a handler from the request attributes
`RequestAttribute::SUBJECT` (`jardisSubject`, e.g. `user:42`) and
`RequestAttribute::TENANT` (`jardisTenant`). The tenant attribute is absent —
never `null` — when the token carries none; the token's `tenant` claim is set at
login: `$sessionManager->create(Subject::from('42'), ['tenant' => 'acme'])`
(`SessionManager` over the same `DbTokenStore`; login itself is yours to build).
Two more attributes come with the session: `RequestAttribute::SUBJECT_ID`
(`jardisSubjectId`, the bare id `42` - no `user:` prefix to split) and
`RequestAttribute::SESSION` (`jardisSession`, the reference of exactly this
session). Log the caller out without parsing the token:
`$sessionManager->invalidateByReference($request->getAttribute(RequestAttribute::SESSION))`
(`invalidateAll('user:42')` still revokes every session of the subject).

`tests/Unit/Session/SessionRecipeTest.php` runs exactly this wiring against
SQLite in memory.

## Upload limits

`upload_max_filesize` and `post_max_size` are `12M` in every PHP container of
the stack (fpm, cli, worker). The php-image-builder images expose no ENV key
for them, so `support/php/60-upload.ini` is mounted into `conf.d` by
`support/docker-compose.yml`. Change both values there (keep `post_max_size`
>= `upload_max_filesize`); `NGINX_CLIENT_MAX_BODY_SIZE` in the root `.env`
is a separate, larger door limit and PHP stays the binding one.

## What the generated domain does at the door

Three behaviours of generated code that a client or a hand-written route has to
know. They come from the Builder, not from this project — nothing to configure.

- **Transaction bracket:** every generated process runs in **one** transaction.
  A run that ends with a 4xx status (validation, not found, conflict, rule
  violation) is rolled back as a whole and still answers that 4xx — the answer
  then carries no domain event. Domain events enter the answer only after the
  commit; the caller publishes them. A process that opts out with
  `runInTransaction: false` in its definition has no bracket (the Builder warns).
- **409 `duplicate` names the hit fields:** the answer's `data.violated` lists the outer
  field names of the unique columns that collided (a child field as
  `<kindProperty>.<field>`); it is `[]` when they are unknown and on every
  `stale_write` / `stale_delete`.
- **Concurrent losers answer 409, also on a transient database conflict:** a snapshot or
  serialization conflict or a deadlock is a `409` `stale_write` / `stale_delete` with
  `violated: []`, not a `500`; the client repeats the request (a new transaction).
- **Computed fields:** a column marked `computed` in the aggregate is not part of
  any create/update request. A request body that names it is answered with `400`
  (`fields[]` reason `unknown`); reads, lists and the aggregate record still
  carry it. It is written only from inside the domain, by a process node
  through the family-internal `Set{Entity}Computed` command.
- **Strict date forms:** `date` is `Y-m-d` and must be a real calendar day,
  `datetime` is RFC 3339 (offsets and `Z` are accepted and normalised to UTC),
  `time` is `H:i:s`. Anything else, also a calendar-invalid value, is answered
  with `400` (`fields[]` reason `type`) before the handler runs. The same gate
  applies to process inputs.

## Skills for AI assistance

`jardis/dev-skills` is a Composer plugin in `require-dev`. On every
`composer install` it scans `vendor/jardis*/` and installs the matching skills
into `.claude/skills/`, plus an aggregated `AGENTS.md`. The installed skills
are deliberately **not** tracked — they come from `vendor/` and would produce
phantom diffs on every update.

`composer.json` sets `agents-md` to `aggregate` explicitly. Without the key the
plugin would apply its `none` default to this project (root package names
`jardis*`, here `jardisops/jardis-app-template`) and empty the managed block of
`AGENTS.md`. The plugin also creates `.agents/` and `.claude/agents/`; they are
meant to be committed, so they are not in `.gitignore`. Agent files for the
other tools (`.codex/`, `.cursor/`, `.gemini/`, `.github/agents/`) are written
only for the hosts listed under `hosts` in `extra."jardis/dev-skills"`; this
template sets no `hosts` key, so the default `claude` applies and a project
adds the tools it actually uses.

After every install or require of a Jardis package (adapters too, for example
after `set_stack_selection`), `AGENTS.md` and `.agents/skills/<package>/` change;
commit them together with `composer.lock` (see the `jardis/dev-skills` README).

## Quality

```sh
make phpunit     # tests
make phpstan     # level 8
make phpcs       # PSR-12 + strict types
make phpcbf      # fix what phpcs can repair automatically
```

The pre-commit hook runs `phpcs` on the staged `*.php` files and `phpstan` over
the whole `phpstan.neon` scope; either failing blocks the commit. Run
`make phpcbf` before committing. The style rules a generated project is held to
are summarised in `AGENTS.md` (below the managed block); `phpcs.xml` is
authoritative. `ext-bcmath` is required for decimal values.

The same three gates run as GitHub Actions on every PR
(`.github/workflows/ci.yml`, house-line copy of the package repos' CI).
No secret is required: the Docker Hub login step only runs when a
`DOCKER_PAT` secret is configured. A fourth, template-specific `smoke` job
guards the delivery-state promise: `make start` with no configuration,
then `/health` must answer 200.

Because `.env.example` is versioned, a secret guardrail
(`support/check-env-secrets.sh`) blocks commits that put real credentials
into it — known token formats and non-trivial plaintext values for
`*_PASSWORD`/`*_SECRET`/`*_TOKEN`/`*_KEY` keys. It also blocks `secret(...)`
on a compose-consumed key (compose cannot decrypt) and any overlay file next
to `.env`. Encrypt kernel-only values as `secret(...)`, or inject the real
value through the process environment. The pre-commit hook runs it on staged
lines, CI on the whole tree.
