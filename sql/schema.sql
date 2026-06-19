/*
  SAMS MVP - SQL Server schema + demo seed
  Run this script in SQL Server Management Studio 22.
*/

IF DB_ID('sams_db') IS NULL
BEGIN
    CREATE DATABASE sams_db;
END
GO

USE sams_db;
GO

IF OBJECT_ID('announcements', 'U') IS NOT NULL DROP TABLE announcements;
IF OBJECT_ID('document_grades', 'U') IS NOT NULL DROP TABLE document_grades;
IF OBJECT_ID('visit_reports', 'U') IS NOT NULL DROP TABLE visit_reports;
IF OBJECT_ID('feedback', 'U') IS NOT NULL DROP TABLE feedback;
IF OBJECT_ID('documents', 'U') IS NOT NULL DROP TABLE documents;
IF OBJECT_ID('placements', 'U') IS NOT NULL DROP TABLE placements;
IF OBJECT_ID('users', 'U') IS NOT NULL DROP TABLE users;
IF OBJECT_ID('companies', 'U') IS NOT NULL DROP TABLE companies;
IF OBJECT_ID('programs', 'U') IS NOT NULL DROP TABLE programs;
IF OBJECT_ID('departments', 'U') IS NOT NULL DROP TABLE departments;
GO

CREATE TABLE departments (
    id INT IDENTITY(1,1) PRIMARY KEY,
    name NVARCHAR(120) NOT NULL UNIQUE
);

CREATE TABLE programs (
    id INT IDENTITY(1,1) PRIMARY KEY,
    department_id INT NOT NULL,
    name NVARCHAR(120) NOT NULL,
    CONSTRAINT fk_program_department FOREIGN KEY (department_id) REFERENCES departments(id)
);

CREATE TABLE companies (
    id INT IDENTITY(1,1) PRIMARY KEY,
    name NVARCHAR(160) NOT NULL,
    address NVARCHAR(240) NOT NULL,
    contact_name NVARCHAR(120) NOT NULL,
    contact_email NVARCHAR(160) NOT NULL,
    contact_phone NVARCHAR(60) NOT NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME()
);

CREATE TABLE users (
    id INT IDENTITY(1,1) PRIMARY KEY,
    role NVARCHAR(20) NOT NULL CHECK (role IN ('student', 'lecturer', 'supervisor', 'admin')),
    reg_number NVARCHAR(20) NULL UNIQUE,
    email NVARCHAR(180) NULL UNIQUE,
    password_hash NVARCHAR(255) NULL,
    first_name NVARCHAR(80) NOT NULL,
    last_name NVARCHAR(80) NOT NULL,
    department_id INT NULL,
    program_id INT NULL,
    company_id INT NULL,
    active_lecturer_id INT NULL,
    is_active BIT NOT NULL DEFAULT 1,
    created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    CONSTRAINT fk_user_department FOREIGN KEY (department_id) REFERENCES departments(id),
    CONSTRAINT fk_user_program FOREIGN KEY (program_id) REFERENCES programs(id),
    CONSTRAINT fk_user_company FOREIGN KEY (company_id) REFERENCES companies(id),
    CONSTRAINT fk_user_active_lecturer FOREIGN KEY (active_lecturer_id) REFERENCES users(id)
);

CREATE TABLE placements (
    id INT IDENTITY(1,1) PRIMARY KEY,
    student_id INT NOT NULL,
    company_name NVARCHAR(160) NOT NULL,
    company_address NVARCHAR(240) NOT NULL,
    department NVARCHAR(120) NULL,
    city NVARCHAR(120) NULL,
    supervisor_name NVARCHAR(120) NOT NULL,
    supervisor_email NVARCHAR(160) NOT NULL,
    supervisor_phone NVARCHAR(60) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    notes NVARCHAR(MAX) NULL,
    status NVARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected', 'confirmed', 'active')),
    lecturer_comment NVARCHAR(MAX) NULL,
    lecturer_id INT NULL,
    reviewed_by INT NULL,
    reviewed_at DATETIME2 NULL,
    supervisor_confirmed BIT NOT NULL DEFAULT 0,
    supervisor_confirmed_at DATETIME2 NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    CONSTRAINT fk_placement_student FOREIGN KEY (student_id) REFERENCES users(id),
    CONSTRAINT fk_placement_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id),
    CONSTRAINT fk_placement_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

CREATE TABLE documents (
    id INT IDENTITY(1,1) PRIMARY KEY,
    placement_id INT NOT NULL,
    student_id INT NOT NULL,
    document_type NVARCHAR(40) NOT NULL,
    file_name NVARCHAR(200) NOT NULL,
    file_path NVARCHAR(255) NOT NULL,
    uploaded_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    CONSTRAINT fk_document_placement FOREIGN KEY (placement_id) REFERENCES placements(id),
    CONSTRAINT fk_document_student FOREIGN KEY (student_id) REFERENCES users(id)
);

CREATE TABLE document_grades (
    id INT IDENTITY(1,1) PRIMARY KEY,
    document_id INT NOT NULL UNIQUE,
    lecturer_id INT NOT NULL,
    score INT NOT NULL CHECK (score BETWEEN 0 AND 100),
    comment NVARCHAR(MAX) NULL,
    graded_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    CONSTRAINT fk_doc_grade_document FOREIGN KEY (document_id) REFERENCES documents(id),
    CONSTRAINT fk_doc_grade_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id)
);

CREATE TABLE feedback (
    id INT IDENTITY(1,1) PRIMARY KEY,
    placement_id INT NOT NULL,
    student_id INT NOT NULL,
    source_role NVARCHAR(20) NOT NULL CHECK (source_role IN ('supervisor', 'lecturer')),
    source_id INT NOT NULL,
    comment NVARCHAR(MAX) NOT NULL,
    score INT NOT NULL CHECK (score BETWEEN 0 AND 100),
    created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    CONSTRAINT fk_feedback_placement FOREIGN KEY (placement_id) REFERENCES placements(id),
    CONSTRAINT fk_feedback_student FOREIGN KEY (student_id) REFERENCES users(id),
    CONSTRAINT fk_feedback_source FOREIGN KEY (source_id) REFERENCES users(id)
);

CREATE TABLE visit_reports (
    id INT IDENTITY(1,1) PRIMARY KEY,
    placement_id INT NOT NULL,
    lecturer_id INT NOT NULL,
    visit_date DATE NOT NULL,
    summary NVARCHAR(MAX) NOT NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    CONSTRAINT fk_visit_placement FOREIGN KEY (placement_id) REFERENCES placements(id),
    CONSTRAINT fk_visit_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id)
);

CREATE TABLE announcements (
    id INT IDENTITY(1,1) PRIMARY KEY,
    lecturer_id INT NOT NULL,
    title NVARCHAR(180) NOT NULL,
    message NVARCHAR(MAX) NOT NULL,
    due_date DATE NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    CONSTRAINT fk_announcement_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id)
);
GO

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
  For MVP seed, non-student passwords are plain text and validated in auth fallback.
  Replace with bcrypt hashes in production.
*/
INSERT INTO users (role, reg_number, email, password_hash, first_name, last_name, department_id, company_id) VALUES
('student', 'r218270v', NULL, NULL, 'Tendai', 'Moyo', 1, NULL),
('lecturer', NULL, 'lecturer@msu.ac.zw', 'Lecturer123!', 'Rudo', 'Chikore', 1, NULL),
('supervisor', NULL, 'supervisor@midlandstech.co.zw', 'Supervisor123!', 'Tapiwa', 'Dube', NULL, 1),
('admin', NULL, 'admin@msu.ac.zw', 'Admin123!', 'System', 'Administrator', NULL, NULL);
GO
