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
AuthServices          bundle of SessionManager, TokenIssuer, TokenRedeemer, PasswordHasher (AuthServicesInterface); fromStore() builds it with defaults
SessionManager        implements SessionManagerInterface — create/refresh/invalidate sessions → SessionResult + events
PasswordAuthenticator lookup user (closure or UserLookupInterface) → verify password → create session → AuthenticationResult
TokenIssuer           issue one-time token (type, subject, ttl, claims) → IssuedToken{plain, hashed}
TokenRedeemer         peek (find → verify type/expiry/revocation, no revoke) / redeem (peek + revoke) → stored token
SignedTokenAuthenticator signed access token (no store) → subject + subject id + tenant + session reference `sid` (TenantAuthResult)
TokenAuthenticator    stored token credential (ApiKey/Verification/PasswordReset/issued) → verified session: subject + subject id + tenant + session reference (TenantAuthResult)
DbTokenStore          TokenStoreInterface on DbConnectionInterface via jardissupport/dbquery (hash only)
PasswordHasher        Argon2id/bcrypt via password_hash() builtins
Guard                 RBAC permission check against immutable Policy
HasRight              closure: right in a list of granted rights (Permission::matches), independent of Policy/roles
```

## API / SIGNATURES

### SessionManager
```php
new SessionManager(
    TokenStoreInterface $tokenStore,
    #[\SensitiveParameter] string $accessTokenKey,   // REQUIRED, raw 32 bytes (AUTH_TOKEN_KEY base64-decoded), else InvalidArgumentException
    VerifyToken $verifyToken = new VerifyToken(),
    int $accessTokenTtl = 900,
    int $refreshTokenTtl = 604800,
    int $tokenLength = 32,
)

$sm->create(Subject|string $subject, array $claims = []): SessionResult   // string = qualified 'type:id' (Subject::parse), contract signature
    // Returns: SessionResult{session, accessToken (SIGNED, not stored), refreshToken (opaque, hash in store), events=[SessionCreated]}
    // session->tokenHash = sid = HashedToken::hashOf(refreshToken); payload {typ:"access",sub,sid,iat,exp,claims}
$sm->refresh(string $refreshToken): SessionResult
    // Returns: events=[SessionCreated, SessionRefreshed]
$sm->invalidate(SessionInterface $session): SessionInvalidated
$sm->invalidateByReference(string $sessionReference): void   // since v1.6 void (contract); handler Handler/Session/InvalidateSessionByReference still returns the SessionInvalidated event. Revokes the refresh token of exactly one session (sid); idempotent. An issued access token stays valid until exp.
// refresh(): new refresh + new access (new sid); the refreshed session keeps the claims of the original session.
// '_'-prefixed claims are internal: never in Session::metadata, never in TenantAuthResult.
$sm->invalidateAll(string $subject): void       // since v1.6 void (contract); handler InvalidateAllSessions returns AllSessionsInvalidated
```

### AuthServices
```php
new AuthServices(SessionManagerInterface, TokenIssuerInterface, TokenRedeemerInterface, PasswordHasherInterface)
AuthServices::fromStore(TokenStoreInterface $store, #[\SensitiveParameter] string $accessTokenKey, ?PasswordHasherInterface $hasher = null): self   // defaults, Argon2id hasher, no env reading
$s->sessions() · ->tokenIssuer() · ->tokenRedeemer() · ->hasher()
```

### DbTokenStore
```php
new DbTokenStore(DbConnectionInterface $connection, string $table = 'auth_tokens')
// implements TokenStoreInterface; table DDL: resources/sql/{mysql,pgsql,sqlite}.sql (apply once per project)
// timestamps UTC; claims JSON in a TEXT column; store() of an existing hash throws PDOException
```

### SignedTokenAuthenticator
```php
new SignedTokenAuthenticator(string $accessTokenKey, string $tenantClaim = SessionClaim::TENANT, ?Closure $clock = null)
$a->authenticate(Credential::token($signed)): TenantAuthResult   // no store access
// success: getSubject() 'user:42', getSubjectId() '42', getTenant() ?string (claims[tenantClaim]), getRole() ?string (claims[SessionClaim::ROLE], null if absent), getRights() list<string> (claims[SessionClaim::RIGHTS]; only a list of strings counts, else []), getSessionReference() = sid
// failure: 'Invalid token' (form/MAC/typ/sub/sid) | 'Token expired' (authentic, exp passed) | 'Unsupported credential type'
// Handlers: Handler/Token/SignAccessToken(key)(array $payload): string · VerifyAccessToken(key, ?clock)(string): ?array (throws TokenExpiredException)
// Token = base64url(json) . '.' . base64url(sodium_crypto_auth(segment, key)); no header/alg; key rotation = all access tokens invalid
```

### TokenAuthenticator
(store-based; since v1.5 session access tokens are signed and NOT in the store — use SignedTokenAuthenticator for them)
```php
new TokenAuthenticator(
    TokenStoreInterface $tokenStore,
    VerifyToken $verifyToken = new VerifyToken(),
    string $tenantClaim = SessionClaim::TENANT,   // claim of the stored token that carries the tenant
    TokenType $expectedType = TokenType::Access,
)
$a->authenticate(Credential::token($plain)): TenantAuthResult   // implements SessionAwareAuthResultInterface
// success: isSuccess(), getSubject() 'user:42', getSubjectId() '42', getTenant() ?string,
//          getRole() ?string (stored claim SessionClaim::ROLE), getRights() list<string> (stored claim SessionClaim::RIGHTS, same rule), getSessionReference() = HashedToken::hashOf($plain), getReason() null
// failure: getReason() 'Invalid token' | 'Token expired' | 'Token revoked' | 'Unsupported credential type'
// Tenant claim for session tokens is set at login: $sm->create(Subject::from('42'), ['tenant' => 'acme'])
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

### HasRight
```php
use JardisSupport\Auth\Handler\Authorization\HasRight;
(new HasRight())(array $rights, string $right): bool   // list<string> of resource:action | resource:* | *
// ['*']+any → true · ['a:b']+'a:b' → true · ['a:*']+'a:c' → true · [] → false · ['a:b']+'c:d' → false
// unparsable right (no 'resource:action' and not '*') → InvalidArgumentException from Permission::from, unchanged
// Typical: (new HasRight())($result->getRights(), 'sales.process:approve') with $result a TenantAuthResult
```

### PasswordAuthenticator
```php
new PasswordAuthenticator(
    PasswordHasherInterface $passwordHasher,
    SessionManagerInterface $sessionManager,
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
$issuer->issue(TokenType $type, Subject|string $subject, int $ttlSeconds, array $claims = []): IssuedToken   // implements TokenIssuerInterface; string = 'type:id'
// IssuedToken (readonly): string $plain (hand out once), HashedToken $hashed (as stored); getters getPlain()/getHashed() (IssuedTokenInterface)

new TokenRedeemer(TokenStoreInterface $tokenStore, VerifyToken $verifyToken = new VerifyToken())
$redeemer->peek(string $plain, TokenType $expected): HashedTokenInterface     // same checks as redeem, token is NOT revoked (Handler/Token/PeekToken)
$redeemer->redeem(string $plain, TokenType $expected): HashedTokenInterface   // implements TokenRedeemerInterface
// success: token is revoked, stored token (subject, claims) returned
// unknown token or wrong type: InvalidCredentialException; expired: TokenExpiredException;
// already redeemed: TokenRevokedException
```

## DATA CLASSES (`src/Data/`)

| Class | Key fields / notes |
|-------|-------------------|
| `Session` | readonly — `subject`, `tokenHash`, `createdAt`, `expiresAt`, `metadata` |
| `SessionResult` | readonly, implements `SessionResultInterface` — `session`, `accessToken`, `refreshToken`, `events: list<object>`; getters `getSession/getAccessToken/getRefreshToken` |
| `AuthResult` | readonly — `::success()` / `::failure()` factories, `subject: Subject`, `reason` |
| `AuthenticationResult` | readonly, implements `AuthResultInterface` — wraps `AuthResult` + session + tokens + events |
| `Subject` | readonly — `id`, `type`, `toString()` → `"type:id"`, `equals()` |
| `Credential` | readonly — `::password()`, `::apiKey()`, `::token()` |
| `Token` | readonly — `::create(TokenType)`, `expiresIn()`, `forSubject()`, `withClaims()`, `hash()` → `HashedToken` |
| `IssuedToken` | readonly, implements `IssuedTokenInterface` — `plain`, `hashed: HashedToken`; getters `getPlain/getHashed` |
| `TenantAuthResult` | readonly, implements `SessionAwareAuthResultInterface` + `RoleAwareAuthResultInterface` + `RightsAwareAuthResultInterface` — `::success($subject, $tenant, $sessionReference, $role, $rights = [])`, `getRole()`, `getRights()` |
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
| `SessionInterface` | `getSubject`, `getTokenHash`, `getExpiresAt` (`?DateTimeImmutable`, null = no expiry), `isExpired`, `getMetadata` |
| `HashedTokenInterface` | `getHash`, `getType`, `getSubject`, `getClaims`, `getExpiresAt`, `getCreatedAt`, `isExpired`, `isRevoked` |
| `CredentialInterface` | `getType`, `getValue`, `getIdentifier` |
| `AuthResultInterface` | `isSuccess`, `getSubject`, `getReason` |
| `TenantAwareAuthResultInterface` | extends `AuthResultInterface` + `getTenant(): ?string` |
| `UserLookupInterface` | `lookup(string): ?CredentialRecordInterface` (contracts >= 2.10) |
| `CredentialRecordInterface` | `getPasswordHash`, `getSubject`, `getClaims` |
| `SessionClaim` | final constants `TENANT = 'tenant'`, `ROLE = 'role'`, `RIGHTS = 'rights'` (contracts >= 2.16) |
| `RequestAttribute` | final constants `SUBJECT`, `SUBJECT_ID`, `TENANT`, `SESSION`, `ROLE = 'jardisRole'`, `RIGHTS = 'jardisRights'` |
| `SessionManagerInterface` | `create(string $subject, array $claims)`, `refresh`, `invalidateByReference(): void`, `invalidateAll(): void` (contracts >= 2.11) |
| `TokenIssuerInterface` / `TokenRedeemerInterface` | `issue` / `peek`, `redeem` (contracts >= 2.11) |
| `SessionResultInterface` / `IssuedTokenInterface` | `getAccessToken/getRefreshToken/getSession` / `getPlain/getHashed` |
| `RoleAwareAuthResultInterface` | extends `AuthResultInterface` + `getRole(): ?string` |
| `RightsAwareAuthResultInterface` | extends `AuthResultInterface` + `getRights(): list<string>` (contracts >= 2.16) |
| `AuthServicesInterface` | `sessions`, `tokenIssuer`, `tokenRedeemer`, `hasher` |
| `TokenType` (enum) | `Access`, `Refresh`, `ApiKey`, `Verification`, `PasswordReset`, `Invitation` |
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
