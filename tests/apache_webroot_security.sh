#!/usr/bin/env bash
# Isolated GitHub runner only. Tests Apache .htaccess, NOT real Hostinger PHP-FPM.
set -euo pipefail

if ! env | grep -qx 'GITHUB_ACTIONS=true'; then
  echo "Refusing to modify an unknown Apache host; only run on disposable GitHub CI." >&2
  exit 2
fi

sudo apt-get update -qq
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq apache2 >/dev/null
sudo a2enmod rewrite headers >/dev/null
sudo sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

WEB=/var/www/html
sudo mkdir -p "$WEB"/{assets,src,storage,config,scripts,tests,docs,admin,internal,.git}
sudo cp public/.htaccess "$WEB/.htaccess"
printf 'NEW_PHP_HOME\n' | sudo tee "$WEB/index.php" >/dev/null
printf 'OLD_STATIC_HOME\n' | sudo tee "$WEB/index.html" >/dev/null
printf 'body { color: black; }\n' | sudo tee "$WEB/assets/site.css" >/dev/null
printf 'User-agent: *\nDisallow: /\n' | sudo tee "$WEB/robots.txt" >/dev/null
printf 'PRIVATE\n' | sudo tee "$WEB/.env" "$WEB/.git/config" "$WEB/src/Jobs.php" \
    "$WEB/storage/jobs.json" "$WEB/config/provider_policy.json" "$WEB/scripts/sync_jobs.php" \
    "$WEB/tests/php_tests.php" "$WEB/docs/private.md" "$WEB/backup.zip" \
    "$WEB/old.tar.gz" "$WEB/admin/export.csv" "$WEB/internal/key.pem" >/dev/null
sudo apache2ctl configtest
sudo service apache2 restart >/dev/null

BASE=http://127.0.0.1
for i in $(seq 1 20); do
  if curl --silent --fail --max-time 2 -o /dev/null "$BASE/"; then break; fi
  sleep 1
done

count=0
probe() {
  local url="$1" expected="$2" status
  status=$(curl --silent --max-time 6 --output /dev/null --write-out "%{http_code}" "$BASE$url")
  if [[ "$status" != "$expected" ]]; then
    echo "FAIL $url expected HTTP $expected got $status" >&2
    exit 1
  fi
  count=$((count+1))
  echo "PASS HTTP $expected $url"
}
probe / 200
root=$(curl -fsS "$BASE/")
[[ "$root" == *NEW_PHP_HOME* ]] || { echo "FAIL root did not prefer index.php" >&2; exit 1; }
count=$((count+1)); echo "PASS DirectoryIndex index.php wins over retained index.html"
probe /index.html 200
probe /assets/site.css 200
probe /robots.txt 200
probe /.env 403
probe /.git/config 403
probe /src/Jobs.php 403
probe /storage/jobs.json 403
probe /config/provider_policy.json 403
probe /scripts/sync_jobs.php 403
probe /tests/php_tests.php 403
probe /docs/private.md 403
probe /backup.zip 403
probe /old.tar.gz 403
probe /admin/export.csv 403
probe /internal/key.pem 403
probe /assets/ 403
probe /tools 200
fallback=$(curl -fsS "$BASE/tools")
[[ "$fallback" == *NEW_PHP_HOME* ]] || { echo "FAIL rewrite fallback" >&2; exit 1; }
count=$((count+1)); echo "PASS rewrite fallback routes through PHP entrypoint"
headers=$(curl -fsSI "$BASE/")
grep -qi '^X-Frame-Options: DENY' <<< "$headers" || { echo "FAIL missing frame denial" >&2; exit 1; }
count=$((count+1)); echo "PASS X-Frame-Options: DENY"
grep -qi '^X-Content-Type-Options: nosniff' <<< "$headers" || { echo "FAIL missing nosniff" >&2; exit 1; }
count=$((count+1)); echo "PASS X-Content-Type-Options: nosniff"
echo "$count isolated Apache webroot security checks passed"
echo "NOTE: Apache container tests do not prove Hostinger's live LiteSpeed/Apache, PHP-FPM, open_basedir, backups or actual website."
