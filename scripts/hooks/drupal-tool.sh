#!/usr/bin/env bash
# pre-commit helper: runs a tool from drupal/vendor/bin with the host PHP when
# available (fast), otherwise inside DDEV. File arguments are made relative to
# drupal/.
set -euo pipefail

tool="$1"
shift
cd "$(dirname "$0")/../../drupal"

if [ ! -x "vendor/bin/${tool}" ]; then
  echo "drupal/vendor/bin/${tool} is missing: run 'make setup' first." >&2
  exit 1
fi

args=()
for arg in "$@"; do
  args+=("${arg#drupal/}")
done

if command -v php >/dev/null 2>&1; then
  exec "vendor/bin/${tool}" "${args[@]}"
fi
exec ddev exec "vendor/bin/${tool}" "${args[@]}"
