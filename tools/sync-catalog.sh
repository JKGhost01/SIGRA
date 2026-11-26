#!/usr/bin/env bash
# CLI only: use the same PHP binary and account as the web installation.
set -euo pipefail
sigra_root="$(cd -- "$(dirname -- "$0")/.." && pwd)"
php_binary="${1:-/usr/bin/php}"
cd "$sigra_root"
mkdir -p writable/logs
# Daily files keep cron output separate from HTTP logs and preserve exit codes.
exec "$php_binary" spark sigra:sync >> "writable/logs/sync-$(date -u +%F).log" 2>&1
