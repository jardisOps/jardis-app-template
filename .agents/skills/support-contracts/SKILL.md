---
name: support-contracts
description: All Jardis interface contracts in one package — ports for Auth, ClassVersion, Connection, Data, DbConnection, DbQuery, DotEnv, EventListener, Filesystem, Identity, Kernel, Mailer, Messaging, Repository, Scheduling, Secret, Validation, Workflow. Use when type-hinting against a Jardis port, importing ResponseStatus/EventScope/PkStrategy, resolving JardisSupport\Contract\* namespaces, or deciding between package name jardissupport/contracts (plural) and namespace JardisSupport\Contract (singular).
user-invocable: false
zone: post-active
persona: C
prerequisites: [foundation-architecture, foundation-patterns]
next: []
---

# CONTRACTS_COMPONENT_SKILL
> jardissupport/contracts | NS: `JardisSupport\Contract` | PHP 8.2+ | MIT

## PACKAGE NAME vs. NAMESPACE — read this first
| | Value |
|---|---|
| Composer package | `jardissupport/contracts` — **PLURAL** |
| PSR-4 namespace | `JardisSupport\Contract\*` — **SINGULAR** |
| Install | `composer require jardissupport/contracts` |

**Trap:** `jardissupport/contract` (singular) is the superseded predecessor. It still resolves on
Packagist up to `v2.1.0` — a **higher** number than the valid plural package — so the wrong
`composer require` looks plausible and pulls dead code. Always require the **plural** package; the
namespace stays **singular**. Package name ≠ namespace, that is not a contradiction.

## WHAT IS IN HERE
Contracts only — interfaces, enums, `final readonly` value objects and exception classes. **Zero
implementation code**; the only tests are contract-shape conformance tests (reflection on signatures). Implementations live in the adapter/support packages; this package
is the port layer that keeps the hexagonal dependency direction honest across the ecosystem.

119 contracts across 18 namespaces under `src/`.

## CONTRACT GROUPS
| Namespace | # | Contracts | Implemented by |
|---|---|---|---|
| `Auth` | 23 | `UserLookupInterface` (`lookup(string $identifier): ?CredentialRecordInterface`), `CredentialRecordInterface` (`getPasswordHash(): string`, `getSubject(): string` format `type:id`, `getClaims(): array<string, mixed>`), `SessionClaim` (final constants `TENANT = tenant`, `ROLE = role`), `AuthResultInterface`, `TenantAwareAuthResultInterface` (additive: `getTenant()`; consumers check `instanceof`), `SessionAwareAuthResultInterface` (extends it; `getSubjectId()` bare id, `getSessionReference()` token-hash reference to revoke exactly this session), `RequestAttribute` (final constants `SUBJECT = jardisSubject`, `TENANT = jardisTenant`, `SUBJECT_ID = jardisSubjectId`, `SESSION = jardisSession`, `ROLE = jardisRole` (2.11; set from claim `SessionClaim::ROLE` only when the token carries it) — request attribute names), `AuthenticatorInterface`, `CredentialInterface`, `CredentialType` (enum), `GuardInterface`, `HashedTokenInterface`, `PasswordHasherInterface`, `SessionInterface`, `TokenStoreInterface`, `TokenType` (enum, since 2.11 also `Invitation`). **Auth services (2.11, additive):** `AuthServicesInterface` (exactly four accessors `sessions()`, `tokenIssuer()`, `tokenRedeemer()`, `hasher()`; reached via `DomainKernelInterface::auth(): ?AuthServicesInterface`, `null` = app wires no auth), `SessionManagerInterface` (`create(string $subject, array $claims = [])` / `refresh(string $refreshToken)` → `SessionResultInterface`, `invalidateByReference(string)`, `invalidateAll(string $subject)`; subject = qualified `type:id`; lifecycle events stay auth-internal), `SessionResultInterface` (`getAccessToken`, `getRefreshToken`, `getSession(): SessionInterface`), `TokenIssuerInterface` (`issue(TokenType, string $subject, int $ttlSeconds, array $claims = []): IssuedTokenInterface`), `IssuedTokenInterface` (`getPlain`, `getHashed(): HashedTokenInterface`), `TokenRedeemerInterface` (`peek(string $plain, TokenType $expected)` finds + validates WITHOUT revoking, `redeem(...)` validates + revokes once; both return `HashedTokenInterface`; failure exceptions live in `jardissupport/auth` and are only documented), `RoleAwareAuthResultInterface` (extends `AuthResultInterface`, `getRole(): ?string`). No auth data classes live in contracts. | `support-auth` |
| `ClassVersion` | 2 | `ClassVersionInterface`, `ClassVersionConfigInterface` | `support-classversion` |
| `Connection` | 1 | `ConnectionInterface` — generic connection abstraction | — |
| `Data` | 3 | `HydrationInterface`, `IdentityInterface`, `FieldMapperInterface` | `support-data` |
| `DbConnection` | 4 | `DbConnectionInterface`, `ConnectionPoolInterface`, `DatabaseConfigInterface`, `SavepointInterface` (additive capability: `savepoint`/`releaseSavepoint`/`rollbackToSavepoint`; consumers check `instanceof SavepointInterface`) | `adapter-dbconnection`, `support-repository` |
| `DbQuery` | 18 | `DbQueryBuilderInterface`, `DbInsertBuilderInterface`, `DbUpdateBuilderInterface`, `DbDeleteBuilderInterface`, `DbQueryConditionBuilderInterface`, `DbQueryJsonConditionBuilderInterface`, `DbWhereConditionInterface`, `DbComparisonOperatorsInterface`, `DbJoinInterface`, `DbOrderLimitInterface`, `DbWindowBuilderInterface`, `DbQueryExistsInterface`, `DbSqlGeneratorInterface`, `DbPreparedQueryInterface`, `ExpressionInterface`, `LikePatternInterface`, `QueryResultInterface`, `ExecuteResultInterface` | `support-dbquery` |
| `DotEnv` | 1 | `DotEnvInterface` | `support-dotenv` |
| `EventListener` | 1 | `EventListenerRegistryInterface` — registration side, paired with the PSR-14 dispatcher | `adapter-eventdispatcher` |
| `Filesystem` | 6 | `FilesystemInterface`, `FilesystemReaderInterface`, `FilesystemWriterInterface`, `FilesystemServiceInterface`, `FileInfoInterface`, `FilesystemExceptionInterface` | `adapter-filesystem` |
| `Identity` | 12 | **DEPRECATED since 2.11.0** (generic subdomain, replaced by the Builder blueprint "Access"; nothing removed, removal only in the next major) — `UserStatus` (enum `Active`/`Disabled`); read-only entities `UserInterface` (`getId/getEmail/getDisplayName/getStatus/getCreatedAt`, no hash), `TenantInterface` (`getId/getSlug/getName/getCreatedAt`), `MembershipInterface` (`getUserId/getTenantId/getRole/getCreatedAt`), `GroupInterface` (`getId/getTenantId/getName/getCreatedAt`), `InvitationInterface` (`getId/getTenantId/getEmail/getRole/getTokenHash/getExpiresAt/getAcceptedAt/getRevokedAt/getCreatedAt`); stores `UserStoreInterface` (`store($user, $passwordHash)`, `updateStatus`, `updatePasswordHash`, `findById`, `findByEmail`, `passwordHashOf`), `TenantStoreInterface` (`store`, `findById`, `findBySlug`), `MembershipStoreInterface` (`store`, `updateRole`, `remove`, `find`, `listForUser`, `listForTenant`), `GroupStoreInterface` (`store`, `findById`, `findByName`, `listForTenant`), `GroupMemberStoreInterface` (`add`, `remove`, `removeUserFromTenantGroups`, `listMembers`, `isMember`), `InvitationStoreInterface` (`store`, `findByTokenHash`, `findPending`, `listPendingForTenant`, `markAccepted(id, at): bool` CAS, `markRevoked`) | `support-identity` (jardissupport/identity) |
| `Kernel` | 9 | `DomainKernelInterface`, `GeneratedContextInterface`, `ContextResponseInterface`, `DomainResponseInterface`, `EventScope` (enum), `ResponseStatus` (enum), `ConcurrencyConflictExceptionInterface`, `ValidationExceptionInterface`, `ReportedFailureExceptionInterface` | `core-kernel` + generated domain |
| `Mailer` | 4 | `MailerInterface`, `MailMessageInterface`, `MailTransportInterface`, `MailerExceptionInterface` | `adapter-mailer` |
| `Messaging` | 10 | `MessagePublisherInterface`, `MessageConsumerInterface`, `PublisherInterface`, `ConsumerInterface`, `MessageHandlerInterface`, `MessagingServiceInterface` + `Exception\{MessageException, ConnectionException, ConsumerException, PublishException}` | `adapter-messaging` |
| `Repository` | 6 | `RepositoryInterface`, `PrimaryKey\PkStrategy` (enum), `Exception\{PersistException, RecordNotFoundException, UniqueViolationException, TransientConflictException}` | `support-repository` |
| `Scheduling` | 5 | `ScheduleInterface`, `ScheduledTaskInterface`, `CronExpressionInterface`, `ConstraintInterface`, `ScheduleViolation` (final readonly) | `support-scheduling` |
| `Secret` | 2 | `SecretResolverInterface`, `SecretResolutionException` | `support-secret` |
| `Validation` | 4 | `ValidatorInterface`, `ValueValidatorInterface`, `MissingValueValidatorInterface` (marker), `ValidationResult` (final readonly; `getKinds()`, `KIND_MISSING`\|`KIND_INVALID`, `toFields()`) | `support-validation` |
| `Workflow` | 8 | `WorkflowInterface`, `WorkflowBuilderInterface`, `WorkflowNodeBuilderInterface`, `WorkflowConfigInterface`, `WorkflowContextInterface`, `WorkflowResultInterface`, `WorkflowChainInterface`, `AggregateResponse` (nur die 7 `Workflow*Interface` sind **deprecated seit 2.12.0, Ersatz `jardissupport/workflow` 4.0**; `AggregateResponse` bleibt) | `support-workflow` |

**Validation marker:** a `ValueValidatorInterface` that also implements `MissingValueValidatorInterface` reports an ABSENT value; `ValidationResult::getKinds()` then yields `KIND_MISSING` (`missing`) for its failures, every other validator yields `KIND_INVALID` (`invalid`).

**Flat findings (v2.7.0):** `ValidationResult::toFields(?string $root = null): list<array{field, reason, message}>` flattens `getErrors()` + `getKinds()` — `field` = dot path of the string keys (list indices add no segment), a TOP-LEVEL segment equal to `$root` is stripped (`null` strips nothing), `reason` = `REASON_MISSING` (`'missing'`) exactly for `KIND_MISSING`, else `REASON_INVALID` (`'invalid'`; no kind = `invalid`, no text fallback), `message` = validator text (may be `''`). Answer texts/`messageKey` stay with the caller — do not re-implement the tree walk.

## PSR BOUNDARY
PSR interfaces are **not** re-declared here — they are required as dependencies and used directly:
`psr/container` (PSR-11), `psr/simple-cache` (PSR-16), `psr/log` (PSR-3),
`psr/event-dispatcher` (PSR-14), `psr/http-client` (PSR-18), `psr/clock` (PSR-20), plus `ext-pdo`.
Jardis declares its own contract only where no PSR exists (Mailer, Filesystem, Messaging,
Repository, DbQuery, Workflow, Auth, …).

## ENUMS YOU WILL ACTUALLY IMPORT
```php
use JardisSupport\Contract\Kernel\ResponseStatus;   // int-backed
// Success 200 · Created 201 · NoContent 204 · ValidationError 400 · Unauthorized 401
// Forbidden 403 · NotFound 404 · MethodNotAllowed 405 · Conflict 409
// RuleViolation 422 · InternalError 500

use JardisSupport\Contract\Kernel\EventScope;       // string-backed
// Internal = 'internal'  — aggregate-level, stays within the system
// Domain   = 'domain'    — announced, leaves the system

use JardisSupport\Contract\Repository\PrimaryKey\PkStrategy;  // pure enum
// AUTOINCREMENT · INTEGER · NONE

use JardisSupport\Contract\Auth\CredentialType;     // string-backed
use JardisSupport\Contract\Auth\TokenType;          // string-backed: Access, Refresh, ApiKey, Verification, PasswordReset, Invitation
use JardisSupport\Contract\Auth\SessionClaim;       // final constants: TENANT = 'tenant', ROLE = 'role'
use JardisSupport\Contract\Identity\UserStatus;      // string-backed: Active, Disabled
use JardisSupport\Contract\Auth\RequestAttribute;  // final constants: SUBJECT, TENANT, SUBJECT_ID = 'jardisSubjectId', SESSION = 'jardisSession', ROLE = 'jardisRole'
```

## KERNEL CONTRACTS — GENERATED-CODE SURFACE
- `DomainKernelInterface` — the DomainKernel contract: 13 accessors (12 nullable/typed service
  accessors plus `container()`), among them `auth(): ?AuthServicesInterface` (2.11; adding it
  obliges every kernel implementor to implement it), and `eventListenerRegistry():
  ?EventListenerRegistryInterface`, the registration counterpart to `eventDispatcher()` (without a
  registry, event routing simply stays inactive), and `messaging(): ?MessagingServiceInterface`
  (added v2.0.0). `projectRoot(): string` was renamed from `domainRoot()` in v2.0.0 (BREAKING) —
  it carries the project root (git-clone target), from which the `<projectRoot>/config/env`
  convention derives.
- `GeneratedContextInterface` — deliberately **empty marker**. Every generated `{Domain}Context`
  implements it — no shared base class needed and independent of domain boundaries. There is no
  `BoundedContextInterface` in this package.
- `ConcurrencyConflictExceptionInterface` / `ValidationExceptionInterface` (2.13) and
  `ReportedFailureExceptionInterface` — exception markers, all `extends \Throwable`. Generated exceptions implement them;
  the delivery layer catches the interface. Concurrency: `getReason()`, `getAggregate()`, `getContext()`,
  `getViolated()` (door → 409). Validation: `getFields()` as `list<array{field, reason, message,
  messageKey}>` (door → 400).
  `ReportedFailureExceptionInterface` (no methods): the cause was already reported where it arose;
  the door keeps the status and does not log it again.
- `DomainResponseInterface` / `ContextResponseInterface` — the response vocabulary that generated
  domains and the delivery layer share.

Generated domains import `ResponseStatus` and the Kernel contracts **from here**, not from
`jardiscore/kernel` — that is what lets a generated domain drop the compile-time dependency on the
kernel package's base classes.

## USAGE RULES
- **Type-hint against the contract, never the implementation** — `RepositoryInterface`, not the
  concrete repository class. Constructor injection, per `foundation-architecture` (five pillars, §5).
- A **domain/adapter package declares the port here** and implements it in its own package —
  the dependency arrow points inward to the contract, never the other way round.
- Adding a method to a published interface is a **breaking change** for every implementor —
  treat contract edits as a coordinated fleet release (see `do-git-update`).
  New capabilities therefore arrive as a **new, additive interface** next to the existing one
  (e.g. `SavepointInterface` beside `DbConnectionInterface`) — consumers check `instanceof`; minor release.
- `UniqueViolationException` (subtype of `PersistException`, v2.5.0) carries `getConstraint(): ?string`,
  `getColumns(): list<string>` (empty = driver/dialect delivers no columns, never a partial list) and
  `getTable(): ?string`; constructor `(message, constraint, previous, columns = [], table = null)` — the old
  3-argument call stays valid.
- `TransientConflictException` (subtype of `PersistException`, v2.6.0) marks a transient DB concurrency conflict
  (snapshot/serialization conflict, deadlock — repeating the unit of work in a NEW transaction can succeed);
  carries `getDriverCode(): ?string` (driver code/SQLSTATE as text, e.g. `'1020'`, `'1213'`, `'40001'`, `'40P01'`)
  and the original as `previous`; constructor `(message = '', driverCode = null, previous = null)`. Catch it
  before `PersistException`.
- Exceptions in this package are concrete classes on purpose (`MessageException` hierarchy,
  `PersistException`, `RecordNotFoundException`, `SecretResolutionException`) so callers can catch
  them without depending on an implementation package.

## LAYER
- **Domain / generated code:** imports Kernel + Data + Repository + Workflow contracts.
- **Application:** type-hints ports; never imports an adapter package directly.
- **Infrastructure (adapter/support):** implements the ports declared here.

## REFERENCE
- Package README: `README.md` (package root)
- Sibling package skills: `core-kernel`, `support-repository`, `support-workflow`, `support-data`,
  `adapter-messaging`, `adapter-filesystem`, `support-validation`
- Architecture rules: `foundation-architecture`, `foundation-patterns`
