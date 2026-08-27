# Frontend-Rezept — typisierter API-Zugriff auf die Jardis-Außentür

Jeder Build des Jardis Builders erzeugt je Domain zwei Dateien in diesem Projekt:

| Datei | Rolle |
|---|---|
| `src/Api/{Domain}/openapi.yaml` | **Die Quelle für dieses Rezept** — der vollständige OpenAPI-3.1-Vertrag der Domain-Außentür (alle Lese-Routen, Commands, Prozesse, Fehlerformen). Wird bei jedem Build frisch erzeugt. |
| `src/Api/{Domain}/routes.php` | Die PHP-Seite desselben Vertrags — `public/index.php` mountet sie automatisch (generischer Mount, siehe dort). |

Das Frontend leitet seine Typen aus der `openapi.yaml` ab. Es gibt **keinen** Jardis-eigenen
Client-Generator und keinen Laufzeitcode aus diesem Rezept — nur Typen plus einen ~20-zeiligen
Fetch-Helper, der danach in Dev-Hoheit liegt.

## 1. Typen erzeugen

Einmalig als Dev-Dependency ins Frontend-Projekt, dann nach **jedem** Build neu ausführen
(die Spec ändert sich mit dem Domain-Modell):

```bash
npx openapi-typescript src/Api/Ecommerce/openapi.yaml -o frontend/src/api/ecommerce.d.ts
```

Ziel-Datei: `frontend/src/api/{domain}.d.ts` — reine Typ-Deklarationen (`paths`,
`components`, `operations`), kein ausführbarer Code. Bei mehreren Domains je Domain eine
Datei. Der Pfad `frontend/` steht hier beispielhaft für dein Frontend-Projekt — das Template
selbst bringt keins mit.

## 2. Envelope-Fetch-Helper

Jede Antwort der Außentür trägt denselben Envelope `{status, data, errors, meta}`
(Erfolg wie Fehler; `400` = Feld-Validierung, `422` = Rule-Ablehnung, `5xx` = technisch).
Der folgende Helper zieht sich Query-, Body- und Response-Typen aus der erzeugten `.d.ts` —
Autocomplete und Compile-Fehler bei Vertragsbruch, ohne Generator:

```ts
// frontend/src/api/jardisFetch.ts — einmal kopieren, danach Dev-Hoheit
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

Verifiziert: kompiliert mit `tsc --strict` gegen die aus einem echten Build-Erzeugnis
(`Ecommerce`-Spec, 27 Routen) erzeugten Typen; fehlende Pflichtfelder und falsche
Feld-Typen im Body sind Compile-Fehler.

### Benutzung

```ts
// Lesen: Liste mit typisierter Query — data.items[].invoiceNumber ist string
const list = await jardisFetch('/accounting/customerInvoices', 'get', {
    query: { limit: 20, offset: 0 },
});

// Schreiben: Command mit Pfad-Parameter + typisiertem Body.
// Decimal-Felder sind im Vertrag Strings (format: decimal), z. B. "4.50".
const result = await jardisFetch('/accounting/customerInvoices/{invoiceNumber}/dunningNotice', 'post', {
    params: { invoiceNumber: 'RE-2026-001' },
    body: { dunningLevel: 1, dunningDate: '2026-08-27', /* … alle Pflichtfelder */ },
});
if (result.status !== 200) { /* errors auswerten: 400 Feld-Validierung, 422 Rule */ }
```

Hinweise zum Vertrag:

- **Schreib-Antworten tragen Referenzen** (fachlicher Identifier der Wurzel + Kind-Referenzen),
  nie Zustandskopien — zum Nachladen die Lese-Routen benutzen (Liste → Keys → `queries/by{Key}s`).
- **Nicht-200-Antworten** kommen im selben Envelope; der Helper typisiert den 200-Ausgang,
  `status`/`errors` stehen zur Laufzeit in jeder Antwort.
- `POST` ist nicht idempotent, `PUT`/`DELETE` sind es (steht auch in der Spec-Beschreibung).

## 3. Spec ausliefern (optional)

Soll das Frontend (oder Swagger-UI) die Spec zur Laufzeit laden, genügt eine Route in
`public/index.php` neben dem Mount:

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

Alternativ liefert der Webserver die Datei statisch aus (nginx `location`-Block auf
`src/Api/`) — für reine Build-Zeit-Nutzung (Abschnitt 1) braucht es gar keine Auslieferung.

## Rahmen

- **Voraussetzung:** `jardiscore/app ^1` (steht in der `composer.json` dieses Templates) —
  die erzeugte `routes.php` baut auf dessen `Routes`-API und Envelope-Mapper.
- **Auth/Middleware sind App-Hoheit** — der erzeugte Vertrag enthält bewusst keine
  Security-Schemes; Authentifizierung gehört in die PSR-15-Pipeline der einbettenden App
  und ist kein Gegenstand dieses Rezepts.
- Fußnote: Wer statt des Helpers einen kompletten Client erzeugen will, kann Werkzeuge wie
  `orval` oder `@hey-api/openapi-ts` auf dieselbe `openapi.yaml` richten. Dieses Rezept
  spricht dafür keine Empfehlung aus.
