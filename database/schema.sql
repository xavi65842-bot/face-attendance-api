-- ============================================================
-- FACE ATTENDANCE SYSTEM — DATABASE SCHEMA
-- Version: 1.1 | Safe to re-import at any time
-- ============================================================
-- HOW TO USE:
--   1. Open phpMyAdmin → http://localhost/phpmyadmin
--   2. Click the "attendance_system" database (or create it)
--   3. Click the SQL tab
--   4. Paste this entire file and click Go
--
-- This script is SAFE TO RE-RUN — it drops and recreates
-- all tables cleanly, then re-seeds all required data.
-- ============================================================

-- ============================================================
-- PART 1: DATABASE
-- ============================================================

CREATE DATABASE IF NOT EXISTS attendance_system
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE attendance_system;

-- ============================================================
-- PART 2: DROP EXISTING TABLES (clean slate, correct order)
-- Foreign-key children must be dropped before parents.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS student_faces;
DROP TABLE IF EXISTS attendance_sessions;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS lecturers;
DROP TABLE IF EXISTS test;
DROP TABLE IF EXISTS sessions;   -- remove any ghost table from old crashes

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- PART 3: STUDENTS
-- ============================================================
-- One row per registered student.

CREATE TABLE students (
    id            INT           PRIMARY KEY AUTO_INCREMENT,
    student_id    VARCHAR(100)  UNIQUE NOT NULL,   -- e.g. LCSMT-NGA-005-ADM-1001530
    full_name     VARCHAR(100)  NOT NULL,
    department    VARCHAR(100)  NOT NULL,
    year_intake   VARCHAR(50)   NOT NULL,           -- e.g. "2024 September"
    semester      INT           NOT NULL,           -- 1 – 6
    face_token      VARCHAR(200)  DEFAULT NULL,       -- legacy Face++ token
    face_descriptor LONGTEXT      DEFAULT NULL,       -- JSON 128-float descriptor from face-api.js (DEPRECATED)
    face_id         VARCHAR(100)  DEFAULT NULL,       -- Amazon Rekognition Face ID
    photo_path      VARCHAR(255)  DEFAULT NULL,       -- filename in uploads/
    registered_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_student_id (student_id),
    INDEX idx_department (department),
    INDEX idx_semester   (semester),
    INDEX idx_face_id    (face_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- PART 4: STUDENT FACES
-- ============================================================
-- Supports multiple face photos per student for better accuracy.

CREATE TABLE student_faces (
    id              INT          PRIMARY KEY AUTO_INCREMENT,
    student_id      VARCHAR(100) NOT NULL,
    face_token      VARCHAR(200) DEFAULT NULL,  -- legacy Face++ token (kept for compatibility)
    face_descriptor LONGTEXT     DEFAULT NULL,  -- JSON array of 128 floats from face-api.js
    photo_path      VARCHAR(255) DEFAULT NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    INDEX idx_face_token (face_token),
    INDEX idx_student_id (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- PART 5: LECTURERS
-- ============================================================
-- Fixed pre-assigned IDs LEC001–LEC020. Cannot be created by users.

CREATE TABLE lecturers (
    id          INT          PRIMARY KEY AUTO_INCREMENT,
    lecturer_id VARCHAR(10)  UNIQUE NOT NULL,   -- e.g. LEC001
    full_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(100) UNIQUE NOT NULL,
    department  VARCHAR(100) NOT NULL,
    is_active   TINYINT(1)   DEFAULT 1,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_lecturer_id (lecturer_id),
    INDEX idx_email       (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- PART 6: ATTENDANCE SESSIONS
-- ============================================================
-- One row per class session opened by a lecturer.

CREATE TABLE attendance_sessions (
    id                 INT          PRIMARY KEY AUTO_INCREMENT,
    session_token      VARCHAR(200) UNIQUE NOT NULL,  -- 32-char hex token
    is_active          TINYINT(1)   DEFAULT 0,        -- 1 = open, 0 = closed
    lecturer_id        VARCHAR(100) NOT NULL,
    lecturer_name      VARCHAR(100) NOT NULL,
    department         VARCHAR(100) NOT NULL,
    semester           INT          NOT NULL,
    course_code        VARCHAR(50)  NOT NULL,
    course_name        VARCHAR(100) NOT NULL,
    started_at         DATETIME     NOT NULL,
    expected_end_time  DATETIME     DEFAULT NULL,
    ended_at           DATETIME     DEFAULT NULL,
    total_students     INT          DEFAULT 0,
    marked_students    INT          DEFAULT 0,
    created_at         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_department (department),
    INDEX idx_semester   (semester),
    INDEX idx_is_active  (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- PART 7: ATTENDANCE RECORDS
-- ============================================================
-- One row per student per session. UNIQUE prevents double-marking.

CREATE TABLE attendance (
    id          INT          PRIMARY KEY AUTO_INCREMENT,
    student_id  VARCHAR(100) NOT NULL,
    session_id  INT          DEFAULT NULL,
    department  VARCHAR(100) DEFAULT NULL,
    semester    INT          DEFAULT NULL,
    course_code VARCHAR(50)  DEFAULT NULL,
    course_name VARCHAR(100) DEFAULT NULL,
    date        DATE         NOT NULL,
    time        TIME         NOT NULL,
    confidence  INT          DEFAULT 0,
    status      VARCHAR(20)  DEFAULT 'present',
    marked_by   VARCHAR(50)  DEFAULT 'system',
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES attendance_sessions(id) ON DELETE SET NULL,

    UNIQUE KEY unique_attendance (student_id, session_id),
    INDEX idx_date       (date),
    INDEX idx_student_id (student_id),
    INDEX idx_session_id (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- PART 8: TEST TABLE  (connection health check only)
-- ============================================================

CREATE TABLE test (
    id         INT          PRIMARY KEY AUTO_INCREMENT,
    message    VARCHAR(100),
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- PART 9: SEED DATA
-- ============================================================

-- Health-check row
INSERT INTO test (message) VALUES ('Database is ready!');

-- ── Lecturers (LEC001–LEC020, fixed IDs) ──────────────────────────────────
INSERT INTO lecturers (lecturer_id, full_name, email, department) VALUES
('LEC001', 'Lecturer 1',  'lec001@school.edu', 'JSS1'),
('LEC002', 'Lecturer 2',  'lec002@school.edu', 'JSS1'),
('LEC003', 'Lecturer 3',  'lec003@school.edu', 'JSS2'),
('LEC004', 'Lecturer 4',  'lec004@school.edu', 'JSS2'),
('LEC005', 'Lecturer 5',  'lec005@school.edu', 'JSS3'),
('LEC006', 'Lecturer 6',  'lec006@school.edu', 'JSS3'),
('LEC007', 'Lecturer 7',  'lec007@school.edu', 'SS1'),
('LEC008', 'Lecturer 8',  'lec008@school.edu', 'SS1'),
('LEC009', 'Lecturer 9',  'lec009@school.edu', 'SS2'),
('LEC010', 'Lecturer 10', 'lec010@school.edu', 'SS2'),
('LEC011', 'Lecturer 11', 'lec011@school.edu', 'SS3'),
('LEC012', 'Lecturer 12', 'lec012@school.edu', 'SS3'),
('LEC013', 'Lecturer 13', 'lec013@school.edu', 'JSS1'),
('LEC014', 'Lecturer 14', 'lec014@school.edu', 'JSS2'),
('LEC015', 'Lecturer 15', 'lec015@school.edu', 'JSS3'),
('LEC016', 'Lecturer 16', 'lec016@school.edu', 'SS1'),
('LEC017', 'Lecturer 17', 'lec017@school.edu', 'SS2'),
('LEC018', 'Lecturer 18', 'lec018@school.edu', 'SS3'),
('LEC019', 'Lecturer 19', 'lec019@school.edu', 'JSS1'),
('LEC020', 'Lecturer 20', 'lec020@school.edu', 'SS1')
ON DUPLICATE KEY UPDATE lecturer_id = lecturer_id;

-- ── Sample students (one per class so you can test immediately) ──────
INSERT INTO students (student_id, full_name, department, year_intake, semester) VALUES
('STD-2026-001', 'John Doe',      'JSS1', '2026', 1),
('STD-2026-002', 'Jane Smith',    'JSS2', '2026', 1),
('STD-2026-003', 'Alice Johnson', 'JSS3', '2026', 3),
('STD-2026-004', 'Bob Williams',  'SS1',  '2026', 1),
('STD-2026-005', 'Carol Brown',   'SS2',  '2026', 2)
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

-- ============================================================
-- PART 10: VERIFY  (these run automatically after import)
-- ============================================================

SHOW TABLES;
SELECT 'students'           AS tbl, COUNT(*) AS total_rows FROM students
UNION ALL
SELECT 'student_faces',              COUNT(*)         FROM student_faces
UNION ALL
SELECT 'lecturers',                  COUNT(*)         FROM lecturers
UNION ALL
SELECT 'attendance_sessions',        COUNT(*)         FROM attendance_sessions
UNION ALL
SELECT 'attendance',                 COUNT(*)         FROM attendance
UNION ALL
SELECT 'test',                       COUNT(*)         FROM test;
