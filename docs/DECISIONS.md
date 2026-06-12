# Technical decisions

## Why Varnish in front of Symfony?

- **Problem:** Homepage loads 10 category lists via JSON API. Each request runs Doctrine queries. Under concurrency, PHP-FPM workers saturate quickly.
- **Solution:** Cache JSON at the edge. Varnish serves ~1 KB responses from memory; origin only on MISS.
- **Trade-off:** Stale data until invalidation. Acceptable for catalog-style lists with explicit purge on admin writes.

## Why tag-based BAN instead of URL purge?

Items belong to **multiple categories** and appear on **multiple homepage slots**. Purging by URL would miss objects or require many requests.

Tags on each cached object:

- `#category-{slug}#` — full category (all pages)
- `#category-{slug}-page-{n}#` — single page
- `#list-{slot}#` / `#list-{slot}-page-{n}#` — homepage slot equivalents

**Beginning** position → purge full category + slot tags.  
**End** position → purge **last page only** (cheaper when appending).

### `#` delimiters

Varnish ban expressions use POSIX regex. Space-separated tags caused false matches (`list-1` vs `list-10`). Pipe `|` is regex alternation. **Hash `#`** is literal and gives exact `obj.http.X-Cache-Tags ~ #tag#` matching.

## Why `grace = 0` on API objects?

After BAN, Varnish must not serve banned objects during grace period. Otherwise admin changes appear delayed even after purge.

## Why Redis?

1. **Symfony cache adapter** (`config/packages/cache.yaml`) — framework metadata, config.
2. **Activity log** — fast append/read for `/panel`; falls back to `var/activity_log.json` if Redis disabled (`FEATURE_REDIS_ACTIVITY=0`).

Redis is **not** used as application data cache for list endpoints (Varnish handles that).

## Why Elasticsearch?

MySQL `LIKE` autocomplete does not scale for typeahead. ES edge n-grams give fast prefix search. Search is **not** Varnish-cached (personalized query strings, low cache hit rate).

Disable with `FEATURE_ELASTICSEARCH=0` to compare latency / availability without ES.

## Why Symfony HttpClient for benchmarks?

- Runs inside Docker without installing `ab` / k6 on the host.
- Same network as `varnish` and `web` services.
- Outputs JSON + Markdown reports under `benchmarks/results/`.

Optional: `benchmarks/run-ab.sh` for Apache Bench if installed.

## Feature flags

| Variable | Default | Effect |
|----------|---------|--------|
| `FEATURE_ELASTICSEARCH` | `1` | Search API uses ES; `0` returns 503 |
| `FEATURE_REDIS_ACTIVITY` | `1` | Activity log uses Redis; `0` uses file only |

Toggle in `.env` or `docker-compose.yml` `web.environment`, then restart `web`.

## Bypass Varnish for comparisons

1. **Benchmark scenario `origin_direct`** — hits `http://web/...` from inside Docker.
2. **Host browser** — `http://localhost:8081` (direct nginx port).

Do not map 8081 in production.
