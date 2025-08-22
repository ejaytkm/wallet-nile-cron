CREATE DATABASE IF NOT EXISTS wallet_nile_cron
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE wallet_nile_cron;

DROP TABLE IF EXISTS cron_jobs;
CREATE TABLE cron_jobs
(
    id                 int auto_increment primary key,
    type              VARCHAR(50)                   not null,
    merchant_id        int                           not null,
    code               varchar(20)                   not null,
    execution_datetime datetime                      not null,
    status             enum('PENDING', 'PROCESSING', 'STARTED', 'FAILED', 'IN_QUEUE') not null default 'PENDING',
    status_datetime    datetime                      null,
    timeout_count      int         default 0         not null,
    constraint merchant_id unique (merchant_id, code)
) Engine = InnoDB collate = utf8mb4_unicode_ci;
CREATE INDEX idx_cron_jobs_status ON cron_jobs (status);
CREATE INDEX idx_cron_jobs_type ON cron_jobs (type);
CREATE INDEX idx_cron_jobs_merchant_id_code ON cron_jobs (merchant_id, code);
