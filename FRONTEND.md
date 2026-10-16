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
(success and error alike; `400` = field validation, `404` = not found, `409` = conflict,
`422` = rule rejection, `5xx` = technical).
The helper pulls query, body, and response types from the generated `.d.ts` —
compile errors on contract breakage, without a generator. It returns the **union of all
answers the operation declares** (narrow on `status`) plus `Offline` (`status: 0`), the one
answer the contract cannot declare (network failure or non-JSON body):

```ts
// frontend/src/api/jardisFetch.ts — copy once, then dev ownership
import type { paths } from './ecommerce';

type Op<P extends keyof paths, M> = M extends keyof paths[P] ? paths[P][M] : never;
type QueryOf<O> = O extends { parameters: { query?: infer Q } } ? Q : never;
type BodyOf<O> = O extends { requestBody?: { content: { 'application/json': infer B } } } ? B : never;
type ResultOf<O> = O extends { responses: infer R }
    ? { [K in keyof R]: R[K] extends { content: { 'application/json': infer J } } ? J : never }[keyof R] : never;

/** Network failure or non-JSON answer: the only answer not from the contract. */
export type Offline = { status: 0; data: null; errors: Record<string, string[]> };

export async function jardisFetch<P extends keyof paths & string, M extends 'get' | 'post' | 'put' | 'delete'>(
    path: P,
    method: M,
    init: { params?: Record<string, string | number>; query?: QueryOf<Op<P, M>>; body?: BodyOf<Op<P, M>> } = {},
): Promise<ResultOf<Op<P, M>> | Offline> {
    const url = path.replace(/\{(\w+)\}/g, (_, key: string) => encodeURIComponent(String(init.params?.[key] ?? '')));
    const query = init.query
        ? '?' + new URLSearchParams(Object.entries(init.query).flatMap(([k, v]) => (v == null ? [] : [[k, String(v)]])))
        : '';

    try {
        const response = await fetch(url + query, {
            method: method.toUpperCase(),
            headers: { 'content-type': 'application/json' },
            body: init.body === undefined ? undefined : JSON.stringify(init.body),
        });
        return (await response.json()) as ResultOf<Op<P, M>>;
    } catch {
        return { status: 0, data: null, errors: { network: ['unreachable'] } };
    }
}
```

Verified: compiles with `tsc --strict` against the types generated from a real
build artifact; wrong or missing body fields are compile errors.

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
if (result.status !== 200) { /* 0 offline, 400 fields, 404, 409, 422 — see "Failure cases per door" */ }
```

Notes on the contract:

- **Write responses carry references** (business identifier of the root plus
  child references), never state copies — use the read routes to reload
  (list → keys → `queries/by{Key}s`).
- **Non-200 responses** arrive in the same envelope and are part of the helper's return type.
- **`409` carries the hit fields.** A `409` with `reason: "duplicate"` (a unique key was
  already taken) answers `data.violated`: the list of outer field names that collided
  (a child field as `<kindProperty>.<field>`) — mark exactly those form fields with a hint
  such as "already taken". `violated` is `[]` when the fields are unknown and on every
  `stale_write` / `stale_delete` conflict (the record changed meanwhile: reload and retry).
  A transient database conflict under concurrent writes (snapshot or serialization failure,
  deadlock) answers the same `409` `stale_write` / `stale_delete` with `violated: []`, not a
  `500`: repeat the whole request, it runs in a new transaction.
- `POST` is not idempotent, `PUT`/`DELETE` are (also stated in the spec description).

## 3. Failure cases per door

Error answers are declared per status code in each operation's `responses`; `data` carries `@type`:

| Status | `@type` | Schema | Fields |
|---|---|---|---|
| `400` | `validation` | `ValidationError` | `fields[]` of `{field, reason, message, messageKey}`; `reason` is `missing` / `invalid` / `type` / `unknown` |
| `403` | none (`data` is `{}`) | — | `errors.messageKey` = `request.attributeMissing`, `errors.context.attribute` (the missing request attribute, e.g. `jardisTenant`), `errors.message`; `data` is `{}`. A door answers this for a request attribute it cannot run without (e.g. a tenant) — one the route reads unconditionally (for a union body only those every alternative reads); an attribute read only by one union alternative or an optional/nullable/list-nested property is not guarded up front, and when it is absent the route fails closed with `500`; without a token the answer is `401`, not `403` |
| `404` | `notFound` | `NotFound` | `context` (the path parameters of the lookup) |
| `409` | `concurrencyConflict` | `ConcurrencyConflict` | `reason`, `aggregate`, `context`, `violated` (see notes above) |
| `422` | `ruleViolation` | `RuleViolation` | `rule`, `messageKey`, `context` |

**422 keys are closed per door.** Where a door knows its rules, its 422 narrows
`messageKey` to an `enum` of exactly the keys that can occur there
(`allOf: [RuleViolation, {messageKey: enum}]`). Read the keys from the generated
type of that operation's 422 response and write one message text per key — texts
are the frontend's business, never guess a key. A bare `RuleViolation` 422 (a rule
without a declared key, or a hand-written node body) means an **open** key space: `messageKey` is a plain
`string`, keep a generic fallback text. Process doors may also
declare exit keys on `404` / `409` (`anyOf` of the base shape and a `messageKey` enum) — narrow on `@type` first.

## 4. Serve the spec (optional)

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
