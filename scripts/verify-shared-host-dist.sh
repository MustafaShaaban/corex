#!/usr/bin/env bash
# Verify a built shared-host dist/ tree (spec 061). Thin wrapper over the portable Node verifier.
#   scripts/verify-shared-host-dist.sh
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
exec node "$DIR/scripts/verify-shared-host-dist.mjs"
