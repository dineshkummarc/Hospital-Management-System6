-- ============================================================
-- Smart Hospital Management System - Database Schema
-- Compatible with MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

CREATE DATABASE IF NOT EXISTS hospital_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hospital_management;

-- ---------------------------------------------------------------
-- USERS (Authentication table for all roles)
-- ---------------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','doctor','patient') NOT NULL DEFAULT 'patient',
    phone VARCHAR(20),
    profile_image VARCHAR(255) DEFAULT 'default.png',
    status ENUM('active','inactive','suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- DEPARTMENTS
-- ---------------------------------------------------------------
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'hospital',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- DOCTORS
-- ---------------------------------------------------------------
CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    department_id INT NOT NULL,
    specialization VARCHAR(100),
    qualification VARCHAR(200),
    experience_years INT DEFAULT 0,
    consultation_fee DECIMAL(10,2) DEFAULT 0.00,
    available_days VARCHAR(100) DEFAULT 'Mon,Tue,Wed,Thu,Fri',
    available_from TIME DEFAULT '09:00:00',
    available_to TIME DEFAULT '17:00:00',
    bio TEXT,
    license_number VARCHAR(50),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- PATIENTS
-- ---------------------------------------------------------------
CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date_of_birth DATE,
    gender ENUM('male','female','other'),
    blood_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-'),
    address TEXT,
    emergency_contact VARCHAR(100),
    emergency_phone VARCHAR(20),
    allergies TEXT,
    chronic_diseases TEXT,
    insurance_number VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- APPOINTMENTS
-- ---------------------------------------------------------------
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    department_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('pending','approved','rejected','completed','cancelled') DEFAULT 'pending',
    reason TEXT,
    notes TEXT,
    token_number INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- PRESCRIPTIONS
-- ---------------------------------------------------------------
CREATE TABLE prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    diagnosis TEXT NOT NULL,
    medicines JSON,
    advice TEXT,
    follow_up_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- BILLING
-- ---------------------------------------------------------------
CREATE TABLE billing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    patient_id INT NOT NULL,
    consultation_fee DECIMAL(10,2) DEFAULT 0.00,
    medicine_charge DECIMAL(10,2) DEFAULT 0.00,
    room_charge DECIMAL(10,2) DEFAULT 0.00,
    test_charge DECIMAL(10,2) DEFAULT 0.00,
    other_charge DECIMAL(10,2) DEFAULT 0.00,
    discount DECIMAL(10,2) DEFAULT 0.00,
    tax DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) DEFAULT 0.00,
    payment_status ENUM('pending','paid','partial','cancelled') DEFAULT 'pending',
    payment_method ENUM('cash','card','upi','insurance','online') DEFAULT 'cash',
    invoice_number VARCHAR(50) UNIQUE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- MEDICAL RECORDS
-- ---------------------------------------------------------------
CREATE TABLE medical_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT,
    record_type ENUM('lab_report','xray','scan','prescription','discharge_summary','other') DEFAULT 'other',
    title VARCHAR(200) NOT NULL,
    description TEXT,
    file_path VARCHAR(255),
    record_date DATE DEFAULT (CURRENT_DATE),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- NOTIFICATIONS
-- ---------------------------------------------------------------
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('appointment','billing','prescription','system','alert') DEFAULT 'system',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- SEED DATA - Default Admin User (password: Admin@123)
-- ---------------------------------------------------------------
INSERT INTO users (name, email, password, role, phone, status) VALUES
('Dr. Admin', 'admin@hospital.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '9876543210', 'active');

-- ---------------------------------------------------------------
-- SEED DATA - Departments
-- ---------------------------------------------------------------
INSERT INTO departments (name, description, icon) VALUES
('Cardiology', 'Heart and cardiovascular disease specialists', 'heart-pulse'),
('Neurology', 'Brain and nervous system disorders', 'activity'),
('Orthopedics', 'Bone, joint and muscle treatment', 'clipboard2-pulse'),
('Pediatrics', 'Healthcare for infants, children and teenagers', 'emoji-smile'),
('Dermatology', 'Skin, hair and nail conditions', 'droplet'),
('Ophthalmology', 'Eye disorders and vision care', 'eye'),
('Gynecology', 'Women health and reproductive system', 'gender-female'),
('ENT', 'Ear, Nose and Throat treatment', 'ear'),
('General Medicine', 'General health consultations', 'hospital'),
('Radiology', 'Medical imaging and diagnosis', 'camera');

-- ---------------------------------------------------------------
-- SEED DATA - Sample Doctors
-- ---------------------------------------------------------------
INSERT INTO users (name, email, password, role, phone, status) VALUES
('Dr. Rajesh Kumar', 'rajesh@hospital.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', '9876543211', 'active'),
('Dr. Priya Sharma', 'priya@hospital.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', '9876543212', 'active'),
('Dr. Anil Verma', 'anil@hospital.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', '9876543213', 'active'),
('Dr. Sunita Patel', 'sunita@hospital.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', '9876543214', 'active');

INSERT INTO doctors (user_id, department_id, specialization, qualification, experience_years, consultation_fee, bio) VALUES
(2, 1, 'Interventional Cardiologist', 'MBBS, MD, DM (Cardiology)', 15, 800.00, 'Expert in heart disease prevention and treatment with 15 years of experience.'),
(3, 2, 'Neurologist', 'MBBS, MD (Medicine), DM (Neurology)', 12, 700.00, 'Specialist in brain and nervous system disorders.'),
(4, 3, 'Orthopedic Surgeon', 'MBBS, MS (Ortho)', 10, 600.00, 'Expert in bone and joint replacement surgeries.'),
(5, 5, 'Dermatologist', 'MBBS, MD (Dermatology)', 8, 500.00, 'Specialist in skin disorders and cosmetic dermatology.');

-- ---------------------------------------------------------------
-- SEED DATA - Sample Patient
-- ---------------------------------------------------------------
INSERT INTO users (name, email, password, role, phone, status) VALUES
('Amit Singh', 'amit@email.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', '9876543220', 'active');

INSERT INTO patients (user_id, date_of_birth, gender, blood_group, address) VALUES
(6, '1990-05-15', 'male', 'B+', '123 Main Street, Raipur, CG');

-- NOTE: Default password for all seeded accounts is: password
-- The hash above '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' = 'password'
