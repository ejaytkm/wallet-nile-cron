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
