CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actor_type VARCHAR(16) NOT NULL,
  actor_user_id INT NULL,
  actor_role VARCHAR(32) NULL,
  action VARCHAR(128) NOT NULL,
  request_method VARCHAR(16) NULL,
  request_uri TEXT NULL,
  ip VARCHAR(64) NULL,
  user_agent TEXT NULL,
  referrer TEXT NULL,
  status_code INT NULL,
  duration_ms INT NULL,
  payload_json JSON NULL,
  PRIMARY KEY (id),
  KEY idx_created_at (created_at),
  KEY idx_actor_user_id (actor_user_id),
  KEY idx_action (action(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

