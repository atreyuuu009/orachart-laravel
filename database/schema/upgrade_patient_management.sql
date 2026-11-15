-- Apply once to an existing OraChart database created from schema v1.
ALTER TABLE pat_patients
  ADD COLUMN emergency_contact_relationship VARCHAR(100) NULL AFTER emergency_contact_number,
  ADD COLUMN guardian_name VARCHAR(150) NULL AFTER emergency_contact_relationship,
  ADD COLUMN guardian_relationship VARCHAR(100) NULL AFTER guardian_name;

ALTER TABLE pat_health_profiles
  ADD COLUMN structured_checklist JSON NULL AFTER oral_history;

ALTER TABLE pat_visit_records
  ADD COLUMN blood_pressure VARCHAR(20) NULL AFTER clinical_notes,
  ADD COLUMN pulse SMALLINT UNSIGNED NULL AFTER blood_pressure,
  ADD COLUMN last_xray_date DATE NULL AFTER pulse;
