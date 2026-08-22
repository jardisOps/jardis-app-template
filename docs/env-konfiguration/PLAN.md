# PLAN — ENV-Konfiguration bereinigen (Voll-Pfad, WIE-Gate eingearbeitet)

> Nach Freigabe → `docs/env-konfiguration/PLAN.md` (Lauf-Heimat
> devops/jardis-app-template); PROGRESS wird für den Neu-Session-Einstieg
> per `/weiter` fortgeschrieben. PRD bestätigt 2026-08-22 (GREMIUM-WAS.md,
> 28 Bescheide). WIE-Gremium gelaufen 2026-08-22: wie-architekt (23),
> wie-test-qa (15), wie-packages-experte (8), wie-senior-php (11) = 57
> Befunde; Bescheide delegiert an den Orchestrator (Bescheid Rolf), alle
> eingearbeitet — Zusammenfassung am Ende, Volltext nach Freigabe in
> GREMIUM-WIE.md. Track aller Phasen: **Backend**.

## Kontext

R1–R7 des PRD: Kernel-Bugfixes (Bool-Klasse, Secrets, Fehlerregeln),
Projekt-Root-Konvention + `projectRoot()`, toter `$_ENV`-Fallback raus,
Messaging-Accessor, Koffer-Sweep, R6-Dokument an den Builder,
DotEnv-String-Input. 5 publizierte Packages + Wissensbasis + Template +
dev-skills.

## Erhebungs-Kernfakten (Ground Truth)

- Bug-Stellen (Familien-Grep über alle 11 Handler gelaufen):
  `BuildConnectionPoolConfigFromEnv.php:46,66`, `BuildHttpClientFromEnv.php:46`.
  Cast-Kette liefert für `"1"/"0"` **int**, für `true/false` **bool** —
  Alt-Handler-Tests füttern Roh-String-Closures und verfehlen das.
- Kernel: nur `tests/Unit`-Suite; Compose nur `phpcli`. DotEnv-Cast-Stellen:
  `LoadValuesFromFiles.php:141-142` (Zeilen) und `:256-257` (`_FILE`);
  `loadFileValues` ist protected (:104). `DotEnvInterface` (contracts) trägt
  nur loadPublic/loadPrivate; `addHandler` ist Klassen-API-Präzedenz.
- Packer-Aufrufer (erschöpfend): Template `public/index.php:41`,
  `bin/console:22` (`$root.'/config/env'`); app `docs/getting-started.md:78`
  + examples + `bootstrap-fail-index.php:28`; Kernel-Tests/README; Builder
  `appstarter.go:83` (nur R6-Dokument).
- Messaging: `ConnectionFactory::kafka(string $brokers,...)` nimmt
  Brokerlisten direkt (`KafkaConnection.php:46-49` verarbeitet `:`/`,` im
  Host-Feld); `ConnectionConfig::fromEnv` ($_ENV, Port-0-Falle) ist
  undokumentiert und ungenutzt. `MessagingService(Closure $publisherFactory,
  Closure $consumerFactory)`. Integrationstests + Broker-Compose vorhanden;
  gleiche phpcli-Image-Familie wie Kernel (Beleg, dass Broker-Extensions im
  Image vorhanden sind — P4-Preflight verifiziert).
- Koffer-Fundstellen: erschöpfende Dateiliste je Repo liegt vor (Explorer
  2026-08-22); dotenv + messaging: 0. Wissensbasis: `INDEX.md` = Generat
  (`make wb-index`), Prüfung `make wb-check`; Wikilinks auf den
  Koffer-Eintrag: `ein-stack...md:30,:67`, `kernel-entkopplung...md:60`.

## Release-Architektur

1. **dotenv v1.2.0** (P1) — Raw-Keys + String-Input, reine Klassen-API,
   `DotEnvInterface` bleibt unverändert (Bescheid am WIE-Gate: verhindert
   die Fatal-Kombination contracts-neu + dotenv-alt ersatzlos).
2. **kernel v1.2.0** (P2) — R1; `composer.json` dotenv `^1.2`; CHANGELOG
   dokumentiert die zwei bewussten Verhaltensänderungen.
3. **contracts v2.0.0** (P4, EIN Release; Major bestätigt Rolf 2026-08-22:
   `^1.0`-Constraints alter Kernel dürfen den Rename nie auflösen) —
   `projectRoot()`-Rename, `env()`-Docblock, `messaging()`,
   `container()`-Docblock.
4. **kernel v2.0.0** (P4) — R2+R3+R4; contracts `^2.0`.
5. **messaging** (P4) — Doku/.env.example-Begradigung; Schnitt im
   do-git-update-Pfad (voraussichtlich Patch).
6. Kein Release: app (Doku), Template, claude, dev-skills.

## Phasen

### P1 — DotEnv: Raw-Keys + String-Input (R1.2-Mechanik, R7)
**Repo:** support/dotenv · **Abhängigkeiten:** keine.
**Scope:** `src/DotEnv.php`, `src/Reader/LoadValuesFromFiles.php`, NEU
`src/Reader/LoadValuesFromRows.php`, NEU `src/Reader/LoadValuesFromString.php`,
NEU `src/Handler/MatchesRawKey.php`, NEU
`src/Exception/IncludeNotSupportedException.php`, `tests/Unit/**` +
`tests/Fixtures/**` (+ Helfer nach `tests/Support/`), `README.md`.
Die kuratierte Skill-Quelle (claude-Repo) wird hier NICHT angefasst —
Single-Writer, Nachzug in P6.
**Bauform (WIE-Bescheide):**
- Zeilen-Engine als eigene atomare Einheit `LoadValuesFromRows`
  (`__invoke(array $rows, bool $public, ?string $baseDir): array`)
  extrahiert; `LoadValuesFromFiles::loadFileValues` delegiert (BC, ein
  öffentlicher Einstieg je Einheit bleibt gewahrt); `LoadValuesFromString`
  (`__invoke(string $content, bool $public, ?string $baseDir): array`)
  nutzt die Engine per Komposition.
- Raw-Keys: `Handler/MatchesRawKey` (`__invoke(string $key): bool`,
  case-insensitives Suffix- ODER Exakt-Match, kein Teilstring-Match) wird an
  BEIDEN Cast-Stellen der Engine gefragt; beim `_FILE`-Pfad gilt der
  **aufgelöste** Key (`DB_PASSWORD_FILE` → Regel greift auf `DB_PASSWORD`).
  Zuführung: `DotEnv::addRawKeys(array $keysOrSuffixes): void`
  (`array<string>`, akkumulierend + dedupliziert, kein remove bis Bedarf) —
  wirkt wie `addHandler` nur auf den intern erzeugten Reader (bestehende
  Injektions-Falle `DotEnv.php:29-30` bleibt unangetastet, dokumentiert,
  Folgekandidat). Abgrenzung zu jardissupport/secret: dort Entschlüsselung
  via Handler-Kette (wert-basiert); hier Cast-Ausnahme (key-basiert) —
  Handler-Kette ist key-blind, deshalb kein Handler.
- String-API: `DotEnv::loadPublicFromString/loadPrivateFromString`;
  `preg_split('/\R/')`, nur exakt leere Zeilen filtern
  (FILE_SKIP_EMPTY_LINES-Parität), führende UTF-8-BOM strippen; keine
  APP_ENV-Kaskade; `load()`-Direktive → `IncludeNotSupportedException`;
  relativer `KEY_FILE`-Pfad ohne übergebenes `$baseDir` → Exception
  (fehlerhaft = laut), absolute Pfade erlaubt.
**AK-Matrix (Beweis-Tiefe):**
- AK1.1 `DB_PASSWORD=false`/`=123456` überleben `loadPrivate` als String
  bei registriertem Raw-Key — e2e echte Fixture-Datei. Auch via
  `DB_PASSWORD_FILE` (Secret-Datei-Fixture).
- AK1.2 Ohne Registrierung Verhalten identisch v1.1.5 — Bestands-Suite
  (144 Tests) grün, Handler-Ketten-API signaturgleich (Diff-Beleg).
- AK1.3 Paritätstest Datei↔String (Casts, `${VAR}`, `KEY_FILE` absolut,
  CRLF, BOM, trailing newline, Whitespace-Zeile) — identische Arrays.
- AK1.4 Negativfälle: `load()` im String → IncludeNotSupportedException;
  relatives `KEY_FILE` ohne baseDir → Exception.
- AK1.5 Matching-Grenzfälle: Suffix case-insensitiv, Teilstring matcht
  NICHT, Exakt-Key, Mehrfach-addRawKeys idempotent.
- AK1.6 `make phpunit/phpstan/phpcs` grün; Coverage ≥ 80 %
  (`make phpunit-coverage`); do-qa-codereview.
**Release:** do-git-update → v1.2.0.

### P2 — Kernel R1: Bool-Klasse + Fehlerregeln + Raw-Keys-Anbindung
**Repo:** core/kernel · **Abhängigkeiten:** P1 releast.
**Scope:** ALLE 11 Dateien `src/Bootstrap/Handler/*.php` (Prüfung gegen die
vier G5-Regeln; bekannte Fixes: PoolConfig :46,:66, HttpClient :46,
BuildConnectionFromEnv-Fehlerpfade :63-65,:84-86,:105-136,
BuildRedisFromEnv :53-55), NEU `src/Bootstrap/Handler/NormalizeEnvBool.php`,
NEU `src/Exception/InvalidEnvConfigurationException.php`,
`src/Bootstrap/BuildDomainKernelFromEnv.php` (Raw-Keys registrieren;
`$envGet`: `''`→`null` zentral), `src/DomainKernel.php` (`env()`: `''`→`null`),
`composer.json` (dotenv `^1.2`), `CHANGELOG.md` (bewusste
Verhaltensänderungen), `phpunit.xml` (+`tests/Integration`-Suite),
`tests/Integration/Bootstrap/**` (Kaskaden-Fixture-Tests NEU),
`tests/Fixtures/Bootstrap/**`, `tests/Unit/Bootstrap/**` (auf
gecastete Werte umgestellt), `tests/Support/` (Helfer), `README.md`,
`.claude/CLAUDE.md`, `.claude/skills/core-kernel/SKILL.md` (nur
R1-Aussagen, neu geschriebener Text koffer-frei).
**Bauform (WIE-Bescheide):**
- Genau EINE `NormalizeEnvBool`-Einheit (keine private Kopie je Handler):
  `__invoke(mixed $value, string $key): ?bool` — bool durchreichen,
  int 0/1, String via `filter_var(..., FILTER_NULL_ON_FAILURE)`; null/''
  → null (fehlend, Aufrufer setzt Default); unparsebar (`maybe`) →
  InvalidEnvConfigurationException (G5-Regel 2).
- Konfig-Validierungsfehler vom Verbindungs-Fallback entkoppeln:
  `InvalidEnvConfigurationException` wird in den catch-\Throwable-Pfaden
  von `BuildConnectionFromEnv` **rethrown** — ungültige Pool-Strategie darf
  nie im PDO-Fallback verschwinden (Senior-PHP-Blocker).
- „Leer = fehlend" zentral: `$envGet`/`env()` mappen `''` auf `null` —
  gilt damit uniform für alle Handler.
- Credential-Suffixe (`*_PASSWORD`,`*_USER`,`*_SECRET`,`*_TOKEN`) liegen
  als Konstante in `src/Bootstrap/Data/` (nicht im Orchestrator-Body);
  Packer registriert sie vor `loadPrivate`.
**AK-Matrix:**
- AK2.1 `HTTP_VERIFY_SSL=true|false|1|0` durch echte Kaskade
  (tests/Integration, Fixture) → korrektes verifySsl; Gegenprobe: mit
  altem Code rot. Ebenso beide `DB_POOL_*`-Bools.
- AK2.2 `HTTP_VERIFY_SSL=maybe` → InvalidEnvConfigurationException.
- AK2.3 `DB_PASSWORD=false` erreicht das öffentliche Config-VO
  (dbConnection MySqlConfig) als String `'false'` — Integration-Fixture,
  Assertion an öffentlicher API.
- AK2.4 Unbekannte Pool-Strategie bei ERREICHBAREM Host → Exception
  (exakt das Verschluck-Szenario); fehlende DB-Konfiguration → null
  (Bestand); `DB_HOST=` (leer) → null-Degradierung; unbekannter fremder
  Schlüssel (`FOO_BAR=x`) → Boot unverändert (G5-Regel 4).
- AK2.5 Konfigurierter, unerreichbarer Host → Exception — für DB UND
  Redis (BuildRedisFromEnv-Fall).
- AK2.6 `make phpunit/phpstan/phpcs` grün; Coverage ≥ 80 %; CHANGELOG
  benennt beide Verhaltensänderungen; do-qa-codereview.
**Release:** do-git-update → v1.2.0 (G3: normaler Release).

### P3 — Kernel R2+R3 Code + Contracts-Änderungen + Wissensbasis-Eintrag (ohne Release)
**Repos:** core/kernel (develop), support/contracts (develop), jardis/claude ·
**Abhängigkeiten:** P2 gemergt.
**Scope:** kernel `src/Bootstrap/BuildDomainKernelFromEnv.php`
(Projekt-Root, mkdir, `config/env`, **$_ENV-Fallback in `$envGet` :83
entfernen**), `src/DomainKernel.php` (projectRoot-Rename, `env()`
:60-64 Fallback raus), Tests (`DomainKernelTest.php` :169,:177-185 mit
Begründungskommentar; `BuildDomainKernelFromEnvTest.php`: Projekt-Root,
mkdir-Erfolg, mkdir-Fehlschlag, leeres Verzeichnis), README/
`.claude/CLAUDE.md`/`docs/env-examples/README.md`; contracts
`src/Kernel/DomainKernelInterface.php` (:35 Rename, :40-46 env-Docblock);
claude-Repo: NEU `wissensbasis/projekt-layout-konvention.md` (G2) +
**Sofort-Korrektur der Frontmatter-description von
`koffer-ist-die-infrastrukturflaeche.md`** (G15) + `make wb-index`.
**Bauform:** `__invoke(string $projectRoot)` → `$configPath =
$projectRoot.'/config/env'`; race-sicheres Anlegen:
`if (!is_dir($p) && !@mkdir($p, 0775, true) && !is_dir($p)) throw` (TOCTOU:
paralleler fpm-Kaltstart); kein Fallback (G1). Kernel-QA in P3 läuft gegen
contracts-develop via **composer path-repository** (festgelegt, kein
Doer-Ermessen); die End-Verifikation gegen den releasten Stand ist AK4.6.
**AK-Matrix:**
- AK3.1 Beide Pfadlagen + mkdir-Fehlschlag (nicht schreibbarer Parent) —
  Integration-Fixture; leeres angelegtes Verzeichnis → null-Kernel.
- AK3.2 `grep -rn '\$_ENV' src/` im Kernel = 0 Treffer; `env()` liefert
  nur Datei-Werte (Alt-Tests angepasst mit Kommentar).
- AK3.3 `grep -rn domainRoot` in kernel+contracts src/tests = 0 (außer
  CHANGELOG/Historie).
- AK3.4 Wissensbasis: neuer Eintrag existiert, description-Fix drin,
  `make wb-check` + `make wb-index CHECK=1` grün; Kernel-README zitiert
  den Eintrag.
- AK3.5 kernel `make phpunit/phpstan/phpcs` grün gegen path-repo-contracts.
**Kein Release** — P4 bündelt.

### P4 — R4 Messaging + gebündelte Releases (contracts → kernel → messaging)
**Repos:** support/contracts, core/kernel, adapter/messaging ·
**Abhängigkeiten:** P3.
**Preflight (STOPP-fähig):** `php -m` im phpcli-Container → rdkafka, amqp,
redis vorhanden? (Beleg-Erwartung: messaging-Repo fährt Integrationstests
mit derselben Image-Familie.) Fehlt eine Extension → STOPP an Rolf
(Fix läge in php-image-builder, außerhalb Scope). `docker ps` auf
Broker-Reste (kernel UND messaging — L2).
**Scope:** contracts `DomainKernelInterface.php`
(`messaging(): ?MessagingServiceInterface` — Typ existiert:
`src/Messaging/MessagingServiceInterface.php`; `container()`-Docblock
:48-58 korrigieren); kernel NEU
`src/Bootstrap/Handler/BuildMessagingFromEnv.php`, `src/DomainKernel.php`
(Accessor; neuer Konstruktor-Parameter **ans Ende, nach `array $env`**,
Packer nutzt named arguments), `BuildDomainKernelFromEnv.php`
(Verdrahtung), `composer.json` (contracts `^2.0`; `suggest` +
`require-dev` `jardisadapter/messaging` + Broker-Extensions nach
messaging-Vorbild), `support/docker-compose.yml` (+kafka/rabbitmq/redis,
EIGENE Container-Namen/Host-Ports — nie parallel zur messaging-QA),
Makefile (`start`-Prerequisite analog qa-stack.mk),
`tests/Integration/Bootstrap/BuildMessagingFromEnvTest.php`,
`docs/env-examples/.env.messaging.example`; messaging `.env.example:54-55`
(KAFKA_PORT als reines Compose-Mapping kommentieren),
`ConnectionConfig.php:43-55` (fromEnv-PHPDoc: Kafka-Brokerliste ins
Host-Feld, Port-0-Falle), README-Kafka-Abschnitt.
**Bauform (WIE-Bescheide):**
- Handler nutzt die **vorhandenen Fabriken**: `match($transport)` →
  `ConnectionFactory::kafka($brokers)` / `rabbitMq(host,port,user,pass)` /
  `redis(host,port,password)`; Publisher/Consumer über
  `PublisherFactory`/`ConsumerFactory`; **kein eigenes Brokerlisten- oder
  Port-Parsing** (G13 erfüllt: `KAFKA_BROKERS` fließt unverändert als
  `$brokers` in die Factory).
- `class_exists(ConnectionFactory::class)`-Guard → null (Adapter nicht
  installiert = fehlend; Muster BuildConnectionPoolConfigFromEnv:32-40).
- Consumer-Zweig: rabbitmq/redis über ConsumerFactory regulär; kafka-
  Consumer braucht groupId (G14 Nicht-Ziel) → Consumer-Closure wirft
  RuntimeException mit klarer Meldung erst bei Nutzung
  (MessagingService instanziiert lazy — Publish-only bleibt frei).
- redis: WERTE aus `REDIS_*` neu lesen, EIGENE Verbindung über die
  Factory (keine geteilte \Redis-Instanz mit Cache/Logger — pub/sub
  blockiert Verbindungen); im env-example dokumentiert (G23).
- Ungültiger `MESSAGING_TRANSPORT` → InvalidEnvConfigurationException;
  fehlend/leer → null.
**AK-Matrix:**
- AK4.1 Je Transport: Kernel aus Fixture-Kaskade, `messaging()`-Publish-
  Roundtrip (publish + consume/read) gegen echten Docker-Broker — e2e.
- AK4.2 `MESSAGING_TRANSPORT=invalid` → Exception; nicht gesetzt → null;
  Adapter nicht installiert → null (Unit mit class_exists-Pfad).
- AK4.3 `KAFKA_BROKERS=host1:9092,host2:9092` erreicht die Factory
  unverändert (Assertion an der öffentlichen Factory-Grenze) + Roundtrip
  über den Ein-Broker-Eintrag der Testumgebung.
- AK4.4 Kafka-Consumer-Aufruf ohne groupId-Konfiguration → klare
  RuntimeException (dokumentierte G14-Grenze).
- AK4.5 Kombi-Fall: Cache UND Messaging über denselben Redis gleichzeitig
  aktiv → beide funktionieren (Integration).
- AK4.6 Release-Kette: contracts v2.0.0 → kernel-`composer update` gegen
  RELEASTE contracts (path-repo entfernt), volle Kernel-QA erneut grün →
  kernel v2.0.0 → messaging-Release. Coverage ≥ 80 % je Release-Repo.

### P5 — Template-Nachzug + App-Doku
**Repos:** devops/jardis-app-template, core/app (nur Doku) ·
**Abhängigkeiten:** P4 (Packagist).
**Scope:** Template `public/index.php:41` + `bin/console:22`
(→ `(new BuildDomainKernelFromEnv())($root)`), `composer.json`
(kernel `^2.0`), NEU `config/env/.env.messaging` (auskommentierter
Schaltpunkt + `load?()`-Kaskadenzeile), README (:74, :90-99;
Eigentums-Tabelle + `.env`/`config/env/` — G21), `.claude/CLAUDE.md`
(zitiert Wissensbasis-Eintrag); app `docs/getting-started.md`
(:51-:78 Projekt-Root, koffer-frei), README, examples,
`bootstrap-fail-index.php:28` (Fehlerfall bleibt Fehlerfall — prüfen).
**AK-Matrix:**
- AK5.1 `make start` + `curl /health` grün (konfiguriert) — e2e.
- AK5.2 Unkonfigurierter Zustand (frischer Klon simuliert) grün — e2e (G17).
- AK5.3 `bin/console kernel` zeigt erwartete Adapter-Zustände.
- AK5.4 app-Doku: Packer-Beispiele Projekt-Root, `grep -ri koffer` in den
  von P5 angefassten app-Dateien = 0.

### P6 — R5 Koffer-Sweep (8 Repos) + Wissensbasis-Umbenennung
**Repos:** alle 8 (erschöpfende Dateiliste = Explorer-Erhebung, L1) ·
**Abhängigkeiten:** P4/P5 (G25: Sweep zuletzt).
**Scope:** Verwendungsregel G18; Umbenennung
`koffer-ist-die-infrastrukturflaeche.md` →
`domainkernel-ist-die-infrastrukturflaeche.md` (Datei + id +
Herkunftsvermerk + G15-Fortschreibung „Messaging umgesetzt" + G11-Regel);
Wikilinks + `ein-stack...`-Passagen (G27-Wortlaut);
`make wb-index`/`wb-check`; kuratierte Skills (12, inkl.
support-dotenv-Nachzug: addRawKeys/fromString) + Package-Kopien
(kernel 4 — `.backup`-Kopien werden GELÖSCHT, Redundanz; template 2,
app 7, contracts 2); dev-skills (7 Skills + README + manifest); kernel
README/docs; rules/development.md + beide Profile (je 1);
marketing (3); reference/REQUIREMENTS_TEMPLATES.md.
Ausgenommen (Archiv): rules/archive/, rules/split-entwurf/,
symfony-demo/vendor-Snapshots, Lauf-Rechenschaft (GREMIUM-*/PRD/BEFUNDE/
PROGRESS).
**AK-Matrix:**
- AK6.1 `grep -ri koffer` = 0 in gepflegten Flächen aller 8 Repos
  (Ausnahmeliste exakt benannt) — je Repo Grep-Beleg.
- AK6.2 `make wb-check` + `make wb-index CHECK=1` grün; Backlinks der
  neuen Kennung aufgelöst.
- AK6.3 Diff-Beleg: nur Doku/Kommentare/Identifier-freie Strings.
- AK6.4 QA-Lauf in JEDEM berührten Code-Repo nach dem Sweep
  (kernel/contracts/dotenv/messaging/app: `make phpstan phpcs phpunit`) —
  ein Diff-Review ersetzt keinen grünen Lauf.

### P7 — R6 Requirement-Dokument an den Builder
**Repo:** jardis/tools/builder (nur docs/) · **Abhängigkeiten:** P4
(Schlüssel final); nach P6 (Begriffe final).
**Scope:** NEU `docs/requirements/projekt-template-env.md`; Verlinkung von
`.claude/wissen/INDEX.md`.
**AK:** Inhalts-Checkliste — die fünf PRD-R6-Punkte je nachweislich
enthalten (Konvention zitiert Wissensbasis-Eintrag; Eigentums-Regel G21;
DB-XOR; Messaging-Schlüssel; Testharness-`projectRoot` +
`appstarter.go:83`-Hinweis); Verlinkung per Grep belegt; Abnahme Rolf
(STOPP-Punkt, User-Gate).

### Abschluss — Akzeptanz-Gate + Docs-Sync + Retro
Gate gegen das ganze PRD (R1–R7, deklarierte AK-Tiefen); volle QA-Suiten
kernel/dotenv/messaging einmal komplett; Template beide Zustände;
PROGRESS eindampfen; Retro.

## Parallelisierung & QA
P1 → P2 → P3 → P4 → P5 → P6 → P7 sequenziell (geteilte Contracts-/
Release-Abhängigkeiten bzw. G25; P7 nach P6, im Zweifel sequenziell).
Kernel-Broker-QA nie parallel zur messaging-QA (eigene Container-Namen/
Ports, L2). Modelle: Umsetzer Sonnet (P6-Mechanik teils Haiku), Verifier
Sonnet, Orchestrator committet/pflegt PROGRESS.

## Wiederanlauf-Preflight-Ergänzungen (in PROGRESS zu übernehmen)
- Nach P1/P2/P4-Releases: `composer show` im abhängigen Repo gegen
  erwartete Version.
- Vor P4: `php -m`-Extension-Check + `docker ps` Broker-Reste.
- P3/P4: kernel composer trägt ggf. temporäres path-repo auf contracts —
  vor Release-Schritten prüfen und entfernen.

## WIE-Gate-Bescheide (Kurzfassung; Volltext nach Freigabe in GREMIUM-WIE.md)
57 Befunde (Architekt 23, Test-QA 15, Packages 8, Senior-PHP 11), Bescheide
an Orchestrator delegiert (Rolf 2026-08-22). Tragend: contracts Major
v2.0.0 (Rolf explizit bestätigt) · DotEnvInterface unverändert
(Klassen-API, addHandler-Präzedenz) · Zeilen-Engine als eigene Einheit
statt zweiter public-Methode · MatchesRawKey als eigene Closure ·
NormalizeEnvBool EINE Einheit, versteht bool|int|string, unparsebar wird
laut · InvalidEnvConfigurationException entkoppelt Konfig-Validierung vom
PDO-Fallback (Senior-PHP-Blocker) · `''`→`null` zentral im envGet ·
$_ENV-Fallback AUCH in Packer :83 · mkdir race-sicher · Messaging-Handler
nutzt ConnectionFactory/PublisherFactory/ConsumerFactory, kein eigenes
Parsing · Kafka-Consumer wirft dokumentiert (G14-Grenze) · Redis: eigene
Verbindung, Werte geteilt · Integration-Suite ehrlich ab P2 · Re-QA gegen
releaste contracts (AK4.6) · Coverage-≥80%-Gate je Release · P6 mit
QA-Läufen statt nur Diff · P7 Inhalts-Checkliste. Verworfen (begründet):
Test für Raw-Keys mit injiziertem Reader (Bestandsfalle, Folgekandidat) ·
Fremd-Repo-Regressionsläufe für DotEnv-Konsumenten (Constraint ^1.0 zieht
v1.2 kompatibel, Default-Verhalten unverändert + Suite grün genügt).
