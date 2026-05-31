.PHONY: up down build seed index migrate logs

up:
	docker compose up -d --build

down:
	docker compose down

build:
	docker compose build

migrate:
	docker compose exec web php bin/console doctrine:migrations:migrate --no-interaction

seed:
	docker compose exec web php bin/console app:seed-database --force

index:
	docker compose exec web php bin/console app:index-elasticsearch --reset

setup: up migrate seed index

logs:
	docker compose logs -f
