CREATE DATABASE IF NOT EXISTS solution_spa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE solution_spa;

CREATE TABLE IF NOT EXISTS clients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(160) NULL,
  birth_date DATE NULL,
  notes TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_clients_name (name), INDEX idx_clients_phone (phone), INDEX idx_clients_active (active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(140) NOT NULL,
  description TEXT NULL,
  image_url VARCHAR(500) NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  price DECIMAL(10,2) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_services_active (active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','operator','client') NOT NULL DEFAULT 'admin',
  client_id BIGINT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
  INDEX idx_users_role (role), UNIQUE INDEX uq_users_client_id (client_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  manager_user_id BIGINT UNSIGNED NULL,
  starts_at DATETIME NOT NULL,
  status ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_appointments_client FOREIGN KEY (client_id) REFERENCES clients(id),
  CONSTRAINT fk_appointments_service FOREIGN KEY (service_id) REFERENCES services(id),
  CONSTRAINT fk_appointments_manager FOREIGN KEY (manager_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_appointments_starts_at (starts_at), INDEX idx_appointments_status (status), INDEX idx_appointments_manager (manager_user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS treatments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NULL,
  appointment_id BIGINT UNSIGNED NULL,
  performed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_treatments_client FOREIGN KEY (client_id) REFERENCES clients(id),
  CONSTRAINT fk_treatments_service FOREIGN KEY (service_id) REFERENCES services(id),
  CONSTRAINT fk_treatments_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id),
  INDEX idx_treatments_client (client_id), INDEX idx_treatments_performed_at (performed_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id BIGINT UNSIGNED NOT NULL,
  appointment_id BIGINT UNSIGNED NULL,
  amount DECIMAL(10,2) NOT NULL,
  method ENUM('cash','card','transfer','other') NOT NULL DEFAULT 'other',
  status ENUM('pending','paid','refunded','cancelled') NOT NULL DEFAULT 'paid',
  reference VARCHAR(120) NULL,
  notes TEXT NULL,
  paid_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_client FOREIGN KEY (client_id) REFERENCES clients(id),
  CONSTRAINT fk_payments_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
  INDEX idx_payments_client (client_id), INDEX idx_payments_appointment (appointment_id), INDEX idx_payments_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS remember_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  selector CHAR(24) NOT NULL UNIQUE,
  validator_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_remember_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_remember_tokens_expires (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS web_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  interest VARCHAR(160) NOT NULL,
  status ENUM('new','contacted','converted','dismissed') NOT NULL DEFAULT 'new',
  source VARCHAR(60) NOT NULL DEFAULT 'website',
  client_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_web_requests_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
  UNIQUE INDEX uq_web_requests_client_id (client_id), INDEX idx_web_requests_status (status), INDEX idx_web_requests_created_at (created_at), INDEX idx_web_requests_phone (phone)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_type VARCHAR(32) NOT NULL DEFAULT 'user',
  actor_id VARCHAR(128) NULL,
  actor_role VARCHAR(64) NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(100) NOT NULL,
  entity_id VARCHAR(128) NULL,
  source VARCHAR(100) NULL,
  request_id VARCHAR(128) NULL,
  ip_address VARCHAR(45) NULL,
  before_data JSON NULL,
  after_data JSON NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_audit_created_at (created_at),
  INDEX idx_audit_actor (actor_type, actor_id),
  INDEX idx_audit_entity (entity_type, entity_id),
  INDEX idx_audit_action (action),
  INDEX idx_audit_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
  name VARCHAR(190) PRIMARY KEY,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
