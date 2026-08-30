#!/usr/bin/env bash
# ENV-key parity check: the root .env.example of this template vs. the Kernel
# ENV example in jardis/core/kernel/docs/.env.example — BLOCKWISE.
#
# Grundsatz (Wissensbasis env-schluessel-eine-quelle-kernel-examples): the
# ENV KEY SET has exactly one source of truth, the Kernel example — this
# template is a copy of that key set with its own delivery state (active vs.
# commented-out, and its own default values). This script never touches
# values or activation state, only the SET of keys and the block each one
# sits in.
#
# Both files are structured by `# === <block> ===` headers. The Kernel owns
# eight blocks (app, database, redis, cache, logger, http, mail, messaging);
# this template adds two of its own (stack, nginx) that the Kernel never sees
# — they are skipped here.
#
# Usage: bin/sync-env-from-kernel.sh [--check|--print-missing]
#   --check         (default) read-only. Reports every Kernel key missing
#                    from its block here (hard failure, exit 1), every Kernel
#                    key that sits in the WRONG block here (hard failure —
#                    placement is semantic, a service is switched on as a
#                    unit), and every UNMARKED extra template key inside a
#                    Kernel block (reporting only — exit stays 0).
#
#                    A deliberate template extra carries a marker: a comment
#                    line starting with `# Template:` directly above the key
#                    line (further comment lines of the same block may sit
#                    between, a blank line ends the run). Marked extras are
#                    known and stay silent; unmarked ones are reported, so a
#                    key that arrived by accident still shows up.
#   --print-missing read-only. For each block with a gap, prints the missing
#                    Kernel keys as commented "#KEY=default" lines, ready to
#                    paste into that block by hand.
#
# There is NO write mode. Placement of an ENV key inside its block is
# semantic — only a human (or the Builder's own generator, which carries the
# same block model) may decide where a newly added key belongs. This script
# only detects and reports the gap.
#
# Exit codes:
#   0 - key-set parity (no missing and no misplaced Kernel key; extra
#       template keys, if any, are reported but don't fail the gate)
#   1 - at least one Kernel key is missing here or sits in the wrong block
#   2 - source missing: JARDIS_KERNEL_DIR does not point at a checkout with
#       docs/.env.example (no drift verdict possible without a source)

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
KERNEL_DIR="${JARDIS_KERNEL_DIR:-$REPO_ROOT/../../jardis/core/kernel}"
KERNEL_FILE="$KERNEL_DIR/docs/.env.example"
TEMPLATE_FILE="$REPO_ROOT/.env.example"

# Blocks this template owns; the Kernel knows nothing about them.
TEMPLATE_ONLY_BLOCKS=" stack nginx "

MODE="check"
case "${1:-}" in
  ""|--check) MODE="check" ;;
  --print-missing) MODE="print-missing" ;;
  *)
    echo "Usage: $0 [--check|--print-missing]" >&2
    exit 2
    ;;
esac

if [[ ! -f "$KERNEL_FILE" ]]; then
  echo "ENV-PARITY.source · hart · Kernel-Example nicht gefunden unter: $KERNEL_FILE (JARDIS_KERNEL_DIR setzen)" >&2
  exit 2
fi

if [[ ! -f "$TEMPLATE_FILE" ]]; then
  echo "ENV-PARITY.source · hart · .env.example nicht gefunden unter: $TEMPLATE_FILE" >&2
  exit 2
fi

# A "key line" is `^#?[A-Z][A-Z0-9_]*=` — the comment char sits DIRECTLY
# before the name (the convention for a disabled key, e.g. "#DB_HOST="). A
# prose comment with a space after '#' ("# DB_HOST=db (network alias...)") is
# NOT a key line: the character right after '#' is a space, not an uppercase
# letter.
KEY_REGEX='^#?[A-Z][A-Z0-9_]*='
BLOCK_REGEX='^# === ([a-z]+) ===[[:space:]]*$'

# A deliberate template extra is marked by a comment line starting with
# `# Template:` above the key line. The run of comment lines directly above a
# key carries the marker; a blank line (or any other content) ends it.
MARKER_REGEX='^# Template:'

# Emits "<block> <key> <marked>" per key line (marked = 0|1), and "<block>"
# alone for every header, so a block with zero keys is still known to exist.
extract_pairs() {
    local file="$1" block="" line key marked=0
    while IFS= read -r line || [[ -n "$line" ]]; do
        if [[ "$line" =~ $BLOCK_REGEX ]]; then
            block="${BASH_REMATCH[1]}"
            marked=0
            echo "$block"
            continue
        fi
        if [[ -z "${line//[[:space:]]/}" ]]; then
            marked=0
            continue
        fi
        if [[ "$line" =~ $MARKER_REGEX ]]; then
            marked=1
            continue
        fi
        [[ -z "$block" ]] && continue
        if [[ "$line" =~ $KEY_REGEX ]]; then
            key="${line%%=*}"
            key="${key#\#}"
            echo "$block $key $marked"
            marked=0
            continue
        fi
        # Any other line: a prose comment keeps a pending marker alive, real
        # content drops it.
        [[ "$line" =~ ^# ]] || marked=0
    done < "$file"
}

kernel_pairs="$(extract_pairs "$KERNEL_FILE" | sort -u)"
tmpl_pairs="$(extract_pairs "$TEMPLATE_FILE" | sort -u)"

kernel_blocks="$(echo "$kernel_pairs" | awk 'NF==1' | sort -u)"
kernel_keyed="$(echo "$kernel_pairs" | awk 'NF==3')"
tmpl_keyed="$(echo "$tmpl_pairs" | awk 'NF==3')"

# Default line of a Kernel key, for --print-missing.
kernel_default_line() {
    grep -E "^#?${1}=" "$KERNEL_FILE" | head -1 || true
}

# Which block does this key sit in on the template side (may be empty)?
tmpl_block_of() {
    echo "$tmpl_keyed" | awk -v k="$1" '$2 == k { print $1 }' | head -1
}

missing_total=0
wrongblock_total=0
extra_total=0

while IFS= read -r block; do
    [[ -z "$block" ]] && continue

    kernel_keys="$(echo "$kernel_keyed" | awk -v b="$block" '$1 == b { print $2 }' | sort -u)"
    tmpl_keys="$(echo "$tmpl_keyed" | awk -v b="$block" '$1 == b { print $2 }' | sort -u)"

    if [[ "$MODE" == "print-missing" ]]; then
        printed_header=0
        while IFS= read -r key; do
            [[ -z "$key" ]] && continue
            grep -qx -- "$key" <<<"$tmpl_keys" && continue
            if (( ! printed_header )); then
                echo "# === $block ==="
                printed_header=1
            fi
            default_line="$(kernel_default_line "$key")"
            echo "#${default_line#\#}"
        done <<<"$kernel_keys"
        continue
    fi

    # 1. Kernel key missing entirely, or sitting in another block here.
    while IFS= read -r key; do
        [[ -z "$key" ]] && continue
        grep -qx -- "$key" <<<"$tmpl_keys" && continue
        found_in="$(tmpl_block_of "$key")"
        if [[ -n "$found_in" ]]; then
            echo ".env.example · ENV-PARITY.wrongblock · hart · ${key} steht unter '${found_in}', gehoert nach '${block}'"
            wrongblock_total=$((wrongblock_total + 1))
        else
            echo ".env.example · ENV-PARITY.missing · hart · ${key} fehlt im Block '${block}'"
            missing_total=$((missing_total + 1))
        fi
    done <<<"$kernel_keys"

    # 2. Extra template key inside a Kernel block. A key the Kernel reads in
    #    ANOTHER block is not "extra" — it was already reported as wrongblock
    #    over there, and reporting it twice would blur the verdict. A key
    #    carrying the `# Template:` marker is a known extra and stays silent.
    while IFS= read -r key; do
        [[ -z "$key" ]] && continue
        grep -qx -- "$key" <<<"$kernel_keys" && continue
        if echo "$kernel_keyed" | awk '{ print $2 }' | grep -qx -- "$key"; then
            continue
        fi
        if [[ "$(echo "$tmpl_keyed" | awk -v b="$block" -v k="$key" '$1 == b && $2 == k { print $3 }' | head -1)" == "1" ]]; then
            continue
        fi
        echo ".env.example · ENV-PARITY.extra · meldend · ${key} im Block '${block}'"
        extra_total=$((extra_total + 1))
    done <<<"$tmpl_keys"
done <<<"$kernel_blocks"

[[ "$MODE" == "print-missing" ]] && exit 0

# Template-only blocks are never compared; say so once, so their absence from
# the report is a decision and not an oversight.
echo "uebersprungen (Template-eigene Bloecke):${TEMPLATE_ONLY_BLOCKS% }"
echo "SUMME: ${missing_total} fehlend (hart) · ${wrongblock_total} falscher Block (hart) · ${extra_total} zusaetzlich (meldend)"

if (( missing_total > 0 || wrongblock_total > 0 )); then
  exit 1
fi
exit 0
