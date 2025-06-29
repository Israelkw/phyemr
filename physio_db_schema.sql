CREATE DATABASE IF NOT EXISTS physio_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE physio_db;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE, -- Length changed from 255 to 100
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NULL, -- Replaces full_name
    last_name VARCHAR(100) NULL,  -- Replaces full_name
    email VARCHAR(255) NULL UNIQUE,
    role VARCHAR(50) NULL, -- Nullability confirmed
    is_active TINYINT(1) DEFAULT 1, -- Added from PHP script
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_username (username),
    INDEX idx_users_email (email),
    INDEX idx_role (role) -- Added from PHP script
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NULL, -- Nullability confirmed
    last_name VARCHAR(100) NULL,  -- Nullability confirmed
    date_of_birth DATE NULL,      -- Nullability confirmed
    sex VARCHAR(20) NULL,
    address TEXT NULL,
    phone_number VARCHAR(50) NULL,
    email VARCHAR(255) NULL UNIQUE,
    insurance_details TEXT NULL,
    reason_for_visit TEXT NULL,
    assigned_clinician_id INT NULL, -- Added from PHP script
    registered_by_user_id INT NOT NULL, -- Added from PHP script (NOT NULL based on PHP)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Was registration_date in PHP, aligned to created_at
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_patients_assigned_clinician FOREIGN KEY (assigned_clinician_id) REFERENCES users(id) ON DELETE SET NULL, -- Added from PHP script
    CONSTRAINT fk_patients_registered_by_user FOREIGN KEY (registered_by_user_id) REFERENCES users(id) ON DELETE CASCADE, -- Added from PHP script

    INDEX idx_patients_name (last_name, first_name), -- Name aligned with SQL (was idx_lastname_firstname in PHP)
    INDEX idx_patients_email (email),
    INDEX idx_assigned_clinician (assigned_clinician_id) -- Added from PHP script
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS patient_form_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    submitted_by_user_id INT NOT NULL,
    form_name VARCHAR(255) NOT NULL,
    form_directory VARCHAR(255) NOT NULL,
    submission_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    treating_clinician VARCHAR(255) NULL, -- Added
    chief_complaint TEXT NULL, -- Added
    evaluation_summary_diagnosis TEXT NULL, -- Added
    submission_notes TEXT NULL, -- Added
    form_data JSON NOT NULL,
    CONSTRAINT fk_submission_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_submission_user FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE RESTRICT, -- Confirmed RESTRICT
    INDEX idx_submission_patient_id (patient_id), -- Confirmed
    INDEX idx_submission_user_id (submitted_by_user_id), -- Added
    INDEX idx_submission_form_name (form_name), -- Confirmed
    INDEX idx_submission_timestamp (submission_timestamp) -- Added
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS submission_vitals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL UNIQUE,
    temperature DECIMAL(4,1) NULL,
    pulse_rate INT NULL,
    bp_systolic INT NULL,
    bp_diastolic INT NULL,
    respiratory_rate INT NULL,
    oxygen_saturation DECIMAL(4,1) NULL,
    height_cm DECIMAL(5,1) NULL,
    weight_kg DECIMAL(5,1) NULL,
    bmi DECIMAL(4,1) NULL,
    pain_scale INT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_vitals_submission FOREIGN KEY (submission_id) REFERENCES patient_form_submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS submission_clinical_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL UNIQUE,
    allergies TEXT NULL,
    medical_history_summary TEXT NULL,
    current_medications TEXT NULL,
    evaluation_treatment_plan_summary TEXT NULL,
    evaluation_short_term_goals TEXT NULL,
    evaluation_long_term_goals TEXT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_details_submission FOREIGN KEY (submission_id) REFERENCES patient_form_submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;
