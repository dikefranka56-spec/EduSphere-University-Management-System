-- =========================================================
-- EDUSPHERE UNIVERSITY MANAGEMENT SYSTEM
-- Database: edusphere
-- Compatible with PHP 8+ and MySQL / MariaDB
-- =========================================================

CREATE DATABASE IF NOT EXISTS edusphere
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE edusphere;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- 1. USERS AND AUTHENTICATION
-- =========================================================

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','student','lecturer') NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_status (status)
) ENGINE=InnoDB;

-- =========================================================
-- 2. FACULTIES
-- =========================================================

CREATE TABLE IF NOT EXISTS faculties (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    code VARCHAR(20) UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- 3. DEPARTMENTS
-- =========================================================

CREATE TABLE IF NOT EXISTS departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(20) UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_departments_faculty (faculty_id),
    CONSTRAINT fk_departments_faculty
        FOREIGN KEY (faculty_id)
        REFERENCES faculties(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 4. STUDENTS
-- =========================================================

CREATE TABLE IF NOT EXISTS students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    matric_no VARCHAR(30) NOT NULL UNIQUE,
    department_id INT UNSIGNED DEFAULT NULL,
    level INT NOT NULL DEFAULT 100,
    admission_year INT DEFAULT NULL,
    gender VARCHAR(20) DEFAULT NULL,
    dob DATE DEFAULT NULL,
    address TEXT,
    guardian_name VARCHAR(150) DEFAULT NULL,
    guardian_phone VARCHAR(40) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_students_department (department_id),
    INDEX idx_students_level (level),

    CONSTRAINT fk_students_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_students_department
        FOREIGN KEY (department_id)
        REFERENCES departments(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 5. LECTURERS
-- =========================================================

CREATE TABLE IF NOT EXISTS lecturers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    staff_id VARCHAR(30) NOT NULL UNIQUE,
    department_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(40) DEFAULT NULL,
    qualification VARCHAR(150) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_lecturers_department (department_id),

    CONSTRAINT fk_lecturers_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_lecturers_department
        FOREIGN KEY (department_id)
        REFERENCES departments(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 6. ACADEMIC SESSIONS
-- =========================================================

CREATE TABLE IF NOT EXISTS academic_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    registration_open TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- 7. SEMESTERS
-- =========================================================

CREATE TABLE IF NOT EXISTS semesters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_semester_session_name (session_id, name),
    INDEX idx_semesters_current (is_current),

    CONSTRAINT fk_semesters_session
        FOREIGN KEY (session_id)
        REFERENCES academic_sessions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- 8. COURSES
-- =========================================================

CREATE TABLE IF NOT EXISTS courses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(180) NOT NULL,
    credit_units INT NOT NULL DEFAULT 3,
    department_id INT UNSIGNED DEFAULT NULL,
    level INT NOT NULL DEFAULT 100,
    semester_id INT UNSIGNED DEFAULT NULL,
    description TEXT,
    prerequisite_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_courses_department (department_id),
    INDEX idx_courses_semester (semester_id),
    INDEX idx_courses_level (level),

    CONSTRAINT fk_courses_department
        FOREIGN KEY (department_id)
        REFERENCES departments(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_courses_semester
        FOREIGN KEY (semester_id)
        REFERENCES semesters(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_courses_prerequisite
        FOREIGN KEY (prerequisite_id)
        REFERENCES courses(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 9. COURSE / LECTURER ASSIGNMENTS
-- =========================================================

CREATE TABLE IF NOT EXISTS course_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    lecturer_id INT UNSIGNED NOT NULL,
    session_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_course_lecturer_session
        (course_id, lecturer_id, session_id),

    INDEX idx_assignments_lecturer (lecturer_id),
    INDEX idx_assignments_session (session_id),

    CONSTRAINT fk_assignments_course
        FOREIGN KEY (course_id)
        REFERENCES courses(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_assignments_lecturer
        FOREIGN KEY (lecturer_id)
        REFERENCES lecturers(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_assignments_session
        FOREIGN KEY (session_id)
        REFERENCES academic_sessions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- 10. COURSE ENROLMENTS
-- =========================================================

CREATE TABLE IF NOT EXISTS enrollments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    session_id INT UNSIGNED NOT NULL,
    semester_id INT UNSIGNED NOT NULL,
    status ENUM('pending','approved','rejected')
        NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_student_course_semester
        (student_id, course_id, session_id, semester_id),

    INDEX idx_enrollments_course (course_id),
    INDEX idx_enrollments_session (session_id),
    INDEX idx_enrollments_status (status),

    CONSTRAINT fk_enrollments_student
        FOREIGN KEY (student_id)
        REFERENCES students(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_enrollments_course
        FOREIGN KEY (course_id)
        REFERENCES courses(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_enrollments_session
        FOREIGN KEY (session_id)
        REFERENCES academic_sessions(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_enrollments_semester
        FOREIGN KEY (semester_id)
        REFERENCES semesters(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- 11. ASSESSMENTS
-- =========================================================

CREATE TABLE IF NOT EXISTS assessments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    type ENUM('CA','Exam','Assignment','Quiz')
        NOT NULL DEFAULT 'CA',
    max_score DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    weight DECIMAL(5,2) NOT NULL DEFAULT 30.00,
    due_date DATE DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_assessments_course (course_id),

    CONSTRAINT fk_assessments_course
        FOREIGN KEY (course_id)
        REFERENCES courses(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_assessments_creator
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 12. RESULTS
-- =========================================================

CREATE TABLE IF NOT EXISTS results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    enrollment_id INT UNSIGNED NOT NULL,
    assessment_id INT UNSIGNED DEFAULT NULL,
    score DECIMAL(5,2) DEFAULT NULL,
    total DECIMAL(5,2) DEFAULT NULL,
    grade VARCHAR(5) DEFAULT NULL,
    grade_point DECIMAL(3,2) DEFAULT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_enrollment_assessment
        (enrollment_id, assessment_id),

    INDEX idx_results_student (student_id),
    INDEX idx_results_published (is_published),

    CONSTRAINT fk_results_student
        FOREIGN KEY (student_id)
        REFERENCES students(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_results_enrollment
        FOREIGN KEY (enrollment_id)
        REFERENCES enrollments(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_results_assessment
        FOREIGN KEY (assessment_id)
        REFERENCES assessments(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 13. GRADING RULES
-- =========================================================

CREATE TABLE IF NOT EXISTS grading_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    min_score DECIMAL(5,2) NOT NULL,
    max_score DECIMAL(5,2) NOT NULL,
    grade VARCHAR(5) NOT NULL,
    grade_point DECIMAL(3,2) NOT NULL,

    UNIQUE KEY uq_grading_range (min_score, max_score)
) ENGINE=InnoDB;

-- =========================================================
-- 14. ATTENDANCE
-- =========================================================

CREATE TABLE IF NOT EXISTS attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    session_id INT UNSIGNED NOT NULL,
    att_date DATE NOT NULL,
    status ENUM('present','absent','late','excused')
        NOT NULL DEFAULT 'present',
    recorded_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_student_course_date
        (student_id, course_id, att_date),

    INDEX idx_attendance_course (course_id),
    INDEX idx_attendance_session (session_id),
    INDEX idx_attendance_date (att_date),

    CONSTRAINT fk_attendance_student
        FOREIGN KEY (student_id)
        REFERENCES students(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_attendance_course
        FOREIGN KEY (course_id)
        REFERENCES courses(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_attendance_session
        FOREIGN KEY (session_id)
        REFERENCES academic_sessions(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_attendance_recorder
        FOREIGN KEY (recorded_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 15. TIMETABLES
-- =========================================================

CREATE TABLE IF NOT EXISTS timetables (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    lecturer_id INT UNSIGNED NOT NULL,
    day_of_week ENUM(
        'Monday','Tuesday','Wednesday',
        'Thursday','Friday','Saturday'
    ) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(60) DEFAULT NULL,
    session_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_timetable_course (course_id),
    INDEX idx_timetable_lecturer (lecturer_id),
    INDEX idx_timetable_session_day
        (session_id, day_of_week),

    CONSTRAINT fk_timetable_course
        FOREIGN KEY (course_id)
        REFERENCES courses(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_timetable_lecturer
        FOREIGN KEY (lecturer_id)
        REFERENCES lecturers(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_timetable_session
        FOREIGN KEY (session_id)
        REFERENCES academic_sessions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- 16. FEE CATEGORIES
-- =========================================================

CREATE TABLE IF NOT EXISTS fee_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    session_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_fee_categories_session (session_id),

    CONSTRAINT fk_fee_categories_session
        FOREIGN KEY (session_id)
        REFERENCES academic_sessions(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 17. STUDENT FEE INVOICES
-- =========================================================

CREATE TABLE IF NOT EXISTS fee_invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('unpaid','partial','paid')
        NOT NULL DEFAULT 'unpaid',
    due_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_invoices_student (student_id),
    INDEX idx_invoices_status (status),

    CONSTRAINT fk_invoices_student
        FOREIGN KEY (student_id)
        REFERENCES students(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_invoices_category
        FOREIGN KEY (category_id)
        REFERENCES fee_categories(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 18. PAYMENTS
-- =========================================================

CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    method ENUM('manual','bank_transfer','gateway')
        NOT NULL DEFAULT 'manual',
    reference VARCHAR(100) DEFAULT NULL,
    gateway_status ENUM('none','pending','verified','failed')
        NOT NULL DEFAULT 'none',
    recorded_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_payments_invoice (invoice_id),
    INDEX idx_payments_student (student_id),
    INDEX idx_payments_reference (reference),

    CONSTRAINT fk_payments_invoice
        FOREIGN KEY (invoice_id)
        REFERENCES fee_invoices(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_payments_student
        FOREIGN KEY (student_id)
        REFERENCES students(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_payments_recorder
        FOREIGN KEY (recorded_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 19. ANNOUNCEMENTS
-- =========================================================

CREATE TABLE IF NOT EXISTS announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    audience ENUM('all','students','lecturers','department')
        NOT NULL DEFAULT 'all',
    department_id INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_announcements_audience (audience),

    CONSTRAINT fk_announcements_department
        FOREIGN KEY (department_id)
        REFERENCES departments(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_announcements_creator
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 20. NOTIFICATIONS
-- =========================================================

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    message VARCHAR(300) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_notifications_user_read (user_id, is_read),

    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- 21. AUDIT LOGS
-- =========================================================

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(180) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_audit_user (user_id),
    INDEX idx_audit_created (created_at),

    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 22. SYSTEM SETTINGS
-- =========================================================

CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_name VARCHAR(120) NOT NULL DEFAULT 'EduSphere',
    max_credit_units INT NOT NULL DEFAULT 24,
    min_credit_units INT NOT NULL DEFAULT 12,
    allow_registration TINYINT(1) NOT NULL DEFAULT 1,
    singleton_key TINYINT NOT NULL DEFAULT 1,
    UNIQUE KEY uq_settings_singleton (singleton_key)
) ENGINE=InnoDB;

-- =========================================================
-- 23. INITIAL GRADING SCALE
-- =========================================================

INSERT INTO grading_rules
    (min_score, max_score, grade, grade_point)
VALUES
    (70.00, 100.00, 'A', 5.00),
    (60.00, 69.99, 'B', 4.00),
    (50.00, 59.99, 'C', 3.00),
    (45.00, 49.99, 'D', 2.00),
    (40.00, 44.99, 'E', 1.00),
    (0.00, 39.99, 'F', 0.00)
ON DUPLICATE KEY UPDATE
    grade = VALUES(grade),
    grade_point = VALUES(grade_point);

-- =========================================================
-- 24. INITIAL SYSTEM SETTINGS
-- =========================================================

INSERT INTO settings
    (id, site_name, max_credit_units, min_credit_units, allow_registration)
VALUES
    (1, 'EduSphere', 24, 12, 1)
ON DUPLICATE KEY UPDATE
    site_name = VALUES(site_name);

-- =========================================================
-- 25. SAMPLE FACULTIES
-- =========================================================

INSERT INTO faculties (name, code, description)
VALUES
    ('Faculty of Science', 'SCI', 'Science and technology programmes'),
    ('Faculty of Arts', 'ART', 'Arts and humanities programmes'),
    ('Faculty of Engineering', 'ENG', 'Engineering and applied technology programmes')
ON DUPLICATE KEY UPDATE
    name = VALUES(name);

-- =========================================================
-- 26. SAMPLE DEPARTMENTS
-- =========================================================

INSERT INTO departments (faculty_id, name, code, description)
SELECT f.id, 'Computer Science', 'CSC',
       'Computer science and software development'
FROM faculties f
WHERE f.code = 'SCI'
AND NOT EXISTS (
    SELECT 1 FROM departments d WHERE d.code = 'CSC'
);

INSERT INTO departments (faculty_id, name, code, description)
SELECT f.id, 'Mathematics', 'MTH',
       'Mathematics and computational sciences'
FROM faculties f
WHERE f.code = 'SCI'
AND NOT EXISTS (
    SELECT 1 FROM departments d WHERE d.code = 'MTH'
);

INSERT INTO departments (faculty_id, name, code, description)
SELECT f.id, 'Electrical Engineering', 'EEE',
       'Electrical and electronic engineering'
FROM faculties f
WHERE f.code = 'ENG'
AND NOT EXISTS (
    SELECT 1 FROM departments d WHERE d.code = 'EEE'
);

-- =========================================================
-- 27. INITIAL ACADEMIC SESSION
-- =========================================================

INSERT INTO academic_sessions
    (name, is_current, registration_open)
VALUES
    ('2026/2027', 1, 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name);

-- =========================================================
-- 28. INITIAL SEMESTERS
-- =========================================================

INSERT INTO semesters (session_id, name, is_current)
SELECT s.id, 'First Semester', 1
FROM academic_sessions s
WHERE s.name = '2026/2027'
AND NOT EXISTS (
    SELECT 1 FROM semesters sem
    WHERE sem.session_id = s.id
      AND sem.name = 'First Semester'
);

INSERT INTO semesters (session_id, name, is_current)
SELECT s.id, 'Second Semester', 0
FROM academic_sessions s
WHERE s.name = '2026/2027'
AND NOT EXISTS (
    SELECT 1 FROM semesters sem
    WHERE sem.session_id = s.id
      AND sem.name = 'Second Semester'
);

-- =========================================================
-- END OF EDUSPHERE DATABASE SETUP
-- =========================================================
