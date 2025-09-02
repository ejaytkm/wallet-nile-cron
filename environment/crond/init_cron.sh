#!/bin/bash
# Create the cron job file for clearing logs = every 5 days at 6:00 AM
cat >/etc/cron.d/clear_app_log <<'EOF'
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
*/5 * * * * www-data /usr/bin/truncate -s 0 /var/www/html/storage/logs/application.log
*/5 * * * * root truncate -s 0 /var/log/php8.3-fpm.log
*/5 * * * * root truncate -s 0 /var/log/nginx/access.log
*/5 * * * * root truncate -s 0 /var/log/nginx/error.log
*/5 * * * * root truncate -s 0 /var/log/deployment.log
*/5 * * * root journalctl --vacuum-time=1s --unit=wallet-nile-cron
EOF

# Set the appropriate permissions and ownership for both cron jobs
chmod 644 /etc/cron.d/clear_app_log
chown root:root /etc/cron.d/clear_app_log

# Restart the cron service to apply changes
systemctl restart cron