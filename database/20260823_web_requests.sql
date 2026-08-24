USE solution_spa;

CREATE TABLE IF NOT EXISTS web_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  interest VARCHAR(160) NOT NULL,
  status ENUM('new','contacted','converted','dismissed') NOT NULL DEFAULT 'new',
  source VARCHAR(60) NOT NULL DEFAULT 'website',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_web_requests_status (status),
  INDEX idx_web_requests_created_at (created_at),
  INDEX idx_web_requests_phone (phone)
) ENGINE=InnoDB;
