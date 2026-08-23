# Deployment to K3s — captured topics for a future undertaking

**Status:** parked (central backlog `jardis/claude/BACKLOG.md`, 2026-08-23).
This document captures the topics discussed on 2026-08-23 (Rolf + Claude,
closing conversation of the `env-konfiguration` run) so the future
undertaking starts from them instead of rediscovering them. It is a seed,
not a plan — every decision below is still open.

## Why a separate undertaking

The template deliberately does not carry deployment. The fundamental gap is
the runtime model: the compose stack **bind-mounts** the project into the
containers (`../:/app`), while K3s needs **baked project images** (code
copied in, `FROM headgent/phpfpm`). That brings its own subjects — a project
Dockerfile, an image build in the pipeline (CD), Helm charts or manifests
instead of compose profiles — each with decisions this template must not
pre-empt ("Nicht überkonstruieren").

Note on the house rule "no Dockerfile in this repo": that rule guards the
*runtime template* (base images come from `devops/php-image-builder`). A
deploy artifact that bakes project code is a different concern and will need
its own, deliberate answer on where such a Dockerfile lives.

## What this project already fulfills (measured 2026-08-23)

The `env-konfiguration` run left the template largely twelve-factor-shaped —
these prerequisites are in place today:

| Prerequisite | Status in the template |
|---|---|
| Configuration entirely ENV-driven | yes — DotEnv cascade over `config/env/`, stack values via `environment:`; maps 1:1 to ConfigMaps |
| Per-environment deltas | yes — `.env.<topic>.{APP_ENV}` delta files (dev/test/prod); a `prod` delta is exactly what a cluster needs |
| Health endpoint for probes | yes — `/health` returns 200 on the bare delivery state (measured by the acceptance gate) |
| Stateless request container | yes — php-fpm holds no local state; sessions/data live behind adapters |
| Worker as a separate process | yes — supervised `worker` service maps to its own Deployment |
| Secrets model cluster-compatible | yes — `secret(...)` values are ciphertext and may live in a ConfigMap; only the key file is a real secret (→ K8s Secret) |
| Image profiles per APP_ENV | yes — the base images switch dev/test/prod behavior (Xdebug/JIT/display_errors) on `APP_ENV` |
| Immutable image pinning | planned — `<version>-<date>` tags, part of project individualization (builder requirement doc §9) |
| CI gates to hang CD onto | yes — `ci.yml` (phpcs/phpstan/tests) + secret guardrail; a CD job can extend it |
| Gitflow discipline | yes — hooks + `do-git-*` skills (dev-skills v1.1.0); deploy tags would slot into this flow |

## Open decisions for the undertaking

1. **Project image build** — where does the project Dockerfile live (template?
   generated? deploy repo?), which registry, who tags.
2. **Packaging** — Helm chart vs. plain manifests vs. kustomize; how compose
   profiles translate (db/cache/broker as in-cluster services, operators, or
   external/managed).
3. **CD trigger** — deploy on tag? on main merge? manual gate? Relation to
   the Gitflow release path.
4. **Secret-key distribution** — how `support/secret.key` reaches the
   cluster (K8s Secret, external secret manager).
5. **Database lifecycle** — migrations, backup, whether the DB belongs in
   the cluster at all.
6. **Relation to Jardis** — whether provisioning/deployment becomes part of
   the Jardis setup surface (MCP tools, Runtime page) or stays a repo-level
   concern.
