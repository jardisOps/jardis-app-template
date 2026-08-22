# WAS-Gremium-Gate — deduplizierte Klär-Agenda (2026-08-22)

Prüfgegenstand: PRD.md (Entwurf). Besetzung: was-skeptiker (S1–S16, Sonnet) +
was-ddd-stratege (B1–B23, Opus), blind und parallel. Dedupliziert vom
Orchestrator. Gate-Semantik: jeder Befund muss von Rolf beschieden sein
(lösen ODER bewusst verwerfen), erst dann gilt das PRD als bestätigt.

Format je Befund: **G# [Schwere] Kategorie — Kern** · Quelle(n) · Stelle.

## Blocker

**G1 [Blocker] Widerspruch — R2-Fallback erzeugt Doppel-Semantik und einen
neuen stillen Rückfallpfad.** Existiert `<root>/config/env` nicht, ist derselbe
Parameter weiter das Config-Verzeichnis — der Rückgabe von `domainRoot()` ist
nicht anzusehen, welche Semantik gilt. Kollidiert mit Wissensbasis
`altformate-migrieren-statt-mitschleppen` („genau EIN gültiges Format") und mit
dem eigenen R1.3-Grundsatz „FEHLERHAFT wird laut" sowie R3 („ehrlich
datei-rein" — dort wird ein stiller Fallback entfernt, hier einer eingeführt).
Quellen: B1, B2, S5. Stelle: R2 Satz 3 vs. R1.3/R3.

**G2 [Blocker] Risiko — R2 verlagert das Projekt-Layout-Wissen in den
publizierten Kernel; Eigentümer der Konvention ungeklärt.** Drei Flächen
tragen danach dieselbe Konvention (Kernel liest aktiv, Template dokumentiert,
Builder generiert R6.1) ohne Führungsregel bei Abweichung
(`keine-doppelte-regelpruefung`); der Kernel zieht mit `src/ =
Builder-OutputDir` einen Scaffolding-Belang in seine Verantwortung.
Quelle: B3. Stelle: R2 + R6.1.

**G3 [Blocker] fehlende Anforderung — Security-Fix R1 ohne
Konsumenten-Kommunikation und ohne festgelegten Release-Schnitt.** Die
Umkehrung der `HTTP_VERIFY_SSL`-Wirkung ändert sicherheitsrelevantes
beobachtbares Verhalten; das PRD verlangt weder Release-Notes/Advisory noch
adressiert es den Versionsschnitt (für R3 ist er adressiert, für R1 nicht).
Der vorgezogene eigene R1-Release steht nur in der PROGRESS-Phasenliste,
nicht als PRD-Zusicherung. Quellen: S4, B14, B13. Stelle: R1-Akzeptanz.

## Major

**G4 [Major] fehlende Anforderung — Bug-KLASSE statt zwei Einzelfälle.** Das
Muster „String-Vergleich gegen bereits gecasteten Wert" ist eine Fehlerklasse;
das PRD verlangt die Prüfung aller Bootstrap-Handler nicht (Familien-Grep,
Lektion L27). Quelle: S3. Stelle: R1.1 vs. Ziel.

**G5 [Major] Mehrdeutigkeit — Grenze FEHLERHAFT (laut) vs. FEHLEND (null)
undefiniert.** Offen mindestens: leerer Wert, unbekannter Schlüssel,
unauflösbarer Wert, Verbindungsfehler (BEFUNDE §1e: derselbe Codepfad), und ob
die Regel auch für neue Handler (R4: ungültiger `MESSAGING_TRANSPORT`) gilt.
Ein Beispiel ersetzt die Regel nicht. Quellen: S1, B8, S12. Stelle: R1.3.

**G6 [Major] Mehrdeutigkeit — „kein Verhaltensbruch für andere
DotEnv-Konsumenten" nicht operationalisiert; Secret-Klassifikation offen.**
Weder Konsumentenliste noch Regressionstest benannt; und WELCHE Schlüssel
Secrets sind (nur DB_PASSWORD? RABBITMQ_PASSWORD, MAIL_*, R4-Credentials?) ist
eine fachliche Klassifikations-Entscheidung, die das PRD dem Plan überlässt.
Quellen: S2, B19. Stelle: R1.2.

**G7 [Major] Risiko — „bestehende Aufrufer brechen nicht" / „gefahrlos" ist
breiter als die Belegbasis.** BEFUNDE §3 belegt Konsumentenfreiheit nur für
`domainRoot()`-Leser, nicht für die Semantikänderung des
`$configPath`-Parameters; Builder-Testharnesse konstruieren domainRoot bereits
mit einem dritten Verständnis (Facade-Verzeichnis) und sind von R6 nicht
erfasst. Quellen: S6, S16, B17. Stelle: R2 Sätze 3–5.

**G8 [Major] Mehrdeutigkeit — „domainRoot = Projekt-Root" vs.
Mehr-Domain-Semantik.** Packer-Docblock: mehrere Domains teilen sich per
config path EINEN Kernel; `ein-stack-eine-technische-umgebung` erlaubt mehrere
Domains je Stack. Ein *Domain*-Root, der ein Mehr-Domain-Projekt-Root ist, ist
falsch benannt — Name/Verhältnis Domain:Root ungeklärt. Quelle: B16.
Stelle: R2.

**G9 [Major] fehlende Anforderung — `messaging()` bricht externe
Interface-Implementierer; Bestandsprüfung fehlt.** Anders als bei R2 keine
Anforderung, Implementierer von `DomainKernelInterface` außerhalb des
Ökosystems zu prüfen/adressieren (Major-Release-Frage). Quelle: S7.
Stelle: R4 Satz 1.

**G10 [Major] fehlende Anforderung — Widerspruch zum
`container()`-Docblock.** `DomainKernelInterface::container()` sichert heute
wörtlich zu, dass Messaging über den Container läuft; nach R4 gäbe es zwei
zugesicherte Wege + falsche Vertragsdoku. Quelle: B6. Stelle: R4;
contracts DomainKernelInterface.php:48–58.

**G11 [Major] fehlende Anforderung — Kriterium „benannter Zugang vs.
container()" fehlt.** Der Entscheid vom 2026-07-30 nennt VIER Pakete
(messaging, scheduling, secret, auth); R4 hebt eines in den Vertrag und
schweigt zu dreien — weder Nicht-Ziel noch Regel, wann ein Dienst einen
Accessor verdient. Quelle: B5. Stelle: R4 + Nicht-Ziele.

**G12 [Major] fehlende Anforderung/Widerspruch — messaging-Package fehlt in
Repo-Liste und Release-Koordination.** R4 ändert Code in
jardisadapter/messaging (Kafka-Falle), das Package ist aber nirgends als zu
releasendes benannt. Quelle: S8. Stelle: R4 + Leitplanken.

**G13 [Major] Mehrdeutigkeit — „Kafka-Falle begradigen" ohne Soll-Zustand.**
KAFKA_PORT entfernen, Factory um Ports erweitern, oder nur dokumentieren?
Akzeptanz nicht eindeutig prüfbar. Quelle: S9. Stelle: R4.

**G14 [Major] Widerspruch — Transport-Abdeckung unvollständig.** Ziel sagt
„vollwertiger Bootstrap-Zugang", R4 deckt kafka|rabbitmq|redis;
`kafkaConsumer` (groupId Pflicht) und `database` sind weder abgedeckt noch
Nicht-Ziel. Quelle: S10. Stelle: Ziel vs. R4.

**G15 [Major] Widerspruch — R4-Akzeptanz-Prämisse zur Wissensbasis ist
falsch.** `koffer-ist-die-infrastrukturflaeche` behauptet die Erweiterung
NICHT als vorhanden (Text sagt explizit „heute nicht im Koffer", Nachziehen
als offene Folge); falsch ist nur die Frontmatter-description. Die
Fortschreibungs-Anforderung trifft den falschen Gegenstand. Quelle: B4.
Stelle: R4-Akzeptanz; Wissensbasis-Eintrag Z. 105–137.

**G16 [Major] fehlende Anforderung — R3 ändert vertraglich dokumentiertes
`env()`-Verhalten, Contracts fehlt in der R3-Akzeptanz.** Docblock
DomainKernelInterface.php:38–46 („falls back to global $_ENV") muss mit;
Leitplanke „ein Contracts-Release" zählt R3 mit, die Akzeptanzliste nicht.
Quelle: B7. Stelle: R3-Akzeptanz.

**G17 [Major] Risiko — Grundsatz „Auslieferungszustand läuft ohne
Konfiguration" nicht zugesichert.** R1.3-Exceptions + R2-Lesepunkt-Verschiebung
könnten den leeren Start brechen; R2-Akzeptanz prüft `make start`/`/health`
nur für den umgestellten, nicht den unkonfigurierten Fall. Quelle: B9.
Stelle: R1.3/R2-Akzeptanz; Template-CLAUDE.md.

**G18 [Major] Mehrdeutigkeit — Ersatzbegriff „Kernel" ist selbst mehrdeutig.**
„Kernel / DomainKernel" ohne Verwendungsregel; „Kernel" kollidiert mit
Paketname `jardiscore/kernel`, `core/`≠Domain-Core und „Domain Core = das
Generat" — der Kernel ist die Gegenfläche zum Domain Core. Quelle: B10.
Stelle: R5.

**G19 [Major] Widerspruch — R5-Sweep vs. Wissensbasis-Kennungen.**
Dateiname/`id:` `koffer-ist-die-infrastrukturflaeche` sind stabile Kennungen
(typisierte Wikilinks, INDEX); `ein-stack-eine-technische-umgebung` Z. 30–33
argumentiert wörtlich über „Koffer-Architektur". Ob Kennungen umbenannt werden
und wie Linkintegrität gesichert wird, sagt das PRD nicht. Quelle: B11.
Stelle: R5-Akzeptanz.

**G20 [Major] Mehrdeutigkeit — R6-Übergabeort nirgends benannt.** „Im
vereinbarten Übergabeort" ist nicht prüfbar; auch Wissenssorte/kanonischer Ort
im Ziel-Repo (wissenssorten-und-ihre-orte) offen. Quellen: S13, B20.
Stelle: R6-Akzeptanz.

**G21 [Major] Mehrdeutigkeit — Eigentum der beiden Konfigschichten bei
Generierung.** R6.2 lässt Jardis BEIDE Schichten schreiben; Template
versioniert die Root-`.env` (Secrets → `.env.local`) und führt eine
Eigentums-Tabelle, in der `.env`/`config/env/` fehlen. Überschreibt
Neugenerierung Entwicklerwerte (ForceOverwrite)? Quelle: B15. Stelle: R6.2.

**G22 [Major] Widerspruch (Prozess) — Ablageort des Vorhabens.** Rechenschaft
gehört ins Repo, dessen Code geändert wird (`wissenssorten-und-ihre-orte`,
Bescheid 2026-08-15); die Arbeit liegt überwiegend in kernel/contracts/
dotenv/wissensbasis, PRD/BEFUNDE aber im Template-Repo (Commit 51fb655
„Lauf-Heimat"). Quelle: B12. Stelle: Ablageort.

## Minor

**G23 [Minor] Mehrdeutigkeit — Redis-Schlüssel-Teilung Cache vs. Messaging.**
„Wiederverwendung des Redis-Fan-outs prüfen" ist eine offene Frage, kein
Requirement; Kollisionsrisiko `REDIS_*` zwischen den Profilen. Quelle: S11.

**G24 [Minor] fehlende Anforderung — Sweep-/Repo-Listen unvollständig.**
R5-Sweep nennt dev-skills nicht (obwohl R2/R3/R5 Skill-Änderungen fordern)
und lässt weitere berührte Repos (dotenv, messaging, secret, …) aus.
Quellen: B22, S14.

**G25 [Minor] fehlende Anforderung — Reihenfolge R1–R3-Doku vs. R5-Sweep
ungeregelt.** Doppelbearbeitungs-Risiko derselben Doku-Flächen, das die
Leitplanke nur für R4/R5 löst. Quelle: S15.

**G26 [Minor] Mehrdeutigkeit — Fall „config/env existiert, Basisdatei
fehlt".** Einstiegspunkt der Kaskade in R2 nicht festgelegt (DotEnv beginnt
bei `.env`; Template kennt Pflichtdatei `.env.database`) — Fallback oder
Exception? Quelle: B18.

**G27 [Minor] Widerspruch — „zwei DBs = zwei Kernel/Stacks".** Der zitierte
Entscheid verlangt einen eigenen Template-Klon (einen Stack) und verwirft
„N Koffer in einem Prozess" — „zwei Kernel" als Alternative ist damit nicht
gedeckt. Quelle: B21. Stelle: Nicht-Ziele.

**G28 [Minor] Risiko (Meta) — „beschieden" vs. Gate.** Das PRD erklärt alle
Anforderungen als beschieden UND stellt das Gate davor; welche Teile für
Befunde offen sind (vs. gelockt, `gelockte-entscheidungen-nicht-neu-fragen`),
ist nicht markiert. Quelle: B23. Stelle: Kopf.

## Bescheide

(je Befund: gelöst wie / verworfen warum — Bescheide Rolf 2026-08-22)

- **G1 GELÖST:** Kein Fallback, aber auch keine Exception bei fehlendem
  Verzeichnis: der Packer legt `<root>/config/env` selbst an (mkdir);
  Exception NUR, wenn das Anlegen scheitert (Rechte/Read-only). Eine einzige
  Semantik: übergebener Pfad = Projekt-Root, gelesen wird immer
  `<root>/config/env`. Bewusster Trade-off (von Rolf gesehen): Tippfehler im
  Root-Pfad wird nicht laut, sondern ergibt leeres Verzeichnis → unkonfigurierter
  Kernel → null-Adapter — das ist die „FEHLEND"-Seite des R1.3-Grundsatzes.
- **G2 GELÖST (Empfehlung übernommen):** Kanonischer Eigentümer der
  Layout-Konvention wird ein neuer Wissensbasis-Grundsatz-Eintrag
  (Root/config/env/src, mit Herkunft). Kernel implementiert und zitiert nur den
  `config/env`-Anteil; Template-README und R6-Dokument zitieren den Eintrag
  statt ihn zu wiederholen. PRD erhält einen Satz zur Eigentums-Regel.
- **G3 GELÖST (vereinfacht):** Es gibt aktuell keine Nutzer außer uns — kein
  Security-Advisory, keine Sonderbehandlung. R1 wird als normaler
  do-git-update-Release mit Versionsschritt gefahren (weiterhin als erste
  Phase vor R2/R3).
- **G4 GELÖST (Empfehlung übernommen):** R1.1 gilt der Fehler-KLASSE: alle
  Bootstrap-Handler werden per Familien-Grep (L27) auf das Muster
  „Roh-String-Vergleich gegen bereits gecasteten Wert" geprüft, alle Treffer
  gefixt; der Grep ist Teil der Akzeptanz.
- **G5 GELÖST (Empfehlung übernommen):** Vier Regeln ins PRD: (1) fehlend =
  Schlüssel nicht gesetzt oder leerer Wert (`KEY=`) → null-Degradierung;
  (2) fehlerhaft = gesetzt aber ungültig (Enum, unparsebar, Wertebereich) →
  Exception; (3) konfiguriert-aber-nicht-erreichbar (z. B. DB-Host gesetzt,
  Verbindung scheitert) → laut, Exception statt stillem error_log;
  (4) unbekannte Schlüssel werden ignoriert. Gilt für ALLE Handler inkl.
  R4-Handler (ungültiger MESSAGING_TRANSPORT → Exception).
- **G6 GELÖST (Empfehlung übernommen):** Secret-Fix DotEnv-seitig als
  Opt-in-Mechanismus „diese Schlüssel nicht casten" (Raw-Key-Liste beim
  Laden), Default unverändert → kein Verhaltensbruch für andere Konsumenten,
  belegt durch grünen dotenv-Testbestand. Klassifikations-Regel im PRD:
  Credential-Suffixe `*_PASSWORD`, `*_USER`, `*_SECRET`, `*_TOKEN` — der
  Packer gibt sie an DotEnv mit. Regressionstests durch die echte Kaskade.
- **NEU (Bescheid Rolf 2026-08-22, Scope-Erweiterung):** DotEnv erhält
  zusätzlich String-Input: `loadPublicFromString()`/`loadPrivateFromString()`
  für .env-Inhalte als String (Anwendungsfall AWS Secrets Manager).
  Machbarkeit belegt: LoadValuesFromFiles parst ab `loadFileValues(array
  $rows, ...)` zeilenbasiert — String-Split nutzt dieselbe Pipeline (Casts,
  `${VAR}`, KEY_FILE, Handler). Kein Kaskaden-Kontext bei String-Input;
  `load()`-Includes im String-Modus verboten (laut), bis realer Bedarf
  kommt. Gleicher DotEnv-Release wie der Raw-Keys-Mechanismus.
- **G7 GELÖST (Empfehlung übernommen):** „Gefahrlos"-Behauptung raus, ersetzt
  durch messbare Akzeptanz: erschöpfender Grep über alle Ökosystem-Repos nach
  Packer-Aufrufern + domainRoot-Konstrukteuren (L1), alle Fundstellen im
  selben Zug migrieren. Achtung durch G1: Alt-Aufrufer mit Config-Pfad
  bekämen still `<configPfad>/config/env` — deshalb Migration statt
  Kompat-Annahme. Builder-Testharnesse (drittes domainRoot-Verständnis)
  werden Punkt im R6-Dokument.
- **G8 GELÖST (Empfehlung übernommen):** `domainRoot()` wird zu
  `projectRoot()` umbenannt (Contracts + Kernel; billig, da keine Nutzer
  außer uns und der Contracts-Release ohnehin schneidet). Docblock: „root of
  the project the kernel serves; multiple domains in one project share it".
- **G17 GELÖST (Empfehlung übernommen):** Akzeptanz-Zeile: nach R1–R3 läuft
  das Template im UNKONFIGURIERTEN Zustand (frischer Klon, keine Dienste)
  weiterhin grün mit `make start` + `/health` — expliziter Testfall.
- **G26 GELÖST (Klarstellung):** Fehlende Dateien = fehlende Konfiguration →
  null, keine Exception (folgt aus G1+G5). Pflicht-`.env.database` per
  `load()` bleibt Template-Entscheidung, keine Kernel-Regel.
- **G9 GELÖST:** Durch G3 entschärft (keine Nutzer außer uns) — Ökosystem-Grep
  nach DomainKernelInterface-Implementierern läuft im G7-Sweep mit;
  Contracts-Schnitt bewusst im do-git-update-Pfad.
- **G10 GELÖST (Empfehlung übernommen):** `container()`-Docblock im selben
  Contracts-Release korrigieren — Messaging raus aus der Container-Liste,
  Verweis auf den Accessor. Ein zugesicherter Weg.
- **G11 GELÖST (Empfehlung übernommen):** Regel ins PRD: benannter Accessor
  genau dann, wenn der Kernel den Dienst selbst aus kanonischen
  ENV-Schlüsseln bootstrappt (trifft: Messaging). Scheduling/Auth (null ENV)
  bleiben Container; Secret ist DotEnv-Handler, kein Kernel-Service. Alle
  drei mit dieser Begründung als Nicht-Ziel deklariert.
- **G12 GELÖST:** `jardis/adapter/messaging` in Bezug-Liste +
  Release-Koordination (eigener do-git-update-Release für den Kafka-Fix).
- **G13 GELÖST (Empfehlung übernommen):** Soll: `KAFKA_BROKERS` =
  kommaseparierte host:port-Liste, fließt vollständig ins Host-Feld der
  Factory; `KAFKA_PORT` ist KEIN kanonischer Schlüssel. Mechanik (fromEnv
  anpassen vs. Handler umgeht fromEnv) entscheidet der Plan; prüfbares Soll
  über den Broker-Integrationstest. Adapter-Doku korrigieren.
- **G14 GELÖST (Nicht-Ziel) + MERKPOSTEN:** R4 deckt kafka|rabbitmq|redis;
  Consumer-Konfiguration (groupId) Folgekandidat übers worker-Profil.
  Merkposten Rolf: für lokale Nutzung ist der `database`-Transport ggf.
  genauso wichtig wie die großen Queue-Server — als Folgekandidat notieren,
  nicht in diesem Vorhaben.
- **G15 GELÖST (Empfehlung übernommen):** R4-Akzeptanz umformuliert: nur die
  Frontmatter-description des Wissensbasis-Eintrags ist falsch (Text führt
  das Nachziehen korrekt als offen) — description jetzt korrigieren; nach
  R4-Umsetzung Eintrag fortschreiben (Messaging = umgesetzt) inkl.
  G11-Regel als Kriterium.
- **G23 GELÖST (Wiederverwendung):** Messaging-Transport redis nutzt
  dieselben `REDIS_*`-Schlüssel wie der Cache (ein Stack = ein Redis),
  explizit dokumentiert; getrennte Instanzen nur als Folgekandidat.
- **G16 GELÖST:** Contracts-`env()`-Docblock in die R3-Akzeptanz (im
  gebündelten Contracts-Release).
- **G18 GELÖST (Verwendungsregel):** Primärbegriff **DomainKernel**;
  „Kernel" allein nur bei eindeutigem Package-Kontext; nie „Kernel" für den
  Domain Core; Rollenbeschreibung „the DomainKernel carries all
  infrastructure services".
- **G19 GELÖST:** Wissensbasis-Kennungen werden MIT umbenannt (Datei + id →
  `domainkernel-ist-die-infrastrukturflaeche`), Wikilinks + INDEX
  nachgezogen, Herkunftsvermerk im Eintrag; „Koffer-Architektur"-Passagen in
  `ein-stack-eine-technische-umgebung` umformuliert (gepflegter Text, kein
  Archiv); eingefrorene Snapshots ausgenommen; Linkintegrität per
  Wissensbasis-Check belegt.
- **G20 GELÖST:** Übergabeort R6-Dokument: `jardis/tools/builder/docs/`
  (requirements-Dokument, von der WIE-Fläche verlinkt) — Pfad steht im PRD.
- **G21 GELÖST (bootstrap.php-Muster):** Jardis schreibt beide
  Konfigschichten nur bei Erst-Provisionierung (ForceOverwrite:false),
  danach Entwickler-Eigentum; dauerhaft maschinenschreibbar bleibt nur die
  `COMPOSE_PROFILES`-Zeile; Secrets schreibt Jardis nie (`.env.local`).
  Eigentums-Tabelle im Template-README um `.env`/`config/env/` ergänzt.
- **G22 VERWORFEN (bewusst):** Lauf-Heimat Template-Repo ist gefallener
  Bescheid Rolf 2026-08-22 (Commit 51fb655) — wird nicht neu aufgerollt;
  Endartefakte landen ohnehin in ihren Ziel-Repos.
- **G24 GELÖST:** dev-skills in Bezug-Liste; R5-Sweep über ALLE berührten
  Repos: kernel, app, contracts, dotenv, adapter/messaging, jardis-claude,
  template, dev-skills.
- **G25 GELÖST (Leitplanken-Satz):** Jede Phase schreibt angefassten
  Doku-Text bereits koffer-frei; R5-Sweep läuft als LETZTES und fängt nur
  Reste.
- **G27 GELÖST (Wortlaut):** „zwei DBs = zwei Stacks (je eigener
  Template-Klon mit eigenem Kernel)".
- **G28 GELÖST (durch Vollzug):** Gate gelaufen, D1–D5 blieben als
  Grundentscheide, Befunde haben sie verfeinert; PRD-Statuszeile
  aktualisiert.

**Damit sind alle 28 Befunde beschieden (27 gelöst, 1 bewusst verworfen) —
das Gate ist durchlaufen. Bescheide Rolf 2026-08-22, blockweise im Dialog.**
