#!/usr/bin/env bash
set -euo pipefail

SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/src"

FORBIDDEN='fromShellCommandline|shell_exec|passthru|proc_open|\bsystem\s*\(|\bexec\s*\('

if grep -rInE "$FORBIDDEN" "$SRC_DIR"; then
    echo ""
    echo "ERROR: forbidden shell execution primitive found in src/."
    echo "All execution must go through Symfony Process with an argument array."
    exit 1
fi

echo "OK: no shell execution primitives in src/."
