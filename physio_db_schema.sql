CREATE DATABASE IF NOT EXISTS physio_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE physio_db;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NULL,
    email VARCHAR(255) NULL UNIQUE,
    role VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_username (username),
    INDEX idx_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    date_of_birth DATE NULL,
    sex VARCHAR(20) NULL,
    address TEXT NULL,
    phone_number VARCHAR(50) NULL,
    email VARCHAR(255) NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_patients_name (last_name, first_name),
    INDEX idx_patients_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS patient_form_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    submitted_by_user_id INT NOT NULL,
    form_name VARCHAR(255) NOT NULL,
    form_directory VARCHAR(255) NOT NULL,
    submission_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    treating_clinician VARCHAR(255) NULL,
    chief_complaint TEXT NULL,
    evaluation_summary_diagnosis TEXT NULL,
    submission_notes TEXT NULL,
    form_data JSON NOT NULL,
    CONSTRAINT fk_submission_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_submission_user FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE RESTRICT, -- Or SET NULL depending on desired behavior
    INDEX idx_submission_patient_id (patient_id),
    INDEX idx_submission_user_id (submitted_by_user_id),
    INDEX idx_submission_form_name (form_name),
    INDEX idx_submission_timestamp (submission_timestamp)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS submission_vitals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL UNIQUE, -- Assuming one set of vitals per submission for simplicity
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
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- In case vitals are recorded separately
    CONSTRAINT fk_vitals_submission FOREIGN KEY (submission_id) REFERENCES patient_form_submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS submission_clinical_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL UNIQUE, -- Assuming one set of clinical details per submission
    medical_history_summary TEXT NULL,
    current_medications TEXT NULL,
    evaluation_treatment_plan_summary TEXT NULL,
    evaluation_short_term_goals TEXT NULL,
    evaluation_long_term_goals TEXT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_details_submission FOREIGN KEY (submission_id) REFERENCES patient_form_submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;
