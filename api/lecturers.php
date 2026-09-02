<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../includes/Database.php';

$db   = new Database();
$conn = $db->getConnection();

// Ensure columns exist and seed 13 Nigerian faculty members
try {
    $conn->exec("ALTER TABLE lecturers ADD COLUMN IF NOT EXISTS username VARCHAR(100)");
    $conn->exec("ALTER TABLE lecturers ADD COLUMN IF NOT EXISTS password VARCHAR(255)");
} catch (Exception $e) {
    // ignore if already exists or older mysql version
}

// 13 Nigerian Male Lecturers
$NIGERIAN_LECTURERS = [
    ['lecturer_id' => 'LEC001', 'username' => 'babatunde.adeyemi', 'full_name' => 'Dr. Babatunde Adeyemi', 'email' => 'b.adeyemi@salvationheritage.edu.ng', 'department' => 'Mathematics', 'password' => 'password123'],
    ['lecturer_id' => 'LEC002', 'username' => 'chukwuemeka.okafor', 'full_name' => 'Prof. Chukwuemeka Okafor', 'email' => 'c.okafor@salvationheritage.edu.ng', 'department' => 'Physics', 'password' => 'password123'],
    ['lecturer_id' => 'LEC003', 'username' => 'olumide.adeleke', 'full_name' => 'Engr. Olumide Adeleke', 'email' => 'o.adeleke@salvationheritage.edu.ng', 'department' => 'Basic Technology', 'password' => 'password123'],
    ['lecturer_id' => 'LEC004', 'username' => 'ibrahim.danjuma', 'full_name' => 'Mr. Ibrahim Danjuma', 'email' => 'i.danjuma@salvationheritage.edu.ng', 'department' => 'English Language', 'password' => 'password123'],
    ['lecturer_id' => 'LEC005', 'username' => 'femi.oladipo', 'full_name' => 'Dr. Femi Oladipo', 'email' => 'f.oladipo@salvationheritage.edu.ng', 'department' => 'Chemistry', 'password' => 'password123'],
    ['lecturer_id' => 'LEC006', 'username' => 'chidiebere.nwosu', 'full_name' => 'Mr. Chidiebere Nwosu', 'email' => 'c.nwosu@salvationheritage.edu.ng', 'department' => 'Biology', 'password' => 'password123'],
    ['lecturer_id' => 'LEC007', 'username' => 'kayode.balogun', 'full_name' => 'Dr. Kayode Balogun', 'email' => 'k.balogun@salvationheritage.edu.ng', 'department' => 'Computer Science', 'password' => 'password123'],
    ['lecturer_id' => 'LEC008', 'username' => 'tunde.bakare', 'full_name' => 'Mr. Tunde Bakare', 'email' => 't.bakare@salvationheritage.edu.ng', 'department' => 'Civic Education', 'password' => 'password123'],
    ['lecturer_id' => 'LEC009', 'username' => 'musa.garba', 'full_name' => 'Dr. Musa Garba', 'email' => 'm.garba@salvationheritage.edu.ng', 'department' => 'Agricultural Science', 'password' => 'password123'],
    ['lecturer_id' => 'LEC010', 'username' => 'segun.ogundipe', 'full_name' => 'Mr. Segun Ogundipe', 'email' => 's.ogundipe@salvationheritage.edu.ng', 'department' => 'Economics', 'password' => 'password123'],
    ['lecturer_id' => 'LEC011', 'username' => 'nnamdi.eze', 'full_name' => 'Prof. Nnamdi Eze', 'email' => 'n.eze@salvationheritage.edu.ng', 'department' => 'Geography', 'password' => 'password123'],
    ['lecturer_id' => 'LEC012', 'username' => 'kelechi.okonkwo', 'full_name' => 'Dr. Kelechi Okonkwo', 'email' => 'k.okonkwo@salvationheritage.edu.ng', 'department' => 'Further Mathematics', 'password' => 'password123'],
    ['lecturer_id' => 'LEC013', 'username' => 'aliyu.bello', 'full_name' => 'Engr. Aliyu Bello', 'email' => 'a.bello@salvationheritage.edu.ng', 'department' => 'Technical Drawing', 'password' => 'password123']
];

// Seed/update these 13 lecturers in the DB
try {
    foreach ($NIGERIAN_LECTURERS as $lec) {
        $check = $conn->prepare("SELECT id FROM lecturers WHERE lecturer_id = ?");
        $check->execute([$lec['lecturer_id']]);
        if ($check->fetch()) {
            $conn->prepare("UPDATE lecturers SET full_name = ?, email = ?, department = ?, username = ?, is_active = 1 WHERE lecturer_id = ?")
                 ->execute([$lec['full_name'], $lec['email'], $lec['department'], $lec['username'], $lec['lecturer_id']]);
        } else {
            $conn->prepare("INSERT INTO lecturers (lecturer_id, username, full_name, email, department, is_active) VALUES (?, ?, ?, ?, ?, 1)")
                 ->execute([$lec['lecturer_id'], $lec['username'], $lec['full_name'], $lec['email'], $lec['department']]);
        }
    }
} catch (Exception $e) {
    // continue
}

$action = trim($_GET['action'] ?? 'list');

// ── GET: list all lecturers ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    try {
        $rows = $conn->query(
            "SELECT lecturer_id, username, full_name, email, department, is_active
             FROM lecturers ORDER BY lecturer_id"
        )->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            $rows = $NIGERIAN_LECTURERS;
        }
    } catch (Exception $e) {
        $rows = $NIGERIAN_LECTURERS;
    }

    echo json_encode(['success' => true, 'lecturers' => $rows]);
    exit();
}

// ── POST or GET: login / validate lecturer ──────────────────────────────────
if ($action === 'login' || $action === 'validate') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $identifier = trim($input['username'] ?? $input['lecturer_id'] ?? $_GET['username'] ?? $_GET['lecturer_id'] ?? '');
    $password   = trim($input['password'] ?? $_GET['password'] ?? '');

    if (!$identifier) {
        echo json_encode(['success' => false, 'message' => 'Username or Lecturer ID is required']);
        exit();
    }

    $lecturer = null;
    try {
        $stmt = $conn->prepare(
            "SELECT lecturer_id, username, full_name, email, department, is_active
             FROM lecturers WHERE UPPER(lecturer_id) = UPPER(?) OR UPPER(username) = UPPER(?) LIMIT 1"
        );
        $stmt->execute([$identifier, $identifier]);
        $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // search in static list
    }

    if (!$lecturer) {
        foreach ($NIGERIAN_LECTURERS as $l) {
            if (strtoupper($l['lecturer_id']) === strtoupper($identifier) || strtoupper($l['username']) === strtoupper($identifier)) {
                $lecturer = $l;
                break;
            }
        }
    }

    if (!$lecturer) {
        echo json_encode([
            'success' => false,
            'message' => "Lecturer account '{$identifier}' does not exist. Please check your credentials."
        ]);
        exit();
    }

    if (isset($lecturer['is_active']) && !$lecturer['is_active']) {
        echo json_encode([
            'success' => false,
            'message' => "This faculty account is inactive. Please contact administration."
        ]);
        exit();
    }

    echo json_encode([
        'success'  => true,
        'lecturer' => [
            'lecturer_id' => $lecturer['lecturer_id'],
            'username'    => $lecturer['username'] ?? strtolower(str_replace(' ', '.', $lecturer['full_name'])),
            'full_name'   => $lecturer['full_name'],
            'email'       => $lecturer['email'],
            'department'  => $lecturer['department']
        ],
        'message'  => "Welcome, {$lecturer['full_name']}!"
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
