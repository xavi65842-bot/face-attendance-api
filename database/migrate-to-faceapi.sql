-- ============================================================
-- MIGRATION: Add face_descriptor columns for face-api.js
-- Run this in phpMyAdmin SQL tab on your attendance_system DB
-- Safe to run multiple times (uses IF NOT EXISTS logic)
-- ============================================================

USE attendance_system;

-- Add face_descriptor to students table
ALTER TABLE students
  ADD COLUMN IF NOT EXISTS face_descriptor LONGTEXT DEFAULT NULL
  AFTER face_token;

-- Add face_descriptor to student_faces table  
ALTER TABLE student_faces
  ADD COLUMN IF NOT EXISTS face_descriptor LONGTEXT DEFAULT NULL
  AFTER face_token;

-- Make face_token optional in student_faces (was NOT NULL before)
ALTER TABLE student_faces
  MODIFY COLUMN face_token VARCHAR(200) DEFAULT NULL;

-- Verify
DESCRIBE students;
DESCRIBE student_faces;

SELECT 'Migration complete!' AS status;
