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

DROP TABLE IF EXISTS queue_jobs;
CREATE table queue_jobs
(
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
     `type`              VARCHAR(50)                   not null,
    `cronId` int NULL COMMENT 'This is the id of the cron job that this queue job is associated with',
    `payload`      LONGTEXT         NULL COMMENT 'Usually in JSON string format for easy parsing',
    `attempts`     TINYINT UNSIGNED DEFAULT 0 COMMENT 'This will be handled by coroutines',
    `status`       ENUM ('CREATED', 'IN_QUEUE', 'IN_FLIGHT', 'COMPLETED', 'TIMED_OUT', 'FAILED') NOT NULL DEFAULT 'CREATED',
    `duration`     FLOAT(10, 3)     NULL DEFAULT NULL COMMENT 'Duration in seconds',
    `available_at` DATETIME(3)         NULL DEFAULT  NULL,
    `executed_at` DATETIME(3)         NULL DEFAULT  NULL,
    `created_at`   DATETIME(3)             DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at`   DATETIME(3)             DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)
) Engine = InnoDB collate = utf8mb4_unicode_ci;
ALTER TABLE queue_jobs ADD INDEX idx_queue_jobs_cronId (cronId);
CREATE INDEX idx_queue_jobs_type ON cron_jobs (type);
ALTER TABLE queue_jobs ADD INDEX idx_created_at_status (created_at, status);
