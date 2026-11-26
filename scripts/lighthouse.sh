#!/usr/bin/env bash
# Runs Lighthouse (mobile emulation, simulated throttling) on key pages and
# prints the category scores. HTML and JSON reports go to reports/lighthouse/.
#
# Usage: scripts/lighthouse.sh [base-url]
# Requires Node.js and Google Chrome on the host. Only published pages can be
# measured meaningfully: draft pages answer 403 to anonymous visitors.
set -euo pipefail

BASE_URL="${1:-https://drupal-ai-portfolio.ddev.site}"
LIGHTHOUSE_VERSION="13.6.0"
OUT_DIR="reports/lighthouse"
PAGES=("/" "/en" "/a-propos" "/mentions-legales" "/en/privacy-policy" "/services" "/contact")

mkdir -p "$OUT_DIR"
printf '%-22s %6s %6s %6s %6s\n' "page" "perf" "a11y" "bp" "seo"
for path in "${PAGES[@]}"; do
  url="${BASE_URL}${path}"
  name="$(printf '%s' "$path" | sed -e 's#^/##' -e 's#/#-#g')"
  name="${name:-home}"
  # Warm Drupal's page cache: measure what a visitor gets, not a cold rebuild.
  curl --insecure --silent --output /dev/null "$url"
  npx --yes "lighthouse@${LIGHTHOUSE_VERSION}" "$url" --quiet \
    --chrome-flags="--headless=new --ignore-certificate-errors" \
    --only-categories=performance,accessibility,best-practices,seo \
    --output=json --output=html --output-path="${OUT_DIR}/${name}" >/dev/null
  node -e '
    const r = require(process.argv[1]);
    const s = (k) => Math.round(r.categories[k].score * 100);
    console.log(process.argv[2].padEnd(22) + [s("performance"), s("accessibility"), s("best-practices"), s("seo")].map((v) => String(v).padStart(7)).join(""));
  ' "$(pwd)/${OUT_DIR}/${name}.report.json" "$path"
done
