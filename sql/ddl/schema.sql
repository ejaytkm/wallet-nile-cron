# CREATE DATABASE IF NOT EXISTS db_swoole_sql_curl
#   DEFAULT CHARACTER SET utf8mb4
#   DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jobs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    payload_json    JSON NULL,

    status          ENUM('created', 'pending','in_flight','ok','error','skipped') NOT NULL DEFAULT 'pending',
    attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,

    lease_owner     VARCHAR(120) NULL,
    lease_until     DATETIME NULL,

    started_at      DATETIME NULL,
    finished_at     DATETIME NULL,
    http_code       SMALLINT UNSIGNED NULL,
    latency_ms      INT UNSIGNED NULL,
    error_message   VARCHAR(255) NULL,

    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY claim_idx(status, lease_until, id),
    KEY pending_idx(status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;