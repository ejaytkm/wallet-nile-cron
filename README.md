# Introduction
Wallet Nile Cron is a simple PHP FPM server that runs a cron job using the php bin and cronjobs defined in the /etc/cron.d/ directory. Localhost does not have cron so testing is done manually by running the cron job script directly.

## How to test manually?
Using the Makefile, run the `APP COMMANDS:`
- `make app@cron`
- `make app@bash`

How to test manually merchant IDS?
TEST_MERCHANT_IDS=21

# syncBetHistory.php
Required environment variables:
- wEnv={see-merchantRepo.php}

## Starting Crons
Run command: 
```
sudo bash /var/www/html/environment/crond/init_cron.sh 
```

## LOGS 
1. PHP-FPM Logs
   Error logs:
   cat /var/log/php8.3-fpm.log
   If the log file is not there, check the PHP-FPM configuration file (e.g., /etc/php/8.3/fpm/php-fpm.conf or /etc/php/8.3/fpm/pool.d/www.conf) for the error_log directive.
2. Nginx Logs
   Access logs:
   cat /var/log/nginx/access.log
   Error logs:
   cat /var/log/nginx/error.log
3. Wallet-Nile-Cron Logs
   Deployment log (as defined in your script):
   cat /var/log/deployment.log
4. Systemd service logs:
   sudo journalctl -u wallet-nile-cron