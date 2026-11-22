#!/bin/sh
# Read-only, CLI staging probe. During an UNAPPROVED release expected indexing policy is noindex.
set -eu
BASE=${1:-}
if [ -z "$BASE" ]; then echo 'Usage: sh scripts/staging_probe.sh https://authorised-staging-domain.example (or http://127.0.0.1:port for local testing)' >&2;exit 2;fi
case "$BASE" in http://*|https://*) ;; *) echo 'Expected http(s) URL' >&2;exit 2;;esac
case "$BASE" in *'@'*|*'?'*|*'#'*) echo 'Staging URL must not contain credentials, query, or fragment' >&2;exit 2;;esac
BASE=${BASE%/}
count=0
status() { curl --max-time 12 --silent --show-error --output /dev/null --write-out '%{http_code}' "$BASE$1";}
expect() { actual=$(status "$1");[ "$actual" = "$2" ] || { echo "FAIL $1 expected $2, got $actual" >&2;exit 1;};count=$((count+1));}
for path in / /jobs /tools /tools/resume-kit /tools/relocation-budget /tools/application-tracker /privacy /safety /sources;do expect "$path" 200;done
private() { actual=$(status "$1"); case "$actual" in 403|404) ;; *) echo "FAIL $1 private path returned $actual" >&2;exit 1;; esac; count=$((count+1)); }
for path in /.htaccess /.env /.git/config /storage/jobs.json /config/provider_policy.json /scripts/sync_jobs.php /src/Jobs.php /tests/php_tests.php /assets/.env; do private "$path"; done
expect /sitemap.xml 404
headers=$(curl --max-time 12 --silent --show-error --dump-header - --output /dev/null "$BASE/")
printf '%s' "$headers" | grep -qi '^X-Frame-Options: DENY' || { echo 'FAIL missing anti-framing response header';exit 1;};count=$((count+1))
printf '%s' "$headers" | grep -qi '^Content-Security-Policy:' || { echo 'FAIL missing CSP response header';exit 1;};count=$((count+1))
robots=$(curl --max-time 12 --silent --show-error "$BASE/robots.txt")
printf '%s' "$robots" | grep -q 'Disallow: /' || { echo 'FAIL preview robots policy';exit 1;};count=$((count+1))
html=$(curl --max-time 12 --silent --show-error "$BASE/")
printf '%s' "$html" | grep -q 'noindex,nofollow' || { echo 'FAIL preview HTML robots directive';exit 1;};count=$((count+1))
printf 'PASS %s staging PREVIEW HTTP/security probes\n' "$count"
printf '%s\n' 'Note: this tests HTTP response safety only; browser E2E, Hostinger plan, real feeds, copyright/licence, DB backup encryption and production release remain unverified.'
