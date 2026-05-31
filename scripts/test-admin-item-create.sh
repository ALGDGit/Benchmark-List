#!/bin/sh
set -e
BASE="${1:-http://web}"
CJ=/tmp/test-cj.$$
trap 'rm -f "$CJ" /tmp/new-form.html /tmp/out.html' EXIT

BEFORE=$(php bin/console doctrine:query:sql 'SELECT COUNT(*) AS c FROM item' 2>/dev/null | grep -E '^[[:space:]]*[0-9]+' | tr -d ' ')

curl -s -c "$CJ" -b "$CJ" "$BASE/admin/items/new" -o /tmp/new-form.html
TOKEN=$(grep -o 'name="item\[_token\]"[^>]*value="[^"]*"' /tmp/new-form.html | sed 's/.*value="\([^"]*\)".*/\1/' | head -n1)
CAT=$(grep -o 'option value="[0-9]*">Category' /tmp/new-form.html | head -n1 | sed 's/option value="\([0-9]*\)".*/\1/')

if [ -z "$TOKEN" ] || [ -z "$CAT" ]; then
  echo "FAIL: could not parse form (token=$TOKEN cat=$CAT)"
  exit 1
fi

NAME="Test item $(date +%s)"
CODE=$(curl -s -c "$CJ" -b "$CJ" -L -o /tmp/out.html -w "%{http_code}" -X POST "$BASE/admin/items/new" \
  --data-urlencode "item[name]=$NAME" \
  --data-urlencode "item[category]=$CAT" \
  --data-urlencode "item[_token]=$TOKEN")

AFTER=$(php bin/console doctrine:query:sql 'SELECT COUNT(*) AS c FROM item' 2>/dev/null | grep -E '^[[:space:]]*[0-9]+' | tr -d ' ')

echo "POST HTTP $CODE token=$TOKEN before=$BEFORE after=$AFTER"

if [ "$AFTER" -le "$BEFORE" ]; then
  echo "FAIL: item count did not increase"
  grep -i "error\|invalid\|csrf" /tmp/out.html 2>/dev/null | head -3 || true
  exit 1
fi

echo "OK: item created"
