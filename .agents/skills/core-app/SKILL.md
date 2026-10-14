---
name: core-app
description: jardiscore/app v1 - the HTTP-Delivery layer for Jardis domains, a FastRoute router hidden behind an own RouterInterface, a PSR-15 middleware pipeline, the canonical DomainResponse-to-PSR-7 envelope mapper, and a DomainKernel bootstrap bridge. TRIGGER: App, Routes, Router, RouterInterface, ParseJsonBody, X-Jardis-Version, knownVersions, jardisVersion, EmitResponse, HandleThrowable, MapDomainResponse, BuildErrorResponse, jardiscore/app, response envelope, {status,data,errors,meta}, health(), auto-HEAD, middleware: when and where, RequireSession, per-route middleware.
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
| `Config\AppConfig` | `final readonly class` — `__construct(bool $debug = false, array $knownVersions = [])` (`list<string>`). Bootstrap-injected only; never reads ENV itself (E11). |
| `Handler\Request\ParseJsonBody` | `__invoke(ServerRequestInterface): array<array-key, mixed>` — the one JSON body parser (E7). |
| `Handler\Request\ResolveVersion` | `__construct(array $knownVersions = [])` (`list<string>`). `__invoke(ServerRequestInterface): ?string` — the one reader of `X-Jardis-Version`: absent → `''` (baseline), exactly one case-sensitive known value → that value, else `null` (unknown / empty / repeated header). `App` wires it from `AppConfig::$knownVersions`. |
| `Handler\Response\MapDomainResponse` | `__construct(ResponseFactoryInterface, StreamFactoryInterface)`. `__invoke(DomainResponseInterface): ResponseInterface` — the canonical envelope mapper (reference impl of `contract/docs/response-envelope.md`). |
| `Handler\Response\BuildErrorResponse` | `__construct(ResponseFactoryInterface, StreamFactoryInterface)`. `__invoke(int $status, array $data = [], array $errors = [], array $meta = [], array $allowedMethods = []): ResponseInterface` — the ONE place assembling `{status,data,errors,meta}`. |
| `Handler\Error\HandleThrowable` | `__construct(ResponseFactoryInterface, StreamFactoryInterface, ?LoggerInterface, AppConfig)`. `__invoke(Closure $inner, ServerRequestInterface): ResponseInterface` — outermost try/catch boundary (F9). |
| `Handler\Error\LogThrowable` | Closure the above composes — logs via the injected PSR-3 logger, falls back to `error_log` if the logger is `null` or itself throws. |
| `Handler\Pipeline\RunMiddlewarePipeline` | `__invoke(array $globalMiddleware, array $routeMiddleware, RequestHandlerInterface $finalHandler, ServerRequestInterface): ResponseInterface` — builds+runs the PSR-15 chain, global outer / route inner (E9). |
| `Handler\Request\CreateServerRequest` | `__invoke(?array $server=null, ?array $headers=null, ?array $cookies=null, ?array $query=null, ?array $post=null, ?array $files=null, mixed $body=null): ServerRequestInterface` — Raw-Body-Invariante: reads `php://input` exactly once. |
| `Handler\Response\EmitResponse` | `__invoke(ServerRequestInterface, ResponseInterface): void` — status line, headers, `Content-Length`, body (suppressed for HEAD, RFC 7231 §4.3.2). Never throws; best-effort 500 fallback on internal failure. |
| `Middleware\RequireSession` (`implements MiddlewareInterface`) | `__construct(AuthenticatorInterface $authenticator, Closure $credentialFor, ResponseFactoryInterface, StreamFactoryInterface, array $anonymousPaths = [], string $headerName = 'Authorization', string $scheme = 'Bearer')`. Session door — see SESSION below. Closures it composes: `Handler\Session\IsAnonymous`, `ReadBearerToken`, `AuthenticateToken`, `ApplySession`. |
| `Data\Route` / `Data\RouteMatch` / `Data\RouteMatchStatus` | `final readonly` VOs. `Route(string $method, string $path, Closure\|RequestHandlerInterface $handler, array $middleware = [], bool $anonymous = false)` (`anonymous` is set only by `Routes::health()`). `RouteMatch` only via named constructors `found()/notFound()/methodNotAllowed()`. `RouteMatchStatus`: `Found`/`NotFound`/`MethodNotAllowed`. |
| `Exception\AppException` (abstract), `Exception\InvalidJsonBody`, `Exception\UnresolvableHandlerResult` | `InvalidJsonBody` — empty/invalid/non-array JSON body (F8, mapped to 400). `UnresolvableHandlerResult` — handler returned neither `DomainResponseInterface` nor `ResponseInterface` (E6, propagates to generic 500). |

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
idempotent.

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

**Delivered:** `Middleware\RequireSession` - reads `Authorization: Bearer <token>`, authenticates through an `AuthenticatorInterface`, else 401 envelope + `WWW-Authenticate: Bearer`. Sets `RequestAttribute::SUBJECT`, `TENANT` (absent without tenant), `SUBJECT_ID`, `SESSION` (last two only for a session-aware result); client-supplied values are overwritten/removed. Anonymous: `Routes::health()` routes and `anonymousPaths` (exact path or `prefix*`, all methods).

**Where (app template):** `public/index.php`, at the marked seam after `$routes->health('/health')` and before the route mount loop; recipe in the template README "Sessions".

**Boundary:** a project-specific middleware is dev code at the same hook. A generally useful one (CORS, request id/audit, rate limit, tenant context) is an issue in `jardisCore/app`, not project code imitating the package.

## SESSION — `RequireSession` (PSR-15, global or per route)
Binds a request to a verified session or rejects it. Depends only on the ports of `jardissupport/contracts` ^2.9
(`AuthenticatorInterface`, `CredentialInterface`, `TenantAwareAuthResultInterface`, `RequestAttribute`) — NOT on `support/auth`.
```php
use JardisCore\App\Middleware\RequireSession;
use JardisSupport\Auth\Data\Credential;      // support/auth (the app requires it, not core/app)
use JardisSupport\Auth\TokenAuthenticator;    // + DbTokenStore as TokenStoreInterface

$routes->middleware(new RequireSession(
    new TokenAuthenticator($tokenStore),
    Credential::token(...),                     // Closure(string): CredentialInterface
    $psr17, $psr17,                             // response + stream factory (the 401 uses BuildErrorResponse)
    ['/auth/login', '/auth/refresh', '/docs/*'], // anonymous: exact path, or prefix when ending in `*`
));
$routes->health('/health');                     // anonymous automatically, no need to list it
```
- **Token:** `Authorization: Bearer <token>` (exactly one header line, scheme case-insensitive, no whitespace in the token).
  `headerName`/`scheme` configurable; `scheme: ''` = the whole header value is the token.
- **401:** `{status:401, data:{}, errors:{message:"Authentication required."}, meta:{}}` + `WWW-Authenticate: Bearer`; missing, malformed,
  unknown, expired, revoked token and a "successful" result without subject are answered identically (reason stays internal).
  The handler is never called. A `Throwable` from the authenticator (token store down) is NOT a 401 but the generic 500.
- **Session:** valid → request attribute `RequestAttribute::SUBJECT` (`jardisSubject`) and, if the result is `TenantAwareAuthResultInterface`
  with a non-empty tenant, `RequestAttribute::TENANT` (`jardisTenant`); no tenant → the attribute is ABSENT (never null/''). Pre-existing
  attributes of these names (e.g. a path placeholder `{jardisSubject}`) are always overwritten/removed, also on anonymous paths.
  If the result is a `SessionAwareAuthResultInterface` (contracts ^2.9, auth ^1.3) it additionally sets `RequestAttribute::SUBJECT_ID`
  (`jardisSubjectId`, bare id without `user:` prefix) and `RequestAttribute::SESSION` (`jardisSession`, reference that revokes exactly this
  session); empty/null or a plain tenant-aware result → both ABSENT. Same forgery strip as SUBJECT/TENANT.
  Recipe "revoke own session": `$sessionManager->invalidateByReference($request->getAttribute(RequestAttribute::SESSION))`.
- **Where it runs:** only on a matched route (inside the pipeline) → unknown path stays 404, wrong method 405. `Routes::health()` routes carry
  `Route::$anonymous` → request attribute `DispatchAndRespond::ANONYMOUS_ATTRIBUTE` (`jardisAnonymousRoute`, strict bool, always written by
  the dispatcher, compare with `=== true`). Anonymous patterns match all HTTP methods of that path.
- **Per route:** `$routes->get('/x', $handler, $requireSession)` guards only that route (global = guard everything except anonymous).

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
- Typed coercion of path/query/body parameters is the route handler's job in v1 (F8); reach for
  `jardissupport/validation` for anything beyond trivial casts.

## DEPENDENCIES
```
jardiscore/kernel            ^2.0
jardissupport/contracts      ^2.9
nikic/fast-route             ^1.3
nyholm/psr7                  ^1.8
nyholm/psr7-server            ^1.1
psr/http-message              ^2
psr/http-factory              ^1
psr/http-server-handler       ^1
psr/http-server-middleware    ^1
```
PHP >= 8.3.
