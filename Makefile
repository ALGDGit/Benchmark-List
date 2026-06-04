.PHONY: up down build seed index migrate logs setup test benchmark

up:
	docker compose up -d --build

down:
	docker compose down

build:
	docker compose build

migrate:
	docker compose exec -u www-data web php bin/console doctrine:migrations:migrate --no-interaction

seed:
	docker compose exec -u www-data web php bin/console app:seed-database --force

index:
	docker compose exec -u www-data web php bin/console app:index-elasticsearch --reset

setup: up migrate seed index

logs:
	docker compose logs -f

test:
	docker compose exec -u www-data web composer test

benchmark:
	docker compose exec -u www-data web php bin/console app:benchmark --all --base-url=http://varnish --output=benchmarks/results/latest
