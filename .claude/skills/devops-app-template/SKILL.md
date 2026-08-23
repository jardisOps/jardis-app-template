---
name: devops-app-template
description: The Docker runtime scaffold for Jardis domains from devops/jardis-app-template — nginx + php-fpm + CLI on the headgent images, opt-in services via compose profiles (MariaDB/Postgres, Redis, RabbitMQ, Kafka, Mailpit, worker), two config layers (.env for the stack, config/env/ DotEnv cascade for the app), runs unconfigured on SQLite with /health answering 200. Use when starting a new Jardis project, adding a service to a stack, wiring builder output into a runtime, or asking what the delivered template already provides. TRIGGER: jardis-app-template, app template, COMPOSE_PROFILES, config/env, Auslieferungszustand, make start, /health, secret guardrail, worker service.
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
values in `config/env/` — one file per kernel adapter: `.env.database`
(the only mandatory `load()`), `.env.cache`, `.env.redis` (shared by cache
layer and logger handler), `.env.logger` (LOG_HANDLERS gate; file, console,
errorlog, syslog, redis, slack/teams/loki/webhook), `.env.mail`, `.env.http`
(tuning only — the client is on once the adapter is installed), and
`.env.messaging` (exactly one transport). The filesystem adapter needs no
env file (keyless packer). `make profiles-check` probes every profile once
(pre-release check of the template itself).

## Two config layers — never mixed

| Layer | File(s) | Read by |
|---|---|---|
| Stack | `.env` (root, versioned — secret guardrail blocks real credentials, `secret(...)` or `*.local` instead) | docker compose + Makefile |
| Application | `config/env/` cascade incl. `.env.<topic>.{APP_ENV}` deltas | DotEnv inside the container (values arrive via `environment:`) |

Layout follows `jardis/claude/wissensbasis/projekt-layout-konvention.md`.

## Daily driving

`make start` / `stop` / `status` / `logs` · `make console ARGS=...` ·
`make shell` · `make install` · QA: `make phpunit` / `phpstan` (level 8) /
`phpcs` (PSR-12) — the same gates run in CI plus the smoke job; gitflow
pre-commit/pre-push hooks are wired via `make` (dev-skills `do-git-*` flow).
Deployment is deliberately NOT in the template (K3s is its own undertaking;
the deploy base image `headgent/phpweb` exists — see
`jardis/claude/wissensbasis/deploy-image-kombiniert-phpweb.md`).
