---
name: devops-app-template
description: The Docker runtime scaffold for Jardis domains from devops/jardis-app-template — nginx + php-fpm + CLI on the headgent images, opt-in services via compose profiles (MariaDB/Postgres, Redis, RabbitMQ, Kafka, Mailpit, worker), ONE root .env in blocks (stack, nginx, and the eight kernel blocks) created from the versioned .env.example, runs unconfigured on SQLite with /health answering 200. Use when starting a new Jardis project, adding a service to a stack, wiring builder output into a runtime, or asking what the delivered template already provides. TRIGGER: jardis-app-template, app template, COMPOSE_PROFILES, .env blocks, .env.example, Auslieferungszustand, make start, /health, secret guardrail, make encrypt, generate-key-file, secret.key, worker service.
---

# Jardis app template (devops/jardis-app-template)

Source repo: `/Users/Rolf/Development/headgent/devops/jardis-app-template`
([jardisOps/jardis-app-template](https://github.com/jardisOps/jardis-app-template)).
Full reference: its `README.md`. It is cloned/derived per project — one stack
carries exactly ONE technical environment
(`jardis/claude/wissensbasis/ein-stack-eine-technische-umgebung.md`).

## What a fresh clone gives you

- **Runs without any configuration:** `make start` brings up web (official
  nginx + shipped template) and app (`headgent/phpfpm`); the kernel degrades
  every unconfigured adapter to `null`, persistence falls back to SQLite,
  `/health` answers 200. CI has a smoke job guarding exactly this promise.
- **No image builds, no business logic:** base images come from
  `devops/php-image-builder`; `src/` is the builder's OutputDir (mixed
  ownership — generated aggregate trees are hermetic, `bootstrap.php` is
  written once, `public/index.php`/`bin/console` are hand-owned).
- **Entry points:** `public/index.php` (HTTP) and `bin/console` (CLI/worker/
  cron; `kernel` subcommand shows which adapters resolved — fastest way to
  tell a missing ENV from a missing package).

## Opt-in services — one line, no YAML

`COMPOSE_PROFILES=` in the root `.env` (machine-writable for Jardis tooling):
`db-mariadb` | `db-postgres` (alternatives — both answer on alias `db`, never
both), `cache` (Redis), `rabbitmq`, `kafka` (single-node KRaft), `mail`
(Mailpit), `worker` (supervised `bin/console $WORKER_COMMAND`), `cli`.
On databases: SQLite needs NO profile (file-based, the delivery default);
MariaDB is the shipped MySQL-compatible engine (same `pdo_mysql` driver, same
alias/port — a derived project wanting Oracle MySQL swaps the one service);
the Jardis dbConnection adapter itself supports MySQL/MariaDB/Postgres/SQLite.
The application side is switched separately: commented, ready-to-uncomment
values in the SAME `.env`, one block per kernel adapter — `database`,
`redis` (shared by cache layer and logger handler), `cache`, `logger`
(LOG_HANDLERS gate; file, console, errorlog, syslog, redis,
slack/teams/loki/webhook), `mail`, `http` (tuning only — the client is on
once the adapter is installed), `messaging` (exactly one transport). The
filesystem adapter needs no key at all (keyless packer). `make
profiles-check` probes every profile once (pre-release check of the template
itself).

## One file, in blocks — no overlays

`.env` in the project root holds every value once. `.env.example` is the
versioned original; the first `make` copies it (the Makefile remakes the
missing include and restarts). There is no cascade — no `.env.local`, no
`.env.{APP_ENV}` — and the guardrail rejects such a file.

| Block | Named by | Read by |
|---|---|---|
| `stack` | this template | docker compose + Makefile (images, ports, profiles, PHP/Xdebug values the image entrypoint reads) |
| `nginx` | php-image-builder `src/shared/nginx/nginx-defaults.env` | the official nginx image via envsubst — compose passes the 11 keys in per `environment:`, never `env_file` |
| `app`, `database`, `redis`, `cache`, `logger`, `http`, `mail`, `messaging` | jardiscore/kernel `docs/.env.example` | DotEnv inside the container; process environment always wins |

Rule: **the reader names the key, the template mirrors it.**
`bin/sync-env-from-kernel.sh --check` (= `make env-parity-check`) measures the
eight kernel blocks against the kernel example blockwise: exit 1 on a missing
or misplaced key, 2 without a kernel checkout. A deliberate template extra
(e.g. `DB_ROOT_PASSWORD`) is marked by a `# Template:` comment line directly
above the key and stays silent; an unmarked extra is reported
(`ENV-PARITY.extra`, exit 0). Layout follows
`jardis/claude/wissensbasis/projekt-layout-konvention.md`.

## Secrets

The guardrail (pre-commit + CI) blocks plaintext credentials in the versioned
`.env.example`; the sanctioned way in: `make generate-key-file` (writes
`support/secret.key`, gitignored, chmod 600 — in prod mounted at the same
path, or the key comes from `APP_SECRET_KEY` in the process environment),
then `make encrypt VALUE="..."` (AES-256-GCM; `encrypt-sodium` for Sodium)
and paste the printed `secret(...)` as the value, e.g.
`MAIL_PASSWORD=secret(...)`. jardiscore/kernel >= 2.4 resolves it at
bootstrap — application code sees plaintext only. **Only kernel-read keys
may be encrypted:** the guardrail fails a `secret(...)` on any key docker
compose consumes (it cannot decrypt), and it rejects overlay files.

## Daily driving

`make start` / `stop` / `status` / `logs` · `make console ARGS=...` ·
`make shell` · `make install` · QA: `make phpunit` / `phpstan` (level 8) /
`phpcs` (PSR-12) — the same gates run in CI plus the smoke job; gitflow
pre-commit/pre-push hooks are wired via `make` (dev-skills `do-git-*` flow).
Deployment is deliberately NOT in the template (K3s is its own undertaking;
the deploy base image `headgent/phpweb` exists — see
`jardis/claude/wissensbasis/deploy-image-kombiniert-phpweb.md`).
