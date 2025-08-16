CREATE DATABASE IF NOT EXISTS wallet_nile_cron
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE wallet_nile_cron;

DROP TABLE IF EXISTS queue_jobs;
CREATE table queue_jobs
(
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uuid`         VARCHAR(255)     NULL COMMENT 'Usually the id of the service provider',
    `payload`      LONGTEXT         NULL COMMENT 'Usually in JSON string format for easy parsing',
    `attempts`     TINYINT UNSIGNED DEFAULT 0 COMMENT 'This will be handled by coroutines',
    `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Maximum number of attempts before the job is considered failed',
    `status`       ENUM ('CREATED', 'IN_QUEUE', 'IN_FLIGHT', 'COMPLETED', 'TIMED_OUT', 'FAILED') NOT NULL DEFAULT 'CREATED',
    `completed_at` DATETIME(3)         NULL DEFAULT  NULL,
    `cron_job_id` int NULL COMMENT 'This is the id of the cron job that this queue job is associated with',
    `created_at`   DATETIME(3)             DEFAULT CURRENT_TIMESTAMP(3)
) Engine = InnoDB collate = utf8mb4_unicode_ci;
ALTER TABLE queue_jobs ADD INDEX idx_cron_job_id (cron_job_id), LOCK = NONE;
ALTER TABLE queue_jobs ADD INDEX idx_created_at_status (created_at, status), LOCK = NONE;

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