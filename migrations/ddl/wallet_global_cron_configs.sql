USE wallet_global;

DROP TABLE IF EXISTS jobs_config;
CREATE TABLE jobs_config (
     id INT AUTO_INCREMENT PRIMARY KEY,
     type              VARCHAR(50)                   not null,
     job_name VARCHAR(50) NOT NULL UNIQUE,
     json_config JSON DEFAULT NULL,
    enabled BOOLEAN DEFAULT FALSE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

SET @JSON_CONFIG = '[300, "jili"]';
INSERT INTO jobs_config (type, job_name, json_config, enabled) VALUES
     ('SYNC_BET_HISTORY','JILI', @JSON_CONFIG, false),
     ('SYNC_BET_HISTORY','JILI2', @JSON_CONFIG, false),
     ('SYNC_BET_HISTORY', 'JILI3', @JSON_CONFIG, false);

DROP TABLE IF EXISTS cron_jobs_v2;
CREATE TABLE cron_jobs_v2
(
    id                 int auto_increment primary key,
    env                VARCHAR(50)                   not null,
    type              VARCHAR(50)                   not null,
    merchant_id        int                           not null,
    code               varchar(20)                   not null,
    execution_datetime datetime                      not null,
    status_datetime    datetime                      null,
    status             enum('PENDING', 'PROCESSING', 'STARTED', 'FAILED', 'IN_QUEUE') not null default 'PENDING',
    constraint merchant_id unique (merchant_id, code)
) Engine = InnoDB collate = utf8mb4_unicode_ci;
CREATE INDEX idx_cron_jobs_env_type ON cron_jobs_v2 (env, type);
CREATE INDEX idx_cron_jobs_merchant_id_code ON cron_jobs_v2 (merchant_id, code);
CREATE INDEX idx_cron_jobs_status ON cron_jobs_v2 (status);
