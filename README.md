# Swoole High Concurrency App (vanilla Swoole)

Massively concurrent cURL using Swoole coroutines with throttling (global/per-host), plus a simple dispatcher.

## Quickstart (Docker)
```bash
cd docker
docker compose up --build -d
# In another shell:
docker compose exec app php /app/dispatcher.php