USE solution_spa;

ALTER TABLE users
  MODIFY role ENUM('admin','staff','operator','client') NOT NULL DEFAULT 'admin';

UPDATE users SET role='operator' WHERE role='staff';

ALTER TABLE users
  ADD COLUMN client_id BIGINT UNSIGNED NULL AFTER role,
  ADD INDEX idx_users_role (role),
  ADD INDEX idx_users_client_id (client_id),
  ADD CONSTRAINT fk_users_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL;

ALTER TABLE users
  MODIFY role ENUM('admin','operator','client') NOT NULL DEFAULT 'admin';
