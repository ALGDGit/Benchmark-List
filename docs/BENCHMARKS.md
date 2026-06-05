# Running benchmarks

## Quick start (Docker)

```bash
docker compose up -d
docker compose exec -u www-data web php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -u www-data web php bin/console app:seed-database --force
docker compose exec -u www-data web php bin/console app:index-elasticsearch --reset

# Run all scenarios → benchmarks/results/latest.json + .md
./benchmarks/run.sh          # Linux/macOS/Git Bash
# or
.\benchmarks\run.ps1         # Windows PowerShell
```

## Scenarios (`benchmarks/scenarios.json`)

| ID | What it measures |
|----|------------------|
| `varnish_warm` | Category API through Varnish after warmup (HIT-heavy) |
| `varnish_cold` | Forced MISS (unique query param) — origin + DB |
| `origin_direct` | Same API bypassing Varnish (`http://web/...`) |
| `search_elasticsearch` | Autocomplete — PHP + ES, no edge cache |
| `homepage_html` | Twig shell only |

## Single scenario

```bash
docker compose exec -u www-data web php bin/console app:benchmark \
  --scenario=varnish_warm \
  --base-url=http://varnish
```

## Ad-hoc URL

```bash
docker compose exec -u www-data web php bin/console app:benchmark \
  --url=http://varnish/api/categories/sopas/items?page=1 \
  --requests=300 -c 15 -w 5
```

## Apache Bench (optional)

```bash
./benchmarks/run-ab.sh http://localhost:8080
```

Requires `ab` (apache2-utils package).

## Comparing configurations

| Comparison | How |
|------------|-----|
| **With vs without Varnish** | `varnish_warm` vs `origin_direct` |
| **HIT vs MISS** | `varnish_warm` vs `varnish_cold` |
| **With vs without Elasticsearch** | Set `FEATURE_ELASTICSEARCH=0`, restart `web`, re-run `search_elasticsearch` |
| **With vs without Redis activity** | Set `FEATURE_REDIS_ACTIVITY=0`, compare `/panel` + minor Symfony cache behavior |

## Output format

**JSON** (`benchmarks/results/latest.json`):

```json
{
  "generated_at": "2026-06-04T...",
  "scenarios": [
    {
      "scenario_id": "varnish_warm",
      "rps": 842.1,
      "latency_ms": { "p50": 12.4, "p95": 28.1, "p99": 45.0 }
    }
  ]
}
```

**Markdown** — summary table for README / portfolio.

## CI

GitHub Actions job `benchmark` runs on `workflow_dispatch` and pushes to `master`. Download artifacts from the Actions tab.

## Interpreting results

- **RPS** — requests per second (successful / duration).
- **p50 / p95 / p99** — latency percentiles in milliseconds.
- Expect **large gap** between `varnish_warm` and `origin_direct` when cache is hot.
- `search_elasticsearch` reflects ES + PHP cost; scale ES JVM if p95 is high.

Re-run on your machine after seeding — numbers vary by CPU and Docker Desktop overhead.

## Screenshots for portfolio

Capture and save under `docs/images/`:

1. Homepage with **Varnish: HIT** badges on list cards.
2. Same page after admin item create — **MISS** then **HIT**.
3. `/panel` activity log showing BAN + API origin lines.
4. Terminal output of `app:benchmark --all`.

See [docs/images/README.md](images/README.md).
