# jardis-app-template

A Docker-based project template for Jardis domains. Clone it, point the Jardis
Builder at `src/`, and the generated code has a runtime around it: nginx plus
php-fpm, a CLI container for workers and tooling, and opt-in services for
database (MariaDB or PostgreSQL), cache, message broker, event stream, mail
and a supervised worker.

It builds no images. The PHP runtimes come from
[`php-image-builder`](../php-image-builder) via Docker Hub — this template is
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

### Database queue schema

Choosing `MESSAGING_TRANSPORT=database` needs the event tables in place
first — the kernel wires publisher and consumer, it does not create schema
(Säule 1). Table creation is a migration, same as any other table your
domain needs:

- **MySQL/MariaDB** — run the delivered reference schema,
  `support/sql/domain_events.sql` (also ships in
  `jardisadapter/messaging:src/Schema/domain_events.sql`).
- **SQLite** — run this DDL (same shape, SQLite dialect):
  ```sql
  CREATE TABLE domain_events (
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
| `src/{BC}/Aggregate/` | the Builder, hermetic — overwritten on every build |
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
5. For typed frontend access to the emitted `openapi.yaml` contract, follow
   the recipe in [`FRONTEND.md`](FRONTEND.md).

The runtime packages generated code imports (`jardissupport/data`, `dbquery`,
`repository`, `validation`, `workflow`) ship preinstalled — a fresh build
runs without any `composer require`.

Autoloading needs no maintenance: generated domains carry top-level namespaces
(`namespace Sales;`), and `composer.json` maps `""` to `src/`. A second domain
works without touching any config — but note that `bootstrap.php` is not
regenerated, so its facade line has to be added by hand.

Composer warns that an empty PSR-4 prefix costs performance, and that is
correct. It is accepted here on purpose: a template cannot know the domain
names in advance, and a freshly generated class has to load without anyone
remembering to dump the autoloader. For deployment, `composer install
--optimize-autoloader` turns it into a classmap and the cost disappears.

## Skills for AI assistance

`jardis/dev-skills` is a Composer plugin in `require-dev`. On every
`composer install` it scans `vendor/jardis*/` and installs the matching skills
into `.claude/skills/`, plus an aggregated `AGENTS.md`. The installed skills
are deliberately **not** tracked — they come from `vendor/` and would produce
phantom diffs on every update.

## Quality

```sh
make phpunit     # tests
make phpstan     # level 8
make phpcs       # PSR-12 + strict types
```

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
