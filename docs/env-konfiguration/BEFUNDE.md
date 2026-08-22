# BEFUNDE — ENV-Konfiguration (Erhebung 2026-08-22)

Kondensat dreier blinder Bestandserhebungen plus zweier Karten (Kernel, App)
aus der Session jardis-app-template vom 2026-08-22. Alle Aussagen sind
Code-Evidenz mit Datei:Zeile; NICHT neu erheben, bei Zweifel Fundstelle lesen.

## §1 Kernel-ENV-Fluss (core/kernel)

**a) Datei-rein:** `BuildDomainKernelFromEnv::__invoke` (src/Bootstrap/
BuildDomainKernelFromEnv.php:80-101) lädt via `DotEnv::loadPrivate($configPath)`
NUR Dateien; `loadPrivate` publiziert nichts nach getenv/$_ENV/$_SERVER
(support/dotenv src/DotEnv.php:67-82, LoadValuesFromFiles.php:285-291 wird im
Private-Modus nie aufgerufen). Alle Keys werden lowercased (:82); Handler lesen
ausschließlich über die `$envGet`-Closure — kein Handler greift selbst auf
getenv/$_ENV zu (grep über Handler/*.php = 0 Treffer).

**b) Toter $_ENV-Fallback:** `$envGet` (:83) und `DomainKernel::env()`
(src/DomainKernel.php:60-64) schlagen mit LOWERCASE-Key in `$_ENV` nach.
Docker-`environment:`-Variablen stehen UPPERCASE in $_ENV → Fallback greift
praktisch nie. Test tests/Unit/DomainKernelTest.php:169 setzt den $_ENV-Key
bewusst lowercase; Präzedenz-Test :177-185 (privat > $_ENV). Reale
Prozess-Env-Einfallstore sind nur: APP_ENV (UPPERCASE-Sonderfall,
DotEnv.php:104-109, steuert Kaskaden-Stufe 2), `${VAR}`-Substitution
(VariableRegistry.php:21-30 → getenv) und `~`-Expansion (CastUserHome.php:66-76).

**c) Bug Bool-Cast vs. String-Vergleich (SECURITY):** Die Cast-Kette
(CastStringToBool.php:22) macht aus `true` in Dateien `bool(true)`.
`BuildHttpClientFromEnv.php:46` prüft `($env('http_verify_ssl') ?? 'true')
=== 'true'` → bei gesetztem `HTTP_VERIFY_SSL=true` wird SSL-Verifikation
ABGESCHALTET. Gleiches Muster: BuildConnectionPoolConfigFromEnv.php:46 und :66.
Bestehende Tests verfehlen das, weil sie Roh-Strings direkt an die Closure
geben (tests/Unit/Bootstrap/Handler/BuildHttpClientFromEnvTest.php:33).

**d) Bug Secret-Casting:** CastStringToNumeric/Bool treffen auch Passwörter:
`DB_PASSWORD=123456` → int (durch `(string)`-Cast im Handler repariert),
`DB_PASSWORD=false` → bool(false) → `(string)` = `''`.

**e) Stilles Degradieren bei FEHLERHAFTER Config:** Verbindungsfehler und
ungültige Werte enden als `null`/PDO-Fallback mit bloßem error_log
(BuildConnectionFromEnv.php:63-65,84-86,129-136). Bsp.: Tippfehler in
`DB_POOL_LOAD_BALANCING_STRATEGY` → lautloser Verlust des Splittings.

**f) DotEnv-Mechanik:** kein `load()` im Interface — `loadPublic()` (published,
Datei überschreibt Prozess-Env bedingungslos) vs. `loadPrivate()` (Array).
Kaskade je Include: `path` < `path.local` < `path.{APP_ENV}` <
`path.{APP_ENV}.local`; `load?()` = optional, aber unlesbare Datei wirft auch
dort; Include-Merge an der Direktiven-Stelle; `KEY_FILE=`-Docker-Secrets;
Zirkelschutz.

## §2 App-Karte (core/app)

`src/` liest NIRGENDS ENV (E11; AppConfig-Docblock src/Config/AppConfig.php:7-10).
Kernel-Nutzung: einzig `$kernel->logger()` (src/App.php:91). `APP_DEBUG` kommt
per Bootstrap: `new AppConfig(debug: (bool) $kernel->env('app_debug'))`
(docs/getting-started.md:88). `HandleThrowable` zeigt Trace nur bei debug
(src/Handler/Error/HandleThrowable.php:62-70). APP_ENV kennt das Package nicht.
→ App braucht für dieses Vorhaben voraussichtlich KEINE Code-Änderung, nur
Doku (getting-started zeigt Packer-Aufruf mit `__DIR__ . '/..'`).

## §3 domainRoot

KEIN produktiver Konsument im gesamten Ökosystem (kernel, app, builder-Renderer/
-Templates, alle adapter/support): Gesamt-grep `->domainRoot()` = 3 Treffer,
alle Assertions in Kernel-Unit-Tests. DomainKernel speichert nur
(src/DomainKernel.php:47-58, Guard gegen Leerstring, kein FS-Zugriff).
ClassVersion läuft über Autoloader, nicht Dateisystem
(support/classversion src/Reader/LoadClassFromSubDirectory.php:29-66).
Widerspruch: Contract-Docblock „where the domain code lives"
(contracts src/Kernel/DomainKernelInterface.php:30-35) vs. Packer
„$configPath doubles as domainRoot" (BuildDomainKernelFromEnv.php:33-35,90;
Test BuildDomainKernelFromEnvTest.php:38,110 assertiert das).
Builder-Testharnesse konstruieren domainRoot als Verzeichnis der generierten
Facade (ReflectionClass-Muster). → Semantik-Schärfung ist gefahrlos.

## §4 dbConnection-Fähigkeiten vs. Packer

Adapter (jardisadapter/dbconnection, 9 Klassen): PdoConnection / SqLite
(PRAGMAs, vacuum) / External(fromPdo) / ConnectionPool (1 Writer + N Reader,
round-robin|random, Healthcheck mit TTL-Cache, Failover, Sticky-Writer bei
offener Transaktion, Stats) / ConnectionFactory. Configs: MySql/Postgres/
Sqlite/External/ConnectionPoolConfig (5 Felder, validiert).

Über den Packer erreichbar: Treiberwahl mysql/pgsql/sqlite, ein Writer
(DB_HOST/PORT/USER/PASSWORD/DATABASE/CHARSET), beliebig viele DB_READER{N}_*,
alle 5 DB_POOL_*. NICHT erreichbar (Folgekandidaten, NICHT Teil des
Vorhabens): PDO-`options` (kein SSL/TLS über ENV!), SqLite-Adapterklasse
(Packer baut rohes `new PDO('sqlite:…')` ohne PRAGMAs,
BuildConnectionFromEnv.php:57-66), External/fromPdo, Pool ohne Reader
(Pool-Features nur MIT mind. 1 Reader), Postgres-Charset-Inkonsistenz
(Pool-Pfad ohne, PDO-Fallback mit client_encoding). Strikt EINE
Connection/Pool pro Kernel (DomainKernel.php:42,97-100); zwei DBs = zwei
Kernel („one kernel per config root", BuildDomainKernelFromEnv.php:33-35) —
deckt sich mit Wissensbasis `ein-stack-eine-technische-umgebung`.

## §5 messaging / scheduling / secret / auth

**messaging:** ConnectionConfig (host, port validiert, username, password,
options) + `fromEnv(string $prefix)` liest `$_ENV` UPPERCASE
({PREFIX}_HOST/PORT/USER|USERNAME/PASSWORD) — einziges der vier Packages mit
ENV-Fähigkeit; Prefix frei, KEINE kanonischen Schlüssel definiert.
ConnectionFactory: redis/kafka/kafkaConsumer(groupId Pflicht)/rabbitMq/
database/from*. KAFKA-FALLE: `fromEnv` liest KAFKA_PORT, aber
`ConnectionFactory::kafka()` ignoriert Ports — Brokerliste gehört ins
Host-Feld (KafkaConnection.php:44-58; Credentials ⇒ automatisch SASL_SSL/
PLAIN). RabbitMq-Options: vhost, exchange_name/type/flags.
MessagingService nimmt nur zwei Factory-Closures.
**scheduling/auth:** null ENV, reine Fluent-API bzw. Konstruktor-Injection
(TokenStore als Port vom Projekt).
**secret:** DotEnv-Handler (`$dotEnv->addHandler(new Secret(...), prepend:
true)`); EnvKeyProvider liest getenv($name) mit frei wählbarem Namen;
AES-256-GCM + Sodium + Chain.

## §6 Koffer-Behauptung der Wissensbasis widerlegt

`wissensbasis/koffer-ist-die-infrastrukturflaeche.md` behauptet „erweitert um
messaging/scheduling/secret/auth" — im Code existiert für keines der vier
weder Accessor noch Handler (DomainKernel.php:34-46: 11 Parameter;
Handler-Verzeichnis: 11 Dateien; Contract-Kommentar verweist auf den
Container: DomainKernelInterface.php:53-56). Der ENV-Packer verdrahtet nicht
einmal einen eigenen Container (BuildDomainKernelFromEnv.php:46-49: „Container
wiring is intentionally out of scope"). Kernel-Doku bestätigt bewusste
Nicht-Emission von Messaging (docs/env-examples/README.md:12-18).
→ Eintrag fortschreiben, sobald R4 die Messaging-Erweiterung real macht.

## §7 Template-Seite (jardis-app-template, bereits umgesetzt)

Commits aba73c7/80efee3: Opt-in-Profile (db-mariadb/db-postgres via Alias
`db`, cache, rabbitmq, kafka, mail=Mailpit, worker), `COMPOSE_PROFILES` als
maschinenschreibbare Schnittstelle, config/env-Schaltpunkte (.env.cache/
.env.redis/.env.mail), Kaskade um `load?(.env.redis)` ergänzt,
DB_NAME(Stack) vs. DB_DATABASE(App) dokumentiert. Registry-Images verifiziert
(phpfpm 8.3-20260726 bootet, APP_ENV-Profile gemessen). Achtung Makefile:
`VAR=x make start` wirkt NICHT (include .env re-exportiert), nur
`make start VAR=x`.
