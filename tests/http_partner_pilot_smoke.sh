#!/bin/sh
# Exercise safe-default public routing, not a live partner integration.
set -eu
ROOT="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
PORT=8853
php -S "127.0.0.1:$PORT" -t "$ROOT/public" "$ROOT/public/router.php" >/dev/null 2>&1 &
PID=$!
trap 'kill "$PID" 2>/dev/null || true' EXIT INT TERM
sleep 1
BASE="http://127.0.0.1:$PORT"; n=0
expect(){ status=$(curl -sS --max-time 8 -o /dev/null -w '%{http_code}' "$BASE$1"); [ "$status" = "$2" ] || { echo "FAIL $1 expected $2 got $status" >&2;exit 1;};n=$((n+1)); }
expect /api/v1/partner/jobs 404
expect /api/v1/partner/usage 404
expect /api/v1/partner/jobs/job_111111111111111111111111 404
expect /api/v1/partner/jobs/job_111111111111111111111111/close 404
expect /src/PartnerMysqlStore.php 404
expect /src/PartnerApi.php 404
expect /src/PartnerTokenAdmin.php 404
expect /database/001_partner_pilot.mysql.sql 404
expect /scripts/partner_tokens.php 404
expect /.env 404
expect /employers/register 503
expect /developers 200
expect /owner 404
expect /jobs 200
expect /robots.txt 200
curl -sS --max-time 8 -i "$BASE/api/v1/partner/jobs" | grep -qi 'X-Content-Type-Options: nosniff' || { echo 'FAIL security header absent';exit 1; };n=$((n+1))
printf 'PASS %s partner pilot HTTP safe-default checks\n' "$n"
