#!/bin/bash
# Guardrail for the ONE versioned env file, `.env.example`. It is committed by
# design (trivial defaults only); real values are injected at runtime or, for
# keys only the kernel reads, encrypted as secret(...).
#
# Three rules:
#   1. no real credential in a versioned env file (token formats, non-trivial
#      plaintext for *_PASSWORD/_SECRET/_TOKEN/_KEY),
#   2. no secret(...) on a key docker compose consumes — compose cannot
#      decrypt and would hand the ciphertext to the container verbatim,
#   3. no overlay file next to .env — this project has ONE config file.
#
# Modes: --staged scans lines being committed (pre-commit hook),
#        --tree scans the full versioned env files (CI).
# Override for a deliberate false positive: JARDIS_ALLOW_ENV_SECRET=1 git commit

set -u

MODE="${1:---staged}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FILE_GLOB='.env*'
SUSPICIOUS_KEY='(_PASSWORD|_SECRET|_TOKEN|_KEY|_PAT|_CREDENTIALS)$'
# Well-known credential formats — blocked in ANY value, regardless of key.
TOKEN_FORMATS='ghp_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,}|glpat-[A-Za-z0-9_-]{15,}|sk-[A-Za-z0-9_-]{20,}|AKIA[0-9A-Z]{16}|xox[abprs]-[A-Za-z0-9-]{10,}|eyJ[A-Za-z0-9_-]{20,}\.eyJ|-----BEGIN .*PRIVATE KEY'
MAX_TRIVIAL_LEN=15

if [[ "${JARDIS_ALLOW_ENV_SECRET:-0}" == "1" && "$MODE" == "--staged" ]]; then
    echo "check-env-secrets: skipped (JARDIS_ALLOW_ENV_SECRET=1)"
    exit 0
fi

# The keys docker compose interpolates, read out of the compose file itself so
# the list can never drift from it. Covers both ${KEY} and ${KEY:-default}.
compose_keys() {
    grep -oE '\$\{[A-Z][A-Z0-9_]*' "$ROOT/support/docker-compose.yml" 2>/dev/null \
        | sed 's/^\${//' | sort -u
}

# The versioned env files: everything tracked under .env*, plus an .env* in
# the root that git does not ignore (i.e. .env.example before its first
# commit). `.env` itself and every overlay name are gitignored and therefore
# never scanned here — rule 3 below rejects the overlays outright.
tree_files() {
    {
        git -C "$ROOT" ls-files -- "$FILE_GLOB" 2>/dev/null
        for f in "$ROOT"/$FILE_GLOB; do
            [[ -f "$f" ]] || continue
            b="$(basename "$f")"
            git -C "$ROOT" check-ignore -q "$b" || echo "$b"
        done
    } | sort -u
}

collect_lines() {
    if [[ "$MODE" == "--staged" ]]; then
        # Added lines of staged changes in the versioned env files.
        git -C "$ROOT" diff --cached --unified=0 -- "$FILE_GLOB" 2>/dev/null \
            | grep -E '^\+[^+]' | sed 's/^+//'
    else
        for f in $(tree_files); do
            [[ -f "$ROOT/$f" ]] && cat "$ROOT/$f"
        done
    fi
}

FAIL=0

# --- rule 3: no overlay files ------------------------------------------------
# One file, no overlays: no per-stage and no per-machine second file. It would
# be invisible to everyone reading .env, and neither make nor compose load it.
for f in "$ROOT"/.env*; do
    [[ -f "$f" ]] || continue
    b="$(basename "$f")"
    [[ "$b" == ".env" || "$b" == ".env.example" ]] && continue
    echo "BLOCKED: '${b}' — overlay files are not supported, edit .env"
    FAIL=1
done

COMPOSE_KEYS="$(compose_keys)"

while IFS= read -r line; do
    # Only KEY=VALUE lines; comments and blanks are fine.
    [[ "$line" =~ ^[[:space:]]*# || ! "$line" =~ ^[[:space:]]*[A-Za-z_][A-Za-z0-9_]*= ]] && continue
    key="${line%%=*}"
    key="${key#"${key%%[![:space:]]*}"}"
    value="${line#*=}"
    value="${value%\"}"; value="${value#\"}"
    value="${value%\'}"; value="${value#\'}"

    # A known credential format is a hard stop, whatever the key is called.
    if [[ "$value" =~ $TOKEN_FORMATS ]]; then
        echo "BLOCKED: '${key}' looks like a real credential (known token format)."
        FAIL=1
        continue
    fi

    # --- rule 2: compose-consumed keys stay plaintext ------------------------
    # Independent of the SUSPICIOUS_KEY list: docker compose has no decryption
    # step, so a secret(...) here reaches the container as literal ciphertext.
    if [[ "$value" == secret\(* ]] && grep -qx -- "$key" <<<"$COMPOSE_KEYS"; then
        echo "BLOCKED: '${key}' carries secret(...) but is consumed by docker compose, which cannot decrypt it. Keep it plaintext."
        FAIL=1
        continue
    fi

    [[ "$key" =~ $SUSPICIOUS_KEY ]] || continue
    # Empty, encrypted, or a variable reference — fine.
    [[ -z "$value" || "$value" == secret\(* || "$value" == \$\{* ]] && continue

    if (( ${#value} > MAX_TRIVIAL_LEN )); then
        echo "BLOCKED: '${key}' carries a non-trivial plaintext value in a versioned env file."
        FAIL=1
    fi
done < <(collect_lines)

if (( FAIL )); then
    cat >&2 <<'EOF'

The versioned .env.example must not carry real secrets, and this project has
exactly one config file. Fix one of these ways:
  - encrypt a KERNEL-only value:  make encrypt VALUE="..."  ->  KEY=secret(...)
    (not for keys docker compose reads — compose cannot decrypt)
  - keep the trivial dev default here and inject the real value through the
    process environment (12-factor III: it always wins over the file)
  - delete the overlay file and put its values into .env
False positive on purpose? Re-run with JARDIS_ALLOW_ENV_SECRET=1.
EOF
    exit 1
fi

echo "check-env-secrets: OK"
exit 0
