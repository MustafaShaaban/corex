#!/usr/bin/env bash
# Shared-host dist builder (spec 061). Thin wrapper over the portable Node builder so the documented
# bash entry point exists on any platform. Usage:
#   scripts/build-shared-host-dist.sh [--client=acme] [--dry-run]
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
exec node "$DIR/scripts/build-shared-host-dist.mjs" "$@"
