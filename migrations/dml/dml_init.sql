USE wallet_global;

SET @JSON_CONFIG = '{"interval": 300, "module": ["jili"]}';
INSERT INTO cron_jobs_config (type, name, json_config, enabled) VALUES
   ('SYNC_BET_HISTORY','JILI', @JSON_CONFIG, false),
   ('SYNC_BET_HISTORY','JILI2', @JSON_CONFIG, false),
   ('SYNC_BET_HISTORY', 'JILI3', @JSON_CONFIG, false);