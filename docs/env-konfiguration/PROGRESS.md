# Fortschritt — ENV-Konfiguration bereinigen

## Kopf
- **Ziel:** ENV-Konfiguration widerspruchsfrei (Konvention Projekt-Root/config/env,
  datei-reiner Kernel, Bugfixes, Messaging-Bootstrap, „Koffer" abgeschafft,
  Requirement-Dokument an Jardis) — Details: PRD R1–R6.
- **Stand:** Vorhaben aufgesetzt (2026-08-22). Erhebungen abgeschlossen und in
  BEFUNDE.md kondensiert. PRD-Entwurf steht, inhaltlich von Rolf beschieden
  (D1–D5) — formale Bestätigung steht aus, WAS-Gremium-Gate noch nicht gelaufen.
  Kein Plan, keine Umsetzung, nichts rot.
- **Nächster Schritt:** WAS-Gremium-Gate am PRD-Entwurf fahren (Voll-Pfad,
  Backend-Track: was-skeptiker + was-ddd-stratege + ggf. discovered Rollen;
  parallel starten, Befunde deduplizieren, mit Rolf beschieden) — danach
  PRD-Bestätigung durch Rolf, dann EnterPlanMode für PLAN.md.
- **Offene Bescheide:** — (D1–D5 sind beschieden, s. Leitplanken)
- **Leitplanken:**
  1. Bescheide Rolf 2026-08-22: D1 Konvention Root/config/env/src · D2 Packer
     nimmt Projekt-Root · D3 Bugfixes zuerst · D4 $_ENV-Fallback entfernen ·
     D5 Messaging in den Kernel · „Koffer" ersatzlos → „Kernel/DomainKernel".
  2. Jede Package-Änderung mit Tests + sämtlichen Docs + do-git-update-Release;
     Contracts-Releases bündeln (ein Release für R2/R3/R4-Anteile).
  3. Öffentliche API/Verhalten publizierter Packages: Release-Schnitt bewusst
     im do-git-update-Pfad entscheiden; nie Historie/Tags anfassen.
  4. Orchestrator-Modus: Hauptdialog delegiert an Subagenten, parallel wo
     dreifach unabhängig (Dateien + Contracts + QA-Infra); Doer ≠ Checker.
  5. Grundsatz `ein-stack-eine-technische-umgebung` (Wissensbasis) — kein
     Multi-Connection-Kernel.

## Bezug
- PRD:  docs/env-konfiguration/PRD.md (Entwurf, s. Stand)
- Plan: docs/env-konfiguration/PLAN.md (existiert noch nicht)
- Belege: docs/env-konfiguration/BEFUNDE.md (alle Datei:Zeile-Fundstellen der
  Erhebungen vom 2026-08-22 — NICHT neu erheben)
- Heimat des Laufs: dieses Repo (devops/jardis-app-template) — Bescheid Rolf
  2026-08-22: alles von hier orchestrieren, kein Repo-Hüpfen. Subagenten
  arbeiten in den Ziel-Repos über absolute Pfade:
  Kernel    /Users/Rolf/Development/headgent/jardis/core/kernel
  Contracts /Users/Rolf/Development/headgent/jardis/support/contracts
  DotEnv    /Users/Rolf/Development/headgent/jardis/support/dotenv
  App       /Users/Rolf/Development/headgent/jardis/core/app (nur Doku)
  Wissensb. /Users/Rolf/Development/headgent/jardis/claude

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
      Betroffene Repos: core/kernel · support/contracts · support/dotenv (nur
      falls Secret-Fix dort landet) · core/app (nur Doku) · jardis-claude ·
      devops/jardis-app-template.

## Phasen (grober Zuschnitt lt. Bescheid; Feinschnitt macht PLAN.md)
- [ ] Phase 0: WAS-Gremium-Gate + PRD-Bestätigung + PLAN.md (WIE-Gremium-Gate)
- [ ] Phase 1: R1 Kernel-Bugfixes (eigener Release, zuerst)
- [ ] Phase 2: R2 Konvention/Packer + R3 Fallback-Entfernung (+ Contracts) +
      Template-Nachzug
- [ ] Phase 3: R4 Messaging-Bootstrap (+ Contracts, Template-Schaltpunkt,
      Wissensbasis-Fortschreibung)
- [ ] Phase 4: R5 Koffer-Sweep + R6 Requirement-Dokument an Jardis
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
