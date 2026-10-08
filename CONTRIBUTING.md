# Maintaining jardis-app-template

Not project rules: this file travels with derived projects as provenance only.
Rules for working in a derived project are in `.claude/CLAUDE.md`.

This repo is the Docker runtime for Jardis domains: nginx + php-fpm, a CLI
container, opt-in services. It is cloned and derived -- what lies here ends up
in real projects. Usage is documented in `README.md`.

## Language

Dialog with the user: German. All artefacts in the repo: English -- comments,
docs, messages, README. Keep comments short: at most two to three sentences per
block, the reason instead of the history.

## What this repo does not do

**It builds no images.** `headgent/phpcli` and `headgent/phpfpm` come from
`devops/php-image-builder` via Docker Hub. No `Dockerfile` belongs here -- if a
runtime need is unmet, it is solved there, not worked around here.

**It contains no business logic.** The delivered state is an empty, runnable
scaffold.

## Origin of the conventions

The building blocks come from `devops/provisioning` and are byte-identical with
`devops/orchestration` there -- that is the house line, not chance. Taken over
unchanged: `Makefile`, `support/makefile/{composer,docker,hooks,qa-stack,ssh}.mk`,
`pre-commit-hook.sh`, `phpstan.neon` (level 8), `phpcs.xml` (PSR-12 + strict
types), `phpunit.xml`, `tests/bootstrap.php`.

Deliberate deviations, each with its reason:

| Deviation | Reason |
|---|---|
| `.env.example` is versioned, `.env` is not | A template must run after cloning: the first `make` creates `.env` from `.env.example` (GNU make rebuilds the missing include and restarts). Values are then changed directly in `.env` -- there is no overlay file, the guardrail rejects it. |
| `type: "project"` instead of `"library"` | It is an application scaffold, not a library. |
| `stack.mk` rewritten | The original called `bin/provision`. `orchestration` copied it unchecked and has carried dead targets since. |
| no Xdebug port mapping | Xdebug connects outward to the IDE, it does not listen in the container. |
| several services instead of one | Core: request (fpm), web (nginx), tooling (cli); plus opt-in profiles (see `.claude/CLAUDE.md`). |
| `qa-stack.mk`: one help text | `integration-test` named `tests/fixtures/<provider>/` -- not present here. Noted in the module header. Otherwise byte-identical. |
| `secret.mk` taken from `jardissupport/secret` | Messages in English (repo rule); the PHP call via `docker compose run --rm --no-deps phpcli` already matches the form used here. Otherwise byte-identical, KEY_FILE default `support/secret.key`. |
| `pre-commit-hook.sh`: secret guardrail call | Because `.env.example` is versioned, `support/check-env-secrets.sh` checks staged env lines for real secrets (token formats, long plaintext values for `*_PASSWORD/_SECRET/_TOKEN/_KEY`), for `secret(...)` on a compose-read key (compose cannot decrypt) and for overlay files next to `.env`. Way out: `secret(...)` on a kernel-only key, process environment, or `JARDIS_ALLOW_ENV_SECRET=1`. CI runs the same check (`--tree`). Otherwise byte-identical. |

The service is still called `phpcli`: `docker.mk` and `qa-stack.mk` address it
by name. Renaming it would mean forking both modules.

## The delivered state must run without configuration

`make start` starts only `web` and `app`, the application runs on SQLite. That
works because the kernel degrades every unconfigured adapter to `null` instead
of failing. This property is why the template may be small -- it must not be
given up for a mandatory service.

## Working method

- **No assumptions.** Every finding is proven -- at the file, by measurement.
  This matters especially here: no other Headgent project wires
  `jardiscore/kernel` and `phpfpm` yet, so there is nothing to copy from.
- **Do not over-engineer.** What a target project should decide itself, the
  template does not decide in advance.
- **Code review after every coding step**, no commit without it.
