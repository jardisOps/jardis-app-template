# Frontend recipe — typed API access to the Jardis front door

Every build of the Jardis Builder generates two files per domain in this project:

| File | Role |
|---|---|
| `src/Api/{Domain}/openapi.yaml` | **The source for this recipe** — the full OpenAPI 3.1 contract of the domain's front door (all read routes, commands, processes, error shapes). Freshly generated on every build. |
| `src/Api/{Domain}/routes.php` | The PHP side of the same contract — `public/index.php` mounts it automatically (generic mount, see there). |

The frontend derives its types from `openapi.yaml`. There is **no** Jardis-own
client generator and no runtime code from this recipe — only types plus a
~20-line fetch helper that stays under dev ownership afterward.

## 1. Generate types

Add once as a dev dependency to the frontend project, then re-run after
**every** build (the spec changes with the domain model):

```bash
npx openapi-typescript src/Api/Ecommerce/openapi.yaml -o frontend/src/api/ecommerce.d.ts
```

Target file: `frontend/src/api/{domain}.d.ts` — pure type declarations (`paths`,
`components`, `operations`), no executable code. With multiple domains, one
file per domain. The `frontend/` path here stands in for your frontend
project — the template itself doesn't ship one.

## 2. Envelope fetch helper

Every response from the front door carries the same envelope `{status, data, errors, meta}`
(success and error alike; `400` = field validation, `422` = rule rejection, `5xx` = technical).
The following helper pulls query, body, and response types from the generated `.d.ts` —
autocomplete and compile errors on contract breakage, without a generator:

```ts
// frontend/src/api/jardisFetch.ts — copy once, then dev ownership
import type { paths } from './ecommerce';

type Op<P extends keyof paths, M> = M extends keyof paths[P] ? paths[P][M] : never;
type QueryOf<O> = O extends { parameters: { query?: infer Q } } ? Q : never;
type BodyOf<O> = O extends { requestBody?: { content: { 'application/json': infer B } } } ? B : never;
type OkOf<O> = O extends { responses: { 200: { content: { 'application/json': infer R } } } } ? R : never;

export async function jardisFetch<P extends keyof paths & string, M extends 'get' | 'post' | 'put' | 'delete'>(
    path: P,
    method: M,
    init: { params?: Record<string, string | number>; query?: QueryOf<Op<P, M>>; body?: BodyOf<Op<P, M>> } = {},
): Promise<OkOf<Op<P, M>>> {
    const url = path.replace(/\{(\w+)\}/g, (_, key: string) => encodeURIComponent(String(init.params?.[key] ?? '')));
    const query = init.query
        ? '?' + new URLSearchParams(Object.entries(init.query).flatMap(([k, v]) => (v == null ? [] : [[k, String(v)]])))
        : '';

    const response = await fetch(url + query, {
        method: method.toUpperCase(),
        headers: { 'content-type': 'application/json' },
        body: init.body === undefined ? undefined : JSON.stringify(init.body),
    });

    return (await response.json()) as OkOf<Op<P, M>>;
}
```

Verified: compiles with `tsc --strict` against the types generated from a real
build artifact (`Ecommerce` spec, 27 routes); missing required fields and
wrong field types in the body are compile errors.

### Usage

```ts
// Read: list with typed query — data.items[].invoiceNumber is string
const list = await jardisFetch('/accounting/customerInvoices', 'get', {
    query: { limit: 20, offset: 0 },
});

// Write: command with path parameter + typed body.
// Decimal fields are strings in the contract (format: decimal), e.g. "4.50".
const result = await jardisFetch('/accounting/customerInvoices/{invoiceNumber}/dunningNotice', 'post', {
    params: { invoiceNumber: 'RE-2026-001' },
    body: { dunningLevel: 1, dunningDate: '2026-08-27', /* … all required fields */ },
});
if (result.status !== 200) { /* evaluate errors: 400 field validation, 422 rule */ }
```

Notes on the contract:

- **Write responses carry references** (business identifier of the root plus
  child references), never state copies — use the read routes to reload
  (list → keys → `queries/by{Key}s`).
- **Non-200 responses** arrive in the same envelope; the helper types the
  200 path, `status`/`errors` are present in every response at runtime.
- `POST` is not idempotent, `PUT`/`DELETE` are (also stated in the spec description).

## 3. Serve the spec (optional)

If the frontend (or Swagger UI) should load the spec at runtime, a single
route in `public/index.php` next to the mount is enough:

```php
$routes->get('/openapi.yaml', static function () use ($root) {
    $psr17 = new Psr17Factory();
    [$file] = glob($root . '/src/Api/*/openapi.yaml') ?: [null];

    return $file === null
        ? $psr17->createResponse(404)
        : $psr17->createResponse(200)
            ->withHeader('Content-Type', 'application/yaml')
            ->withBody($psr17->createStream((string) file_get_contents($file)));
});
```

Alternatively the web server serves the file statically (nginx `location`
block on `src/Api/`) — for pure build-time use (section 1), no serving is
needed at all.

## Boundaries

- **Prerequisite:** `jardiscore/app ^1` (listed in this template's
  `composer.json`) — the generated `routes.php` builds on its `Routes` API
  and envelope mapper.
- **Auth/middleware are app territory** — the generated contract
  deliberately contains no security schemes; authentication belongs in the
  embedding app's PSR-15 pipeline and is not part of this recipe.
- Footnote: anyone who wants a complete client instead of the helper can
  point tools like `orval` or `@hey-api/openapi-ts` at the same
  `openapi.yaml`. This recipe makes no recommendation either way.
