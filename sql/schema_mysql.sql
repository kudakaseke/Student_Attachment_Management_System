/*
  SAMS MVP - MySQL/MariaDB schema + demo seed
  Import in phpMyAdmin.
*/

CREATE DATABASE IF NOT EXISTS sams_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sams_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS document_grades;
DROP TABLE IF EXISTS visit_reports;
DROP TABLE IF EXISTS feedback;
DROP TABLE IF EXISTS documents;
DROP TABLE IF EXISTS placements;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS companies;
DROP TABLE IF EXISTS programs;
DROP TABLE IF EXISTS departments;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    CONSTRAINT fk_program_department FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB;

CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    address VARCHAR(240) NOT NULL,
    contact_name VARCHAR(120) NOT NULL,
    contact_email VARCHAR(160) NOT NULL,
    contact_phone VARCHAR(60) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('student', 'lecturer', 'supervisor', 'admin') NOT NULL,
    reg_number VARCHAR(20) NULL UNIQUE,
    email VARCHAR(180) NULL UNIQUE,
    password_hash VARCHAR(255) NULL,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    department_id INT NULL,
    program_id INT NULL,
    company_id INT NULL,
    active_lecturer_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_department FOREIGN KEY (department_id) REFERENCES departments(id),
    CONSTRAINT fk_user_program FOREIGN KEY (program_id) REFERENCES programs(id),
    CONSTRAINT fk_user_company FOREIGN KEY (company_id) REFERENCES companies(id),
    CONSTRAINT fk_user_active_lecturer FOREIGN KEY (active_lecturer_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE placements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    company_name VARCHAR(160) NOT NULL,
    company_address VARCHAR(240) NOT NULL,
    department VARCHAR(120) NULL,
    city VARCHAR(120) NULL,
    supervisor_name VARCHAR(120) NOT NULL,
    supervisor_email VARCHAR(160) NOT NULL,
    supervisor_phone VARCHAR(60) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    notes TEXT NULL,
    status ENUM('pending', 'approved', 'rejected', 'confirmed', 'active') NOT NULL DEFAULT 'pending',
    lecturer_comment TEXT NULL,
    lecturer_id INT NULL,
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    supervisor_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    supervisor_confirmed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_placement_student FOREIGN KEY (student_id) REFERENCES users(id),
    CONSTRAINT fk_placement_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id),
    CONSTRAINT fk_placement_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    placement_id INT NOT NULL,
    student_id INT NOT NULL,
    document_type VARCHAR(40) NOT NULL,
    file_name VARCHAR(200) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_document_placement FOREIGN KEY (placement_id) REFERENCES placements(id),
    CONSTRAINT fk_document_student FOREIGN KEY (student_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE document_grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL UNIQUE,
    lecturer_id INT NOT NULL,
    score INT NOT NULL,
    comment TEXT NULL,
    graded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_doc_grade_document FOREIGN KEY (document_id) REFERENCES documents(id),
    CONSTRAINT fk_doc_grade_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id),
    CONSTRAINT chk_doc_grade_score CHECK (score BETWEEN 0 AND 100)
) ENGINE=InnoDB;

CREATE TABLE feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    placement_id INT NOT NULL,
    student_id INT NOT NULL,
    source_role ENUM('supervisor', 'lecturer') NOT NULL,
    source_id INT NOT NULL,
    comment TEXT NOT NULL,
    score INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_feedback_placement FOREIGN KEY (placement_id) REFERENCES placements(id),
    CONSTRAINT fk_feedback_student FOREIGN KEY (student_id) REFERENCES users(id),
    CONSTRAINT fk_feedback_source FOREIGN KEY (source_id) REFERENCES users(id),
    CONSTRAINT chk_feedback_score CHECK (score BETWEEN 0 AND 100)
) ENGINE=InnoDB;

CREATE TABLE visit_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    placement_id INT NOT NULL,
    lecturer_id INT NOT NULL,
    visit_date DATE NOT NULL,
    summary TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_visit_placement FOREIGN KEY (placement_id) REFERENCES placements(id),
    CONSTRAINT fk_visit_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lecturer_id INT NOT NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    due_date DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_announcement_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id)
) ENGINE=InnoDB;

INSERT INTO departments (name) VALUES
('Information Systems'),
('Computer Science'),
('Accounting');

INSERT INTO programs (department_id, name) VALUES
(1, 'BSc Information Systems'),
(2, 'BSc Computer Science'),
(3, 'BCom Accounting');

INSERT INTO companies (name, address, contact_name, contact_email, contact_phone) VALUES
('Midlands Tech Solutions', '12 Gweru CBD, Gweru', 'Tapiwa Dube', 'supervisor@midlandstech.co.zw', '+263772000111');

/*
  Demo seed:
  - student logs in with reg number only
  - non-student credentials are plain text for MVP convenience
*/
INSERT INTO users (role, reg_number, email, password_hash, first_name, last_name, department_id, company_id) VALUES
('student', 'r218270v', NULL, 'Student123!', 'Tendai', 'Moyo', 1, NULL),
('lecturer', NULL, 'lecturer@msu.ac.zw', 'Lecturer123!', 'Rudo', 'Chikore', 1, NULL),
('supervisor', NULL, 'supervisor@midlandstech.co.zw', 'Supervisor123!', 'Tapiwa', 'Dube', NULL, 1),
('admin', NULL, 'admin@msu.ac.zw', 'Admin123!', 'System', 'Administrator', NULL, NULL);
