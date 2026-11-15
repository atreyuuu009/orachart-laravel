-- Apply once after upgrade_patient_management.sql to an existing OraChart database.
ALTER TABLE usr_consents
  MODIFY COLUMN user_id INT UNSIGNED NULL,
  ADD COLUMN patient_id INT UNSIGNED NULL AFTER user_id,
  ADD INDEX idx_usr_consents_patient_id (patient_id),
  ADD CONSTRAINT fk_usr_consents_patient_id FOREIGN KEY (patient_id) REFERENCES pat_patients(patient_id),
  ADD CONSTRAINT chk_usr_consents_owner CHECK (user_id IS NOT NULL OR patient_id IS NOT NULL);
