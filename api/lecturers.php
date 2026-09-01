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

$action = trim($_GET['action'] ?? 'list');

// ── GET: list all lecturers ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $rows = $conn->query(
        "SELECT lecturer_id, full_name, email, department, is_active
         FROM lecturers ORDER BY lecturer_id"
    )->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'lecturers' => $rows]);
    exit();
}

// ── GET: validate a lecturer_id (used at login) ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'validate') {
    $lecturer_id = trim($_GET['lecturer_id'] ?? '');

    if (!$lecturer_id) {
        echo json_encode(['success' => false, 'message' => 'lecturer_id is required']);
        exit();
    }

    $stmt = $conn->prepare(
        "SELECT lecturer_id, full_name, email, department, is_active
         FROM lecturers WHERE lecturer_id = ? LIMIT 1"
    );
    $stmt->execute([$lecturer_id]);
    $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lecturer) {
        echo json_encode([
            'success' => false,
            'message' => "❌ Lecturer ID '{$lecturer_id}' does not exist. IDs are pre-assigned (LEC001–LEC020). Contact admin if you need help."
        ]);
        exit();
    }

    if (!$lecturer['is_active']) {
        echo json_encode([
            'success' => false,
            'message' => "❌ This lecturer account is inactive. Please contact admin."
        ]);
        exit();
    }

    echo json_encode([
        'success'  => true,
        'lecturer' => $lecturer,
        'message'  => "✅ Welcome, {$lecturer['full_name']}!"
    ]);
    exit();
}

// ── POST: update lecturer name/email (admin only) ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input       = json_decode(file_get_contents('php://input'), true);
    $lecturer_id = trim($input['lecturer_id'] ?? '');
    $full_name   = trim($input['full_name']   ?? '');
    $email       = trim($input['email']       ?? '');
    $department  = trim($input['department']  ?? '');

    if (!$lecturer_id) {
        echo json_encode(['success' => false, 'message' => 'lecturer_id is required']);
        exit();
    }

    // Confirm this ID exists — cannot create new IDs, only update existing ones
    $check = $conn->prepare("SELECT lecturer_id FROM lecturers WHERE lecturer_id = ? LIMIT 1");
    $check->execute([$lecturer_id]);
    if (!$check->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => "❌ Lecturer ID '{$lecturer_id}' not found. Only LEC001–LEC020 are valid."
        ]);
        exit();
    }

    // Check email uniqueness if provided
    if ($email) {
        $emailCheck = $conn->prepare(
            "SELECT lecturer_id FROM lecturers WHERE email = ? AND lecturer_id != ? LIMIT 1"
        );
        $emailCheck->execute([$email, $lecturer_id]);
        if ($emailCheck->fetch()) {
            echo json_encode([
                'success' => false,
                'message' => "❌ That email is already used by another lecturer."
            ]);
            exit();
        }
    }

    $fields = [];
    $params = [];
    if ($full_name)  { $fields[] = "full_name = ?";  $params[] = $full_name; }
    if ($email)      { $fields[] = "email = ?";      $params[] = $email; }
    if ($department) { $fields[] = "department = ?"; $params[] = $department; }

    if (empty($fields)) {
        echo json_encode(['success' => false, 'message' => 'Nothing to update']);
        exit();
    }

    $params[] = $lecturer_id;
    $conn->prepare("UPDATE lecturers SET " . implode(', ', $fields) . " WHERE lecturer_id = ?")
         ->execute($params);

    echo json_encode([
        'success' => true,
        'message' => "✅ Lecturer {$lecturer_id} updated successfully."
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>
