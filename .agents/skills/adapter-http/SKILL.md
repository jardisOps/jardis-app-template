---
name: adapter-http
description: PSR-18 HTTP client, cURL transport, handler pipeline, retry. Use for HttpClient, HTTP requests.
user-invocable: false
zone: post-active
persona: C
prerequisites: [foundation-architecture, foundation-patterns]
next: []
---

# HTTP_COMPONENT_SKILL
> jardisadapter/http | NS: `JardisAdapter\Http` | PSR-18 on cURL | PHP 8.2+

## ARCHITECTURE
```
HttpClient (Orchestrator, implements ClientInterface)
  Transformers (Request → Request, only when configured):
    BaseUrl        (baseUrl != null)
    DefaultHeaders (defaultHeaders != [])
    BearerAuth     (bearerToken != null)
    BasicAuth      (basicUser + basicPassword != null)
  Transport (Request → Response):
    CurlTransport              (default)
    Retry wraps CurlTransport  (maxRetries > 0)
```

## API / SIGNATURES
```php
use JardisAdapter\Http\HttpClient;
use JardisAdapter\Http\Config\ClientConfig;
use JardisAdapter\Http\Message\Psr17Factory;

$psr17  = new Psr17Factory();   // bundled PSR-17, no external dep
$client = new HttpClient(
    requestFactory:  RequestFactoryInterface,
    streamFactory:   StreamFactoryInterface,
    responseFactory: ResponseFactoryInterface,
    uriFactory:      UriFactoryInterface,
    config:          ClientConfig,
    transport:       ?Closure = null,   // (RequestInterface, ClientConfig): ResponseInterface
);

// Convenience (auto-build PSR-7 requests)
$client->get(string $uri, array $headers = []): ResponseInterface
$client->post(string $uri, array $data = [], array $headers = []): ResponseInterface   // JSON
$client->put(string $uri, array $data = [], array $headers = []): ResponseInterface    // JSON
$client->patch(string $uri, array $data = [], array $headers = []): ResponseInterface  // JSON
$client->delete(string $uri, array $headers = []): ResponseInterface
$client->head(string $uri, array $headers = []): ResponseInterface

// PSR-18 full control
$client->sendRequest(RequestInterface $request): ResponseInterface
```

## CLIENTCONFIG
```php
new ClientConfig(
    timeout:        30,     // request timeout (s)
    connectTimeout: 10,     // connect timeout (s)
    baseUrl:        null,   // base URL for relative paths
    verifySsl:      true,
    defaultHeaders: [],
    bearerToken:    null,   // Bearer takes precedence over Basic if both set
    basicUser:      null,
    basicPassword:  null,
    maxRetries:     0,      // 0 = no retry; retries on 5xx + NetworkException
    retryDelayMs:   100,    // base for exponential backoff
)
```
Readonly VO.

## HANDLER TABLE
| Handler | Signature |
|---------|-----------|
| `BaseUrl` | `(RequestInterface): RequestInterface` |
| `DefaultHeaders` | `(RequestInterface): RequestInterface` |
| `BearerAuth` | `(RequestInterface): RequestInterface` |
| `BasicAuth` | `(RequestInterface): RequestInterface` |
| `CurlTransport` | `(RequestInterface, ClientConfig): ResponseInterface` |
| `Retry` | `(RequestInterface, ClientConfig): ResponseInterface` |

## EXCEPTIONS
| Exception | Trigger |
|-----------|---------|
| `NetworkException` | DNS, connection refused, timeout |
| `RequestException` | Malformed URI |
| `HttpClientException` | Base class (extends RuntimeException) |

No exception on 4xx/5xx — those are valid responses. Retry applies to 5xx + `HttpClientException`.

```php
use JardisAdapter\Http\Exception\NetworkException;
try { $response = $client->get('/api'); }
catch (NetworkException $e) { $e->getRequest(); }
```

## KERNEL INTEGRATION
`jardiscore/foundation` is deleted, including its `HttpClientHandler`. The successor lives in `jardiscore/kernel`: `JardisCore\Kernel\Bootstrap\Handler\BuildHttpClientFromEnv`, packed by the Bootstrap-Packer `Bootstrap\BuildDomainKernelFromEnv`. Access via the Koffer: `$kernel->httpClient(): ?ClientInterface` (`DomainKernel.php:91`).

ENV variables consumed by `BuildHttpClientFromEnv` (`.env` keys are case-insensitive — `BuildDomainKernelFromEnv.php:83` lowercases before lookup; verified against `core/kernel/src/Bootstrap/Handler/BuildHttpClientFromEnv.php`):
```
HTTP_BASE_URL, HTTP_TIMEOUT (default 30), HTTP_CONNECT_TIMEOUT (default 10), HTTP_VERIFY_SSL (default true),
HTTP_BEARER_TOKEN, HTTP_BASIC_USER, HTTP_BASIC_PASSWORD,
HTTP_MAX_RETRIES (default 0), HTTP_RETRY_DELAY_MS (default 100)
```
Full ENV reference: `core/kernel/docs/env-examples/README.md`.

Returns the instance or `null` — there is no third state. `DomainKernel::httpClient()` is typed `?ClientInterface` (`DomainKernel.php:91`), so `false` is impossible. `null` means: adapter not installed.

## CUSTOM TRANSPORT (testing)
```php
$client = new HttpClient(..., transport: function (RequestInterface $req, ClientConfig $cfg) use ($responseFactory, $streamFactory): ResponseInterface {
    return $responseFactory->createResponse(200)->withBody($streamFactory->createStream('{"ok":true}'));
});
```

## LAYER
- Application: inject `ClientInterface`.
- Infrastructure: build `HttpClient` from ENV via `Bootstrap\Handler\BuildHttpClientFromEnv` (`jardiscore/kernel`).
- Domain: never imports HTTP classes.
- Logging/caching: Decorator on `ClientInterface` in the caller — never inside the package.

## DEPENDENCIES
- `psr/http-client ^1.0`, `psr/http-factory ^1.0`, `psr/http-message ^2.0`, `ext-curl`
- Bundled PSR-7/PSR-17: `src/Message/` (`Psr17Factory`, `Request`, `Response`, `Stream`, `Uri`) — no external PSR-7 in require.
