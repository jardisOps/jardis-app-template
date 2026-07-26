# jardis-app-template — Arbeitsanweisung

Dieses Repo ist die Docker-Laufzeitumgebung für Jardis-Domains: nginx +
php-fpm, ein CLI-Container, zuschaltbare Dienste. Es wird geklont und
abgeleitet — was hier liegt, landet in echten Projekten. Bedienung steht in der
`README.md`.

## Sprache

**Dialog mit dem User: Deutsch. Alle Artefakte im Repo: Englisch** — Kommentare,
Doku, Meldungen, README. Kommentare knapp: höchstens zwei bis drei Sätze je
Block, der Grund statt der Entstehungsgeschichte.

## Was dieses Repo nicht tut

**Es baut keine Images.** `headgent/phpcli` und `headgent/phpfpm` kommen aus
`devops/docker/php-image-builder` über Docker Hub. Ein `Dockerfile` gehört hier
nicht hinein — wenn ein Laufzeit-Bedürfnis nicht erfüllt ist, wird es dort
gelöst, nicht hier umgangen.

**Es enthält keine Fachlogik.** Der Auslieferungszustand ist ein leeres,
lauffähiges Gerüst.

## Herkunft der Konventionen

Die Bausteine stammen aus `devops/provisioning` und sind dort mit
`devops/orchestration` byte-identisch — das ist die Hauslinie, nicht Zufall.
Unverändert übernommen: `Makefile`, `support/makefile/{composer,docker,hooks,
qa-stack,ssh}.mk`, `pre-commit-hook.sh`, `phpstan.neon` (Level 8), `phpcs.xml`
(PSR-12 + strict types), `phpunit.xml`, `tests/bootstrap.php`.

Bewusst abweichend, jeweils mit Grund:

| Abweichung | Grund |
|---|---|
| `.env` ist versioniert | Ein Template muss nach dem Klonen laufen. Secrets gehören nach `.env.local`. |
| `type: "project"` statt `"library"` | Es ist ein Anwendungsgerüst, keine Bibliothek. |
| `stack.mk` neu geschrieben | Die Vorlage rief `bin/provision` auf. `orchestration` hat sie ungeprüft kopiert und trägt seither tote Targets. |
| kein Xdebug-Port-Mapping | Xdebug verbindet sich ausgehend zur IDE, es lauscht nicht im Container. |
| drei Services statt einem | Request (fpm), Web (nginx), Werkzeug (cli). |
| `qa-stack.mk`: ein Hilfetext | `integration-test` nannte `tests/fixtures/<provider>/` — hier nicht vorhanden. Im Modulkopf vermerkt. Sonst byte-identisch. |

Der Service heißt weiterhin `phpcli`: `docker.mk` und `qa-stack.mk` sprechen
ihn namentlich an. Umbenennen hieße, beide Module zu forken.

## Die zwei Konfigurationsschichten

Sie werden nicht vermischt, sonst steht jeder Wert zweimal da:

- **Stack** — `.env` im Root, gelesen von `docker compose` und dem Makefile.
- **Anwendung** — `config/env/`, gelesen von der DotEnv-Kaskade *im Container*.
  Werte kommen über `environment:` aus der Root-`.env` herein, statt dort
  wiederholt zu werden.

Nur `config/env/.env.database` wird per `load()` geladen und ist damit Pflicht;
alles andere ist `load?()` und darf fehlen.

## Der Auslieferungszustand muss ohne Konfiguration laufen

`make start` startet nur `web` und `app`, die Anwendung läuft auf SQLite. Das
geht, weil der Kernel jeden nicht konfigurierten Adapter zu `null` degradiert
statt zu scheitern. Diese Eigenschaft ist der Grund, warum das Template klein
sein darf — sie darf nicht durch einen Pflichtdienst aufgegeben werden.

## Eigentum am generierten Code

`src/` ist das OutputDir des Builders und gehört **nicht** in die `.gitignore`:
dort liegt gemischtes Eigentum.

| Pfad | Wem |
|---|---|
| `src/{BC}/Aggregate/` | Generator, hermetisch — wird bei jedem Build überschrieben |
| `src/App/bootstrap.php` | einmal geschrieben, danach dir (ForceOverwrite:false) |
| `src/{BC}/Rule/`, Teile von `Process/` | dir |
| `public/index.php`, `bin/console` | dir, wird nie generiert |

Eine zweite Domain erscheint **nicht** von selbst in `bootstrap.php` — die
Datei wird nicht neu geschrieben. Die Facade-Zeile ist von Hand nachzutragen.

## Arbeitsweise

- **Keine Annahmen.** Jeder Befund wird belegt — an der Datei, an der Messung.
  Das gilt besonders hier: kein anderes Headgent-Projekt verdrahtet bisher
  `jardiscore/kernel` und `phpfpm`, es gibt also nichts zum Abschauen.
- **Nicht überkonstruieren.** Was ein Zielprojekt selbst entscheiden soll,
  entscheidet das Template nicht vor.
- **Code Review nach jeder Codierung** (`do-qa-codereview`), kein Commit ohne.
