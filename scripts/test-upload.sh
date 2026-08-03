#!/usr/bin/env bash
# Test upload validation: reject non-image, accept a real image.
set -uo pipefail

BASE="http://127.0.0.1:8123"
COOKIES="$(mktemp)"
cleanup() { pkill -f "php -S 127.0.0.1:8123" 2>/dev/null || true; rm -f "$COOKIES"; }
trap cleanup EXIT

# Build fixtures
echo "this is definitely not an image" > /tmp/notanimage.txt
# 1x1 transparent PNG
printf 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC' | base64 --decode > /tmp/valid.png

php -S 127.0.0.1:8123 -t public/ >/tmp/ina_srv.log 2>&1 &
sleep 3

# Login as admin
token=$(curl -s -c "$COOKIES" -b "$COOKIES" "$BASE/login" \
  | grep -o 'name="_csrf_token" value="[^"]*"' | sed 's/.*value="//;s/"//')
curl -s -o /dev/null -b "$COOKIES" -c "$COOKIES" \
  -d "_username=ina" -d "_password=password" -d "_csrf_token=$token" "$BASE/login"

get_media_token() {
  curl -s -b "$COOKIES" -c "$COOKIES" "$BASE/admin/media/add" \
    | tr '>' '\n' | grep 'media\[_token\]' \
    | grep -oE 'value="[^"]*"' | head -1 | sed 's/value="//;s/"$//'
}

echo "=== 1) Upload NON-image (expect: 200 + validation error, no redirect) ==="
mt=$(get_media_token)
resp=$(curl -s -b "$COOKIES" -o /tmp/resp_bad.html -w "%{http_code}" \
  -F "media[title]=Bad file" \
  -F "media[file]=@/tmp/notanimage.txt;type=text/plain" \
  -F "media[_token]=$mt" \
  "$BASE/admin/media/add")
echo "HTTP $resp"
grep -o 'Format invalide[^<]*\|image valide[^<]*\|uploader une image[^<]*' /tmp/resp_bad.html | head -1 \
  && echo "-> validation error shown ✓" || echo "-> NO validation error found ✗"

echo "=== 2) Upload VALID image (expect: 302 redirect to media index) ==="
mt=$(get_media_token)
resp=$(curl -s -b "$COOKIES" -o /tmp/resp_good.html -w "%{http_code} -> %{redirect_url}" \
  -F "media[title]=Good image" \
  -F "media[file]=@/tmp/valid.png;type=image/png" \
  -F "media[_token]=$mt" \
  "$BASE/admin/media/add")
echo "HTTP $resp"
echo "--- any validation errors in the response? ---"
grep -oE 'Format invalide[^<]*|uploader une image[^<]*|Veuillez sélectionner[^<]*|This value[^<]*|Cette valeur[^<]*|should not be null[^<]*|ne doit pas être vide[^<]*' /tmp/resp_good.html | sort -u | head
echo "--- server log tail ---"
tail -6 /tmp/ina_srv.log




