# PRD — ENV-Konfiguration bereinigen (Kernel · Contracts · Template)

**Status:** Entwurf, inhaltlich beschieden von Rolf (2026-08-22, Session
jardis-app-template). Vor der formalen Bestätigung läuft das WAS-Gremium-Gate
(Voll-Pfad); jeder Gremiums-Befund wird beschieden.

**Pfad:** Voll (harte Trigger: öffentliche API/Verhalten publizierter Packages,
>3 Phasen). Track: nur Backend.

## Ziel

Die ENV-Konfiguration der Jardis-Laufzeit ist widerspruchsfrei: eine verbindliche
Verzeichnis-Konvention, ein datei-reiner Kernel ohne tote Fallbacks, korrekte
Boolean-/Secret-Behandlung, Messaging als vollwertiger Bootstrap-Zugang, und der
Begriff „Koffer" ist aus allen Doku-Flächen entfernt.

## Kontext / Belege

Vollständige Erhebungen (Kernel-ENV-Fluss, App-Karte, domainRoot-Konsumenten,
dbConnection-Fähigkeiten, messaging/scheduling/secret/auth vs. Kernel):
`docs/env-konfiguration/BEFUNDE.md` — dort stehen alle Datei:Zeile-Belege.
NICHT neu erheben.

## Anforderungen (je mit Bescheid Rolf 2026-08-22)

### R1 — Kernel-Bugfixes (Bescheid D3, höchste Priorität)
1. Boolean-Vergleiche der Handler vertragen die DotEnv-Typcast-Kette:
   `BuildHttpClientFromEnv` (`http_verify_ssl`) und
   `BuildConnectionPoolConfigFromEnv` (2 Bool-Keys) akzeptieren `bool|string`.
   Heutiger Effekt ist security-relevant: `HTTP_VERIFY_SSL=true` schaltet die
   SSL-Verifikation AB (Beleg in BEFUNDE.md §1c).
2. Passwörter/Secrets überleben das Typcasting unverfälscht
   (`DB_PASSWORD=false` → heute `''`). Fix-Ort (Handler-seitig roh casten vs.
   DotEnv-Ausnahme) entscheidet der Plan; Vorgabe: kein Verhaltensbruch für
   andere DotEnv-Konsumenten.
3. FEHLERHAFTE Konfiguration wird laut (z. B. unbekannte
   `DB_POOL_LOAD_BALANCING_STRATEGY` → Exception statt stillem Verlust des
   Read/Write-Splittings). FEHLENDE Konfiguration degradiert weiterhin zu
   `null` — dieser Grundsatz bleibt unangetastet.
- **Akzeptanz:** Regressionstests, die die Werte durch die ECHTE
  DotEnv-Kaskade schicken (nicht als Roh-Strings an die Closure — genau so
  haben die bestehenden Tests die Bugs verfehlt); `make phpunit/phpstan/phpcs`
  grün; Release über do-git-update.

### R2 — Konvention + Packer aufs Projekt-Root (Bescheide D1+D2)
Konvention für Jardis-Projekte: `<projektRoot>` = git-clone-Ziel ·
`<root>/config/env/` = Kernel-Konfiguration (fix) · `<root>/src/` = Builder-
OutputDir · Root-`.env` = ausschließlich Stack (docker compose).
`BuildDomainKernelFromEnv` nimmt künftig das Projekt-Root und liest selbst
`<root>/config/env` (existiert das Verzeichnis nicht: Fallback auf das
übergebene Verzeichnis — bestehende Aufrufer brechen nicht).
`domainRoot` = Projekt-Root; der Docblock-Widerspruch im Contract
(`DomainKernelInterface::domainRoot()`, „where the domain code lives" vs.
Packer „config root") wird auf die neue Semantik geschärft. Kein produktiver
Konsument existiert (BEFUNDE.md §3) — die Schärfung ist gefahrlos.
- **Akzeptanz:** Packer-Tests für beide Pfadlagen; Contracts-Docblock
  aktualisiert; Kernel-README/Skills zeigen nur noch die Konvention;
  jardis-app-template (README, public/index.php, bin/console) auf
  Projekt-Root-Aufruf umgestellt und mit `make start` + `/health` verifiziert.

### R3 — Toten `$_ENV`-Fallback entfernen (Bescheid D4)
`BuildDomainKernelFromEnv::$envGet` und `DomainKernel::env()` verlieren den
lowercase-`$_ENV`-Fallback (nachweislich wirkungslos für reale
Docker-Umgebungen, BEFUNDE.md §1b). Der Kernel ist damit ehrlich datei-rein.
Dokumentiertes + getestetes Verhalten ändert sich → Release-Schnitt
(minor/major) wird im do-git-update-Pfad entschieden, nicht still.
- **Akzeptanz:** Tests `testEnvPrivateOverridesGlobal` u. a. angepasst mit
  Begründungskommentar (rules-testing: Verhalten geklärt, dann Test ändern);
  README/AGENTS/Skill des Kernels nachgezogen.

### R4 — Messaging in den Kernel (Bescheid D5)
`DomainKernelInterface` + `DomainKernel` erhalten `messaging()`; neuer Handler
`BuildMessagingFromEnv` mit kanonischen Schlüsseln:
`MESSAGING_TRANSPORT=kafka|rabbitmq|redis`, `KAFKA_BROKERS`,
`RABBITMQ_HOST/PORT/USER/PASSWORD`, `REDIS_*` (Wiederverwendung des
Redis-Fan-outs prüfen). Kafka-Falle begradigen: `ConnectionConfig::fromEnv`
liest `KAFKA_PORT`, die Factory ignoriert Ports und erwartet die Brokerliste
im Host-Feld (BEFUNDE.md §5). Fehlende Konfiguration → `null` (Grundsatz).
Neues `docs/env-examples/.env.messaging.example`; jardis-app-template erhält
`config/env/.env.messaging` (auskommentierter Schaltpunkt) passend zu den
Compose-Profilen `kafka`/`rabbitmq`.
- **Akzeptanz:** Integrationstest gegen echten Broker (Docker-Service gehört
  zur Testinfrastruktur, development.md §5); Contracts + Kernel releast;
  Wissensbasis-Eintrag `koffer-ist-die-infrastrukturflaeche` fortgeschrieben
  (heutiger Text behauptet die Erweiterung fälschlich als vorhanden —
  BEFUNDE.md §6).

### R5 — Begriff „Koffer" abschaffen (Bescheid Rolf, wörtlich „MUSS WEG")
Ersatzlos statt neue Metapher: überall der präzise Name **Kernel /
DomainKernel** (en wie de); wo die Rolle beschrieben wird: „the kernel carries
all infrastructure services" / „die Infrastrukturfläche". Flächen: core/kernel
(README, AGENTS, Skills, docs), core/app, jardissupport/contracts,
jardis-claude (Wissensbasis-Einträge, CLAUDE.md, Skills), jardis-app-template.
Publizierte Historie bleibt unangetastet (nur HEAD-Doku).
- **Akzeptanz:** `grep -ri koffer` über die genannten Repos = 0 Treffer in
  gepflegten Doku-/Code-Flächen (Archiv-/Beleg-Snapshots wie `split-entwurf/`
  und eingefrorene Rechenschafts-Dokumente ausgenommen — dort wird Historie
  nicht umgeschrieben).

### R6 — Requirement-Dokument an Jardis (Builder)
Ein Dokument, das dem Builder-Projekt übergeben wird:
1. Zielverzeichnis-Konvention (R2) — Builder gibt Projekt-Root vor, klont
   Template, generiert nach `src/`, `bootstrap.php` ruft den Packer mit
   Projekt-Root.
2. Jardis schreibt BEIDE Konfigurationsschichten aus einer Quelle:
   `COMPOSE_PROFILES` + Provisionierungswerte in die Root-`.env`,
   Verbindungswerte nach `config/env/` (löst die belegte
   DB_NAME/DB_DATABASE-Doppelpflege).
3. DB-Wahl als exklusive Auswahl (db-mariadb XOR db-postgres — Alias-`db`-
   Mechanik, Wissensbasis `ein-stack-eine-technische-umgebung`).
4. Kanonische Messaging-Schlüssel aus R4 für den generierten EventRouter.
- **Akzeptanz:** Dokument liegt im vereinbarten Übergabeort und ist von Rolf
  abgenommen; Umsetzung selbst ist NICHT Teil dieses Vorhabens (Go-Seite).

## Nicht-Ziele
- Kein Multi-Connection-Kernel (zwei DBs = zwei Kernel/Stacks — Grundsatz
  `ein-stack-eine-technische-umgebung`).
- Keine neue Konfig-Metapher als Koffer-Ersatz.
- Keine Builder-(Go-)Änderungen — nur das Requirement-Dokument (R6).
- dbConnection-Lücken jenseits der Bugs (SSL-Optionen über ENV, SQLite-
  Adapterklasse im Packer, Pool ohne Reader) sind NICHT in diesem Vorhaben —
  als Folgekandidaten in BEFUNDE.md §4 dokumentiert.

## Leitplanken
- Jede Package-Änderung: Tests + sämtliche Docs im selben Zug + Release über
  `do-git-update` (Version forward, nie Historie anfassen — development.md §6).
- Reihenfolge-Zwang: R4 vor der Wissensbasis-Fortschreibung in R4-Akzeptanz;
  R5-Sweep erst nach R4 (sonst zweimal über dieselben Doku-Flächen).
- Contracts-Package wird von R2/R3/R4 berührt → Releases koordinieren
  (ein Contracts-Release, nicht drei).
- Kein Push/Release ohne den do-git-update-Pfad; STOPP-Kriterien aus
  project-workflow §3.6/3.9 gelten.
