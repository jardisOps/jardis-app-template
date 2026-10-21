---
name: core-app
description: jardiscore/app v2 - the HTTP-Delivery layer for Jardis domains, a FastRoute router hidden behind an own RouterInterface, a PSR-15 middleware pipeline, the canonical DomainResponse-to-PSR-7 envelope mapper, and a DomainKernel bootstrap bridge. TRIGGER: ReadRequestAttribute, ReadRequestAttributeStrict, AnswerUnsupportedRequest, App, Routes, Router, RouterInterface, ParseJsonBody, X-Jardis-Version, knownVersions, jardisVersion, EmitResponse, HandleThrowable, MapDomainResponse, BuildErrorResponse, jardiscore/app, response envelope, {status,data,errors,meta}, health(), auto-HEAD, middleware: when and where, RequireSession, RequireRequestAttribute, request.attributeMissing, required request attribute, 403, per-route middleware.
user-invocable: false
zone: post-active
persona: D
prerequisites: [foundation-architecture, foundation-patterns, core-kernel]
next: []
---

# APP_COMPONENT_SKILL
> `jardiscore/app` | NS: `JardisCore\App` | PHP 8.3+

## CLASSES
| Class | Responsibility |
|-------|---------------|
| `Routes` | Registration collector. `__construct(ResponseFactoryInterface $responseFactory)`. Accumulates `Data\Route` VOs + global middleware for one-time consumption by `Router`. |
| `Router` (`implements Contract\RouterInterface`) | `__construct(array $routes)` (`list<Route>`). `dispatch(ServerRequestInterface): RouteMatch`. Wraps FastRoute; dispatcher built lazily on first call. |
| `Contract\RouterInterface` | `dispatch(ServerRequestInterface $request): RouteMatch` — the only routing contract; FastRoute never leaks past `Router`. |
| `App` | The orchestrator. `__construct(Routes $routes, DomainKernelInterface $kernel, AppConfig $config, ?ResponseFactoryInterface, ?StreamFactoryInterface, ?ServerRequestFactoryInterface, ?UriFactoryInterface, ?UploadedFileFactoryInterface)` — all PSR-17 factories default to `Nyholm\Psr7\Factory\Psr17Factory`. `handle(ServerRequestInterface): ResponseInterface` (pure). `run(): void` (impure — builds request from SAPI, `handle()`s, emits). |
| `Config\AppConfig` | `final readonly class` — `__construct(bool $debug = false, array $knownVersions = [], bool $requireSessionGuard = false)` (`list<string>`). `requireSessionGuard`: `App` throws `LogicException` at construction if a non-anonymous route has no `SessionGuardInterface` in its chain (via `Handler\Pipeline\AssertSessionGuard`). Bootstrap-injected only; never reads ENV itself (E11). |
| `Handler\Request\ParseJsonBody` | `__invoke(ServerRequestInterface): array<array-key, mixed>` — the one JSON body parser (E7). |
| `Handler\Request\ReadRequestAttribute` | `__invoke(ServerRequestInterface, string $name): string` - the request attribute as a string; absent / not a string -> `''`. Reads only (a guarded route sits behind `RequireRequestAttribute`). |
| `Handler\Request\ReadRequestAttributeStrict` | same signature; absent / not a string / empty -> `Exception\RequestAttributeMissing` (`AppException` -> `RuntimeException`, text `Request attribute <name> is not set - the scoped route needs the session middleware.`), the error boundary answers the generic 500. For an attribute no middleware guards. |
| `Handler\Response\AnswerUnsupportedRequest` | `__invoke(list<string> $properties): DomainResponseInterface` - `DomainResponse` 500, `data` `[]`, `errors` `['routes' => ['Request property not buildable by the generated adapter: <a, b>']]`. The route whose request the generated adapter cannot build. |
| `Handler\Request\ResolveVersion` | `__construct(array $knownVersions = [])` (`list<string>`). `__invoke(ServerRequestInterface): ?string` — the one reader of `X-Jardis-Version`: absent → `''` (baseline), exactly one case-sensitive known value → that value, else `null` (unknown / empty / repeated header). `App` wires it from `AppConfig::$knownVersions`. |
| `Handler\Response\MapDomainResponse` | `__construct(ResponseFactoryInterface, StreamFactoryInterface)`. `__invoke(DomainResponseInterface): ResponseInterface` — the canonical envelope mapper (reference impl of `contract/docs/response-envelope.md`). |
| `Handler\Response\BuildErrorResponse` | `__construct(ResponseFactoryInterface, StreamFactoryInterface)`. `__invoke(int $status, array $data = [], array $errors = [], array $meta = [], array $allowedMethods = []): ResponseInterface` — the ONE place assembling `{status,data,errors,meta}`. |
| `Handler\Error\HandleThrowable` | `__construct(ResponseFactoryInterface, StreamFactoryInterface, ?LoggerInterface, AppConfig)`. `__invoke(Closure $inner, ServerRequestInterface): ResponseInterface` — outermost try/catch boundary (F9). |
| `Handler\Error\LogThrowable` | Closure the above composes — logs via the injected PSR-3 logger, falls back to `error_log` if the logger is `null` or itself throws. |
| `Handler\Pipeline\RunMiddlewarePipeline` | `__invoke(array $globalMiddleware, array $routeMiddleware, RequestHandlerInterface $finalHandler, ServerRequestInterface): ResponseInterface` — builds+runs the PSR-15 chain, global outer / route inner (E9). |
| `Handler\Request\CreateServerRequest` | `__invoke(?array $server=null, ?array $headers=null, ?array $cookies=null, ?array $query=null, ?array $post=null, ?array $files=null, mixed $body=null): ServerRequestInterface` — Raw-Body-Invariante: reads `php://input` exactly once. |
| `Handler\Response\EmitResponse` | `__invoke(ServerRequestInterface, ResponseInterface): void` — status line, headers, `Content-Length`, body (suppressed for HEAD, RFC 7231 §4.3.2). Never throws; best-effort 500 fallback on internal failure. |
| `Middleware\RequireSession` (`implements Contract\SessionGuardInterface`, which extends `MiddlewareInterface`) | `__construct(AuthenticatorInterface $authenticator, Closure $credentialFor, ResponseFactoryInterface, StreamFactoryInterface, string $headerName = 'Authorization', string $scheme = 'Bearer')`. Session door — see SESSION below. Closures it composes: `ReadBearerToken`, `AuthenticateToken`, `ApplySession`; anonymity is read from the route attribute `DispatchAndRespond::ANONYMOUS_ATTRIBUTE`. |
| `Middleware\RequireRequestAttribute` (`implements MiddlewareInterface`, NOT `SessionGuardInterface`) | `__construct(ResponseFactoryInterface, StreamFactoryInterface, string ...$names)`. Per-route presence check — see REQUIRED ATTRIBUTES below. Empty list / empty name -> `InvalidArgumentException`. |
| `Data\Route` / `Data\RouteMatch` / `Data\RouteMatchStatus` | `final readonly` VOs. `Route(string $method, string $path, Closure\|RequestHandlerInterface $handler, array $middleware = [], bool $anonymous = false)` (`anonymous` is set only by `Routes::health()` and `Routes::anonymous()`). `RouteMatch` only via named constructors `found()/notFound()/methodNotAllowed()`. `RouteMatchStatus`: `Found`/`NotFound`/`MethodNotAllowed`. |
| `Exception\AppException` (abstract), `Exception\InvalidJsonBody`, `Exception\RequestAttributeMissing`, `Exception\UnresolvableHandlerResult` | `InvalidJsonBody` — empty/invalid/non-array JSON body (F8, mapped to 400). `RequestAttributeMissing` — thrown by `ReadRequestAttributeStrict` (generic 500). `UnresolvableHandlerResult` — handler returned neither `DomainResponseInterface` nor `ResponseInterface` (E6, propagates to generic 500). |

## ROUTES — registration API
```php
use JardisCore\App\Routes;
use Nyholm\Psr7\Factory\Psr17Factory;

$routes = new Routes(new Psr17Factory());   // ResponseFactoryInterface — used only by health()

$routes->get('/orders/{id}', $handler, ...$middleware);      // MiddlewareInterface ...$middleware, variadic
$routes->post('/orders', $handler);
$routes->put('/orders/{id}', $handler);
$routes->patch('/orders/{id}', $handler);
$routes->delete('/orders/{id}', $handler);

$routes->middleware($globalMiddleware);      // applied to every route (outer), registration order
$routes->health('/health');                  // GET, always 200 {"status":200} — M10, never touches a domain
$routes->anonymous()->post('/auth/login', $h); // same verbs; the route needs no session (Route::$anonymous)
```
Each verb accepts `callable|RequestHandlerInterface`; a callable is converted to a `Closure` immediately on
registration (E4) — every collected `Route` already satisfies `Closure|RequestHandlerInterface`.

**Path placeholders arrive decoded.** Matching runs on the percent-encoded PSR-7 path; `DispatchRequest` then
`rawurldecode`s every placeholder value exactly once before it lands in `RouteMatch::$parameters` and, via
`ApplyRouteAttributes`, in `$request->getAttribute('email')` (`%40`→`@`, `%20`→space, `%C3%BC`→`ü`, `%2F`→`/` inside
the value without changing the route match; `+` stays `+`). Handlers never decode again (a second decode would corrupt
a literal `%`).

## PARSEJSONBODY — the E7 contract
`ParseJsonBody::__invoke(ServerRequestInterface): array` throws `Exception\InvalidJsonBody` for exactly three
cases: **empty raw body**, **JSON syntax error** (`JSON_THROW_ON_ERROR`), or a **decoded value that is not an
array** (scalar/`null` JSON). Raw-Body-Invariante: rewinds the body stream before and after reading, so the
same request's raw body can be read again afterwards (webhook-HMAC use case) and calling the parser twice is
idempotent. The decoded array goes on to the request mapper of `jardissupport/response` ^1.4 (`Handler\Request\Read*` with an `Accept\*` closure, `Handler\Answer\AnswerInvalidRequest` -> 400; skill `support-response`) - no longer part of this package (2.0.0).

## X-JARDIS-VERSION — the class-version contract
`App` reads the request header `X-Jardis-Version` ONCE (`Handler\Request\ResolveVersion`, called by `DispatchAndRespond`
on a Found route, after the path attributes and BEFORE the middleware pipeline) and sets the request attribute
`jardisVersion` (`DispatchAndRespond::VERSION_ATTRIBUTE`): header absent → `''` (baseline); one value, exactly (case-sensitive)
in `AppConfig::$knownVersions` → that value. Unknown value, empty value or repeated header → **400** in the validation form,
no middleware and no handler runs: `data = {"@type":"validation","fields":[{"field":"X-Jardis-Version","reason":"invalid",
"message":"The requested version is not known.","messageKey":"request.versionUnknown"}]}`, `errors = {}`, `meta = {}`.
404/405 stay as before (the check only runs on a Found route). Default `knownVersions = []` → only the baseline is reachable;
an app that never receives the header behaves as before. Wiring: `new AppConfig(knownVersions: ['v2', 'v3'])`; a handler
reads `$request->getAttribute('jardisVersion')`. The set of known versions comes from outside (the Builder generates it
from the domain manifest); this package scans no directories and knows no classes.

## Middleware: when and where

**Is it middleware? Three questions, all "yes":** (1) Does it apply equally to all or many routes, independent of any aggregate? (2) Does it need only the request/response, not the domain model? (3) Does it run before or after the route, never in the middle? Otherwise it is a process node / Rule / Closure. "May this user see this project" is domain logic (Rule); "is there a valid session" is middleware.

**Fixed, not switchable** (`src/App.php`, `Handler/Routing/DispatchAndRespond.php`): `HandleThrowable` (outermost, any throwable incl. from middleware -> 500 envelope) -> router (404/405 answered before any middleware) -> path attributes -> `ResolveVersion` (`X-Jardis-Version`, 400 before any middleware) -> middleware pipeline -> route handler (`ResolveRouteHandler`/`ResolveResponse`, `MapDomainResponse` maps `DomainResponseInterface`) -> `EmitResponse` in `run()`. `ParseJsonBody` is a helper handlers call, not a chain stage.

**Pluggable:** any PSR-15 `MiddlewareInterface`. `Routes::middleware($m)` = global; the verb methods (`get/post/put/patch/delete`) take trailing `MiddlewareInterface ...$middleware` = per route. There is no route-group API. Order (`Handler/Pipeline/RunMiddlewarePipeline.php`): `[...global, ...route]`, outer first, registration order within each group.

**Delivered:** `Middleware\RequireSession` - reads `Authorization: Bearer <token>`, authenticates through an `AuthenticatorInterface`, else 401 envelope + `WWW-Authenticate: Bearer`. Sets `RequestAttribute::SUBJECT`, `TENANT` (absent without tenant), `ROLE` (only for a `RoleAwareAuthResultInterface` with a non-empty role, else absent), `SUBJECT_ID`, `SESSION` (last two only for a session-aware result); client-supplied values are overwritten/removed. **Also delivered:** `Middleware\RequireRequestAttribute` - per route, behind the session door: each named attribute must be a non-empty string, else 403 envelope `errors {message, messageKey: 'request.attributeMissing', context: {attribute}}` (first missing name wins); presence only, authorizes nothing, not a session guard. Anonymous: routes registered by `Routes::health()` or `Routes::anonymous()` (`Route::$anonymous`); there is no path-pattern list.

**Where (app template):** the pipeline is the **project setting `middleware`** (Builder, `project.json`). `core/app` ships no wiring: the Builder generator emits `src/App/middleware.php` (`return static function (DomainKernelInterface $kernel): array`, regenerated on every build), which builds the PSR-15 instances itself, e.g.
```php
return static function (DomainKernelInterface $kernel): array {
    $psr17 = new Psr17Factory();
    $key = base64_decode((string) $_ENV['AUTH_TOKEN_KEY'], true);   // raw 32 bytes

    return [new RequireSession(new SignedTokenAuthenticator($key), Credential::token(...), $psr17, $psr17)];
};
```
The once-written `src/App/bootstrap.php` loads it into the `'middleware'` entry of the app config, and the template's `public/index.php` registers every entry of `$app['middleware']` via `$routes->middleware()` (strictly checked: `list<MiddlewareInterface>`, else 500 envelope, never a stack trace). The template sets `new AppConfig(requireSessionGuard: true)`, so a non-anonymous route without a session guard fails at start. A hand-written route that needs no session goes through `$routes->anonymous()->get(...)` in `public/index.php`; the session middleware must stand before any middleware that reads session attributes.

**Guarantees:** `Routes::health()` is always anonymous and never mounted under `$prefix`; 404, 405 and the version check (`X-Jardis-Version`) are answered before the pipeline runs.

**Boundary:** retrofitting a standard middleware = a PSR-15 class in `jardisCore/app` plus one line in the generated `middleware.php`; a project-specific middleware = an own PSR-15 class in the project, its FQCN named in the project setting. Not a patch of the template's `index.php`. A generally useful one (CORS, request id/audit, rate limit, tenant context) is an issue in `jardisCore/app`, not project code imitating the package.

## SESSION — `RequireSession` (PSR-15, global or per route)
Binds a request to a verified session or rejects it. Depends only on the ports of `jardissupport/contracts` ^2.13
(`AuthenticatorInterface`, `CredentialInterface`, `TenantAwareAuthResultInterface`, `RequestAttribute`) — NOT on `support/auth`.
```php
use JardisCore\App\Middleware\RequireSession;
use JardisSupport\Auth\Data\Credential;      // support/auth (the app requires it, not core/app)
use JardisSupport\Auth\SignedTokenAuthenticator; // access tokens of SessionManager (auth ^1.5): signature + exp, no store

$routes->middleware(new RequireSession(
    new SignedTokenAuthenticator($accessTokenKey), // raw 32-byte key
    Credential::token(...),                     // Closure(string): CredentialInterface
    $psr17, $psr17,                             // response + stream factory (the 401 uses BuildErrorResponse)
));
$routes->anonymous()->post('/auth/login', $login); // routes that need no session
$routes->health('/health');                     // anonymous automatically
```
- **Token:** `Authorization: Bearer <token>` (exactly one header line, scheme case-insensitive, no whitespace in the token).
  `headerName`/`scheme` configurable; `scheme: ''` = the whole header value is the token.
- **401:** `{status:401, data:{}, errors:{message:"Authentication required."}, meta:{}}` + `WWW-Authenticate: Bearer`; missing, malformed,
  unknown, expired, revoked token and a "successful" result without subject are answered identically (reason stays internal).
  The handler is never called. A `Throwable` from the authenticator (token store down) is NOT a 401 but the generic 500.
- **Session:** valid → request attribute `RequestAttribute::SUBJECT` (`jardisSubject`) and, if the result is `TenantAwareAuthResultInterface`
  with a non-empty tenant, `RequestAttribute::TENANT` (`jardisTenant`); no tenant → the attribute is ABSENT (never null/''). Pre-existing
  attributes of these names (e.g. a path placeholder `{jardisSubject}`) are always overwritten/removed, also on anonymous paths.
  If the result is a `RoleAwareAuthResultInterface` (contracts ^2.11, auth ^1.6) with a non-empty role, `RequestAttribute::ROLE` (`jardisRole`) is set too; else ABSENT (never null/''). It is an attribute, not a guard - core/app authorizes nothing, no new middleware. Same forgery strip.
  If the result is a `SessionAwareAuthResultInterface` (contracts ^2.9, auth ^1.3) it additionally sets `RequestAttribute::SUBJECT_ID`
  (`jardisSubjectId`, bare id without `user:` prefix) and `RequestAttribute::SESSION` (`jardisSession`, reference that revokes exactly this
  session); empty/null or a plain tenant-aware result → both ABSENT. Same forgery strip as SUBJECT/TENANT.
  Recipe "revoke own session": `$sessionManager->invalidateByReference($request->getAttribute(RequestAttribute::SESSION))`.
- **Where it runs:** only on a matched route (inside the pipeline) → unknown path stays 404, wrong method 405. `Routes::health()` and `Routes::anonymous()` routes carry
  `Route::$anonymous` → request attribute `DispatchAndRespond::ANONYMOUS_ATTRIBUTE` (`jardisAnonymousRoute`, strict bool, always written by
  the dispatcher, compare with `=== true`). 
- **Per route:** `$routes->get('/x', $handler, $requireSession)` guards only that route (global = guard everything except anonymous).
- **`Contract\SessionGuardInterface`:** method-less marker (`extends MiddlewareInterface`), implemented by `RequireSession`. The start-up check only tests `instanceof`; a decorator around the guard does not count unless it implements the marker.
- **Switch:** `new AppConfig(requireSessionGuard: true)` makes `new App(...)` throw `LogicException` listing `METHOD path` of every non-anonymous route without a guard in `[...global, ...route]`. Default `false`.

## REQUIRED ATTRIBUTES — `RequireRequestAttribute` (PSR-15, per route)
For a door that depends on a request attribute (e.g. a tenant). Presence check only - no role/ownership check (that is domain logic).
```php
use JardisCore\App\Middleware\RequireRequestAttribute;
use JardisSupport\Contract\Auth\RequestAttribute;

$routes->middleware($requireSession);
$routes->get('/projects', $handler, new RequireRequestAttribute($psr17, $psr17, RequestAttribute::TENANT));
```
- **Check:** per name, in order: the attribute must be a non-empty string (absent / not a string / `''` fails); the first failing name ends the request.
- **403:** `{status:403, data:{}, errors:{message, messageKey:"request.attributeMissing" (const `MESSAGE_KEY`), context:{attribute:"<name>"}}, meta:{}}` - same form as the 401, no `@type`; handler not called. Otherwise the request passes unchanged.
- **Order:** behind the middleware that sets the attribute; with no token the session door answers 401 first. A path placeholder `{jardisTenant}` cannot satisfy it (`ApplySession` strips it).
- **Not a guard:** does not implement `SessionGuardInterface` -> never satisfies `requireSessionGuard`. 404/405 unchanged (runs only on a matched route).

## ENVELOPE CONTRACT — `{status, data, errors, meta}`
Reference: `jardissupport/contracts` → `docs/response-envelope.md`. `ResponseStatus` (11 cases, `jardissupport/contracts`,
`JardisSupport\Contract\Kernel\ResponseStatus`) maps 1:1 to the HTTP code:

| Case | HTTP |
|---|---|
| `Success` | 200 |
| `Created` | 201 |
| `NoContent` | 204 |
| `ValidationError` | 400 |
| `Unauthorized` | 401 |
| `Forbidden` | 403 |
| `NotFound` | 404 |
| `MethodNotAllowed` | 405 |
| `Conflict` | 409 |
| `RuleViolation` | 422 |
| `InternalError` | 500 |

- **204 (`NoContent`)** is special-cased by `MapDomainResponse`: no body at all, no `Content-Type` header — a
  bare empty response, never the envelope builder.
- **Empty `data`/`errors`/`meta`** are coerced to a JSON object (`{}` via `stdClass`), never `[]`, by
  `BuildErrorResponse`.
- **405** carries an RFC-7231 `Allow` header (`, `-joined allowed methods) — the only case that populates
  `BuildErrorResponse`'s 5th (`allowedMethods`) parameter.
- **422 (`RuleViolation`)** — `getData()` is serialized completely unchanged under `data`; for a Rules-Layer
  violation this is typically `{rule, messageKey, context}`, but the mapper performs no reshaping.
- `getEvents()` is deliberately NOT part of the client-facing envelope — only 4 top-level keys ever appear.

## ERROR CONTRACT — `HandleThrowable`
- `Exception\InvalidJsonBody` → 400, envelope `errors.message` = the exception's own (non-sensitive) message.
- Any other `Throwable` → 500, **generic** body (no message/class/trace) unless `AppConfig::$debug === true`
  (then `errors.message`/`errors.exception`/`errors.trace` are added). The full exception always goes to the
  injected PSR-3 logger at `error` level regardless of `$debug` — `LogThrowable` falls back to `error_log()`
  when the logger is `null` or itself throws; the 500 response is unaffected either way.

## E13/E14 — auto-HEAD, OPTIONS
- **Auto-HEAD (E13):** `BuildDispatcher` registers `HEAD` automatically for every `GET` route unless an
  explicit `HEAD` route already exists for the same path — no double registration. `EmitResponse` then
  suppresses the body for a `HEAD` request but still sends `Content-Length`.
- **OPTIONS is never auto-added (E14):** an unregistered `OPTIONS` on a path with no routes at all → 404; on a
  path registered for other methods → 405 (`OPTIONS` absent from the `Allow` list) unless the app explicitly
  registers an `OPTIONS` route itself.

## BOOTSTRAP-REZEPT
`BuildDomainKernelFromEnv` (packer) → `DomainKernel` (DomainKernel) → Builder-generated domain(s) (`new
{Domain}($kernel)`, K8, not part of this package) → `Routes` + handlers → `App` → `run()`. Full runnable
`public/index.php` incl. a fatal-error shutdown-function safety net, `AppConfig::debug` wired from
`$kernel->env('app_debug')`, and API-versioning-by-parameter: `docs/getting-started.md`.

## EXAMPLES
- `examples/basic/` — the full recipe wired against a **real** Builder-generated domain (`Ecommerce`/`Sales`)
  over MySQL: `GET/POST/PATCH /orders`, the 422 rule-violation payload, 404/405/500 boundary cases, and a real
  PSR-15 ecosystem middleware (`middlewares/client-ip`).
- `examples/symfony-demo/` (N3) — a **separate, isolated** Composer project answering the same
  `{status,data,errors,meta}` envelope for the same vendored `Ecommerce` fixture via **Symfony**
  (`symfony/http-foundation` + `symfony/routing`) instead of `jardiscore/app` — illustrates that no domain
  depends on this package.

## RULES
- A route handler must return `DomainResponseInterface` or a PSR-7 `ResponseInterface` — anything else throws
  `UnresolvableHandlerResult`, which propagates to the generic 500 boundary (E6/E8).
- `AppConfig` never reads ENV itself — the bootstrap layer always injects `debug` and `knownVersions` explicitly (E11).
- PSR-17 factories are received as interfaces only; `Nyholm\Psr7\Factory\Psr17Factory` is `App`'s own
  swappable-by-injection default, never hardwired inside handler/business code (E12-symmetric).
- Request-body size limits, Trusted-Proxy/`X-Forwarded-*`, and `display_errors=Off` are deliberately **not**
  solved by this package (N1) — named responsibilities of the surrounding webserver/FPM/proxy infrastructure.
- Typed coercion of path/query/body parameters and the 400 for a bad request are NOT in this package (since 2.0.0): the request mapper
  lives in `jardissupport/response` ^1.4 (skill `support-response`); business validation is `jardissupport/validation` / a Rule.

## DEPENDENCIES
```
jardiscore/kernel            ^2.0
jardissupport/contracts      ^2.13
jardissupport/response       ^1.4
jardissupport/factory        ^1.0
nikic/fast-route             ^1.3
nyholm/psr7                  ^1.8
nyholm/psr7-server            ^1.1
psr/http-message              ^2
psr/http-factory              ^1
psr/http-server-handler       ^1
psr/http-server-middleware    ^1
```
PHP >= 8.3.
