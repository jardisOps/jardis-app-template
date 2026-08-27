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

If port 8080 is taken, pass another one **on the make command line** — a
plain environment variable does not survive the Makefile's `include .env`
(make re-exports the file's value), a make variable does (measured):

```sh
make start HTTP_PORT=8091
```

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
switched on separately in `config/env/` — `.env.database`, `.env.cache`,
`.env.redis`, `.env.logger`, `.env.mail`, `.env.http` and
`.env.messaging` carry commented,
ready-to-uncomment values that point at the service names above.
`.env.messaging` picks exactly one transport (`kafka`, `rabbitmq` or
`redis`) for the kernel's `messaging()` accessor — its `redis` transport
reuses `.env.redis`'s connection values (one stack, one Redis).

The classic shortcut still works:

```sh
make start-full  # adds db (MariaDB), cache (Redis) and mail (Mailpit)
```

Before a release of the template itself, `make profiles-check` probes every
opt-in profile once — start, wait until healthy, remove — sequentially,
because the two db profiles share the network alias `db`. The worker probe
runs the real `WORKER_COMMAND` once and therefore needs a prior
`make install`.

## Where things live

| Path | What |
|---|---|
| `public/index.php` | front controller — hand-written, never generated |
| `bin/console` | CLI entry point for workers, cron, one-off commands |
| `src/` | the Builder's OutputDir. `src/App/bootstrap.php` and one folder per domain |
| `config/env/` | the DotEnv cascade the kernel reads inside the container |
| `config/nginx/` | the config template, rendered by the official nginx image at start |
| `support/` | build tooling: compose file and Makefile modules |
| `.env` | stack configuration — versioned, trivial defaults; change values in place (compose reads only this file, `.env.local` does not apply here) |

The rule behind it: the project root holds what a tool looks for on its own
(`compose.yml` via `COMPOSE_FILE`, `Makefile`, `composer.json`, `.env`), and
`config/` holds what is read at runtime.

## Secrets

Credentials never go into the versioned env files in plaintext. Run
`make generate-key-file` once (creates `support/secret.key`, gitignored),
then `make encrypt VALUE="..."` and put the printed ciphertext into the
`config/env/` file, e.g. `DB_PASSWORD=secret(...)`. The key file is the only
real secret: in production it is mounted (volume or K8s secret) at the same
path. The kernel (jardiscore/kernel >= 2.1) resolves `secret(...)` values
during bootstrap — application code only ever sees plaintext.

## Ownership

The project-root/`config/env`/`src` layout is a Jardis-wide convention (see
the `projekt-layout-konvention` entry in the Jardis knowledge base) — this
template's part of it is which paths Jardis writes once and which stay
yours from the start:

| Path | Owned by |
|---|---|
| `.env` | you, once cloned — except the `COMPOSE_PROFILES` line, which stays machine-writable for provisioning tools |
| `config/env/` | you, once cloned |
| `src/{BC}/Aggregate/` | the Builder, hermetic — overwritten on every build |
| `src/App/bootstrap.php` | the Builder writes it **once** (`ForceOverwrite:false`); yours from that point on |
| `public/index.php`, `bin/console` | you — never generated |

## Wiring in a generated domain

1. Set the Builder's OutputDir to this project's `src/`.
2. Build. The Builder writes `src/App/bootstrap.php` **once** and never
   overwrites it — from then on the file is yours.
3. That file calls `BuildDomainKernelFromEnv` with the project root, the
   same way `public/index.php`/`bin/console` do — the packer derives
   `config/env/` itself, so no path needs adjusting relative to where the
   generated file lives.
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

Because `.env` and `config/env/` are versioned, a secret guardrail
(`support/check-env-secrets.sh`) blocks commits that put real credentials
into them — known token formats and non-trivial plaintext values for
`*_PASSWORD`/`*_SECRET`/`*_TOKEN`/`*_KEY` keys. Encrypt such values as
`secret(...)` or keep them in gitignored `*.local` files. The pre-commit
hook runs it on staged lines, CI on the whole tree.
