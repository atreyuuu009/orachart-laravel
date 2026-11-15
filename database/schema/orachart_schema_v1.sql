-- =====================================================================
-- OraChart: Guda & Soriano Dental Clinic
-- Schema v1  (MySQL 8.0+, InnoDB, utf8mb4)
--
-- RULES
--  * Everyone runs THIS file to create the same database: orachart_db
--  * Table prefix = owning module. Only the owner writes to its tables.
--      usr_ users/auth/dentists   svc_ dental services   sch_ schedule
--      apt_ appointments          pat_ patient records   odo_ odontogram
--      pay_ payments/installments ntf_ email             bot_ chatbot (Clary)
--      sys_ audit/backup/settings
--  * PK = <singular>_id.  FK column has the SAME name as the PK it points to
--    (exceptions: role-specific FKs such as requested_dentist_id).
--  * Adding columns: new migration file, nullable or with DEFAULT. Never
--    rename/drop/change a type without telling the PM (Schema Owner).
--  * Run time zone for the app connection: Asia/Manila (+08:00)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS orachart_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE orachart_db;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- usr_  USERS, AUTH, DENTISTS                     (Dev C owns; Dev B: registration flow)
-- =====================================================================
CREATE TABLE usr_roles (
  role_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_name      VARCHAR(30) NOT NULL,
  CONSTRAINT uq_usr_roles_role_name UNIQUE (role_name)
) ENGINE=InnoDB;

CREATE TABLE usr_users (
  user_id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id             INT UNSIGNED NOT NULL,
  username            VARCHAR(50)  NOT NULL,
  email               VARCHAR(255) NOT NULL,
  password_hash       VARCHAR(255) NOT NULL,
  first_name          VARCHAR(100) NOT NULL,
  last_name           VARCHAR(100) NOT NULL,
  contact_number      VARCHAR(20)  NULL,
  is_active           TINYINT(1)   NOT NULL DEFAULT 1,
  is_email_verified   TINYINT(1)   NOT NULL DEFAULT 0,
  failed_login_count  INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until        DATETIME     NULL,
  last_login_at       DATETIME     NULL,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at          DATETIME     NULL,
  CONSTRAINT uq_usr_users_username UNIQUE (username),
  CONSTRAINT uq_usr_users_email    UNIQUE (email),
  CONSTRAINT fk_usr_users_role_id  FOREIGN KEY (role_id) REFERENCES usr_roles(role_id)
) ENGINE=InnoDB;

-- Data Privacy Act consent (Backlog 001)
CREATE TABLE usr_consents (
  consent_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NULL,
  patient_id      INT UNSIGNED NULL,
  consent_type    VARCHAR(50)  NOT NULL DEFAULT 'data_privacy',
  policy_version  VARCHAR(20)  NOT NULL,
  is_accepted     TINYINT(1)   NOT NULL,
  accepted_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_address      VARCHAR(45)  NULL,
  CONSTRAINT fk_usr_consents_user_id FOREIGN KEY (user_id) REFERENCES usr_users(user_id),
  CONSTRAINT chk_usr_consents_owner CHECK (user_id IS NOT NULL OR patient_id IS NOT NULL)
) ENGINE=InnoDB;

-- Email verification + password reset (store only the HASH of the token)
CREATE TABLE usr_email_tokens (
  email_token_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  token_type      ENUM('verify_email','reset_password') NOT NULL,
  token_hash      CHAR(64)     NOT NULL,
  expires_at      DATETIME     NOT NULL,
  used_at         DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usr_email_tokens_user_id FOREIGN KEY (user_id) REFERENCES usr_users(user_id),
  INDEX idx_usr_email_tokens_token_hash (token_hash)
) ENGINE=InnoDB;

CREATE TABLE usr_specialties (
  specialty_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  specialty_name  VARCHAR(100) NOT NULL,
  CONSTRAINT uq_usr_specialties_specialty_name UNIQUE (specialty_name)
) ENGINE=InnoDB;

CREATE TABLE usr_dentists (
  dentist_id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id               INT UNSIGNED NOT NULL,
  license_number        VARCHAR(50)  NULL,
  is_accepting_bookings TINYINT(1)   NOT NULL DEFAULT 1,
  created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_usr_dentists_user_id UNIQUE (user_id),
  CONSTRAINT fk_usr_dentists_user_id FOREIGN KEY (user_id) REFERENCES usr_users(user_id)
) ENGINE=InnoDB;

CREATE TABLE usr_dentist_specialties (
  dentist_id   INT UNSIGNED NOT NULL,
  specialty_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (dentist_id, specialty_id),
  CONSTRAINT fk_usr_dentist_specialties_dentist_id   FOREIGN KEY (dentist_id)   REFERENCES usr_dentists(dentist_id),
  CONSTRAINT fk_usr_dentist_specialties_specialty_id FOREIGN KEY (specialty_id) REFERENCES usr_specialties(specialty_id)
) ENGINE=InnoDB;

-- =====================================================================
-- svc_  DENTAL SERVICES                                           (Dev C)
-- =====================================================================
CREATE TABLE svc_categories (
  category_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_name  VARCHAR(100) NOT NULL,
  CONSTRAINT uq_svc_categories_category_name UNIQUE (category_name)
) ENGINE=InnoDB;

-- PRICING MODEL (from the clinic's price list)
--  * Each material / tier is its own row (e.g. "Crown/Bridge - E-Max").
--  * pricing_unit says what the price is charged per.
--  * price_min_amount = current selling price (lowest price if it is a range)
--  * price_max_amount = upper end of the range, NULL if fixed price
--  * original_price_amount = crossed-out regular price when on promo, else NULL
--  * additional_unit_price_amount = price of each succeeding unit (fillings: extra surface)
--  * is_addon = extra charged on top of another service (clasp, calcimol); not bookable
--  * Staff always enters the ACTUAL charged amount in pay_payment_items.
CREATE TABLE svc_services (
  service_id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id                   INT UNSIGNED NOT NULL,
  service_name                  VARCHAR(150) NOT NULL,
  description                   TEXT NULL,
  pricing_unit                  ENUM('flat','per_arch','per_tooth','per_surface','per_canal',
                                     'per_quadrant','per_session','per_post','per_clasp','package') NOT NULL DEFAULT 'flat',
  price_min_amount              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  price_max_amount              DECIMAL(10,2) NULL,
  original_price_amount         DECIMAL(10,2) NULL,
  additional_unit_price_amount  DECIMAL(10,2) NULL,
  duration_minutes              INT UNSIGNED NOT NULL,        -- drives slot calculation
  required_specialty_id         INT UNSIGNED NULL,            -- NULL = any dentist
  is_addon                      TINYINT(1)   NOT NULL DEFAULT 0,
  is_bookable                   TINYINT(1)   NOT NULL DEFAULT 1,
  is_multi_visit                TINYINT(1)   NOT NULL DEFAULT 0,
  is_active                     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_svc_services_service_name UNIQUE (service_name),
  CONSTRAINT fk_svc_services_category_id FOREIGN KEY (category_id) REFERENCES svc_categories(category_id),
  CONSTRAINT fk_svc_services_required_specialty_id FOREIGN KEY (required_specialty_id) REFERENCES usr_specialties(specialty_id)
) ENGINE=InnoDB;

-- Braces-style packages: downpayment + monthly adjustment + inclusions
CREATE TABLE svc_package_terms (
  package_term_id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id                 INT UNSIGNED NOT NULL,
  downpayment_amount         DECIMAL(10,2) NOT NULL,
  monthly_adjustment_amount  DECIMAL(10,2) NOT NULL,
  inclusions                 JSON NULL,
  terms_note                 VARCHAR(500) NULL,
  CONSTRAINT uq_svc_package_terms_service_id UNIQUE (service_id),
  CONSTRAINT fk_svc_package_terms_service_id FOREIGN KEY (service_id) REFERENCES svc_services(service_id)
) ENGINE=InnoDB;

-- Staff proposes, Admin approves (Backlog 008)
CREATE TABLE svc_change_requests (
  change_request_id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id                  INT UNSIGNED NULL,          -- NULL when change_type = 'add'
  change_type                 ENUM('add','update','deactivate') NOT NULL,
  proposed_changes            JSON NULL,   -- keys = svc_services column names, e.g. {"price_min_amount": 1200, "duration_minutes": 45}
  status                      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  requested_by_user_id        INT UNSIGNED NOT NULL,
  reviewed_by_user_id         INT UNSIGNED NULL,
  review_note                 VARCHAR(500) NULL,
  reviewed_at                 DATETIME NULL,
  created_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_svc_change_requests_service_id FOREIGN KEY (service_id) REFERENCES svc_services(service_id),
  CONSTRAINT fk_svc_change_requests_requested_by_user_id FOREIGN KEY (requested_by_user_id) REFERENCES usr_users(user_id),
  CONSTRAINT fk_svc_change_requests_reviewed_by_user_id  FOREIGN KEY (reviewed_by_user_id)  REFERENCES usr_users(user_id)
) ENGINE=InnoDB;

-- =====================================================================
-- sch_  CLINIC SCHEDULE & BOOKING RULES                           (Dev A)
-- day_of_week: 0 = Sunday ... 6 = Saturday (matches JS Date.getDay())
-- =====================================================================
CREATE TABLE sch_settings (
  setting_key    VARCHAR(60) PRIMARY KEY,
  setting_value  VARCHAR(255) NOT NULL,
  description    VARCHAR(255) NULL,
  updated_by_user_id INT UNSIGNED NULL,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sch_settings_updated_by_user_id FOREIGN KEY (updated_by_user_id) REFERENCES usr_users(user_id)
) ENGINE=InnoDB;

CREATE TABLE sch_clinic_hours (
  clinic_hour_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  day_of_week    TINYINT UNSIGNED NOT NULL,
  open_time      TIME NULL,
  close_time     TIME NULL,
  is_closed      TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT uq_sch_clinic_hours_day_of_week UNIQUE (day_of_week)
) ENGINE=InnoDB;

CREATE TABLE sch_dentist_schedules (
  schedule_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dentist_id    INT UNSIGNED NOT NULL,
  day_of_week   TINYINT UNSIGNED NOT NULL,
  start_time    TIME NOT NULL,
  end_time      TIME NOT NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_sch_dentist_schedules_dentist_id FOREIGN KEY (dentist_id) REFERENCES usr_dentists(dentist_id),
  INDEX idx_sch_dentist_schedules_dentist_day (dentist_id, day_of_week)
) ENGINE=InnoDB;

-- Date-based override. dentist_id NULL = applies to the whole clinic (holiday)
CREATE TABLE sch_overrides (
  override_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dentist_id     INT UNSIGNED NULL,
  override_date  DATE NOT NULL,
  is_closed      TINYINT(1) NOT NULL DEFAULT 0,
  start_time     TIME NULL,
  end_time       TIME NULL,
  reason         VARCHAR(255) NULL,
  created_by_user_id INT UNSIGNED NOT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sch_overrides_dentist_id FOREIGN KEY (dentist_id) REFERENCES usr_dentists(dentist_id),
  CONSTRAINT fk_sch_overrides_created_by_user_id FOREIGN KEY (created_by_user_id) REFERENCES usr_users(user_id),
  INDEX idx_sch_overrides_override_date (override_date)
) ENGINE=InnoDB;

CREATE TABLE sch_time_offs (
  time_off_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dentist_id     INT UNSIGNED NOT NULL,
  start_at       DATETIME NOT NULL,
  end_at         DATETIME NOT NULL,
  reason         VARCHAR(255) NULL,
  created_by_user_id INT UNSIGNED NOT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sch_time_offs_dentist_id FOREIGN KEY (dentist_id) REFERENCES usr_dentists(dentist_id),
  CONSTRAINT fk_sch_time_offs_created_by_user_id FOREIGN KEY (created_by_user_id) REFERENCES usr_users(user_id),
  INDEX idx_sch_time_offs_dentist_range (dentist_id, start_at, end_at)
) ENGINE=InnoDB;

-- Reserved emergency windows (exempt from minimum booking notice)
CREATE TABLE sch_emergency_blocks (
  emergency_block_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dentist_id     INT UNSIGNED NULL,           -- NULL = any dentist
  day_of_week    TINYINT UNSIGNED NOT NULL,
  start_time     TIME NOT NULL,
  end_time       TIME NOT NULL,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_sch_emergency_blocks_dentist_id FOREIGN KEY (dentist_id) REFERENCES usr_dentists(dentist_id)
) ENGINE=InnoDB;

-- =====================================================================
-- pat_  PATIENT RECORDS (core table first)                        (Dev B)
-- user_id is NULL for walk-in patients without an account.
-- =====================================================================
CREATE TABLE pat_patients (
  patient_id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id                    INT UNSIGNED NULL,
  patient_code               VARCHAR(20) NOT NULL,      -- e.g. GS-2026-00001 (auto-index)
  first_name                 VARCHAR(100) NOT NULL,
  middle_name                VARCHAR(100) NULL,
  last_name                  VARCHAR(100) NOT NULL,
  birth_date                 DATE NOT NULL,
  sex                        ENUM('male','female','other') NULL,
  contact_number             VARCHAR(20) NOT NULL,
  email                      VARCHAR(255) NULL,
  address                    VARCHAR(500) NULL,
  emergency_contact_name     VARCHAR(150) NULL,
  emergency_contact_number   VARCHAR(20) NULL,
  emergency_contact_relationship VARCHAR(100) NULL,
  guardian_name              VARCHAR(150) NULL,
  guardian_relationship      VARCHAR(100) NULL,
  created_by_user_id         INT UNSIGNED NULL,
  created_at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at                 DATETIME NULL,
  CONSTRAINT uq_pat_patients_user_id      UNIQUE (user_id),
  CONSTRAINT uq_pat_patients_patient_code UNIQUE (patient_code),
  CONSTRAINT fk_pat_patients_user_id            FOREIGN KEY (user_id) REFERENCES usr_users(user_id),
  CONSTRAINT fk_pat_patients_created_by_user_id FOREIGN KEY (created_by_user_id) REFERENCES usr_users(user_id),
  INDEX idx_pat_patients_name (last_name, first_name),
  INDEX idx_pat_patients_contact_number (contact_number)
) ENGINE=InnoDB;

ALTER TABLE usr_consents
  ADD INDEX idx_usr_consents_patient_id (patient_id),
  ADD CONSTRAINT fk_usr_consents_patient_id FOREIGN KEY (patient_id) REFERENCES pat_patients(patient_id);

CREATE TABLE pat_health_profiles (
  health_profile_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id           INT UNSIGNED NOT NULL,
  blood_type           ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-','unknown') NOT NULL DEFAULT 'unknown',
  allergies            TEXT NULL,
  medical_conditions   TEXT NULL,
  current_medications  TEXT NULL,
  oral_history         TEXT NULL,
  structured_checklist JSON NULL,
  last_reviewed_at     DATETIME NULL,
  updated_by_user_id   INT UNSIGNED NULL,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_pat_health_profiles_patient_id UNIQUE (patient_id),
  CONSTRAINT fk_pat_health_profiles_patient_id FOREIGN KEY (patient_id) REFERENCES pat_patients(patient_id),
  CONSTRAINT fk_pat_health_profiles_updated_by_user_id FOREIGN KEY (updated_by_user_id) REFERENCES usr_users(user_id)
) ENGINE=InnoDB;

-- =====================================================================
-- apt_  APPOINTMENTS                                              (Dev A)
-- Double-booking prevention = transaction + SELECT ... FOR UPDATE
-- =====================================================================
CREATE TABLE apt_treatment_plans (            -- linked booking (multi-visit, e.g. braces)
  treatment_plan_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id          INT UNSIGNED NOT NULL,
  service_id          INT UNSIGNED NOT NULL,
  assigned_dentist_id INT UNSIGNED NULL,
  planned_visits      INT UNSIGNED NOT NULL DEFAULT 1,
  interval_days       INT UNSIGNED NULL,       -- e.g. 30 for monthly adjustment
  status              ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
  notes               TEXT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_apt_treatment_plans_patient_id FOREIGN KEY (patient_id) REFERENCES pat_patients(patient_id),
  CONSTRAINT fk_apt_treatment_plans_service_id FOREIGN KEY (service_id) REFERENCES svc_services(service_id),
  CONSTRAINT fk_apt_treatment_plans_assigned_dentist_id FOREIGN KEY (assigned_dentist_id) REFERENCES usr_dentists(dentist_id)
) ENGINE=InnoDB;

CREATE TABLE apt_appointments (
  appointment_id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id                  INT UNSIGNED NOT NULL,
  service_id                  INT UNSIGNED NOT NULL,
  requested_dentist_id        INT UNSIGNED NULL,     -- what the patient picked (NULL = no preference)
  assigned_dentist_id         INT UNSIGNED NOT NULL, -- who actually got it (round-robin result)
  treatment_plan_id           INT UNSIGNED NULL,
  visit_number                INT UNSIGNED NULL,     -- visit N within the treatment plan
  start_at                    DATETIME NOT NULL,
  end_at                      DATETIME NOT NULL,     -- start + duration + buffers
  status                      ENUM('pending','confirmed','rescheduled','cancelled','completed','no_show') NOT NULL DEFAULT 'pending',
  is_emergency                TINYINT(1) NOT NULL DEFAULT 0,
  needs_reschedule            TINYINT(1) NOT NULL DEFAULT 0,  -- flagged by time-off/override
  rescheduled_from_appointment_id INT UNSIGNED NULL,
  cancel_reason               VARCHAR(500) NULL,
  rejection_reason            VARCHAR(500) NULL,
  patient_notes               VARCHAR(500) NULL,
  booked_by_user_id           INT UNSIGNED NULL,     -- patient's user or staff who booked
  confirmed_by_user_id        INT UNSIGNED NULL,
  created_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_apt_appointments_patient_id           FOREIGN KEY (patient_id) REFERENCES pat_patients(patient_id),
  CONSTRAINT fk_apt_appointments_service_id           FOREIGN KEY (service_id) REFERENCES svc_services(service_id),
  CONSTRAINT fk_apt_appointments_requested_dentist_id FOREIGN KEY (requested_dentist_id) REFERENCES usr_dentists(dentist_id),
  CONSTRAINT fk_apt_appointments_assigned_dentist_id  FOREIGN KEY (assigned_dentist_id)  REFERENCES usr_dentists(dentist_id),
  CONSTRAINT fk_apt_appointments_treatment_plan_id    FOREIGN KEY (treatment_plan_id) REFERENCES apt_treatment_plans(treatment_plan_id),
  CONSTRAINT fk_apt_appointments_rescheduled_from     FOREIGN KEY (rescheduled_from_appointment_id) REFERENCES apt_appointments(appointment_id),
  CONSTRAINT fk_apt_appointments_booked_by_user_id    FOREIGN KEY (booked_by_user_id) REFERENCES usr_users(user_id),
  CONSTRAINT fk_apt_appointments_confirmed_by_user_id FOREIGN KEY (confirmed_by_user_id) REFERENCES usr_users(user_id),
  INDEX idx_apt_appointments_dentist_start (assigned_dentist_id, start_at, end_at),
  INDEX idx_apt_appointments_patient (patient_id, start_at),
  INDEX idx_apt_appointments_status (status)
) ENGINE=InnoDB;

-- Temporary hold while the patient is filling the booking form (expires in ~5 min)
CREATE TABLE apt_slot_locks (
  slot_lock_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dentist_id       INT UNSIGNED NOT NULL,
  start_at         DATETIME NOT NULL,
  end_at           DATETIME NOT NULL,
  session_id       VARCHAR(128) NOT NULL,
  expires_at       DATETIME NOT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_apt_slot_locks_dentist_start UNIQUE (dentist_id, start_at),
  CONSTRAINT fk_apt_slot_locks_dentist_id FOREIGN KEY (dentist_id) REFERENCES usr_dentists(dentist_id),
  INDEX idx_apt_slot_locks_expires_at (expires_at)
) ENGINE=InnoDB;

-- =====================================================================
-- pat_  (continued: tables that reference appointments)           (Dev B)
-- =====================================================================
CREATE TABLE pat_visit_records (
  visit_record_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id       INT UNSIGNED NOT NULL,
  appointment_id   INT UNSIGNED NULL,
  dentist_id       INT UNSIGNED NOT NULL,
  visit_date       DATE NOT NULL,
  chief_complaint  TEXT NULL,
  diagnosis        TEXT NULL,
  clinical_notes   TEXT NULL,
  blood_pressure  VARCHAR(20) NULL,
  pulse           SMALLINT UNSIGNED NULL,
  last_xray_date  DATE NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pat_visit_records_patient_id     FOREIGN KEY (patient_id)     REFERENCES pat_patients(patient_id),
  CONSTRAINT fk_pat_visit_records_appointment_id FOREIGN KEY (appointment_id) REFERENCES apt_appointments(appointment_id),
  CONSTRAINT fk_pat_visit_records_dentist_id     FOREIGN KEY (dentist_id)     REFERENCES usr_dentists(dentist_id),
  INDEX idx_pat_visit_records_patient_date (patient_id, visit_date)
) ENGINE=InnoDB;

CREATE TABLE pat_prescriptions (
  prescription_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visit_record_id  INT UNSIGNED NOT NULL,
  patient_id       INT UNSIGNED NOT NULL,
  dentist_id       INT UNSIGNED NOT NULL,
  medication_name  VARCHAR(150) NOT NULL,
  dosage           VARCHAR(100) NULL,
  frequency        VARCHAR(100) NULL,
  duration         VARCHAR(100) NULL,
  instructions     VARCHAR(500) NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pat_prescriptions_visit_record_id FOREIGN KEY (visit_record_id) REFERENCES pat_visit_records(visit_record_id),
  CONSTRAINT fk_pat_prescriptions_patient_id      FOREIGN KEY (patient_id) REFERENCES pat_patients(patient_id),
  CONSTRAINT fk_pat_prescriptions_dentist_id      FOREIGN KEY (dentist_id) REFERENCES usr_dentists(dentist_id)
) ENGINE=InnoDB;

CREATE TABLE pat_lab_requests (
  lab_request_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id       INT UNSIGNED NOT NULL,
  visit_record_id  INT UNSIGNED NULL,
  dentist_id       INT UNSIGNED NOT NULL,
  scan_type        VARCHAR(100) NOT NULL,       -- e.g. panoramic, CBCT
  reason           VARCHAR(500) NULL,
  status           ENUM('requested','completed','cancelled') NOT NULL DEFAULT 'requested',
  requested_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pat_lab_requests_patient_id      FOREIGN KEY (patient_id) REFERENCES pat_patients(patient_id),
  CONSTRAINT fk_pat_lab_requests_visit_record_id FOREIGN KEY (visit_record_id) REFERENCES pat_visit_records(visit_record_id),
  CONSTRAINT fk_pat_lab_requests_dentist_id      FOREIGN KEY (dentist_id) REFERENCES usr_dentists(dentist_id)
) ENGINE=InnoDB;

CREATE TABLE pat_attachments (
  attachment_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id           INT UNSIGNED NOT NULL,
  visit_record_id      INT UNSIGNED NULL,
  attachment_type      ENUM('xray','lab_result','prescription','medical_certificate','other') NOT NULL DEFAULT 'other',
  original_file_name   VARCHAR(255) NOT NULL,
  stored_file_path     VARCHAR(500) NOT NULL,   -- store files OUTSIDE the public web folder
  mime_type            VARCHAR(100) NOT NULL,
  file_size_bytes      INT UNSIGNED NOT NULL,
  uploaded_by_user_id  INT UNSIGNED NOT NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at           DATETIME NULL,
  CONSTRAINT fk_pat_attachments_patient_id          FOREIGN KEY (patient_id) REFERENCES pat_patients(patient_id),
  CONSTRAINT fk_pat_attachments_visit_record_id     FOREIGN KEY (visit_record_id) REFERENCES pat_visit_records(visit_record_id),
  CONSTRAINT fk_pat_attachments_uploaded_by_user_id FOREIGN KEY (uploaded_by_user_id) REFERENCES usr_users(user_id)
) ENGINE=InnoDB;

-- =====================================================================
-- odo_  DIGITAL ODONTOGRAM (FDI / ISO 3950)                       (Dev B)
-- ONE ROW PER FINDING (not 32 columns). See design notes in chat.
-- =====================================================================
CREATE TABLE odo_teeth (                          -- reference data, 52 rows, seeded below
  tooth_number  TINYINT UNSIGNED PRIMARY KEY,     -- FDI: 11-48 permanent, 51-85 primary
  quadrant      TINYINT UNSIGNED NOT NULL,        -- 1-4 permanent, 5-8 primary
  arch          ENUM('upper','lower') NOT NULL,
  tooth_type    ENUM('incisor','canine','premolar','molar') NOT NULL,
  is_primary    TINYINT(1) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE odo_condition_codes (
  condition_code_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code              VARCHAR(20)  NOT NULL,
  label             VARCHAR(100) NOT NULL,
  category          ENUM('condition','treatment') NOT NULL,
  applies_to        ENUM('tooth','surface') NOT NULL,
  color_hex         CHAR(7) NOT NULL DEFAULT '#FF0000',
  is_active         TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT uq_odo_condition_codes_code UNIQUE (code)
) ENGINE=InnoDB;

-- CURRENT state of the chart
CREATE TABLE odo_findings (
  finding_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id         INT UNSIGNED NOT NULL,
  tooth_number       TINYINT UNSIGNED NOT NULL,
  surface            ENUM('whole','mesial','distal','occlusal','buccal','lingual') NOT NULL DEFAULT 'whole',
  condition_code_id  INT UNSIGNED NOT NULL,
  notes              VARCHAR(500) NULL,
  visit_record_id    INT UNSIGNED NULL,
  recorded_by_dentist_id INT UNSIGNED NOT NULL,
  recorded_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_odo_findings_patient_id        FOREIGN KEY (patient_id) REFERENCES pat_patients(patient_id),
  CONSTRAINT fk_odo_findings_tooth_number      FOREIGN KEY (tooth_number) REFERENCES odo_teeth(tooth_number),
  CONSTRAINT fk_odo_findings_condition_code_id FOREIGN KEY (condition_code_id) REFERENCES odo_condition_codes(condition_code_id),
  CONSTRAINT fk_odo_findings_visit_record_id   FOREIGN KEY (visit_record_id) REFERENCES pat_visit_records(visit_record_id),
  CONSTRAINT fk_odo_findings_recorded_by_dentist_id FOREIGN KEY (recorded_by_dentist_id) REFERENCES usr_dentists(dentist_id),
  INDEX idx_odo_findings_patient_tooth (patient_id, tooth_number)
) ENGINE=InnoDB;

-- APPEND-ONLY history: every create / update / revert / remove. Never UPDATE or DELETE here.
CREATE TABLE odo_finding_history (
  finding_history_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  finding_id         INT UNSIGNED NULL,
  patient_id         INT UNSIGNED NOT NULL,
  tooth_number       TINYINT UNSIGNED NOT NULL,
  action             ENUM('created','updated','reverted','removed') NOT NULL,
  old_value          JSON NULL,
  new_value          JSON NULL,
  performed_by_dentist_id INT UNSIGNED NOT NULL,
  performed_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_odo_finding_history_patient_id   FOREIGN KEY (patient_id) REFERENCES pat_patients(patient_id),
  CONSTRAINT fk_odo_finding_history_tooth_number FOREIGN KEY (tooth_number) REFERENCES odo_teeth(tooth_number),
  CONSTRAINT fk_odo_finding_history_performed_by_dentist_id FOREIGN KEY (performed_by_dentist_id) REFERENCES usr_dentists(dentist_id),
  INDEX idx_odo_finding_history_patient_tooth (patient_id, tooth_number, performed_at)
) ENGINE=InnoDB;

-- =====================================================================
-- pay_  PAYMENTS, RECEIPTS, INSTALLMENTS                          (Dev C)
-- =====================================================================
CREATE TABLE pay_discounts (
  discount_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  discount_name     VARCHAR(100) NOT NULL,
  discount_percent  DECIMAL(5,2) NOT NULL,
  is_active         TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT uq_pay_discounts_discount_name UNIQUE (discount_name)
) ENGINE=InnoDB;

CREATE TABLE pay_payments (
  payment_id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id            INT UNSIGNED NOT NULL,
  appointment_id        INT UNSIGNED NULL,
  invoice_number        VARCHAR(50) NULL,            -- BIR invoice no. entered by staff
  subtotal_amount       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  discount_id           INT UNSIGNED NULL,
  discount_amount       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_amount          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  amount_paid           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  balance_amount        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payment_method        ENUM('cash','gcash','bank_transfer','card','other') NOT NULL,
  payment_date          DATE NOT NULL,
  status                ENUM('pending_verification','verified','rejected') NOT NULL DEFAULT 'pending_verification',
  recorded_by_user_id   INT UNSIGNED NOT NULL,
  verified_by_user_id   INT UNSIGNED NULL,
  verified_at           DATETIME NULL,
  rejection_reason      VARCHAR(500) NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_pay_payments_invoice_number UNIQUE (invoice_number),
  CONSTRAINT fk_pay_payments_patient_id          FOREIGN KEY (patient_id) REFERENCES pat_patients(patient_id),
  CONSTRAINT fk_pay_payments_appointment_id      FOREIGN KEY (appointment_id) REFERENCES apt_appointments(appointment_id),
  CONSTRAINT fk_pay_payments_discount_id         FOREIGN KEY (discount_id) REFERENCES pay_discounts(discount_id),
  CONSTRAINT fk_pay_payments_recorded_by_user_id FOREIGN KEY (recorded_by_user_id) REFERENCES usr_users(user_id),
  CONSTRAINT fk_pay_payments_verified_by_user_id FOREIGN KEY (verified_by_user_id) REFERENCES usr_users(user_id),
  INDEX idx_pay_payments_date_status (payment_date, status)
) ENGINE=InnoDB;

-- Line items: needed for the "income by dental service" dashboard
CREATE TABLE pay_payment_items (
  payment_item_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id         INT UNSIGNED NOT NULL,
  service_id         INT UNSIGNED NOT NULL,
  tooth_number       TINYINT UNSIGNED NULL,
  quantity           INT UNSIGNED NOT NULL DEFAULT 1,   -- count of the service's pricing_unit (arches, teeth, surfaces, canals...)
  unit_price_amount  DECIMAL(10,2) NOT NULL,
  line_total_amount  DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_pay_payment_items_payment_id FOREIGN KEY (payment_id) REFERENCES pay_payments(payment_id),
  CONSTRAINT fk_pay_payment_items_service_id FOREIGN KEY (service_id) REFERENCES svc_services(service_id)
) ENGINE=InnoDB;

CREATE TABLE pay_receipts (
  receipt_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id          INT UNSIGNED NOT NULL,
  receipt_number      VARCHAR(30) NOT NULL,
  issued_by_user_id   INT UNSIGNED NOT NULL,
  issued_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_pay_receipts_payment_id     UNIQUE (payment_id),
  CONSTRAINT uq_pay_receipts_receipt_number UNIQUE (receipt_number),
  CONSTRAINT fk_pay_receipts_payment_id        FOREIGN KEY (payment_id) REFERENCES pay_payments(payment_id),
  CONSTRAINT fk_pay_receipts_issued_by_user_id FOREIGN KEY (issued_by_user_id) REFERENCES usr_users(user_id)
) ENGINE=InnoDB;

CREATE TABLE pay_installment_plans (
  installment_plan_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id            INT UNSIGNED NOT NULL,
  service_id            INT UNSIGNED NOT NULL,
  treatment_plan_id     INT UNSIGNED NULL,
  total_amount          DECIMAL(10,2) NOT NULL,
  discount_id           INT UNSIGNED NULL,
  discount_amount       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  net_amount            DECIMAL(10,2) NOT NULL,
  number_of_installments INT UNSIGNED NOT NULL,
  status                ENUM('requested','approved','rejected','active','completed','cancelled') NOT NULL DEFAULT 'requested',
  rejection_reason      VARCHAR(500) NULL,
  reviewed_by_user_id   INT UNSIGNED NULL,
  reviewed_at           DATETIME NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pay_installment_plans_patient_id        FOREIGN KEY (patient_id) REFERENCES pat_patients(patient_id),
  CONSTRAINT fk_pay_installment_plans_service_id        FOREIGN KEY (service_id) REFERENCES svc_services(service_id),
  CONSTRAINT fk_pay_installment_plans_treatment_plan_id FOREIGN KEY (treatment_plan_id) REFERENCES apt_treatment_plans(treatment_plan_id),
  CONSTRAINT fk_pay_installment_plans_discount_id       FOREIGN KEY (discount_id) REFERENCES pay_discounts(discount_id),
  CONSTRAINT fk_pay_installment_plans_reviewed_by_user_id FOREIGN KEY (reviewed_by_user_id) REFERENCES usr_users(user_id)
) ENGINE=InnoDB;

CREATE TABLE pay_installment_payments (
  installment_payment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installment_plan_id    INT UNSIGNED NOT NULL,
  installment_number     INT UNSIGNED NOT NULL,
  due_date               DATE NOT NULL,
  amount_due             DECIMAL(10,2) NOT NULL,
  amount_paid            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status                 ENUM('unpaid','proof_submitted','verified','rejected','overdue') NOT NULL DEFAULT 'unpaid',
  proof_file_path        VARCHAR(500) NULL,
  proof_uploaded_at      DATETIME NULL,
  verified_by_user_id    INT UNSIGNED NULL,
  verified_at            DATETIME NULL,
  rejection_reason       VARCHAR(500) NULL,
  payment_id             INT UNSIGNED NULL,           -- set once verified and recorded in pay_payments
  CONSTRAINT uq_pay_installment_payments_plan_number UNIQUE (installment_plan_id, installment_number),
  CONSTRAINT fk_pay_installment_payments_installment_plan_id FOREIGN KEY (installment_plan_id) REFERENCES pay_installment_plans(installment_plan_id),
  CONSTRAINT fk_pay_installment_payments_verified_by_user_id FOREIGN KEY (verified_by_user_id) REFERENCES usr_users(user_id),
  CONSTRAINT fk_pay_installment_payments_payment_id          FOREIGN KEY (payment_id) REFERENCES pay_payments(payment_id)
) ENGINE=InnoDB;

-- =====================================================================
-- ntf_  EMAIL NOTIFICATIONS                                       (Dev C base, Dev A uses)
-- Reminders: insert a row with status='queued' and scheduled_for; a cron job sends it.
-- =====================================================================
CREATE TABLE ntf_templates (
  template_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_key   VARCHAR(60) NOT NULL,
  subject        VARCHAR(255) NOT NULL,
  body_html      MEDIUMTEXT NOT NULL,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_ntf_templates_template_key UNIQUE (template_key)
) ENGINE=InnoDB;

CREATE TABLE ntf_email_log (
  email_log_id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_id             INT UNSIGNED NULL,
  recipient_user_id       INT UNSIGNED NULL,
  recipient_email         VARCHAR(255) NOT NULL,
  subject                 VARCHAR(255) NOT NULL,
  body_html               MEDIUMTEXT NULL,
  related_appointment_id  INT UNSIGNED NULL,
  status                  ENUM('queued','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
  scheduled_for           DATETIME NULL,
  sent_at                 DATETIME NULL,
  error_message           VARCHAR(500) NULL,
  created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ntf_email_log_template_id            FOREIGN KEY (template_id) REFERENCES ntf_templates(template_id),
  CONSTRAINT fk_ntf_email_log_recipient_user_id      FOREIGN KEY (recipient_user_id) REFERENCES usr_users(user_id),
  CONSTRAINT fk_ntf_email_log_related_appointment_id FOREIGN KEY (related_appointment_id) REFERENCES apt_appointments(appointment_id),
  INDEX idx_ntf_email_log_status_scheduled (status, scheduled_for)
) ENGINE=InnoDB;

-- =====================================================================
-- bot_  CHATBOT "CLARY"                                           (Dev B)
-- =====================================================================
CREATE TABLE bot_responses (
  response_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  intent_key          VARCHAR(60) NOT NULL,       -- e.g. clinic_hours, services_list
  keywords            VARCHAR(500) NOT NULL,      -- comma-separated trigger words
  response_template   TEXT NOT NULL,              -- may contain {placeholders} filled from live data
  data_source         ENUM('static','clinic_hours','services','dentist_schedules') NOT NULL DEFAULT 'static',
  is_active           TINYINT(1) NOT NULL DEFAULT 1,
  updated_by_user_id  INT UNSIGNED NULL,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_bot_responses_intent_key UNIQUE (intent_key),
  CONSTRAINT fk_bot_responses_updated_by_user_id FOREIGN KEY (updated_by_user_id) REFERENCES usr_users(user_id)
) ENGINE=InnoDB;

-- Questions Clary could not answer: tells the admin which responses to add
CREATE TABLE bot_unanswered_queries (
  unanswered_query_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  query_text          VARCHAR(500) NOT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================================
-- sys_  AUDIT, BACKUP, SETTINGS, SESSIONS                         (PM)
-- =====================================================================
CREATE TABLE sys_audit_logs (
  audit_log_id    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NULL,
  action_type     VARCHAR(50) NOT NULL,        -- login, logout, create, update, delete, verify, view, ...
  table_affected  VARCHAR(64) NULL,
  record_id       VARCHAR(64) NULL,
  description     VARCHAR(500) NULL,
  old_value       JSON NULL,
  new_value       JSON NULL,
  ip_address      VARCHAR(45) NULL,
  is_archived     TINYINT(1) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sys_audit_logs_user_id FOREIGN KEY (user_id) REFERENCES usr_users(user_id),
  INDEX idx_sys_audit_logs_created_at (created_at),
  INDEX idx_sys_audit_logs_user_id (user_id),
  INDEX idx_sys_audit_logs_table_record (table_affected, record_id)
) ENGINE=InnoDB;

CREATE TABLE sys_backups (
  backup_id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  file_name           VARCHAR(255) NOT NULL,
  file_size_bytes     BIGINT UNSIGNED NULL,
  backup_type         ENUM('automatic','manual') NOT NULL,
  status              ENUM('success','failed') NOT NULL,
  restored_at         DATETIME NULL,
  created_by_user_id  INT UNSIGNED NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sys_backups_created_by_user_id FOREIGN KEY (created_by_user_id) REFERENCES usr_users(user_id)
) ENGINE=InnoDB;

CREATE TABLE sys_settings (
  setting_key    VARCHAR(60) PRIMARY KEY,
  setting_value  VARCHAR(500) NOT NULL,
  description    VARCHAR(255) NULL,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Only needed if the backend uses a MySQL session store
CREATE TABLE sys_sessions (
  session_id   VARCHAR(128) PRIMARY KEY,
  expires      INT UNSIGNED NOT NULL,
  data         MEDIUMTEXT NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SEED DATA (identical for everyone so IDs line up)
-- =====================================================================
INSERT INTO usr_roles (role_id, role_name) VALUES
  (1,'admin'),(2,'dentist'),(3,'clinic_staff'),(4,'patient');

INSERT INTO usr_specialties (specialty_id, specialty_name) VALUES
  (1,'General Dentistry'),(2,'Cosmetic Dentistry'),(3,'Orthodontics'),
  (4,'Prosthodontics'),(5,'Pediatric Dentistry'),(6,'Oral Surgery');

INSERT INTO sch_clinic_hours (day_of_week, open_time, close_time, is_closed) VALUES
  (0,NULL,NULL,1),(1,'09:00','18:00',0),(2,'09:00','18:00',0),(3,'09:00','18:00',0),
  (4,'09:00','18:00',0),(5,'09:00','18:00',0),(6,'09:00','18:00',0);

INSERT INTO sch_settings (setting_key, setting_value, description) VALUES
  ('slot_interval_minutes','30','Granularity of bookable slots'),
  ('buffer_before_minutes','0','Buffer before each treatment'),
  ('buffer_after_minutes','10','Buffer after each treatment'),
  ('min_booking_notice_hours','12','Minimum notice for normal bookings'),
  ('slot_lock_minutes','5','How long a temporary slot lock lasts'),
  ('max_patients_per_day','17','Clinic daily capacity');

INSERT INTO sys_settings (setting_key, setting_value, description) VALUES
  ('clinic_name','Guda & Soriano Dental Clinic',NULL),
  ('session_timeout_minutes','15','Inactivity timeout'),
  ('privacy_policy_version','1.0','Current DPA consent version'),
  ('gcash_qr_path','','Path to clinic GCash QR image');

INSERT INTO pay_discounts (discount_name, discount_percent) VALUES
  ('Senior Citizen',20.00),('PWD',20.00),
  ('RCT Restoration Promo',10.00);   -- 10% off pasta/restoration of the tooth that had RCT

INSERT INTO ntf_templates (template_key, subject, body_html) VALUES
  ('verify_email','Verify your OraChart account','TODO'),
  ('reset_password','Reset your OraChart password','TODO'),
  ('booking_received','We received your booking request','TODO'),
  ('booking_confirmed','Your appointment is confirmed','TODO'),
  ('booking_rejected','Your appointment request was declined','TODO'),
  ('appointment_cancelled','Your appointment was cancelled','TODO'),
  ('appointment_reminder','Appointment reminder','TODO'),
  ('post_treatment_thank_you','Thank you for visiting us','TODO'),
  ('recall_reminder','Time for your routine check-up','TODO'),
  ('treatment_reminder','Your next treatment visit','TODO'),
  ('installment_due','Installment payment reminder','TODO'),
  ('installment_approved','Your installment plan was approved','TODO'),
  ('installment_rejected','Your installment request was declined','TODO'),
  ('payment_verified','Payment verified','TODO'),
  ('payment_rejected','Payment could not be verified','TODO'),
  ('schedule_changed','Your appointment needs rescheduling','TODO');

-- 52 teeth: permanent 11-18,21-28,31-38,41-48 / primary 51-55,61-65,71-75,81-85
INSERT INTO odo_teeth (tooth_number, quadrant, arch, tooth_type, is_primary)
WITH RECURSIVE q(n) AS (SELECT 1 UNION ALL SELECT n+1 FROM q WHERE n < 8),
               p(n) AS (SELECT 1 UNION ALL SELECT n+1 FROM p WHERE n < 8)
SELECT q.n*10 + p.n,
       q.n,
       IF(q.n IN (1,2,5,6),'upper','lower'),
       CASE WHEN p.n IN (1,2) THEN 'incisor'
            WHEN p.n = 3 THEN 'canine'
            WHEN q.n <= 4 AND p.n IN (4,5) THEN 'premolar'
            ELSE 'molar' END,
       IF(q.n >= 5, 1, 0)
FROM q JOIN p
WHERE q.n <= 4 OR p.n <= 5;

INSERT INTO odo_condition_codes (code, label, category, applies_to, color_hex) VALUES
  ('CARIES','Caries / decay','condition','surface','#D32F2F'),
  ('FRACTURE','Fractured tooth','condition','tooth','#F57C00'),
  ('MISSING','Missing tooth','condition','tooth','#616161'),
  ('IMPACTED','Impacted tooth','condition','tooth','#7B1FA2'),
  ('UNERUPTED','Unerupted tooth','condition','tooth','#9E9E9E'),
  ('MOBILITY','Mobility','condition','tooth','#FBC02D'),
  ('FILLING','Filling / restoration','treatment','surface','#1976D2'),
  ('SEALANT','Sealant','treatment','surface','#0097A7'),
  ('CROWN','Crown','treatment','tooth','#FFB300'),
  ('BRIDGE','Bridge','treatment','tooth','#8D6E63'),
  ('RCT','Root canal treatment','treatment','tooth','#C2185B'),
  ('EXTRACTED','Extracted','treatment','tooth','#212121'),
  ('IMPLANT','Implant','treatment','tooth','#388E3C'),
  ('VENEER','Veneer','treatment','tooth','#26A69A'),
  ('BRACES','Orthodontic braces','treatment','tooth','#3F51B5');

-- ---------------------------------------------------------------------
-- SERVICE CATALOG (from the clinic's price list)
-- !! duration_minutes and required_specialty_id are PLACEHOLDER ESTIMATES.
-- !! Confirm both with the clinic; the booking logic depends on them.
-- ---------------------------------------------------------------------
INSERT INTO svc_categories (category_id, category_name) VALUES
  (1,'Dentures'),(2,'Braces'),(3,'Tooth Extraction'),(4,'Crowns & Bridges'),
  (5,'Fillings'),(6,'Cleaning'),(7,'Veneers'),(8,'Root Canal'),
  (9,'Whitening'),(10,'Sealants'),(11,'Gum Treatment'),(12,'Inlay / Onlay'),
  (13,'Consultation');

-- Column order: category_id, service_name, pricing_unit, price_min, price_max,
--               original_price, additional_unit_price, duration_minutes,
--               required_specialty_id, is_addon, is_bookable, is_multi_visit
INSERT INTO svc_services
 (category_id, service_name, pricing_unit, price_min_amount, price_max_amount, original_price_amount,
  additional_unit_price_amount, duration_minutes, required_specialty_id, is_addon, is_bookable, is_multi_visit)
VALUES
 -- Dentures (Prosthodontics)
 (1,'Ordinary Denture - 1 Missing Tooth','per_arch',3500,NULL,4000,NULL,60,4,0,1,1),
 (1,'Ordinary Denture - 2 to 3 Missing Teeth','per_arch',4000,NULL,6000,NULL,60,4,0,1,1),
 (1,'Ordinary Denture - 4 to 5 Missing Teeth','per_arch',5000,NULL,7000,NULL,60,4,0,1,1),
 (1,'Ordinary Denture - 6 to 7 Missing Teeth','per_arch',6500,NULL,8000,NULL,60,4,0,1,1),
 (1,'Ordinary Denture - 8 to 9 Missing Teeth','per_arch',8500,NULL,10000,NULL,60,4,0,1,1),
 (1,'Ordinary Denture - 10 or More Missing Teeth (Full Denture)','per_arch',13500,NULL,15000,NULL,60,4,0,1,1),
 (1,'Flexible Denture','per_arch',15000,NULL,NULL,NULL,60,4,0,1,1),
 (1,'Thermosens Denture','per_arch',18000,NULL,NULL,NULL,60,4,0,1,1),
 (1,'Ivocap Denture','per_arch',20000,NULL,NULL,NULL,60,4,0,1,1),
 (1,'Ivocap Denture - More Than 8 Missing Teeth','per_arch',25000,NULL,NULL,NULL,60,4,0,1,1),
 (1,'Add-on: Casted Metal Clasp','per_arch',5000,NULL,NULL,NULL,0,NULL,1,0,0),
 (1,'Add-on: Porcelain Teeth','per_arch',4000,NULL,NULL,NULL,0,NULL,1,0,0),
 (1,'Add-on: Wire Clasp','per_clasp',500,NULL,NULL,NULL,0,NULL,1,0,0),
 -- Braces (Orthodontics)
 (2,'Metal Braces (Upper & Lower) - Standard Package','package',50000,60000,NULL,NULL,60,3,0,1,1),
 (2,'Metal Braces (Upper & Lower) - Promo Package','package',30000,35000,NULL,NULL,60,3,0,1,1),
 (2,'Braces Monthly Adjustment','flat',1000,NULL,NULL,NULL,30,3,0,1,1),
 -- Extraction
 (3,'Simple Extraction','per_tooth',1000,NULL,NULL,NULL,30,1,0,1,0),
 (3,'Open Flap Extraction','per_tooth',2500,NULL,NULL,NULL,60,6,0,1,0),
 (3,'Erupted Wisdom Tooth Extraction','per_tooth',3000,9000,5000,NULL,60,6,0,1,0),
 (3,'Impacted Wisdom Tooth Extraction','per_tooth',9000,12000,15000,NULL,90,6,0,1,0),
 -- Crowns & Fixed Bridges (Prosthodontics)
 (4,'Crown/Bridge - Porcelain Fused to Metal','per_tooth',7000,NULL,9000,NULL,60,4,0,1,1),
 (4,'Crown/Bridge - Ceramage','per_tooth',9000,NULL,12000,NULL,60,4,0,1,1),
 (4,'Crown/Bridge - E-Max','per_tooth',20000,NULL,25000,NULL,60,4,0,1,1),
 (4,'Crown/Bridge - Zirconia','per_tooth',20000,NULL,25000,NULL,60,4,0,1,1),
 -- Fillings (price = first surface, additional_unit_price = each succeeding surface)
 (5,'Filling - Posterior (Molars)','per_surface',800,NULL,NULL,600,45,1,0,1,0),
 (5,'Filling - Anterior','per_surface',800,NULL,NULL,700,45,1,0,1,0),
 (5,'Add-on: Calcimol (Deep Cavity Medication)','per_tooth',400,NULL,NULL,NULL,0,NULL,1,0,0),
 -- Cleaning
 (6,'Cleaning (Oral Prophylaxis)','per_session',1000,NULL,NULL,NULL,45,1,0,1,0),
 (6,'Deep Scaling','per_session',2500,NULL,NULL,NULL,60,1,0,1,0),
 -- Veneers (Cosmetic)
 (7,'Veneer - Direct Composite','per_tooth',4000,NULL,5000,NULL,60,2,0,1,0),
 (7,'Veneer - Ceramage','per_tooth',9000,NULL,12000,NULL,60,2,0,1,1),
 (7,'Veneer - E-Max','per_tooth',20000,NULL,25000,NULL,60,2,0,1,1),
 -- Root canal
 (8,'Root Canal Therapy','per_canal',7000,NULL,NULL,NULL,60,1,0,1,1),
 (8,'Post and Core','per_post',4000,NULL,NULL,NULL,45,1,0,1,0),
 (8,'Root Canal Retreatment','per_canal',8500,NULL,NULL,NULL,60,1,0,1,1),
 -- Others
 (9,'Laser Teeth Whitening','per_session',5000,NULL,NULL,NULL,90,2,0,1,0),
 (10,'Pits and Fissure Sealant','per_tooth',1000,NULL,NULL,NULL,20,1,0,1,0),
 (11,'Gum Depigmentation','per_arch',5000,NULL,NULL,NULL,60,2,0,1,0),
 (12,'Composite Inlay/Onlay/Overlay','per_tooth',5000,NULL,NULL,NULL,60,NULL,0,1,1),
 (13,'Dental Check-up / Consultation','flat',0,NULL,NULL,NULL,30,1,0,1,0);  -- CONFIRM PRICE with clinic

INSERT INTO svc_package_terms (service_id, downpayment_amount, monthly_adjustment_amount, inclusions)
SELECT service_id, 7000, 1000,
  JSON_ARRAY('Free cleaning every month','Free orthokit','Free extraoral and intraoral photo analysis',
             'Free cast analysis','Free panoramic X-ray','Free retainers (terms apply)','Possible same-day installation')
FROM svc_services WHERE service_name = 'Metal Braces (Upper & Lower) - Standard Package';

INSERT INTO svc_package_terms (service_id, downpayment_amount, monthly_adjustment_amount, inclusions)
SELECT service_id, 4000, 1000,
  JSON_ARRAY('Free cleaning every month','Free orthokit','Free extraoral and intraoral photo analysis',
             'Free cast analysis','Free retainers (terms apply)','Possible same-day installation')
FROM svc_services WHERE service_name = 'Metal Braces (Upper & Lower) - Promo Package';
