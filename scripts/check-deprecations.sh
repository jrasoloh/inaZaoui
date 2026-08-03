#!/usr/bin/env bash
# Exercise all routes (front + authenticated admin) and report runtime deprecations.
set -euo pipefail

BASE="http://127.0.0.1:8123"
COOKIES="$(mktemp)"
LOG="var/log/dev.log"

cleanup() {
  pkill -f "php -S 127.0.0.1:8123" 2>/dev/null || true
  rm -f "$COOKIES"
}
trap cleanup EXIT

# Fresh log + server
: > "$LOG"
php -S 127.0.0.1:8123 -t public/ >/tmp/ina_srv.log 2>&1 &
sleep 3

# Public routes
for u in / /guests /portfolio /guest/1 /about /login; do
  code=$(curl -s -o /dev/null -w "%{http_code}" -c "$COOKIES" -b "$COOKIES" "$BASE$u")
  echo "GET $u -> $code"
done

# Login (stateful CSRF)
token=$(curl -s -c "$COOKIES" -b "$COOKIES" "$BASE/login" \
  | grep -o 'name="_csrf_token" value="[^"]*"' | sed 's/.*value="//;s/"//')
code=$(curl -s -o /dev/null -w "%{http_code}" -b "$COOKIES" -c "$COOKIES" \
  -d "_username=ina" -d "_password=password" -d "_csrf_token=$token" "$BASE/login")
echo "POST /login -> $code"

# Admin routes (authenticated)
for u in /admin/album /admin/media /admin/album/add /admin/media/add; do
  code=$(curl -s -o /dev/null -w "%{http_code}" -b "$COOKIES" "$BASE$u")
  echo "GET $u -> $code"
done

echo "=== deprecation count ==="
grep -ci deprecat "$LOG" || true
echo "=== unique deprecation messages ==="
grep -i deprecat "$LOG" | sed -E 's/^\[[^]]*\] //' | sort -u || true

