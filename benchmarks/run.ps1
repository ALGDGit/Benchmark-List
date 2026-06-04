#Requires -Version 5.1
$ErrorActionPreference = "Stop"
Set-Location (Join-Path $PSScriptRoot "..")

Write-Host "==> Waiting for web container..."
docker compose exec -T web curl -sf http://127.0.0.1/health.php | Out-Null

Write-Host "==> Warming Varnish cache..."
docker compose exec -T web curl -sf "http://varnish/api/categories/sopas/items?page=1&limit=10" | Out-Null

Write-Host "==> Running benchmarks..."
docker compose exec -u www-data -T web php bin/console app:benchmark --all `
  --base-url=http://varnish `
  --output=benchmarks/results/latest

Write-Host "==> Done. See benchmarks/results/latest.md"
