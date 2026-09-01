-- ============================================================
-- MIGRATION: Face-API.js to Amazon Rekognition
-- Run this ONCE to add face_id column to existing database
-- ============================================================

USE attendance_system;

-- Add face_id column to students table if it doesn't exist
ALTER TABLE students 
ADD COLUMN face_id VARCHAR(100) DEFAULT NULL AFTER face_descriptor,
ADD INDEX idx_face_id (face_id);

-- Show the updated table structure
DESCRIBE students;

-- Check if migration was successful
SELECT 'Migration completed successfully!' as status;