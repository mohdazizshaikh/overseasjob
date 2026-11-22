#!/bin/sh
# Safe defaults: no employer identity state, tokens or private SQL exposed before real staging.
set -eu
ROOT="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
PORT=8856
php -S "127.0.0.1:$PORT" -t "$ROOT/public" "$ROOT/public/router.php" >/dev/null 2>&1 &
PID=$!
trap 'kill "$PID" 2>/dev/null || true' EXIT INT TERM
sleep 1
BASE="http://127.0.0.1:$PORT"; n=0
expect(){ got=$(curl -sS --max-time 8 -o /dev/null -w '%{http_code}' "$BASE$1"); [ "$got" = "$2" ] || { echo "FAIL $1 expected $2 got $got" >&2;exit 1;}; n=$((n+1));}
expect /employers 503
expect /employers/login 503
expect /employers/verify 503
expect /employers/dashboard 503
expect /employers/jobs/new 503
expect /employers/jobs/job_aaaaaaaaaaaaaaaaaaaaaaaa/edit 503
expect /employers/jobs/job_aaaaaaaaaaaaaaaaaaaaaaaa/close 503
expect /jobs/direct/job_aaaaaaaaaaaaaaaaaaaaaaaa 404
expect /src/EmployerReview.php 404
expect /src/RecruiterPortal.php 404
expect /src/EmployerDomainProof.php 404
expect /src/EmployerDomainMysql.php 404
expect /database/004_employer_domain_proof.mysql.sql 404
expect /scripts/employer_domain.php 404
expect /database/002_employer_review.mysql.sql 404
expect /database/003_recruiter_accounts.mysql.sql 404
expect /scripts/employer_review.php 404
expect /scripts/recruiter_invites.php 404
expect /tests/mysql_integration_tests.php 404
expect /jobs 200
expect /owner 404
expect /.env 404
curl -sS "$BASE/employers/dashboard" | grep -q 'Employer posting is not open' || { echo 'FAIL invitation workflow appears live';exit 1; }; n=$((n+1))
curl -sSI "$BASE/jobs/direct/job_aaaaaaaaaaaaaaaaaaaaaaaa" | grep -qi 'X-Content-Type-Options: nosniff' || { echo 'FAIL secure response header missing';exit 1; };n=$((n+1))
printf 'PASS %s employer review/dashboard HTTP security gate checks\n' "$n"
