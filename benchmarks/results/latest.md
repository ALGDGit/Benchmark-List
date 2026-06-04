# Benchmark results

Generated: **2026-06-04T14:30:00+00:00**

## Environment

- **base_url:** http://varnish
- **host:** benchmarklist-web-1
- **php:** 8.3.31
- **note:** Sample results — re-run `./benchmarks/run.sh` for your environment

## Summary

| Scenario | RPS | p50 (ms) | p95 (ms) | p99 (ms) | Errors |
|----------|-----|----------|----------|----------|--------|
| Category API via Varnish (warm cache) | 817 | 18.2 | 41.5 | 58.3 | 0 |
| Category API via Varnish (cold / MISS) | 20.7 | 215.4 | 312.8 | 398.2 | 0 |
| Category API direct to PHP (bypass Varnish) | 93.2 | 95.6 | 178.4 | 241.2 | 0 |
| Search autocomplete (Elasticsearch, not cached) | 79.3 | 108.2 | 195.6 | 248.9 | 0 |
| Homepage HTML (not API-cached) | 203.3 | 62.1 | 112.8 | 145.2 | 0 |

## Takeaways

- **Varnish (warm)** is ~8× faster RPS than direct PHP for the same JSON endpoint.
- **Cold/MISS** latency reflects full Symfony + Doctrine path — similar order of magnitude to `origin_direct`.
- **Elasticsearch** adds ~100ms+ p50 vs cached API — expected for uncached search.

## Details

### Category API via Varnish (warm cache)

- URL: `http://varnish/api/categories/sopas/items?page=1&limit=10`
- Requests: 500 (concurrency 20, warmup 10)
- Duration: 0.612s
- Mean latency: 22.4ms

### Category API via Varnish (cold / MISS)

- URL: `http://varnish/api/categories/sopas/items?page=1&limit=10&_bench=sample`
- Requests: 100 (concurrency 5, warmup 0)
- Duration: 4.821s
- Mean latency: 228.6ms

### Category API direct to PHP (bypass Varnish)

- URL: `http://web/api/categories/sopas/items?page=1&limit=10`
- Requests: 200 (concurrency 10, warmup 3)
- Duration: 2.145s
- Mean latency: 102.8ms

### Search autocomplete (Elasticsearch, not cached)

- URL: `http://varnish/api/search/autocomplete?q=pa`
- Requests: 150 (concurrency 10, warmup 5)
- Duration: 1.892s
- Mean latency: 118.5ms

### Homepage HTML (not API-cached)

- URL: `http://varnish/`
- Requests: 200 (concurrency 15, warmup 3)
- Duration: 0.984s
- Mean latency: 68.4ms
