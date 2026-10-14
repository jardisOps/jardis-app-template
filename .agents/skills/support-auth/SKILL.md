---
name: support-auth
description: Session management, password hashing, RBAC, password and token authentication (subject + tenant), SQL token store, events.
user-invocable: false
zone: post-active
persona: C
prerequisites: [foundation-architecture, foundation-patterns, support-dbquery]
next: []
---

# AUTH_COMPONENT_SKILL
> `jardissupport/auth` | NS: `JardisSupport\Auth` | No HTTP, no JWT, no framework coupling | PHP 8.3+

## ARCHITECTURE
```
SessionManager        create/refresh/invalidate sessions → SessionResult + events
PasswordAuthenticator lookup user (closure or UserLookupInterface) → verify password → create session → AuthenticationResult
TokenIssuer           issue one-time token (type, subject, ttl, claims) → IssuedToken{plain, hashed}
TokenRedeemer         redeem one-time token: find → verify type/expiry → revoke → stored token
TokenAuthenticator    token credential → verified session: subject + subject id + tenant + session reference (TenantAuthResult)
DbTokenStore          TokenStoreInterface on DbConnectionInterface via jardissupport/dbquery (hash only)
PasswordHasher        Argon2id/bcrypt via password_hash() builtins
Guard                 RBAC permission check against immutable Policy
```

## API / SIGNATURES

### SessionManager
```php
new SessionManager(
    TokenStoreInterface $tokenStore,
    VerifyToken $verifyToken = new VerifyToken(),
    int $accessTokenTtl = 3600,
    int $refreshTokenTtl = 604800,
    int $tokenLength = 32,
)

$sm->create(Subject $subject, array $claims = []): SessionResult
    // Returns: SessionResult{session, accessToken, refreshToken, events=[SessionCreated]}
$sm->refresh(string $refreshTokenValue): SessionResult
    // Returns: events=[SessionCreated, SessionRefreshed]
$sm->invalidate(SessionInterface $session): SessionInvalidated
$sm->invalidateByReference(string $sessionReference): SessionInvalidated   // exactly one session incl. its paired refresh token; idempotent
// Pair link: access token claim '_refresh' = refresh hash, refresh token claim '_access' = access hash.
// refresh(): the refreshed session keeps the claims of the original session (both tokens carry them).
// '_'-prefixed claims are internal: never in Session::metadata, never in TenantAuthResult.
$sm->invalidateAll(string $subject): AllSessionsInvalidated
```

### DbTokenStore
```php
new DbTokenStore(DbConnectionInterface $connection, string $table = 'auth_tokens')
// implements TokenStoreInterface; table DDL: resources/sql/{mysql,pgsql,sqlite}.sql (apply once per project)
// timestamps UTC; claims JSON in a TEXT column; store() of an existing hash throws PDOException
```

### TokenAuthenticator
```php
new TokenAuthenticator(
    TokenStoreInterface $tokenStore,
    VerifyToken $verifyToken = new VerifyToken(),
    string $tenantClaim = SessionClaim::TENANT,   // claim of the access token that carries the tenant
    TokenType $expectedType = TokenType::Access,
)
$a->authenticate(Credential::token($plain)): TenantAuthResult   // implements SessionAwareAuthResultInterface
// success: isSuccess(), getSubject() 'user:42', getSubjectId() '42', getTenant() ?string,
//          getSessionReference() = HashedToken::hashOf($plain) (revoke it via $sm->invalidateByReference()), getReason() null
// failure: getReason() 'Invalid token' | 'Token expired' | 'Token revoked' | 'Unsupported credential type'
// Set the tenant at login: $sm->create(Subject::from('42'), ['tenant' => 'acme'])
// Transports store the result under RequestAttribute::SUBJECT / ::SUBJECT_ID / ::TENANT / ::SESSION (jardissupport/contracts >= 2.9)
// Hash of a plain token anywhere: HashedToken::hashOf($plain) — one function, never hash('sha256', …) in the consumer
```

### PasswordHasher
```php
PasswordHasher::argon2id(int $memoryCost = ..., int $timeCost = ..., int $threads = ...): self
PasswordHasher::bcrypt(int $cost = 12): self

$hasher->hash(string $password): string
$hasher->verify(string $password, string $hash): bool
$hasher->needsRehash(string $hash): bool
```

### Guard
```php
new Guard(Policy $policy)

$guard->check(SessionInterface $session, string $permission): bool
$guard->authorize(SessionInterface $session, string $permission): void  // throws UnauthorizedException
// metadata[SessionClaim::ROLE] accepts string or string[] — first match wins
```

### PasswordAuthenticator
```php
new PasswordAuthenticator(
    PasswordHasherInterface $passwordHasher,
    SessionManager $sessionManager,
    Closure|UserLookupInterface $userLookup
    // Closure: fn(string $id): ?array{hash, subject: Subject, claims: array}
    // UserLookupInterface: lookup(string): ?CredentialRecordInterface (getPasswordHash, getSubject 'type:id', getClaims)
    //   mapped by Handler/Authentication/LookupCredentialRecord; subject without 'type:' → 'user:<value>'
)
$auth->authenticate(Credential $credential): AuthenticationResult
// AuthenticationResult->isSuccess(), ->session, ->accessToken, ->events
// Events on success: [SessionCreated, AuthenticationSucceeded]
// Events on failure: [AuthenticationFailed]
```

### TokenIssuer / TokenRedeemer
```php
new TokenIssuer(TokenStoreInterface $tokenStore, int $tokenLength = 32)
$issuer->issue(TokenType $type, Subject $subject, int $ttlSeconds, array $claims = []): IssuedToken
// IssuedToken (readonly): string $plain (hand out once), HashedToken $hashed (as stored)

new TokenRedeemer(TokenStoreInterface $tokenStore, VerifyToken $verifyToken = new VerifyToken())
$redeemer->redeem(string $plainToken, TokenType $expectedType): HashedTokenInterface
// success: token is revoked, stored token (subject, claims) returned
// unknown token or wrong type: InvalidCredentialException; expired: TokenExpiredException;
// already redeemed: TokenRevokedException
```

## DATA CLASSES (`src/Data/`)

| Class | Key fields / notes |
|-------|-------------------|
| `Session` | readonly — `subject`, `tokenHash`, `createdAt`, `expiresAt`, `metadata` |
| `SessionResult` | readonly — `session`, `accessToken`, `refreshToken`, `events: list<object>` |
| `AuthResult` | readonly — `::success()` / `::failure()` factories, `subject: Subject`, `reason` |
| `AuthenticationResult` | readonly, implements `AuthResultInterface` — wraps `AuthResult` + session + tokens + events |
| `Subject` | readonly — `id`, `type`, `toString()` → `"type:id"`, `equals()` |
| `Credential` | readonly — `::password()`, `::apiKey()`, `::token()` |
| `Token` | readonly — `::create(TokenType)`, `expiresIn()`, `forSubject()`, `withClaims()`, `hash()` → `HashedToken` |
| `IssuedToken` | readonly — `plain`, `hashed: HashedToken` |
| `HashedToken` | readonly, implements `HashedTokenInterface` — SHA-256 hash, `revoked`, `withRevoked()` |
| `Permission` | readonly — `::from("resource:action")`, `matches()`, wildcard support |
| `Policy` | readonly — `::fromArray()` or `PolicyBuilder`, `isAllowed(role, permission)`, `getRoles()` |
| `PolicyBuilder` | mutable — `role()->allow()->deny()->includes()->build()` |

## EVENTS (`src/Data/Event/`)
Plain readonly data objects — no interface, no dispatcher dependency. Caller dispatches.

| Event | Fields |
|-------|--------|
| `AuthenticationSucceeded` | `subject`, `timestamp` |
| `AuthenticationFailed` | `credentialType`, `reason`, `timestamp` |
| `SessionCreated` | `subject`, `tokenHash`, `timestamp` |
| `SessionRefreshed` | `subject`, `oldTokenHash`, `newTokenHash`, `timestamp` |
| `SessionInvalidated` | `subject`, `tokenHash`, `timestamp` |
| `AllSessionsInvalidated` | `subject`, `timestamp` |

## EXCEPTIONS (`src/Exception/`)
| Exception | Notes |
|-----------|-------|
| `AuthenticationException` | base, extends `RuntimeException` |
| `InvalidCredentialException` | extends `AuthenticationException` |
| `TokenExpiredException` | extends `AuthenticationException` |
| `TokenRevokedException` | extends `AuthenticationException` |
| `UnauthorizedException` | extends `RuntimeException`, NOT `AuthenticationException` |

## CONTRACTS (`jardissupport/contracts`)
| Interface | Methods |
|-----------|---------|
| `TokenStoreInterface` | `store`, `find`, `revoke`, `revokeAllForSubject`, `deleteExpired` — `DbTokenStore` ships; or implement your own |
| `PasswordHasherInterface` | `hash`, `verify`, `needsRehash` |
| `GuardInterface` | `check`, `authorize` |
| `AuthenticatorInterface` | `authenticate(CredentialInterface): AuthResultInterface` |
| `SessionInterface` | `getSubject`, `getTokenHash`, `isExpired`, `getMetadata` |
| `HashedTokenInterface` | `getHash`, `getType`, `getSubject`, `getClaims`, `getExpiresAt`, `getCreatedAt`, `isExpired`, `isRevoked` |
| `CredentialInterface` | `getType`, `getValue`, `getIdentifier` |
| `AuthResultInterface` | `isSuccess`, `getSubject`, `getReason` |
| `TenantAwareAuthResultInterface` | extends `AuthResultInterface` + `getTenant(): ?string` |
| `UserLookupInterface` | `lookup(string): ?CredentialRecordInterface` (contracts >= 2.10) |
| `CredentialRecordInterface` | `getPasswordHash`, `getSubject`, `getClaims` |
| `SessionClaim` | final constants `TENANT = 'tenant'`, `ROLE = 'role'` |
| `RequestAttribute` | final constants `SUBJECT = 'jardisSubject'`, `TENANT = 'jardisTenant'` |
| `TokenType` (enum) | `Access`, `Refresh`, `ApiKey`, `Verification`, `PasswordReset` |
| `CredentialType` (enum) | `Password`, `ApiKey`, `Token` |

## USAGE — RBAC
```php
$policy = Policy::create()
    ->role('admin')->allow('*')
    ->role('editor')->allow('article:read', 'article:write')->deny('article:delete')
    ->role('moderator')->includes('editor')->allow('comment:delete')
    ->build();

$guard = new Guard($policy);
$guard->check($session, 'article:write');     // bool
$guard->authorize($session, 'article:write'); // void or UnauthorizedException
```

## RULES
- Events created and returned, never dispatched internally. Public error messages stay generic; details only in Events.
- `deny` overrides `allow`; role inheritance via `includes()`.
