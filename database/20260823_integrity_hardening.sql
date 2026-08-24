USE solution_spa;

-- Run once after checking that no client_id is linked to more than one user.
ALTER TABLE users
  ADD UNIQUE INDEX uq_users_client_id (client_id);

ALTER TABLE web_requests
  ADD COLUMN client_id BIGINT UNSIGNED NULL AFTER source,
  ADD UNIQUE INDEX uq_web_requests_client_id (client_id),
  ADD CONSTRAINT fk_web_requests_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL;
