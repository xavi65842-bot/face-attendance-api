<?php
// Auto Database Setup for Railway / Production
// Location: C:\xampp\htdocs\face-attendance-api\api\setup-db.php

require_once 'config.php';

try {
    $db = getDB();

    // 1. Create students table
    $db->exec("CREATE TABLE IF NOT EXISTS students (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id VARCHAR(50) UNIQUE NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        department VARCHAR(100) NOT NULL,
        year_intake VARCHAR(50) NOT NULL,
        semester INT NOT NULL,
        face_token VARCHAR(200) DEFAULT NULL,
        face_descriptor LONGTEXT DEFAULT NULL,
        face_id VARCHAR(100) DEFAULT NULL,
        photo_path VARCHAR(255) DEFAULT NULL,
        registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student_id (student_id),
        INDEX idx_department (department)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    // 2. Create student_faces table
    $db->exec("CREATE TABLE IF NOT EXISTS student_faces (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id VARCHAR(50) NOT NULL,
        face_token VARCHAR(200) DEFAULT NULL,
        face_descriptor LONGTEXT DEFAULT NULL,
        photo_path VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student_id (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    // 3. Create lecturers table
    $db->exec("CREATE TABLE IF NOT EXISTS lecturers (
        id INT PRIMARY KEY AUTO_INCREMENT,
        lecturer_id VARCHAR(20) UNIQUE NOT NULL,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        department VARCHAR(100) NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_lecturer_id (lecturer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    // 4. Create attendance_sessions table
    $db->exec("CREATE TABLE IF NOT EXISTS attendance_sessions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        session_token VARCHAR(200) UNIQUE NOT NULL,
        is_active TINYINT(1) DEFAULT 0,
        lecturer_id VARCHAR(50) NOT NULL,
        lecturer_name VARCHAR(100) NOT NULL,
        department VARCHAR(100) NOT NULL,
        semester INT NOT NULL,
        course_code VARCHAR(50) NOT NULL,
        course_name VARCHAR(100) NOT NULL,
        started_at DATETIME NOT NULL,
        expected_end_time DATETIME DEFAULT NULL,
        ended_at DATETIME DEFAULT NULL,
        total_students INT DEFAULT 0,
        marked_students INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_department (department),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    // 5. Create attendance table
    $db->exec("CREATE TABLE IF NOT EXISTS attendance (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id VARCHAR(50) NOT NULL,
        session_id INT DEFAULT NULL,
        department VARCHAR(100) DEFAULT NULL,
        semester INT DEFAULT NULL,
        course_code VARCHAR(50) DEFAULT NULL,
        course_name VARCHAR(100) DEFAULT NULL,
        date DATE NOT NULL,
        time TIME NOT NULL,
        confidence INT DEFAULT 0,
        status VARCHAR(20) DEFAULT 'present',
        marked_by VARCHAR(50) DEFAULT 'system',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_student_date (student_id, date, session_id),
        INDEX idx_date (date),
        INDEX idx_student_id (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    // 6. Seed 13 Nigerian Lecturers
    $lecturers = [
        ['LEC001', 'babatunde.adeyemi', 'password123', 'Dr. Babatunde Adeyemi', 'b.adeyemi@salvationheritage.edu.ng', 'Mathematics'],
        ['LEC002', 'chukwuemeka.okafor', 'password123', 'Prof. Chukwuemeka Okafor', 'c.okafor@salvationheritage.edu.ng', 'Physics'],
        ['LEC003', 'olumide.adeleke', 'password123', 'Engr. Olumide Adeleke', 'o.adeleke@salvationheritage.edu.ng', 'Basic Technology'],
        ['LEC004', 'ibrahim.danjuma', 'password123', 'Mr. Ibrahim Danjuma', 'i.danjuma@salvationheritage.edu.ng', 'English Language'],
        ['LEC005', 'femi.oladipo', 'password123', 'Dr. Femi Oladipo', 'f.oladipo@salvationheritage.edu.ng', 'Chemistry'],
        ['LEC006', 'chidiebere.nwosu', 'password123', 'Mr. Chidiebere Nwosu', 'c.nwosu@salvationheritage.edu.ng', 'Biology'],
        ['LEC007', 'kayode.balogun', 'password123', 'Dr. Kayode Balogun', 'k.balogun@salvationheritage.edu.ng', 'Computer Science'],
        ['LEC008', 'tunde.bakare', 'password123', 'Mr. Tunde Bakare', 't.bakare@salvationheritage.edu.ng', 'Civic Education'],
        ['LEC009', 'musa.garba', 'password123', 'Dr. Musa Garba', 'm.garba@salvationheritage.edu.ng', 'Agricultural Science'],
        ['LEC010', 'segun.ogundipe', 'password123', 'Mr. Segun Ogundipe', 's.ogundipe@salvationheritage.edu.ng', 'Economics'],
        ['LEC011', 'nnamdi.eze', 'password123', 'Prof. Nnamdi Eze', 'n.eze@salvationheritage.edu.ng', 'Geography'],
        ['LEC012', 'kelechi.okonkwo', 'password123', 'Dr. Kelechi Okonkwo', 'k.okonkwo@salvationheritage.edu.ng', 'Further Mathematics'],
        ['LEC013', 'aliyu.bello', 'password123', 'Engr. Aliyu Bello', 'a.bello@salvationheritage.edu.ng', 'Technical Drawing']
    ];

    $stmt = $db->prepare("INSERT INTO lecturers (lecturer_id, username, password, full_name, email, department)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            username = VALUES(username),
            password = VALUES(password),
            full_name = VALUES(full_name),
            email = VALUES(email),
            department = VALUES(department)");

    foreach ($lecturers as $lec) {
        $stmt->execute($lec);
    }

    // 7. Seed Sample Salvation Heritage Students
    $students = [
        ['SAL-1001', 'Emmanuel Adebayo', 'SS2', '2026 January', 1],
        ['SAL-1002', 'Chidinma Okoro', 'SS3', '2026 January', 1],
        ['SAL-1003', 'Oluwaseun Balogun', 'JSS3', '2026 January', 1],
        ['SAL-1004', 'Fatima Bello', 'SS1', '2026 January', 1],
        ['SAL-1005', 'Godwin Eze', 'JSS2', '2026 January', 1]
    ];

    $stmtStu = $db->prepare("INSERT INTO students (student_id, full_name, department, year_intake, semester)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            full_name = VALUES(full_name),
            department = VALUES(department),
            year_intake = VALUES(year_intake),
            semester = VALUES(semester)");

    foreach ($students as $stu) {
        $stmtStu->execute($stu);
    }

    // Seed initial attendance for demonstration
    $today = date('Y-m-d');
    $time = date('H:i:s');
    $stmtAtt = $db->prepare("INSERT INTO attendance (student_id, department, semester, date, time, confidence, status)
        VALUES (?, ?, ?, ?, ?, 98, 'present')
        ON DUPLICATE KEY UPDATE status = VALUES(status)");

    $stmtAtt->execute(['SAL-1001', 'SS2', 1, $today, $time]);
    $stmtAtt->execute(['SAL-1002', 'SS3', 1, $today, $time]);

    sendResponse(true, 'Salvation Heritage Database schema, 13 faculty members, and sample students installed successfully!', [
        'tables_created' => ['students', 'student_faces', 'lecturers', 'attendance_sessions', 'attendance'],
        'faculty_count' => count($lecturers),
        'students_count' => count($students),
        'status' => 'READY_FOR_VERCEL'
    ]);

} catch (Exception $e) {
    sendResponse(false, 'Database setup error: ' . $e->getMessage(), null, 500);
}
?>