# jardis-app-template

A Docker-based project template for Jardis domains. Clone it, point the Jardis
Builder at `src/`, and the generated code has a runtime around it: nginx plus
php-fpm, a CLI container for workers and tooling, and opt-in services for
database, cache and mail.

It builds no images. The PHP runtimes come from
[`php-image-builder`](../php-image-builder) via Docker Hub — this template is
their first consumer on the fpm side.

## Requirements

Docker, and a PHP runtime image that starts. The latter is currently the
catch: **the published `headgent/phpfpm` does not boot** — it fails with
`failed to open error_log (/proc/self/fd/2): Permission denied`, the very
defect the new image builder fixes. Until those images are pushed, build them
locally and point the stack at them:

```sh
cd ../php-image-builder && make build      # writes headgent/phpcli + phpfpm locally
```

## Getting started

```sh
make install     # composer install in the phpcli container
make start       # web + app — http://localhost:8080
make console ARGS=kernel   # which services the kernel resolved
make stop
```

If port 8080 is taken, pass another one through the environment — compose
prefers it over the `.env` file:

```sh
HTTP_PORT=8091 make start
```

The delivered state runs on SQLite and needs no database container. That is
possible because the Jardis kernel treats every adapter as optional: an unset
ENV means the kernel carries `null` for that service instead of failing.

For the full stack:

```sh
make start-full  # adds db (MariaDB), cache (Redis) and mail (Mailhog)
```

## Where things live

| Path | What |
|---|---|
| `public/index.php` | front controller — hand-written, never generated |
| `bin/console` | CLI entry point for workers, cron, one-off commands |
| `src/` | the Builder's OutputDir. `src/App/bootstrap.php` and one folder per domain |
| `config/env/` | the DotEnv cascade the kernel reads inside the container |
| `config/nginx/` | the config template, rendered by the official nginx image at start |
| `support/` | build tooling: compose file and Makefile modules |
| `.env` | stack configuration — versioned, secrets belong in `.env.local` |

The rule behind it: the project root holds what a tool looks for on its own
(`compose.yml` via `COMPOSE_FILE`, `Makefile`, `composer.json`, `.env`), and
`config/` holds what is read at runtime.

## Wiring in a generated domain

1. Set the Builder's OutputDir to this project's `src/`.
2. Build. The Builder writes `src/App/bootstrap.php` **once** and never
   overwrites it — from then on the file is yours.
3. In that file, point `BuildDomainKernelFromEnv` at `../../config/env`
   instead of `__DIR__`: the cascade lives in `config/env/`, the generated file
   assumes its own directory.
4. Add routes in `public/index.php`; the domain facades come out of the array
   `bootstrap.php` returns.

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
