# Wallet Nile Cron (Swoole)
## Description
Massively concurrent cURL using Swoole coroutines with throttling (global/per-host), plus a simple dispatcher.

![queue_chart.png](storage/docs/workflow.png)

## Installation
See `/environment/` for how to set up and download packages for full environment.

### Docker
Start the PHP environment with Docker:
```bash
make build
```

```shell
Fatal error: Uncaught MeekroDBException: SQLSTATE[HY000] [2002] No such file or directory in /var/app/current/vendor/sergeytsalkov/meekrodb/db.class.php:212
```

# Production 
