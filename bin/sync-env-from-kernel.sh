#!/usr/bin/env bash
# ENV-key parity check: config/env/* (this template) vs. the Kernel ENV
# examples in jardis/core/kernel/docs/env-examples/*.example.
#
# Grundsatz (Wissensbasis env-schluessel-eine-quelle-kernel-examples): the
# ENV KEY SET has exactly one source of truth, the Kernel examples — this
# template is a copy of that key set with its own delivery state (active vs.
# commented-out, and its own default values). This script never touches
# values or activation state, only the SET of keys.
#
# Usage: bin/sync-env-from-kernel.sh [--check|--print-missing]
#   --check         (default) read-only. Reports every Kernel key missing
#                    from a template file (hard failure, exit 1) and every
#                    template key the Kernel does not read (reporting only,
#                    App-level key such as APP_DEBUG — exit stays 0).
#   --print-missing read-only. For each file with a gap, prints the missing
#                    Kernel keys as commented "#KEY=default" lines, ready to
#                    paste into the right block by hand.
#
# There is NO write mode. Placement of an ENV key inside its file is
# semantic in the Builder's runtime block model (docs/runtime-nachbefunde)
# — a block begins at its own switch line, e.g. MESSAGING_TRANSPORT=redis
# starts the redis block; a line mechanically appended at end-of-file would
# land in the wrong block (e.g. a REDIS_HOST meant for the redis transport
# appended after the database block). Only a human (or the Builder's own
# generator, which already carries this block model) may decide where a
# newly added key belongs — this script only detects and reports the gap.
#
# Exit codes:
#   0 - key-set parity (no missing Kernel key; extra template keys, if any,
#       are reported but don't fail the gate)
#   1 - at least one Kernel key is missing from a template file (Realbefund)
#   2 - source missing: JARDIS_KERNEL_DIR does not point at a checkout with
#       docs/env-examples/ (no drift verdict possible without a source)

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
KERNEL_DIR="${JARDIS_KERNEL_DIR:-$REPO_ROOT/../../jardis/core/kernel}"
EXAMPLES_DIR="$KERNEL_DIR/docs/env-examples"
ENV_DIR="$REPO_ROOT/config/env"

MODE="check"
case "${1:-}" in
  ""|--check) MODE="check" ;;
  --print-missing) MODE="print-missing" ;;
  *)
    echo "Usage: $0 [--check|--print-missing]" >&2
    exit 2
    ;;
esac

if [[ ! -d "$EXAMPLES_DIR" ]]; then
  echo "ENV-PARITY.source · hart · Kernel-Examples nicht gefunden unter: $EXAMPLES_DIR (JARDIS_KERNEL_DIR setzen)" >&2
  exit 2
fi

# A "key line" is `^#?[A-Z][A-Z0-9_]*=` — the comment char sits DIRECTLY
# before the name (the Kernel examples' own convention for a disabled key,
# e.g. "#DB_READER1_HOST="). A prose comment with a space after '#'
# ("#   DB_HOST=db  (network alias...)") is NOT a key line — it never
# matches this regex because the character right after '#' is a space, not
# an uppercase letter.
KEY_REGEX='^#?[A-Z][A-Z0-9_]*='

extract_keys() {
  grep -oE "$KEY_REGEX" "$1" 2>/dev/null | sed -E 's/^#//; s/=$//' | sort -u || true
}

# Kernel-example-file : template-file pairs (all eight files the Kernel
# README table names).
PAIRS=(
  ".env.example:.env"
  ".env.cache.example:.env.cache"
  ".env.database.example:.env.database"
  ".env.http.example:.env.http"
  ".env.logger.example:.env.logger"
  ".env.mail.example:.env.mail"
  ".env.messaging.example:.env.messaging"
  ".env.redis.example:.env.redis"
)

missing_total=0
extra_total=0

for pair in "${PAIRS[@]}"; do
  kernel_file="${pair%%:*}"
  tmpl_file="${pair##*:}"
  kernel_path="$EXAMPLES_DIR/$kernel_file"
  tmpl_path="$ENV_DIR/$tmpl_file"

  kernel_keys="$(extract_keys "$kernel_path")"
  tmpl_keys="$(extract_keys "$tmpl_path")"

  missing="$(comm -23 <(echo "$kernel_keys") <(echo "$tmpl_keys") | sed '/^$/d' || true)"
  extra="$(comm -13 <(echo "$kernel_keys") <(echo "$tmpl_keys") | sed '/^$/d' || true)"

  if [[ "$MODE" == "print-missing" ]]; then
    if [[ -n "$missing" ]]; then
      echo "# --- missing from config/env/$tmpl_file (source: $kernel_file) ---"
      while IFS= read -r key; do
        [[ -z "$key" ]] && continue
        default_line="$(grep -E "^#?${key}=" "$kernel_path" | head -1 || true)"
        echo "#${default_line#\#}"
      done <<<"$missing"
    fi
    continue
  fi

  while IFS= read -r key; do
    [[ -z "$key" ]] && continue
    echo "config/env/${tmpl_file} · ENV-PARITY.missing · hart · ${key}"
    missing_total=$((missing_total + 1))
  done <<<"$missing"

  while IFS= read -r key; do
    [[ -z "$key" ]] && continue
    echo "config/env/${tmpl_file} · ENV-PARITY.extra · meldend · ${key}"
    extra_total=$((extra_total + 1))
  done <<<"$extra"
done

[[ "$MODE" == "print-missing" ]] && exit 0

echo "SUMME: ${missing_total} fehlend (hart) · ${extra_total} zusaetzlich (meldend)"

[[ "$missing_total" -gt 0 ]] && exit 1
exit 0
