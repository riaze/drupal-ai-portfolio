#!/usr/bin/env bash
# Fails when the active configuration differs from config/sync.
#
# Run from the Composer root (drupal/) inside DDEV (make config-check) or in
# CI after installing the site from config/sync.
set -euo pipefail
cd "$(dirname "$0")/.."

differences="$(vendor/bin/drush config:status --state='Only in DB,Only in sync dir,Different' --format=list 2>/dev/null || true)"
if [ -n "$differences" ]; then
  echo "The active configuration differs from config/sync:" >&2
  echo "$differences" | sed 's/^/  - /' >&2
  echo "Export it with 'make export-config' (or import it with 'drush config:import') and commit." >&2
  exit 1
fi
echo "Configuration matches config/sync."
