#!/usr/bin/env bash
# End-to-end test of guest management against the running Symfony server.
set -uo pipefail
BASE="https://127.0.0.1:8001"
ADMIN_CK="$(mktemp)"; GUEST_CK="$(mktemp)"
trap 'rm -f "$ADMIN_CK" "$GUEST_CK"' EXIT

G_NAME="Guest Test"; G_MAIL="guest.test@example.com"; G_PASS="guestpass"

csrf_login() { # $1 = cookie jar
  curl -sk -c "$1" -b "$1" "$BASE/login" | tr '>' '\n' | grep '_csrf_token' \
    | grep -oE 'value="[^"]*"' | head -1 | sed 's/value="//;s/"$//'
}
login() { # $1=jar $2=user $3=pass -> prints redirect url
  local t; t=$(csrf_login "$1")
  curl -sk -o /dev/null -w "%{redirect_url}" -b "$1" -c "$1" \
    -d "_username=$2" -d "_password=$3" -d "_csrf_token=$t" "$BASE/login"
}

echo "=== admin login ==="
login "$ADMIN_CK" "ina@zaoui.com" "password" >/dev/null
curl -sk -o /dev/null -w "GET /admin/guest -> HTTP %{http_code}\n" -b "$ADMIN_CK" "$BASE/admin/guest"

echo "=== add a guest ==="
gt=$(curl -sk -b "$ADMIN_CK" -c "$ADMIN_CK" "$BASE/admin/guest/add" | tr '>' '\n' | grep 'guest\[_token\]' | grep -oE 'value="[^"]*"' | head -1 | sed 's/value="//;s/"$//')
curl -sk -o /dev/null -w "POST add -> HTTP %{http_code} -> %{redirect_url}\n" -b "$ADMIN_CK" \
  -F "guest[name]=$G_NAME" -F "guest[email]=$G_MAIL" -F "guest[description]=desc" \
  -F "guest[plainPassword]=$G_PASS" -F "guest[_token]=$gt" "$BASE/admin/guest/add"
gid=$(php bin/console dbal:run-sql "SELECT id FROM \`user\` WHERE email='$G_MAIL'" 2>/dev/null | grep -oE '[0-9]+' | head -1)
echo "created guest id=$gid"
php bin/console dbal:run-sql "SELECT admin, active, CHAR_LENGTH(password) AS pwlen FROM \`user\` WHERE email='$G_MAIL'" 2>/dev/null | grep -E '[0-9]'

echo "=== guest can log in (active) ==="
r=$(login "$GUEST_CK" "$G_MAIL" "$G_PASS"); echo "guest login redirect -> $r  (expect .../ )"

echo "=== admin blocks the guest ==="
curl -sk -o /dev/null -w "toggle -> HTTP %{http_code}\n" -b "$ADMIN_CK" "$BASE/admin/guest/toggle/$gid"
php bin/console dbal:run-sql "SELECT active FROM \`user\` WHERE email='$G_MAIL'" 2>/dev/null | grep -E '[0-9]' | sed 's/^/active now: /'

echo "=== blocked guest cannot log in ==="
rm -f "$GUEST_CK"
r=$(login "$GUEST_CK" "$G_MAIL" "$G_PASS"); echo "blocked login redirect -> $r  (expect .../login )"

echo "=== blocked guest hidden from front /guests ==="
curl -sk "$BASE/guests" | grep -q "$G_NAME" && echo "-> STILL listed FAIL" || echo "-> hidden OK"

echo "=== delete the guest ==="
curl -sk -o /dev/null -w "delete -> HTTP %{http_code}\n" -b "$ADMIN_CK" "$BASE/admin/guest/delete/$gid"
echo -n "remaining rows: "; php bin/console dbal:run-sql "SELECT COUNT(*) FROM \`user\` WHERE email='$G_MAIL'" 2>/dev/null | grep -oE '[0-9]+' | head -1

