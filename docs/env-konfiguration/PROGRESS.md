# Fortschritt — ENV-Konfiguration bereinigen

## Kopf
- **Ziel:** ENV-Konfiguration widerspruchsfrei (Konvention Projekt-Root/config/env,
  datei-reiner Kernel, Bugfixes, Messaging-Bootstrap, „Koffer" abgeschafft,
  Requirement-Dokument an Jardis) — Details: PRD R1–R6.
- **Stand:** Phase 0 KOMPLETT (2026-08-22): PRD bestätigt (WAS-Gate: 28
  Befunde beschieden, GREMIUM-WAS.md) · PLAN.md erstellt und via
  ExitPlanMode FREIGEGEBEN (WIE-Gate: 57 Befunde beschieden — delegiert an
  Orchestrator per Bescheid Rolf, Contracts-Major von Rolf bestätigt;
  GREMIUM-WIE.md). Explorer-Erhebungen sind im PLAN als
  „Erhebungs-Kernfakten" eingearbeitet. Keine Umsetzung gestartet,
  nichts rot.
- **Nächster Schritt:** Phase P1 starten (DotEnv Raw-Keys + String-Input,
  PLAN.md §P1): Umsetzungs-Subagent (Sonnet) nach 3-subagent-auftrag.md
  briefen, danach unabhängiger Verifier; Release do-git-update → v1.2.0.
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
- [ ] P1: DotEnv Raw-Keys + String-Input → Release v1.2.0
- [ ] P2: Kernel R1 (Bool-Klasse, Fehlerregeln, Raw-Keys) → Release v1.2.0
- [ ] P3: Kernel R2+R3 + Contracts-Änderungen + Wissensbasis-Eintrag
      (kein Release; path-repo auf contracts-develop)
- [ ] P4: R4 Messaging + Releases contracts v2.0.0 → kernel v2.0.0 →
      messaging (Preflight: php -m Extension-Check, STOPP-fähig)
- [ ] P5: Template-Nachzug + App-Doku (make start beide Zustände)
- [ ] P6: Koffer-Sweep 8 Repos + Wissensbasis-Umbenennung (+ QA-Läufe)
- [ ] P7: R6-Dokument an Builder (Abnahme Rolf = STOPP-Punkt)
- [ ] Abschluss: Akzeptanz-Gate gegen das ganze PRD, Docs-Sync, Retro

## Letzte abgeschlossene Phase
- — (noch keine)

## Prozess-Notizen (3.7)
- —

## Delegierte Entscheidungen (Rückfrage-Gate)
- —

## Offene Punkte / Risiken
- Release-Schnitt R3 (minor vs. major) bewusst im do-git-update-Pfad klären.
- R4-Testinfrastruktur: Broker-Container für Kernel-Integrationstests nötig
  (development.md §5) — Aufwand im Plan bemessen.
- dbConnection-Folgekandidaten (BEFUNDE.md §4) bewusst NICHT in diesem
  Vorhaben — nach Abschluss als eigenes kleines Vorhaben vorschlagen.
