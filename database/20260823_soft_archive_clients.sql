USE solution_spa;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS active TINYINT(1) NOT NULL DEFAULT 1 AFTER notes;
CREATE INDEX IF NOT EXISTS idx_clients_active ON clients(active);
