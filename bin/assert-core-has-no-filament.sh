#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if grep -rnE '(Filament|Livewire)\\' src/ --include='*.php' | grep -v '^src/Filament/'; then
    echo "ERROR: Filament or Livewire referenced outside src/Filament/."
    exit 1
fi

echo "OK: core is free of Filament and Livewire."
