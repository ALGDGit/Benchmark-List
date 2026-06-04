#!/usr/bin/env sh
# Optional: Apache Bench scenarios (requires ab in PATH or Docker profile tools)
set -eu

BASE="${1:-http://localhost:8080}"
URL="${BASE}/api/categories/sopas/items?page=1&limit=10"

echo "Apache Bench — warm cache then load test"
echo "URL: $URL"
curl -sf "$URL" >/dev/null
curl -sf "$URL" >/dev/null

if command -v ab >/dev/null 2>&1; then
  ab -n 1000 -c 25 -H "Accept: application/json" "$URL"
else
  echo "ab not found. Install apache2-utils or run: benchmarks/run.sh (Symfony benchmark command)"
  exit 1
fi
