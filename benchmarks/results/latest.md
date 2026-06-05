# Benchmark results

Generated: **2026-06-04T23:51:21+00:00**

## Environment

- **base_url:** http://varnish
- **host:** 2858bec62a07
- **php:** 8.3.31

## Summary

| Scenario | RPS | p50 (ms) | p95 (ms) | p99 (ms) | Errors |
|----------|-----|----------|----------|----------|--------|
| Category API via Varnish (warm cache) | 8381.6 | 1.5 | 2.41 | 2.63 | 0 |
| Category API via Varnish (cold / MISS) | 18.7 | 0.39 | 0.98 | 5351.34 | 0 |
| Category API direct to PHP (bypass Varnish) | 5.9 | 1030.03 | 1782.68 | 1830.24 | 0 |
| Search autocomplete (Elasticsearch, not cached) | 7.4 | 780.23 | 1417.35 | 1480.72 | 0 |
| Homepage HTML (not API-cached) | 5.7 | 1520.18 | 2795.55 | 2838.79 | 0 |

## Details

### Category API via Varnish (warm cache)

- URL: `http://varnish/api/categories/sopas/items?page=1&limit=10`
- Requests: 500 (concurrency 20, warmup 10)
- Duration: 0.06s
- Mean latency: 1.63ms

### Category API via Varnish (cold / MISS)

- URL: `http://varnish/api/categories/sopas/items?page=1&limit=10&_bench=d9afb592`
- Requests: 100 (concurrency 5, warmup 0)
- Duration: 5.361s
- Mean latency: 267.96ms

### Category API direct to PHP (bypass Varnish)

- URL: `http://web/api/categories/sopas/items?page=1&limit=10`
- Requests: 200 (concurrency 10, warmup 3)
- Duration: 33.744s
- Mean latency: 1183.09ms

### Search autocomplete (Elasticsearch, not cached)

- URL: `http://varnish/api/search/autocomplete?q=pa`
- Requests: 150 (concurrency 10, warmup 5)
- Duration: 20.344s
- Mean latency: 943.4ms

### Homepage HTML (not API-cached)

- URL: `http://varnish/`
- Requests: 200 (concurrency 15, warmup 3)
- Duration: 35.114s
- Mean latency: 1612.82ms

