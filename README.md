# Benchmark List

Symfony demo app with Varnish, MySQL, Redis, and Elasticsearch. Everything runs in Docker.

## Requirements

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (or Docker Engine + Compose)

## Install and run

```bash
# 1. Start all services (first run builds images; wait ~1–2 min)
docker compose up -d --build

# 2. Create tables and sample data
docker compose exec -u www-data web php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -u www-data web php bin/console app:seed-database --force

# 3. Index items for search
docker compose exec -u www-data web php bin/console app:index-elasticsearch --reset
```

Open the site when the `web` container is healthy (about 1–2 minutes on first start):

| URL | Purpose |
|-----|---------|
| http://localhost:8080 | Public site (via Varnish) |
| http://localhost:8080/admin | Admin panel |
| http://localhost:8080/panel | Activity / cache log |

If you get **503 Backend fetch failed**, restart Varnish after `web` is healthy:

```bash
docker compose ps   # web should be "healthy"
docker compose restart varnish
```

Use `-u www-data` for console commands (or the **Refresh Symfony cache & restart web** button in Admin → Overview).
