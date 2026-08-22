# Fortschritt — ENV-Konfiguration bereinigen

## Kopf
- **Ziel:** ENV-Konfiguration widerspruchsfrei (Konvention Projekt-Root/config/env,
  datei-reiner Kernel, Bugfixes, Messaging-Bootstrap, „Koffer" abgeschafft,
  Requirement-Dokument an Jardis) — Details: PRD R1–R6.
- **Stand:** P1–P4 KOMPLETT (2026-08-22). Releases heute: dotenv v1.2.0+
  v1.2.1, kernel v1.2.0+v2.0.0, contracts v2.0.0, messaging v1.0.4 +
  Patch-Welle (classversion/dbconnection/eventdispatcher/filesystem/
  mailer) — alle Packagist-konsistent. Alle Phasen Doer grün + Verifier
  GRÜN (P4 inkl. Finding-Fix + Nachprüfung). path-repo/Compose-Mount
  entfernt. Nichts rot.
- **Nächster Schritt:** P6 starten (Koffer-Sweep 8 Repos + Wissensbasis-
  Umbenennung, PLAN §P6; Dateiliste = Explorer-Erhebung; .backup-Skill-
  Duplikate in kernel UND Template löschen). Danach P7 (R6-Dokument,
  endet mit STOPP „Abnahme Rolf").
- **Offene Bescheide:** —
- **Leitplanken:**
  1. Bescheide Rolf 2026-08-22: D1 Konvention Root/config/env/src · D2 Packer
     nimmt Projekt-Root · D3 Bugfixes zuerst · D4 $_ENV-Fallback entfernen ·
     D5 Messaging in den Kernel · „Koffer" ersatzlos → „DomainKernel".
     Dazu Gate-Bescheide G1–G28 (GREMIUM-WAS.md), markanteste: G1 Packer
     legt config/env per mkdir an (Exception nur bei Fehlschlag) · G6/R7
     DotEnv-Release mit Raw-Keys + String-Input · G8 domainRoot()→
     projectRoot() · G11 Accessor-Regel (nur ENV-gebootstrappte Dienste) ·
     G13 KAFKA_BROKERS kanonisch · keine Nutzer außer uns (G3).
     NEU Bescheid Rolf 2026-08-22 (P4-STOPP aufgelöst, Weg 1): Patch-Welle
     — dotenv v1.2.1, classversion, dbconnection, eventdispatcher,
     filesystem, mailer erhalten je einen Patch-Release mit contracts
     `^1.0 || ^2.0` VOR dem contracts-v2-Konsum; messaging bekommt das
     Widening im ohnehin geplanten P4-Release. cache/http/logger nicht
     betroffen (gemessen). Major bleibt Major.
     NEU Bescheid Rolf 2026-08-22 (P5-Blocker): jardiscore/app erhält
     Minor-Release v1.1.0 mit kernel ^2.0 + contracts ^2.0 und
     Doku/examples auf Projekt-Root-Konvention — der P5-App-Doku-Teil
     wandert in diesen Release; app v1.0.x bleibt für Kernel-v1-Nutzer.
     NEU Bescheid Rolf 2026-08-22 (Welle Teil 2): auch jardissupport/
     {data, dbquery, repository, validation, workflow} erhalten je einen
     Widening-Patch (contracts ^1.0 || ^2.0) — apps require-dev braucht
     sie; gemessen 0 Treffer DomainKernel/domainRoot in allen fünf src/.
  2. Jede Package-Änderung mit Tests + sämtlichen Docs + do-git-update-Release;
     Contracts-Releases bündeln (ein Release für R2/R3/R4-Anteile).
  3. Öffentliche API/Verhalten publizierter Packages: Release-Schnitt bewusst
     im do-git-update-Pfad entscheiden; nie Historie/Tags anfassen.
  4. Orchestrator-Modus: Hauptdialog delegiert an Subagenten, parallel wo
     dreifach unabhängig (Dateien + Contracts + QA-Infra); Doer ≠ Checker.
  5. Grundsatz `ein-stack-eine-technische-umgebung` (Wissensbasis) — kein
     Multi-Connection-Kernel.

## Bezug
- PRD:  docs/env-konfiguration/PRD.md (bestätigt 2026-08-22)
- Plan: docs/env-konfiguration/PLAN.md (freigegeben 2026-08-22 — enthält
  Phasen P1–P7, AK-Matrizen, Release-Architektur, Erhebungs-Kernfakten)
- Gremium WIE: docs/env-konfiguration/GREMIUM-WIE.md (57 Befunde + Bescheide)
- Belege: docs/env-konfiguration/BEFUNDE.md (alle Datei:Zeile-Fundstellen der
  Erhebungen vom 2026-08-22 — NICHT neu erheben)
- Gremium: docs/env-konfiguration/GREMIUM-WAS.md (28 deduplizierte Befunde
  des WAS-Gates, Bescheide-Abschnitt am Ende nachzutragen)
- Heimat des Laufs: dieses Repo (devops/jardis-app-template) — Bescheid Rolf
  2026-08-22: alles von hier orchestrieren, kein Repo-Hüpfen. Subagenten
  arbeiten in den Ziel-Repos über absolute Pfade:
  Kernel    /Users/Rolf/Development/headgent/jardis/core/kernel
  Contracts /Users/Rolf/Development/headgent/jardis/support/contracts
  DotEnv    /Users/Rolf/Development/headgent/jardis/support/dotenv
  Messaging /Users/Rolf/Development/headgent/jardis/adapter/messaging (G12)
  App       /Users/Rolf/Development/headgent/jardis/core/app (nur Doku)
  Wissensb. /Users/Rolf/Development/headgent/jardis/claude
  DevSkills /Users/Rolf/Development/headgent/jardis/tools/dev-skills (G24)
  Builder   /Users/Rolf/Development/headgent/jardis/tools/builder (nur R6-Dok)

## Wiederanlauf-Preflight (vor dem ersten Schritt jeder neuen Session)
- [ ] **ZUERST — Welle-Teil-2-Stand (Session-Ende 2026-08-22 abends, Agent
      lief evtl. noch):** je Repo data/dbQuery/repository/validation/
      workflow (unter jardis/support/): `git -C <repo> status --short`
      (halbfertige fix/260822_widen-contracts-constraint-Branches?) UND
      `curl -s https://repo.packagist.org/p2/jardissupport/<name>.json |
      jq '.packages[][0] | .version + " " + .require["jardissupport/contracts"]'`
      — Constraint `^1.0 || ^2.0` = Release durch. Unfertige nachfahren
      (patch, Muster s. Prozess-Notizen Welle Teil 1), Halbreste (offene
      PRs/Branches) erst sichten, nie blind neu anfangen.
- [ ] **Arbeitsbaum:** `git status --short` + Branch/HEAD in JEDEM betroffenen
      Repo (Liste unter „Bezug") — uncommittete Reste? Erst klären, dann
      arbeiten.
- [ ] **Verwaiste Arbeiter:** laufen noch Hintergrund-Tasks aus der Vorsession?
- [ ] **Container-Zustand:** `docker compose ps` — Reste eines QA-Laufs räumen.
- [ ] **Projekt-Konkreta:** `.claude/PROJEKT_PROFIL.md` des jeweiligen
      Ziel-Repos lesen (falls vorhanden), sonst dessen Makefile (`make help`).
- [ ] **Lektionen-Treffer:** `rules/workflow/lektionen.md` auf dieses Vorhaben
      sichten (publiziertes Package, Mehr-Repo-Sweep) → Treffer in Leitplanken.
- [ ] **Projektspezifisch:** BEFUNDE.md §1c lesen, BEVOR irgendein Handler
      angefasst wird (die Tests täuschen — sie umgehen die DotEnv-Kaskade).
      Betroffene Repos: s. Bezug-Liste (inkl. adapter/messaging, dev-skills,
      builder-docs).
- [ ] **Release-Stände:** nach P1/P2/P4-Releases `composer show` im
      abhängigen Repo gegen die erwartete Version prüfen (Plan-Vorgabe).
- [ ] **Vor P4:** `php -m` im phpcli (rdkafka/amqp/redis) + `docker ps` auf
      Broker-Container-Reste (kernel UND messaging, L2).
- [ ] **P3/P4:** kernel composer.json auf temporäres path-repo (contracts)
      prüfen — vor Release-Schritten entfernen.

## Phasen (verbindlich = PLAN.md; hier nur Status)
- [x] Phase 0: WAS-Gate (28 beschieden) + PRD bestätigt + PLAN freigegeben
      (WIE-Gate 57 beschieden) — 2026-08-22
- [x] P1: DotEnv Raw-Keys + String-Input → Release v1.2.0 — 2026-08-22
      (PR #35 squash→develop, Release-PR #36, Tag v1.2.0=main=Packagist)
- [x] P2: Kernel R1 (Bool-Klasse, Fehlerregeln, Raw-Keys) → Release v1.2.0
      — 2026-08-22 (PR #13 squash→develop, Release-PR #14, Tag
      v1.2.0=main=Packagist e5c8b9a)
- [x] P3: Kernel R2+R3 + Contracts-Änderungen + Wissensbasis-Eintrag
      (kein Release) — 2026-08-22; committet auf kernel
      feature/260822_project-root-env-cleanup, contracts
      feature/260822_project-root-rename (beide ungepusht, P4 bündelt),
      claude main (ungepusht, jetzt 4 ahead). path-repo + Compose-Mount
      im Kernel temporär aktiv — vor P4-Release entfernen.
- [x] P4: R4 Messaging + Releases — 2026-08-22. Kette (umgeordnet):
      messaging v1.0.4 (PR #10, vorgezogen) → contracts v2.0.0 (PR #11/#12,
      /release major, Workflow-Tag 0401d13) → kernel v2.0.0 (PR #15/#16,
      Tag 0ae2882); alle Tag==main==Packagist, develop synchron. Dazu
      Patch-Welle (6 Repos, s. Prozess-Notizen) per Rolf-Bescheid Weg 1.
- [x] P5: Template-Nachzug + App-Doku — 2026-08-22/23. app v1.1.0 releast
      (Tag 8e01144==main==Packagist, kernel ^2.0); Wellen Teil 2 (5/5,
      inkl. repository-Testfix v1.1.2) + Teil 3 (secret v1.0.5) komplett,
      alle Packagist-verifiziert; Template auf Projekt-Root (index.php/
      console Packer($root)), kernel v2.0.0 gezogen, .env.messaging-
      Schaltpunkt, README-Ownership (G21), CLAUDE.md zitiert Wissensbasis.
      Doer grün + Verifier BESTANDEN (health 200 Auslieferungszustand,
      Schaltpunkt-Gegenprobe wirft G5-konform). Muster-Klasse final
      vermessen: auth/scheduling bleiben einzige v1-Pinner (kein Graph).
- [ ] P6: Koffer-Sweep 8 Repos + Wissensbasis-Umbenennung (+ QA-Läufe)
- [ ] P7: R6-Dokument an Builder (Abnahme Rolf = STOPP-Punkt)
- [ ] Abschluss: Akzeptanz-Gate gegen das ganze PRD, Docs-Sync, Retro

## Letzte abgeschlossene Phase
- P4 (2026-08-22): messaging()-Accessor end-to-end — contracts v2.0.0
  (projectRoot-Rename P3 + messaging() gebündelt), kernel v2.0.0 (R2+R3+R4,
  eager Boot-Validierung der Transport-Pflichtfelder KAFKA_BROKERS/
  RABBITMQ_HOST/REDIS_HOST, Redis-STREAMS über native Adapter-API,
  phpunit-Target mit start-Prerequisite nach CI-Rotlauf), messaging v1.0.4
  (Kafka-Doku + Widening, vorgezogen). Verifier GRÜN inkl. Finding-Fix-
  Nachprüfung. Patch-Welle Teil 1 (6 Repos) per Rolf-Bescheid.
- P2 (2026-08-22): Kernel R1 — `NormalizeEnvBool` (eine Einheit, filter_var
  NULL_ON_FAILURE, unparsebar → InvalidEnvConfigurationException),
  Exception-Rethrow in BuildConnectionFromEnv/BuildRedisFromEnv (kein
  stilles Degradieren bei ungültigen Werten/unerreichbaren konfigurierten
  Diensten), ''→null zentral in $envGet/env(), Credential-Raw-Keys
  (`CredentialEnvKeySuffixes` via dotenv addRawKeys vor loadPrivate),
  ZUSATZ `IsEnvValueUnset` (gemessen+verifiziert: `KEY=` wird in der
  Kaskade bool(false); gilt nur für String-Presence-Checks). Neue
  tests/Integration-Suite mit echter Kaskade. Releast als jardiscore/kernel
  v1.2.0 (dotenv ^1.2, contracts 1.1.1 innerhalb ^1.0). Bekannte Grenze:
  `KEY=` vs. `KEY=false` bei Bool-Keys nicht unterscheidbar (dotenv-
  Folgekandidat, in CHANGELOG dokumentiert).
- P1 (2026-08-22): DotEnv Raw-Keys (`MatchesRawKey`, `addRawKeys`) +
  String-Input (`LoadValuesFromRows`-Engine, `loadPublicFromString`/
  `loadPrivateFromString`, `IncludeNotSupportedException`) — releast als
  jardissupport/dotenv v1.2.0. Doer-Entscheidungen: relatives KEY_FILE ohne
  baseDir → InvalidArgumentException; resolveFileValue()/publish() in die
  Engine verschoben (kein Subklassen-Konsument, Verifier-bestätigt BC-frei).
  DotEnvInterface unverändert.

## Prozess-Notizen (3.7)
- 2026-08-22 P5/app: Doer grün (kernel/contracts ^2.0, getting-started
  Projekt-Root, examples/Fixtures projectRoot:, symfony-demo bewusst v1 mit
  Hinweis; 122 Tests) · Verifier BESTANDEN (eigene QA + Gegenproben, Doku
  wörtlich gegen kernel-v2-Code geprüft). UPDATE-RELEASE-Protokoll app
  (Vorab-Freigabe Bescheid Rolf + PLAN Nr. 6): jardiscore/app · v1.0.3 →
  v1.1.0 (minor) · Auto-Release-Workflow · Branch
  feature/260822_kernel-v2-project-root · keine Anomalie.
- 2026-08-22 P4-Blocker AUFGELÖST: Rückfrage-Gate → UNENTSCHEIDBAR →
  STOPP → Bescheid Rolf (Weg 1, Patch-Welle). Welle durch und vom
  Orchestrator auf Packagist verifiziert: dotenv v1.2.1, classversion
  v1.0.3, dbconnection v1.1.1, eventdispatcher v1.0.5, filesystem v1.0.5,
  mailer v1.0.5 — alle mit contracts `^1.0 || ^2.0`; develop==main überall.
  Nebenwirkung: Wellen-Agent stoppte die fremden jardisbuilder-DB-Container
  (Port-Kollision bei dbconnection-QA, Docker-Regel) — sie blieben
  gestoppt; ggf. für das Builder-Projekt neu starten.
- 2026-08-22 P4 Teil A Doer grün (nach Blocker-Auflösung + einer
  L12-Prozess-Korrektur: Hintergrund-QA nachträglich synchron wiederholt):
  BuildMessagingFromEnv über vorhandene Fabriken, messaging()-Accessor
  (contracts+kernel), Broker-Compose kernel-test-*, 8 Integrationstests
  (AK4.1–4.5), 107 Tests / 93,30 % Lines. Entscheidungen: Redis STREAMS
  statt Pub/Sub (gemessene Message-Verluste/Hänger — Verifier prüft
  Fabrik-Treue adversarial); RabbitMQ Topic=Queue-Name; KAFKA_USER statt
  KAFKA_USERNAME (Raw-Key-Suffix, Review-Fund gefixt); class_exists-Pfad
  @codeCoverageIgnore nach Bestandsmuster. Commit-Msgs geliefert.
- 2026-08-22 P4-Verifier GRÜN (eigene QA-Läufe gegen echte Broker, 107/107,
  93,30 % Lines; Streams-Abweichung = native Adapter-API useStreams, kein
  Umgehungscode; G23-Eigenverbindung im Code belegt). EIN Finding
  (reproduziert): eager-Validierungs-Behauptung im Docblock vs. lazy
  unverpackte InvalidArgumentException bei leerem KAFKA_BROKERS; try/catch
  um match() toter Code. → Doer-Fix grün (2026-08-22): Connections eager
  gebaut (Config-Validierung beim Boot, kein Netzwerk-I/O; Erreichbarkeit
  bleibt lazy), RABBITMQ_HOST/REDIS_HOST Pflichtfelder ohne Default
  (Familien-Fix), 3 neue Boot-Fehler-Tests, Suite 110 Tests / 93,63 %
  Lines. Kernel-Commit-Msg jetzt: fix(bootstrap): validate messaging
  config eagerly at boot, not on first publish (R4). Punkt-Nachprüfung
  durch Verifier läuft.
- 2026-08-22 Reihenfolge-Anpassung P4 (Orchestrator-Ermessen, kein neuer
  Release): messaging-Release (PLAN Nr. 5, Doku-Begradigung + Widening lt.
  Bescheid Nr. 7) wird in der Kette VORGEZOGEN (messaging → contracts →
  kernel statt contracts → kernel → messaging), weil kernel-QA die
  messaging-Klassen mit `^1.0 || ^2.0` von Packagist braucht; messaging
  hängt von keinem der beiden ab. Diff-Gegenprobe am Teil-B-Stand durch
  Orchestrator: 3 Dateien/11 Zeilen, exakt Scope.
- UPDATE-RELEASE-Protokoll messaging (Vorab-Freigabe durch Plan Nr. 5+7):
  Package jardisadapter/messaging · Typ implementation · Release-Modus
  Auto-Release-Workflow · v1.0.3 → v1.0.4 (patch) · Branch
  fix/260822_kafka-doc-and-contracts-widening · keine Anomalie.
  DURCH: PR #10 squash→develop (CI grün; lokal 410 Tests grün), Workflow
  taggte v1.0.4==main==Packagist f01c1b3, contracts-Constraint publiziert,
  develop synchron. P4-Doer Teil A fortgesetzt (composer-Graph jetzt
  lösbar). Damit ist der messaging-Anteil der P4-Release-Kette ERLEDIGT —
  Restkette: contracts v2.0.0 → kernel v2.0.0.
- 2026-08-22 P4-Finding-Nachprüfung durch Verifier BESTANDEN (eigene
  Proben: Kafka/RabbitMQ-Boot-Exception, Port-99999-Wrap beweist lebendigen
  try/catch, kein I/O im Eager-Pfad, 11/11 isoliert grün).
- UPDATE-RELEASE-Protokoll contracts (Vorab-Freigabe durch Plan Nr. 3,
  Major von Rolf bestätigt): Package jardissupport/contracts · Typ
  interface (phpstan/phpcs) · Release-Modus Auto-Release-Workflow ·
  v1.1.1 → v2.0.0 (major) · Branch feature/260822_project-root-rename
  (P3-Rename + P4-messaging() gebündelt = EIN Release lt. Leitplanke 2) ·
  keine Anomalie.
- 2026-08-22 P4-Preflight grün: rdkafka+amqp+redis im phpcli belegt
  (php -m), keine Broker-Container-Reste — kein STOPP.
- 2026-08-22 P4 Teil B (messaging-Doku) Doer grün: .env.example (KAFKA_PORT
  = reines Compose-Mapping), src/Config/ConnectionConfig.php fromEnv-PHPDoc
  (Port-0-Falle), README-Kafka-Abschnitt; 11(+)/1(−) reine Doku, phpstan/
  phpcs grün, keine Broker gestartet. Uncommittet, Commit nach P4-Verifier.
  Commit-Msg: docs(messaging): clarify Kafka broker-list-in-host convention
  (KAFKA_PORT unused). Teil A (contracts+kernel Messaging) läuft.
- 2026-08-22 P3 Teil A (kernel+contracts) Doer grün: Projekt-Root-Packer
  (__invoke(string $projectRoot), race-sicherer mkdir), $_ENV-Fallback raus
  ($envGet + env(), grep src/ = 0), domainRoot→projectRoot (kernel +
  contracts-Interface), neue ProjectRoot-Fixtures, 99 Tests / 91,97 % Lines,
  contracts phpstan/phpcs grün. Doer-Entscheidungen: (a) mkdir-Fehlschlag →
  \RuntimeException (nicht InvalidEnvConfigurationException); (b) path-repo
  mit Inline-Alias `dev-develop as 1.2.0` + temporärer Compose-Mount
  ../../../support/contracts:ro — BEIDES vor P4-Release entfernen
  (Preflight-Posten existiert). Commit-Msgs geliefert (kernel:
  refactor(bootstrap): project-root convention + drop $_ENV fallback
  (R2/R3); contracts: refactor(kernel): rename domainRoot() to
  projectRoot(), correct env() docblock (R2/R3)). Verifier läuft.
- 2026-08-22 P3 Teil B (Wissensbasis) Doer grün: NEU
  wissensbasis/projekt-layout-konvention.md (G2, Herkunft vollständig);
  koffer-…-description-Fix (G15, git diff nur Frontmatter-Zeile);
  wb-check/wb-index CHECK=1 grün (Exit 0). Uncommittet — Commit nach
  P3-Verifier zusammen mit Teil A. Commit-Msg (geliefert): wissensbasis:
  Projekt-Layout-Konvention Root/config/env+src (G2) +
  Koffer-description-Fix (G15). Teil A (kernel+contracts) läuft.
- 2026-08-22 P2-Doer (Sonnet) grün: 98 Tests, Coverage 91,81 % Lines,
  phpstan/phpcs sauber, dotenv v1.2.0 gezogen (Constraint ^1.2; composer
  update hob dabei contracts 1.0.1→1.1.1 innerhalb ^1.0). Scope-Zusatz über
  Plan hinaus: NEU `IsEnvValueUnset` — Doer maß, dass die DotEnv-Cast-Kette
  leere Werte (`KEY=`) zu bool(false) statt '' macht, wodurch die geplante
  reine ''→null-Mappung Presence-Checks verfehlt; behandelt null/''/false
  als „nicht konfiguriert" für String-Keys. Bewusste Grenze dokumentiert:
  `KEY=` vs. `KEY=false` bei Bool-Keys nicht unterscheidbar (Fix läge in
  dotenv, Folgekandidat). Verifier misst die Behauptung nach und bewertet
  die Semantik. Commit-Msg (geliefert): fix(bootstrap): correct ENV
  bool/error handling per G5 (R1)
- 2026-08-22 P1: Doer grün (179 Tests, davor 144 Bestand; Coverage 96,49 %
  Lines) · Verifier GRÜN mit eigenen QA-Läufen + 2 Gegenproben (CRLF+BOM-
  String, KEY_FILE mit Raw-Key/true-Inhalt); kernel nutzt nur new DotEnv()
  + loadPrivate() → BC-frei. Details: „Letzte abgeschlossene Phase".
- UPDATE-RELEASE-Protokoll (Vorab-Freigabe durch Plan, do-git-update):
  Package jardissupport/dotenv (/Users/Rolf/Development/headgent/jardis/
  support/dotenv) · Typ implementation · Release-Modus manuell
  (auto-release.yml fehlt) · v1.1.5 → v1.2.0 (minor) · Branch
  feature/260822_raw-keys-string-input · Vorab-Freigabe durch PLAN.md
  Release-Architektur Nr. 1 + §P1 „Release: do-git-update → v1.2.0",
  Plan-Freigabe 2026-08-22 (ExitPlanMode, s. Kopf) · TARGET == Plan-Version,
  keine Anomalie (Verifier grün, kein Greenfield).
- 2026-08-22 P2-Verifier GRÜN (eigene QA-Läufe + eigene Gegenproben:
  HTTP_VERIFY_SSL=0 e2e, ungültige Pool-Strategie bei echtem 127.0.0.1,
  DB_PASSWORD=123456 bis env()): AK2.1–2.6 belegt; IsEnvValueUnset-Messung
  BESTÄTIGT (KEY= → bool(false) in der Kaskade), Semantik korrekt (nur an
  String-Presence-Checks, Bool-Keys separat via NormalizeEnvBool).
  Nebenbefund Fixture-Name „InvalidPoolStrategyReachable" irreführend →
  vom Orchestrator zu InvalidPoolStrategy umbenannt, Suite danach 98/98 grün.
- UPDATE-RELEASE-Protokoll P2 (Vorab-Freigabe durch Plan, do-git-update):
  Package jardiscore/kernel · Typ implementation · Release-Modus manuell
  (auto-release.yml fehlt) · v1.1.1 → v1.2.0 (minor) · Branch
  feature/260822_env-bool-error-rules · Vorab-Freigabe durch PLAN.md §P2
  „Release: do-git-update → v1.2.0 (G3)", Plan-Freigabe 2026-08-22 ·
  TARGET == Plan-Version, keine Anomalie (Verifier grün).
- Preflight 2026-08-22: alle Repos sauber; tools/builder-Änderungen gehören
  zu fremdem Feature-Branch (Query-P5, nicht dieser Lauf); jardis/claude
  3 Commits ahead origin (Push ausstehend, kein Blocker).

## Delegierte Entscheidungen (Rückfrage-Gate)
- 2026-08-22 (Rolf schläft, „mach einfach weiter"): Welle Teil 2 zu 4/5
  durch (data v1.0.4, dbquery v1.1.2, validation v1.1.3, workflow v1.0.4,
  je Packagist-konsistent lt. Wellen-Agent). repository blockiert: CI ohne
  Lockfile zieht dbquery v1.1.x (Backtick-Quoting), 3 Tests asserten alte
  SQL-Strings — vorbestehender latenter Drift, kein Wellen-Schaden.
  Orchestrator-Entscheid: Testfix im Rahmen der Testfehler-Regel (Soll
  zuerst klären, STOPP falls dbquery-Bug) + Release auf dem offenen
  PR-#12-Branch — der repository-Release selbst ist beschieden (Welle
  Teil 2), nur der Weg braucht den Fix; „keine SQL-Strings in Assertions"
  (development.md §5) deckt die Anpassungsrichtung. ERLEDIGT: Backticks
  = gewolltes dbquery-v1.1.0-Verhalten (CHANGELOG „Code that
  string-compares generated SQL must be adjusted"), 3 Assertions auf
  Regex mit Kommentar, repository v1.1.2 Packagist-konsistent. Welle
  Teil 2 KOMPLETT 5/5, vom Orchestrator verifiziert (alle ^1.0 || ^2.0).
- 2026-08-22 (delegiert, dritter identischer Fall): Template-Graph
  blockiert an jardissupport/secret (direktes require, contracts ^1.0).
  Finaler Familien-Grep über ALLE lokalen Packages (L27-Nachholung):
  nur noch auth/scheduling/secret pinnen ^1.0; ausschließlich secret
  hängt in einem Zielgraphen (kernel/app/Template), 0 Interface-Treffer.
  Orchestrator-Entscheid unter dem zweifach beschiedenen Wellen-Muster +
  „weiter bis P7"-Mandat: Welle Teil 3 = NUR secret (Widening-Patch).
  auth/scheduling bleiben bewusst v1-gebunden (Folgekandidaten). Damit
  ist die Muster-Klasse abschließend vermessen — kein vierter Fall
  möglich in diesem Lauf.

## Offene Punkte / Risiken
- P3-Verifier-Nebenbefund: `domainRoot` steht noch in kernel
  `.claude/skills/core-kernel/SKILL.md` und `docs/app-layer/PLAN.md`
  (Doku außerhalb P3-Scope) — im P6-Sweep bzw. spätestens am
  Akzeptanz-Gate begradigen.
- Release-Schnitt R3 (minor vs. major) bewusst im do-git-update-Pfad klären.
- R4-Testinfrastruktur: Broker-Container für Kernel-Integrationstests nötig
  (development.md §5) — Aufwand im Plan bemessen.
- dbConnection-Folgekandidaten (BEFUNDE.md §4) bewusst NICHT in diesem
  Vorhaben — nach Abschluss als eigenes kleines Vorhaben vorschlagen.
