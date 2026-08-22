# WIE-Gremium-Gate — Befunde + Bescheide (2026-08-22)

Prüfgegenstand: PLAN-Entwurf. Besetzung: wie-architekt (A1–A23, Opus),
wie-test-qa (T1–T15, Sonnet), wie-packages-experte (F1–F8, Sonnet),
wie-senior-php (S1–S11, Sonnet; gezogen mit Grund: DotEnv-Parser-Eingriff +
Handler-Fehlermodell), blind und parallel. wie-ddd-taktiker entfiel (keine
Domänen-Modellierung). 57 Befunde, dedupliziert zu Clustern.

**Bescheid-Modus:** Rolf 2026-08-22 delegierte die Bescheide an den
Orchestrator („entscheide selbst, außer erheblicher Zweifel"); einzig der
Contracts-Major-Schnitt wurde Rolf vorgelegt und von ihm bestätigt („okay").
Alle Bescheide sind in den freigegebenen PLAN (docs/env-konfiguration/
PLAN.md) eingearbeitet — dieser Text ist die Rechenschaft.

## Cluster + Bescheide

**C1 Release/SemVer (A1 Blocker, A2, A19, A11, F4, F8, T8, A20/T7):**
contracts wird **v2.0.0** (Major; `^1.0`-Constraints alter Kernel dürfen den
`projectRoot()`-Rename nie auflösen — Rolf bestätigt). `DotEnvInterface`
bleibt UNVERÄNDERT (String-API/addRawKeys als Klassen-API, Präzedenz
addHandler) — löst A2 (Fatal-Kombination contracts-neu + dotenv-alt)
ersatzlos. Kernel-composer in P4: contracts `^2.0`, suggest+require-dev
messaging + Broker-Extensions (A11/F4). MessagingServiceInterface existiert,
kein Prüfvorbehalt (F8). P3 baut fix per composer path-repository; AK4.6
verlangt Re-QA gegen releaste contracts vor kernel v2.0.0 (T8, A20/T7).
Kernel v1.2.0 dokumentiert beide Verhaltensänderungen im CHANGELOG (A19).

**C2 DotEnv-Bauform (A3 Blocker, A4, A5, A6, S7, S8, S9, S11, T13, T14,
F7, A21, A23):** Zeilen-Engine als eigene Einheit `LoadValuesFromRows`,
`LoadValuesFromString` als eigene atomare Einheit per Komposition — keine
zweite public-Methode (A3). Raw-Key-Matching als eigene Closure
`Handler/MatchesRawKey` (A6, A23); Zuführung `DotEnv::addRawKeys(array)`
akkumulierend/dedupliziert, `array<string>`, kein remove bis Bedarf (A5,
S11). `_FILE`-Pfad: Regel greift auf den AUFGELÖSTEN Key (S7). Neue
`IncludeNotSupportedException` (S8). String-Split: nur exakt leere Zeilen,
BOM-Strip, CRLF; Parität in AK1.3 exerziert (S9). Relatives `KEY_FILE` ohne
baseDir → Exception (A4). Matching-Grenzfälle als AK1.5 (T13).
Secret-Abgrenzung im Plan benannt (F7). P1 fasst die kuratierte Skill-Quelle
NICHT an — Nachzug in P6, Single-Writer (A21).
VERWORFEN: Test für Raw-Keys mit injiziertem Reader — Bestandsfalle
(identisch addHandler), dokumentiert, Folgekandidat (T14).

**C3 Kernel-R1-Bauform (S1 Blocker, S2, S3, S4, A7, A8, A9, T1, T2, T3,
T4, T15, A18):** `InvalidEnvConfigurationException` wird in den
catch-\Throwable-Pfaden von BuildConnectionFromEnv rethrown — ungültige
Pool-Config verschwindet nie im PDO-Fallback; AK2.4 exerziert das
Verschluck-Szenario mit ERREICHBAREM Host (S1). `''`→`null` zentral in
`$envGet`/`env()` — leer = fehlend uniform (S2, T2). EINE
`NormalizeEnvBool`-Einheit, versteht bool|int|string (Cast-Kette liefert
int für "1"/"0"), unparsebar → Exception via FILTER_NULL_ON_FAILURE (S3,
S4, A8). Credential-Suffixe als Konstante in `Bootstrap/Data/`, nicht im
Orchestrator-Body (A7). Scope = ALLE 11 Handler inkl. BuildRedisFromEnv
:53-55 (A9); unbekannte-Schlüssel-Regel als AK2.4-Fall (T1).
Integration-Suite ehrlich ab P2 (`tests/Integration`, T3); AK2.3 über das
öffentliche Config-VO (T4); Helfer nach `tests/Support/` (T15);
Coverage ≥ 80 % je Release-Phase (A18).

**C4 Kernel-R2/R3 (A10/S5, S6):** $_ENV-Fallback AUCH im Packer-`$envGet`
:83 explizit gescoped, AK3.2 = grep `\$_ENV` in src = 0 (A10, S5).
mkdir race-sicher: `!is_dir && !@mkdir && !is_dir → throw` (TOCTOU
paralleler fpm-Kaltstart, S6).

**C5 Messaging (F1, F2, F3, A13, A14, A16, A17/F5, A12, S10/T6, F6, T5,
T11):** Handler nutzt ConnectionFactory + PublisherFactory/ConsumerFactory
— kein eigenes Broker-/Port-Parsing (F1, F2; macht S10/T6/F6 gegenstandslos;
`match($transport)` als Form, A13). Kafka-Consumer-Closure wirft
dokumentierte RuntimeException bei Nutzung ohne groupId — lazy, Publish-only
bleibt frei (F3, G14-Grenze, AK4.4). Redis: Werte teilen, EIGENE Verbindung
(pub/sub blockiert; A14); Kombi-AK4.5 Cache+Messaging gleichzeitig (T11).
class_exists-Degradation → null (A16). messaging-Parameter ans Ende nach
`$env`, named args (A17, F5). P4-Preflight: `php -m` Extension-Check im
phpcli, STOPP falls rdkafka/amqp/redis fehlen (A12); AK4.3 = Roundtrip +
Assertion an der öffentlichen Factory-Grenze (T5).

**C6 Sweep/Orga (A15, T9, T10/A22, A21):** G15-description-Sofortfix in P3
(claude-Repo wird dort ohnehin angefasst; A15). P6 bekommt AK6.4: QA-Lauf in
jedem berührten Code-Repo nach dem Sweep (T9). P7 bekommt Inhalts-Checkliste
der fünf R6-Punkte + Link-Grep (T10, A22). `.backup`-Skill-Kopien im Kernel
werden gelöscht (Redundanz).
VERWORFEN: Fremd-Repo-Regressionsläufe für weitere DotEnv-Konsumenten —
Constraint `^1.0` zieht v1.2 kompatibel, Default-Verhalten unverändert +
grüne dotenv-Suite genügt (T12); G23-Funktionskollision ist durch AK4.5
abgedeckt.

**Damit sind alle 57 Befunde beschieden (55 gelöst/eingearbeitet, 2 mit
Begründung verworfen: T14, T12). Gate durchlaufen, Plan freigegeben
(ExitPlanMode, Rolf 2026-08-22).**
