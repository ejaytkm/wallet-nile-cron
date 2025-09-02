#!/bin/bash
# Create the cron job file for clearing logs = every 5 days at 6:00 AM
cat >/etc/cron.d/clear_app_log <<'EOF'
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
*/2 * * * * www-data /usr/bin/truncate -s 0 /var/www/html/storage/logs/application.log
EOF

# Create the cron job file for running server/cron.php
cat >/etc/cron.d/run_server_cron <<'EOF'
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
* * * * * www-data /usr/bin/php /var/www/html/server/cron.php
EOF

# Set the appropriate permissions and ownership for both cron jobs
chmod 644 /etc/cron.d/clear_app_log
chown root:root /etc/cron.d/clear_app_log

chmod 644 /etc/cron.d/run_server_cron
chown root:root /etc/cron.d/run_server_cron

# Restart the cron service to apply changes
systemctl restart cron