#!/bin/bash
# Guardrail: versioned env files must never carry real secrets. The stack
# .env and config/env/* are committed by design (trivial defaults only);
# real values go encrypted as secret(...) or into gitignored *.local files.
# Modes: --staged scans lines being committed (pre-commit hook),
#        --tree scans the full tracked files (CI).
# Override for a deliberate false positive: JARDIS_ALLOW_ENV_SECRET=1 git commit

set -u

MODE="${1:---staged}"
FILES=".env config/env/*"
SUSPICIOUS_KEY='(_PASSWORD|_SECRET|_TOKEN|_KEY|_PAT|_CREDENTIALS)$'
# Well-known credential formats — blocked in ANY value, regardless of key.
TOKEN_FORMATS='ghp_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,}|glpat-[A-Za-z0-9_-]{15,}|sk-[A-Za-z0-9_-]{20,}|AKIA[0-9A-Z]{16}|xox[abprs]-[A-Za-z0-9-]{10,}|eyJ[A-Za-z0-9_-]{20,}\.eyJ|-----BEGIN .*PRIVATE KEY'
MAX_TRIVIAL_LEN=15

if [[ "${JARDIS_ALLOW_ENV_SECRET:-0}" == "1" && "$MODE" == "--staged" ]]; then
    echo "check-env-secrets: skipped (JARDIS_ALLOW_ENV_SECRET=1)"
    exit 0
fi

collect_lines() {
    if [[ "$MODE" == "--staged" ]]; then
        # Added lines of staged changes in the versioned env files.
        git diff --cached --unified=0 -- $FILES 2>/dev/null \
            | grep -E '^\+[^+]' | sed 's/^+//'
    else
        for f in $(git ls-files $FILES 2>/dev/null); do
            cat "$f"
        done
    fi
}

FAIL=0
while IFS= read -r line; do
    # Only KEY=VALUE lines; comments and blanks are fine.
    [[ "$line" =~ ^[[:space:]]*# || ! "$line" =~ ^[[:space:]]*[A-Za-z_][A-Za-z0-9_]*= ]] && continue
    key="${line%%=*}"
    value="${line#*=}"
    value="${value%\"}"; value="${value#\"}"
    value="${value%\'}"; value="${value#\'}"

    # A known credential format is a hard stop, whatever the key is called.
    if [[ "$value" =~ $TOKEN_FORMATS ]]; then
        echo "BLOCKED: '${key}' looks like a real credential (known token format)."
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

Versioned env files (.env, config/env/*) must not carry real secrets.
Fix one of these ways:
  - encrypt the value:  make encrypt VALUE="..."  ->  KEY=secret(...)
  - move it to a gitignored local file (config/env/.env.<topic>.local)
  - keep the trivial dev default and inject the real value at runtime
False positive on purpose? Re-run with JARDIS_ALLOW_ENV_SECRET=1.
EOF
    exit 1
fi

echo "check-env-secrets: OK"
exit 0
