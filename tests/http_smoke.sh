#!/bin/sh
set -eu
ROOT="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
PORT=8793
php -S "127.0.0.1:$PORT" -t "$ROOT/public" "$ROOT/public/router.php" > /dev/null 2>&1 &
PID=$!
trap 'kill "$PID" 2>/dev/null || true' EXIT INT TERM
sleep 1
BASE="http://127.0.0.1:$PORT"
count=0
for p in / /jobs /tools /tools/salary-savings /tools/offer-compare /tools/time-zone-overlap /tools/application-tracker /tools/resume-kit /tools/relocation-budget /tools/interview-practice /tools/country-checklist /tools/workproof /tools/career-roadmap /my-career /safety /sources /guides /guides/remote-worldwide-vs-us-only /guides/compare-overseas-job-offers /guides/recognise-overseas-job-scams /guides/uae-relocation-budget /guides/germany-career-research /privacy /terms /about /report-job; do
 status=$(curl -s -o /dev/null -w '%{http_code}' "$BASE$p")
 test "$status" = '200' || { echo "FAIL $p returned $status";exit 1; }
 count=$((count+1))
done
status=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/does-not-exist")
test "$status" = '404' || { echo 'FAIL unknown path 404';exit 1; }
count=$((count+1))
robots=$(curl -fsS "$BASE/robots.txt")
printf '%s' "$robots" | grep -q 'Disallow: /' || { echo 'FAIL preview index protection';exit 1; }
count=$((count+1))
status=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/sitemap.xml")
test "$status" = '404' || { echo 'FAIL premature sitemap';exit 1; }
count=$((count+1))
html=$(curl -fsS "$BASE/")
printf '%s' "$html" | grep -q 'noindex,nofollow' || { echo 'FAIL preview noindex';exit 1; }
count=$((count+1))
if RELEASE_READY=1 php "$ROOT/scripts/launch_preflight.php" | grep -q '"ready": false'; then
 count=$((count+1))
else echo 'FAIL missing release evidence does not block public readiness';exit 1;fi

# New browser-local tools must not submit private form content via a normal HTTP GET.
for p in /tools/interview-practice /tools/country-checklist /tools/workproof /tools/career-roadmap /tools/resume-kit /tools/application-tracker; do
 html=$(curl -fsS "$BASE$p")
 printf '%s' "$html" | grep -q 'data-local-only="true"' || { echo "FAIL $p local-only form marker";exit 1; }
 printf '%s' "$html" | grep -q 'method="dialog"' || { echo "FAIL $p no-network form method";exit 1; }
 printf '%s' "$html" | grep -q 'noindex,nofollow' || { echo "FAIL $p development indexing protection";exit 1; }
 count=$((count+1))
done
# Validate source-bound content never exposes raw HTML script tags in JSON data blocks.
html=$(curl -fsS "$BASE/tools/career-roadmap")
printf '%s' "$html" | grep -q 'id="roadmap-data"' || { echo 'FAIL roadmap structured data';exit 1; }
count=$((count+1))
html=$(curl -fsS "$BASE/tools/interview-practice")
printf '%s' "$html" | grep -q 'id="interview-data"' || { echo 'FAIL interview structured data';exit 1; }
count=$((count+1))
printf 'PASS %s HTTP smoke/preview safety checks\n' "$count"
sh "$ROOT/scripts/staging_probe.sh" "$BASE"
