CREATE DATABASE IF NOT EXISTS wallet_nile_cron
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE wallet_nile_cron;

DROP TABLE IF EXISTS queue_jobs;
CREATE table queue_jobs
(
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `payload`      LONGTEXT         NULL COMMENT 'Usually in JSON string format for easy parsing',
    `attempts`     TINYINT UNSIGNED NOT NULL COMMENT 'This will be handled by coroutines',
    `status`       ENUM ('CREATED', 'IN_QUEUE', 'PROCESSING', 'COMPLETED', 'FAILED') NOT NULL DEFAULT 'CREATED',
    `available_at` DATETIME(3)        NULL DEFAULT NULL,
    `completed_at` DATETIME(3)         NULL DEFAULT  NULL,
    `cron_job_id`  INT UNSIGNED NULL DEFAULT NULL COMMENT 'Foreign key to cron_jobs.id',
    `created_at`   DATETIME(3)             DEFAULT CURRENT_TIMESTAMP(3)
) Engine = InnoDB collate = utf8mb4_unicode_ci;
ALTER TABLE queue_jobs ADD INDEX idx_jobs_status (status), LOCK = NONE;
ALTER TABLE queue_jobs ADD INDEX idx_jobs_created_at (created_at), LOCK = NONE;

DROP TABLE IF EXISTS cron_jobs;
CREATE TABLE cron_jobs
(
    id                 int auto_increment primary key,
    merchant_id        int                           not null,
    code               varchar(20)                   not null,
    execution_datetime datetime                      not null,
    status             enum('PENDING', 'PROCESSING', 'STARTED', 'FAILED', 'IN_QUEUE') not null default 'PENDING',
    status_datetime    datetime                      null,
    timeout_count      int         default 0         not null,
    constraint merchant_id unique (merchant_id, code)
) Engine = InnoDB collate = utf8mb4_unicode_ci;
CREATE INDEX idx_cron_jobs_status ON cron_jobs (status);
CREATE INDEX idx_cron_jobs_merchant_id_code ON cron_jobs (merchant_id, code);