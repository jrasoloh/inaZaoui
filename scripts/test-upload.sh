#!/usr/bin/env bash
# Full media lifecycle test against the running Symfony server:
#  - reject a non-image upload
#  - accept a valid image (file stored in the configured uploads dir)
#  - delete it from the admin and check the file is physically removed
set -uo pipefail

BASE="https://127.0.0.1:8001"
COOKIES="$(mktemp)"
UPLOADS_DIR="$(php bin/console debug:container --parameters 2>/dev/null | awk '/app.uploads_dir/{print $NF}')"
cleanup() { rm -f "$COOKIES"; }
trap cleanup EXIT

# Fixtures
echo "not an image" > /tmp/notanimage.txt
php -r 'file_put_contents("/tmp/valid.png", base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC"));'

# Login (email identifier + stateful CSRF)
tok=$(curl -sk -c "$COOKIES" -b "$COOKIES" "$BASE/login" \
  | tr '>' '\n' | grep '_csrf_token' | grep -oE 'value="[^"]*"' | head -1 | sed 's/value="//;s/"$//')
curl -sk -o /dev/null -b "$COOKIES" -c "$COOKIES" \
  -d "_username=ina@zaoui.com" -d "_password=password" -d "_csrf_token=$tok" "$BASE/login"

media_token() {
  curl -sk -b "$COOKIES" -c "$COOKIES" "$BASE/admin/media/add" \
    | tr '>' '\n' | grep 'media\[_token\]' | grep -oE 'value="[^"]*"' | head -1 | sed 's/value="//;s/"$//'
}

echo "=== 1) Upload NON-image (expect 200 + validation error) ==="
mt=$(media_token)
curl -sk -b "$COOKIES" -o /tmp/resp_bad.html -w "HTTP %{http_code}\n" \
  -F "media[title]=Bad file" -F "media[file]=@/tmp/notanimage.txt;type=text/plain" -F "media[_token]=$mt" \
  "$BASE/admin/media/add"
grep -oE 'Format invalide[^<]*' /tmp/resp_bad.html | head -1 && echo "-> rejected OK" || echo "-> NOT rejected FAIL"

echo "=== 2) Upload VALID image (expect 302) ==="
mt=$(media_token)
curl -sk -b "$COOKIES" -o /dev/null -w "HTTP %{http_code} -> %{redirect_url}\n" \
  -F "media[title]=__lifecycle_test__" -F "media[file]=@/tmp/valid.png;type=image/png" -F "media[_token]=$mt" \
  "$BASE/admin/media/add"

# Find the media we just created (by its unique title)
row=$(php bin/console dbal:run-sql "SELECT id, path FROM media WHERE title='__lifecycle_test__' ORDER BY id DESC LIMIT 1" 2>/dev/null)
mid=$(echo "$row" | grep -oE '[0-9]+' | head -1)
mpath=$(echo "$row" | grep -oE 'uploads/[^ ]+' | head -1)
echo "created media id=$mid path=$mpath"
[ -f "$UPLOADS_DIR/$(basename "$mpath")" ] && echo "-> file present on disk OK" || echo "-> file MISSING FAIL"

echo "=== 3) Delete from admin (expect file physically removed) ==="
curl -sk -b "$COOKIES" -o /dev/null -w "GET delete/$mid -> HTTP %{http_code} -> %{redirect_url}\n" \
  "$BASE/admin/media/delete/$mid"
[ -f "$UPLOADS_DIR/$(basename "$mpath")" ] && echo "-> file STILL present FAIL" || echo "-> file removed from disk OK"
echo -n "-> remaining rows with test title: "
php bin/console dbal:run-sql "SELECT COUNT(*) AS remaining FROM media WHERE title='__lifecycle_test__'" 2>/dev/null | grep -oE '[0-9]+' | head -1

