#!/usr/bin/env bash
set -euo pipefail
# Retired for safety. This legacy deployment targeted two AZARI domains and
# omitted Phase Four migrations. Never use it for resavar.com.
echo "BLOCKED: legacy multi-domain deploy removed. See docs/RESAVAR_DEPLOYMENT_HANDOFF.md for a manual release checklist." >&2
exit 2
