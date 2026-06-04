#!/usr/bin/env sh
set -eu

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "==> Waiting for web container..."
docker compose exec -T web curl -sf http://127.0.0.1/health.php >/dev/null

echo "==> Warming Varnish cache (category API)..."
docker compose exec -T web curl -sf "http://varnish/api/categories/sopas/items?page=1&limit=10" >/dev/null || true

echo "==> Running benchmarks (all scenarios)..."
docker compose exec -u www-data -T web php bin/console app:benchmark --all \
  --base-url=http://varnish \
  --output=benchmarks/results/latest

echo "==> Done. See benchmarks/results/latest.md"
