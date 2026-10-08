# Working in this project

Derived from jardis-app-template (see `git log` of the initial commit for the
version). These are the rules for working in THIS project; usage is in
`README.md`. Artefacts (code, comments, docs, messages) are written in English.

## Images

Images come from `headgent/phpcli` and `headgent/phpfpm` (Docker Hub); there is
no Dockerfile here. A missing runtime capability is solved in the image
source, not worked around in this repo.

## Opt-in services via compose profiles

Every service beyond `web` + `app` carries a `profiles:` label and is switched
by ONE line: `COMPOSE_PROFILES` in the root `.env` (the machine-writable
interface for Jardis; never edit the YAML). Profiles: `db-mariadb` /
`db-postgres` (alternatives, both on network alias `db` -- never enable both),
`cache`, `rabbitmq`, `kafka`, `mail`, `worker`, `cli`. `stop`/`status` use
`--profile "*"` so no profile is ever missed. The application side is switched
by commented switch-point lines in the service blocks of `.env`.

## One configuration layer, one file

Every configuration value lives exactly once, in the `.env` in the project
root (the Jardis project-layout convention). Three readers share the file and
each reads only the keys it knows:

- **docker compose** interpolates `${...}` in `support/docker-compose.yml` and
  passes each service exactly its keys via `environment:`. Never
  `env_file: ../.env` on a service (nginx would receive the DB passwords).
- **make** does `include .env`; the rule `.env: .env.example` creates it.
- **The kernel** runs `DotEnv::loadPrivate(<projectRoot>)` inside the
  container. The process environment always wins (12-factor III).

The file is divided into blocks (`# === <name> ===`): `stack` and `nginx`
belong to the template, the other eight to the kernel contract. Whoever reads
a key names it; the template mirrors it and invents no names. There is no
cascade: no `.env.local`, no `.env.{APP_ENV}`; the guardrail rejects them.

## Ownership of generated code

`src/` is the builder's OutputDir and is NOT in `.gitignore`: ownership is mixed.

| Path | Owner |
|---|---|
| `.env` | you, from the first `make` -- the `COMPOSE_PROFILES` line stays machine-writable |
| `.env.example` | the template -- the delivered original every clone starts from |
| `src/{BC}/Model/` | generator, hermetic -- overwritten on every build |
| `src/App/bootstrap.php` | written once, then yours (ForceOverwrite:false) |
| `src/{BC}/Closure/`, parts of `Process/` | yours |
| `public/index.php`, `bin/console` | yours, never generated |

A second domain does not appear in `bootstrap.php` by itself: add its facade
line by hand.
