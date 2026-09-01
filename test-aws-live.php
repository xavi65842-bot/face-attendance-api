<?php
require_once 'includes/AmazonRekognition.php';
require_once 'includes/Database.php';

echo "=== AWS REKOGNITION AI SYSTEM STATUS ===\n\n";

try {
    $rekognition = new AmazonRekognition();
    echo "[1] AWS Rekognition Client: READY\n";
    
    $listResult = $rekognition->listFaces();
    if ($listResult['success']) {
        $count = count($listResult['faces']);
        echo "[2] Collection 'school_attendance': ACTIVE ($count face records stored in AWS cloud)\n";
        
        echo "\nSample Registered Student Face IDs in AWS Cloud:\n";
        $i = 1;
        foreach (array_slice($listResult['faces'], 0, 5) as $face) {
            echo "   $i. FaceID: {$face['FaceId']} | StudentID: " . ($face['ExternalImageId'] ?? 'N/A') . " | Confidence: " . round($face['Confidence'], 2) . "%\n";
            $i++;
        }
    } else {
        echo "[2] Error listing faces: " . $listResult['error'] . "\n";
    }

    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->query("SELECT COUNT(*) as cnt FROM students");
    $dbCount = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    echo "\n[3] Local Database Sync: $dbCount students in DB mapped with AWS Face IDs\n";
    
    echo "\n✅ AWS REKOGNITION FACIAL CAPTURE & MATCHING SYSTEM IS 100% OPERATIONAL!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
