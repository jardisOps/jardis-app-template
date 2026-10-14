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

Platform boundary: packages and generator are immutable (no `vendor/` patches, no generated-code edits); report gaps as issues, meanwhile write interim process-scope code.

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
# jardis/dev-skills

Composer plugin that distributes Jardis skills (into `.claude/skills` and `.agents/skills` of the consumer project) and aggregates `AGENTS.md` from Jardis vendor packages into the consumer project.

<!-- source: jardisadapter/dbconnection -->
# jardisadapter/dbconnection

Database connection pooling with read/write splitting for MySQL, PostgreSQL, and SQLite.

- **Use when:** you need managed database connections, a connection pool, or read/write endpoint splitting
- **How:** load skill `adapter-dbconnection` before touching the API — it carries the usage essentials and pitfalls.
  Full reference: https://docs.jardis.io/en/adapter/dbconnection

<!-- source: jardisadapter/filesystem -->
# jardisadapter/filesystem

Unified filesystem abstraction for local and S3 storage with stream support and visibility control.

- **Use when:** you need to read, write, delete, or stream files across local or cloud storage backends
- **How:** load skill `adapter-filesystem` before touching the API — it carries the usage essentials and pitfalls.
  Full reference: https://docs.jardis.io/en/adapter/filesystem

<!-- source: jardisadapter/http -->
# jardisadapter/http

Outbound HTTP client with a composable middleware pipeline, automatic retry, and cURL transport.

- **Use when:** you need to make outbound HTTP requests with configurable middleware, retry logic, or a custom transport
- **How:** load skill `adapter-http` before touching the API — it carries the usage essentials and pitfalls.
  Full reference: https://docs.jardis.io/en/adapter/http

<!-- source: jardiscore/app -->
# jardiscore/app

HTTP delivery layer for Jardis-generated domains — a FastRoute router behind its own RouterInterface, a PSR-15 middleware pipeline (global and per-route, ready-made RequireSession), and the canonical DomainResponse-to-PSR-7 envelope mapper.

- **Use when:** you need to expose a Jardis-generated domain's read-facades/process()/rules over HTTP, with routing, PSR-15 middleware, and a ready-made {status,data,errors,meta} response envelope
- **How:** load skill `core-app` before touching the API — it carries the usage essentials and pitfalls.
  Full reference: https://docs.jardis.io/en/core/app

<!-- source: jardiscore/kernel -->
# jardiscore/kernel

Immutable infrastructure holder (the DomainKernel) for Jardis-generated domains, with an optional ENV-driven bootstrap packer.

- **Use when:** you need to assemble and inject the infrastructure (db connection, cache, logger, events, HTTP client, mailer, filesystem) that a generated {Domain} facade takes via constructor injection
- **How:** load skill `core-kernel` before touching the API — it carries the usage essentials and pitfalls.
  Full reference: https://docs.jardis.io/en/core/kernel

<!-- source: jardissupport/auth -->
# jardissupport/auth

Session management, password hashing and authentication, and role-based access control with domain events.

- **Use when:** you need user login, session lifecycle management, password verification, or role-based permission checks
- **How:** load skill `support-auth` before touching the API — it carries the usage essentials and pitfalls.
  Full reference: https://docs.jardis.io/en/support/auth

<!-- source: jardissupport/classversion -->
# jardissupport/classversion

Versioned class resolution via namespace injection with proxy caching and fallback chain.

- **Use when:** you need to load domain classes by version, resolve namespace-based variants, or support backward-compatible class loading
- **How:** load skill `support-classversion` before touching the API — it carries the usage essentials and pitfalls.
  Full reference: https://docs.jardis.io/en/support/classversion

<!-- source: jardissupport/contracts -->
# jardissupport/contracts

Shared port interfaces and contracts for all Jardis packages.

- **Use when:** you need the interface definitions that Jardis adapters implement, without depending on any concrete implementation
- **How:** load skill `support-contracts` before touching the API — it carries the usage essentials and pitfalls.
  Full reference: https://docs.jardis.io/en/support/contracts

<!-- source: jardissupport/data -->
# jardissupport/data

Entity hydration from raw data, change tracking, field mapping, and unique identifier generation.

- **Use when:** you need to hydrate domain entities from persistence data, detect changed fields, map column names, or generate unique entity identifiers
- **How:** load skill `support-data` before touching the API — it carries the usage essentials and pitfalls.
  Full reference: https://docs.jardis.io/en/support/data

<!-- source: jardissupport/dbquery -->
# jardissupport/dbquery

Fluent SQL query builder for SELECT, INSERT, UPDATE, DELETE, CTEs, and window functions.

- **Use when:** you need to build SQL queries programmatically without a full ORM, or construct complex queries with CTEs or window functions
- **How:** load skill `support-dbquery` before touching the API — it carries the usage essentials and pitfalls.
  Full reference: https://docs.jardis.io/en/support/dbquery

<!-- source: jardissupport/dotenv -->
# jardissupport/dotenv

.env file loading with type casting, variable substitution, cascade loading, and optional secret integration.

- **Use when:** you need to load and manage environment configuration from .env files with type safety or multi-file cascade
- **How:** load skill `support-dotenv` before touching the API — it carries the usage essentials and pitfalls.
  Full reference: https://docs.jardis.io/en/support/dotenv

<!-- source: jardissupport/factory -->
# jardissupport/factory

Dependency injection container with reflection-based instantiation and optional backend container delegation.

- **Use when:** you need to resolve and wire service dependencies automatically without manual constructor calls
- **How:** load skill `support-factory` before touching the API — it carries the usage essentials and pitfalls.
  Full reference: https://docs.jardis.io/en/support/factory

<!-- source: jardissupport/repository -->
# jardissupport/repository

Generic CRUD repository with raw-data persistence, read/write endpoint splitting, and flexible primary-key strategies.

- **Use when:** you need a data-access layer for persisting and retrieving domain entities with built-in read/write splitting
- **How:** load skill `support-repository` before touching the API — it carries the usage essentials and pitfalls.
  Full reference: https://docs.jardis.io/en/support/repository

<!-- source: jardissupport/secret -->
# jardissupport/secret

Encrypted secret resolution from .env files using AES-256-GCM and Sodium.

- **Use when:** you need to store and resolve encrypted credentials or secrets in environment files with strong cryptographic protection
- **How:** load skill `support-secret` before touching the API — it carries the usage essentials and pitfalls.
  Full reference: https://docs.jardis.io/en/support/secret

<!-- source: jardissupport/validation -->
# jardissupport/validation

Composable object-graph validation with composite validators, field-level rules, and a fluent builder API.

- **Use when:** you need to validate domain objects, input data, or value objects with chainable, reusable validation rules
- **How:** load skill `support-validation` before touching the API — it carries the usage essentials and pitfalls.
  Full reference: https://docs.jardis.io/en/support/validation

<!-- source: jardissupport/workflow -->
# jardissupport/workflow

Multi-step business process orchestration with sequential step execution and process state management.

- **Use when:** you need to model and execute multi-step workflows or business processes with defined step sequences
- **How:** load skill `support-workflow` before touching the API — it carries the usage essentials and pitfalls.
  Full reference: https://docs.jardis.io/en/support/workflow

<!-- END jardis/dev-skills -->

## Code style

Maßgeblich ist `phpcs.xml`; dieser Abschnitt fasst nur zusammen.

- PSR-12; lines at most 120 characters.
- PascalCase for classes, process nodes and events.
- `declare(strict_types=1);` in every PHP file.
- Every class carries a class docblock (a description, no tags); a `@todo` belongs in the method docblock.
- Decimal values are `numeric-string` in the docblock; the native type stays `string`. Needs `ext-bcmath`.
- Run `make phpcbf` before committing; the pre-commit hook runs `phpcs` and `phpstan`.

## Rules at the generated front door

For AI agents working in a customer project built on this template. Each rule only
points at the place that carries the detail — do not restate it elsewhere.

- **Version:** a client selects a class version with the header `X-Jardis-Version`; an
  unknown, empty or repeated value is answered `400`, without the header the baseline
  serves. Never edit `src/Api/*/versions.php` (generated). See README "Versioned front door".
- **Computed fields:** a column marked `computed` is read-only for clients. Never put it
  into a create/update request body — it is answered `400` (`fields[]` reason `unknown`).
  See README "What the generated domain does at the door".
- **409 `duplicate`:** `data.violated` lists the outer fields that collided — mark exactly
  those form fields with a hint. See FRONTEND.md "Notes on the contract".
- **409 `stale_write` / `stale_delete`:** the record changed meanwhile (also on a transient
  database conflict) — reload it and repeat the whole request as a new request.
  See FRONTEND.md "Notes on the contract".
- **Transaction:** every generated process runs in one transaction; a 4xx result rolls it
  back, and domain events enter the answer only after the commit. See README "What the
  generated domain does at the door".
