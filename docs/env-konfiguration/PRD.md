# PRD — ENV-Konfiguration bereinigen (Kernel · Contracts · DotEnv · Messaging · Template)

**Status:** WAS-Gremium-Gate durchlaufen (2026-08-22, 28 Befunde, alle
beschieden — GREMIUM-WAS.md). Diese Fassung arbeitet sämtliche Bescheide ein;
formale Bestätigung durch Rolf steht aus, danach Plan.

**Pfad:** Voll (harte Trigger: öffentliche API/Verhalten publizierter Packages,
>3 Phasen). Track: nur Backend.

## Ziel

Die ENV-Konfiguration der Jardis-Laufzeit ist widerspruchsfrei: eine verbindliche
Verzeichnis-Konvention mit benanntem Eigentümer, ein datei-reiner Kernel ohne
tote Fallbacks, korrekte Boolean-/Secret-Behandlung über die gesamte
Handler-Klasse, DotEnv kann .env-Inhalte auch als String laden (AWS Secrets
Manager), Messaging ist vollwertiger Bootstrap-Zugang, und der Begriff
„Koffer" ist aus allen gepflegten Flächen entfernt.

## Kontext / Belege

Erhebungen (Kernel-ENV-Fluss, App-Karte, domainRoot-Konsumenten, dbConnection,
messaging/scheduling/secret/auth): `docs/env-konfiguration/BEFUNDE.md` — alle
Datei:Zeile-Belege dort, NICHT neu erheben. Gremiums-Befunde + Bescheide:
`docs/env-konfiguration/GREMIUM-WAS.md`.

## Anforderungen

### R1 — Kernel-Bugfixes (Bescheid D3; Gate G3–G6)

1. **Bug-Klasse, nicht Einzelfälle (G4):** Alle Bootstrap-Handler werden per
   Familien-Grep (L27) auf das Muster „Roh-String-Vergleich gegen bereits
   gecasteten Wert" geprüft; alle Treffer gefixt (bekannt:
   `BuildHttpClientFromEnv` `http_verify_ssl` — schaltet heute bei `true` die
   SSL-Verifikation AB; `BuildConnectionPoolConfigFromEnv` 2 Bool-Keys). Der
   Grep ist Teil der Akzeptanz.
2. **Secrets überleben das Casting (G6):** Fix DotEnv-seitig als
   Opt-in-Mechanismus „diese Schlüssel nicht casten" (Raw-Key-Liste beim
   Laden); Default-Verhalten unverändert → kein Verhaltensbruch für andere
   DotEnv-Konsumenten, belegt durch grünen dotenv-Testbestand.
   Klassifikations-Regel: Credential-Suffixe `*_PASSWORD`, `*_USER`,
   `*_SECRET`, `*_TOKEN`; der Packer gibt sie an DotEnv mit.
3. **Fehlerregeln (G5), gelten für ALLE Handler inkl. R4:**
   - *fehlend* = Schlüssel nicht gesetzt oder leerer Wert (`KEY=`) →
     null-Degradierung (Grundsatz bleibt);
   - *fehlerhaft* = gesetzt, aber ungültig (Enum, unparsebar, Wertebereich) →
     Exception (z. B. unbekannte `DB_POOL_LOAD_BALANCING_STRATEGY`);
   - *konfiguriert, aber nicht erreichbar* (z. B. DB-Host gesetzt, Verbindung
     scheitert) → Exception statt stillem `error_log`;
   - *unbekannte Schlüssel* werden ignoriert.
- **Akzeptanz:** Regressionstests durch die ECHTE DotEnv-Kaskade (nicht
  Roh-Strings an die Closure — so verfehlten die Alt-Tests die Bugs);
  `make phpunit/phpstan/phpcs` grün; normaler do-git-update-Release mit
  Versionsschritt, als eigene erste Phase vor R2/R3 (G3: keine Nutzer außer
  uns, kein Advisory).

### R2 — Konvention + Packer aufs Projekt-Root (Bescheide D1+D2; Gate G1, G2, G7, G8, G17, G26)

Konvention für Jardis-Projekte: `<projektRoot>` = git-clone-Ziel ·
`<root>/config/env/` = Kernel-Konfiguration (fix) · `<root>/src/` =
Builder-OutputDir · Root-`.env` = ausschließlich Stack (docker compose).

- **Eigentümer der Konvention (G2):** ein neuer Wissensbasis-Grundsatz-Eintrag
  (Projekt-Layout-Konvention, mit Herkunft) ist die kanonische Quelle; Kernel
  implementiert und zitiert nur den `config/env`-Anteil, Template-README und
  R6-Dokument zitieren den Eintrag statt ihn zu wiederholen.
- **Packer-Verhalten (G1):** `BuildDomainKernelFromEnv` nimmt das Projekt-Root
  und liest immer `<root>/config/env`. Fehlt das Verzeichnis, legt der Packer
  es selbst an (mkdir); Exception NUR, wenn das Anlegen scheitert
  (Rechte/Read-only). Kein Fallback, EINE Semantik. Leeres Verzeichnis =
  fehlende Konfiguration → null-Adapter (G26; fehlende Dateien ebenso — die
  Pflicht-`.env.database` per `load()` bleibt Template-Entscheidung).
- **Umbenennung (G8):** `domainRoot()` → `projectRoot()` in Contracts +
  Kernel; Docblock: „root of the project the kernel serves; multiple domains
  in one project share it". Löst den Docblock-Widerspruch (BEFUNDE §3) auf.
- **Migration statt Kompat-Annahme (G7):** erschöpfender Grep über alle
  Ökosystem-Repos nach Packer-Aufrufern und domainRoot-Konstrukteuren (L1);
  alle Fundstellen im selben Zug migriert (bekannt: Template, Kernel-Tests,
  getting-started-Doku; Builder-Testharnesse → Punkt im R6-Dokument).
- **Akzeptanz:** Packer-Tests für Projekt-Root, mkdir-Pfad und
  mkdir-Fehlschlag; Contracts-Docblocks aktualisiert; Kernel-README/Skills
  zeigen nur die Konvention; jardis-app-template (README, public/index.php,
  bin/console) umgestellt und mit `make start` + `/health` verifiziert —
  zusätzlich der UNKONFIGURIERTE Zustand (frischer Klon, keine Dienste)
  weiterhin grün (G17).

### R3 — Toten `$_ENV`-Fallback entfernen (Bescheid D4; Gate G16)

`BuildDomainKernelFromEnv::$envGet` und `DomainKernel::env()` verlieren den
lowercase-`$_ENV`-Fallback (nachweislich wirkungslos, BEFUNDE §1b). Der Kernel
ist ehrlich datei-rein. Release-Schnitt (minor/major) im do-git-update-Pfad.
- **Akzeptanz:** Tests (`testEnvPrivateOverridesGlobal` u. a.) angepasst mit
  Begründungskommentar; README/AGENTS/Skill des Kernels nachgezogen; der
  `env()`-Docblock im Contract („falls back to global $_ENV") wird im
  gebündelten Contracts-Release mitkorrigiert (G16).

### R4 — Messaging in den Kernel (Bescheid D5; Gate G9–G15, G23)

**Accessor-Regel (G11):** Ein Dienst bekommt einen benannten Kernel-Accessor
genau dann, wenn der Kernel ihn selbst aus kanonischen ENV-Schlüsseln
bootstrappt. Das trifft auf Messaging zu; Scheduling/Auth (null
ENV-Fähigkeit) bleiben Container, Secret ist DotEnv-Handler (s. Nicht-Ziele).

`DomainKernelInterface` + `DomainKernel` erhalten `messaging()`; neuer Handler
`BuildMessagingFromEnv` mit kanonischen Schlüsseln:
- `MESSAGING_TRANSPORT=kafka|rabbitmq|redis`;
- `KAFKA_BROKERS` = kommaseparierte host:port-Liste, fließt vollständig ins
  Host-Feld der Factory; `KAFKA_PORT` ist KEIN kanonischer Schlüssel (G13 —
  begradigt die Kafka-Falle aus BEFUNDE §5; Mechanik entscheidet der Plan,
  prüfbar über den Broker-Integrationstest; Adapter-Doku korrigiert);
- `RABBITMQ_HOST/PORT/USER/PASSWORD`;
- redis nutzt dieselben `REDIS_*`-Schlüssel wie der Cache (G23: ein Stack =
  ein Redis, dokumentiert).

Fehlende Konfiguration → `null`; ungültiger `MESSAGING_TRANSPORT` → Exception
(R1.3-Regeln). Der `container()`-Docblock im Contract wird korrigiert:
Messaging raus aus der Container-Liste, Verweis auf den Accessor — ein
zugesicherter Weg (G10). Neues `docs/env-examples/.env.messaging.example`;
jardis-app-template erhält `config/env/.env.messaging` (auskommentierter
Schaltpunkt) passend zu den Compose-Profilen `kafka`/`rabbitmq`.
- **Akzeptanz:** Integrationstest gegen echten Broker (Docker-Service gehört
  zur Testinfrastruktur, development.md §5); Contracts + Kernel +
  adapter/messaging releast (G12); Wissensbasis: Frontmatter-description von
  `koffer-ist-die-infrastrukturflaeche` sofort korrigiert (behauptet die
  Erweiterung fälschlich — der Eintragstext ist korrekt), nach Umsetzung
  Eintrag fortgeschrieben (Messaging = umgesetzt, G11-Regel als Kriterium)
  (G15).

### R5 — Begriff „Koffer" abschaffen (Bescheid „MUSS WEG"; Gate G18, G19, G24, G25)

Ersatzlos statt neue Metapher. **Verwendungsregel (G18):** Primärbegriff
**DomainKernel** (Contracts, API-Doku, Wissensbasis, überall wo Verwechslung
möglich); „Kernel" allein nur bei eindeutigem Package-Kontext; nie „Kernel"
für den Domain Core. Rollenbeschreibung: „the DomainKernel carries all
infrastructure services" / „die Infrastrukturfläche".

**Wissensbasis-Kennungen (G19):** Datei + `id:` des Eintrags werden
mitumbenannt (`domainkernel-ist-die-infrastrukturflaeche`), Wikilinks + INDEX
nachgezogen, Herkunftsvermerk im Eintrag; „Koffer-Architektur"-Passagen in
`ein-stack-eine-technische-umgebung` umformuliert. Publizierte Historie und
eingefrorene Snapshots (`split-entwurf/` u. ä.) bleiben unangetastet.
- **Akzeptanz:** `grep -ri koffer` = 0 Treffer in gepflegten Flächen ALLER
  berührten Repos: kernel, app, contracts, dotenv, adapter/messaging,
  jardis-claude, jardis-app-template, dev-skills (G24; Archive ausgenommen);
  Linkintegrität der Wissensbasis per Check-Werkzeug belegt.

### R6 — Requirement-Dokument an Jardis/Builder (Gate G7, G20, G21)

Ein Dokument an das Builder-Projekt, **Übergabeort:
`jardis/tools/builder/docs/`** (requirements-Dokument, von der WIE-Fläche
verlinkt) (G20). Inhalt:
1. Zielverzeichnis-Konvention (zitiert den R2-Wissensbasis-Eintrag) — Builder
   gibt Projekt-Root vor, klont Template, generiert nach `src/`,
   `bootstrap.php` ruft den Packer mit Projekt-Root.
2. **Eigentums-Regel (G21, bootstrap.php-Muster):** Jardis schreibt beide
   Konfigurationsschichten nur bei Erst-Provisionierung (ForceOverwrite:false),
   danach Entwickler-Eigentum; dauerhaft maschinenschreibbar bleibt nur die
   `COMPOSE_PROFILES`-Zeile der Root-`.env`; Secrets schreibt Jardis nie
   (`.env.local`). Löst die DB_NAME/DB_DATABASE-Doppelpflege aus EINER Quelle.
   Die Eigentums-Tabelle im Template-README wird um `.env`/`config/env/`
   ergänzt.
3. DB-Wahl als exklusive Auswahl (db-mariadb XOR db-postgres, Alias-`db`).
4. Kanonische Messaging-Schlüssel aus R4 für den generierten EventRouter.
5. **Builder-Testharnesse (G7):** konstruieren domainRoot heute als
   Facade-Verzeichnis — Anpassung an die `projectRoot`-Semantik.
- **Akzeptanz:** Dokument liegt am benannten Ort und ist von Rolf abgenommen;
  Umsetzung (Go-Seite) ist NICHT Teil dieses Vorhabens.

### R7 — DotEnv String-Input (Bescheid Rolf 2026-08-22, Scope-Erweiterung)

DotEnv kann .env-Inhalte auch als **String** laden
(`loadPublicFromString()`/`loadPrivateFromString()`) — Anwendungsfall: AWS
Secrets Manager liefert Secret-Inhalte als String. Machbarkeit belegt:
`LoadValuesFromFiles` parst ab `loadFileValues(array $rows, ...)`
zeilenbasiert; String-Split nutzt dieselbe Pipeline (Cast-Kette, `${VAR}`,
`KEY_FILE`, Handler). Kein Kaskaden-Kontext bei String-Input;
`load()`-Include-Direktiven im String-Modus verboten → Exception (bis realer
Bedarf kommt).
- **Akzeptanz:** Tests für beide String-Einstiege inkl. Include-Verbot;
  DotEnv-Doku; EIN DotEnv-Release zusammen mit dem R1.2-Raw-Keys-Mechanismus.

## Nicht-Ziele

- Kein Multi-Connection-Kernel; zwei DBs = zwei **Stacks** (je eigener
  Template-Klon mit eigenem Kernel — G27, Wortlaut
  `ein-stack-eine-technische-umgebung`).
- Keine neue Konfig-Metapher als Koffer-Ersatz.
- Keine Accessoren für Scheduling/Auth/Secret — per G11-Regel: kein
  ENV-Bootstrap durch den Kernel (Scheduling/Auth: reine Fluent-API bzw.
  Konstruktor-Injection; Secret: DotEnv-Handler).
- `kafkaConsumer`-/`database`-Transport nicht in R4 (G14) — Folgekandidaten
  übers worker-Profil. **Merkposten Rolf:** für lokale Nutzung ist der
  `database`-Transport ggf. genauso wichtig wie die großen Queue-Server.
- Keine Builder-(Go-)Änderungen — nur das R6-Dokument.
- dbConnection-Lücken jenseits der Bugs (SSL-Optionen über ENV, SQLite-
  Adapterklasse im Packer, Pool ohne Reader) — Folgekandidaten, BEFUNDE §4.

## Leitplanken

- Jede Package-Änderung: Tests + sämtliche Docs im selben Zug + Release über
  `do-git-update` (Version forward, nie Historie anfassen — development.md §6).
- Reihenfolge: R1 als eigene erste Phase mit eigenem Release; R4 vor der
  Wissensbasis-Fortschreibung; **jede Phase schreibt angefassten Doku-Text
  bereits koffer-frei, der R5-Sweep läuft als LETZTES und fängt nur Reste**
  (G25).
- Contracts wird von R2/R3/R4 berührt → EIN Contracts-Release, nicht drei.
  DotEnv: EIN Release für R1.2 + R7.
- Kein Push/Release ohne den do-git-update-Pfad; STOPP-Kriterien aus
  project-workflow §3.6/3.9 gelten.
