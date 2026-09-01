<?php
// Test System Without AWS (for development)
header('Content-Type: text/html; charset=utf-8');
echo "<h1>🧪 System Test (Without AWS)</h1>";

echo "<h2>Step 1: PHP Version Check</h2>";
$phpVersion = phpversion();
echo "<p>PHP Version: <strong>{$phpVersion}</strong></p>";

if (version_compare($phpVersion, '8.2.0', '>=')) {
    echo "<p style='color:green'>✅ PHP version is compatible</p>";
} else {
    echo "<p style='color:red'>❌ PHP version too old. Need 8.2+</p>";
}

echo "<h2>Step 2: Required Extensions</h2>";
$extensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p style='color:green'>✅ {$ext} extension loaded</p>";
    } else {
        echo "<p style='color:red'>❌ {$ext} extension missing</p>";
    }
}

echo "<h2>Step 3: Composer Dependencies</h2>";
if (file_exists('vendor/autoload.php')) {
    echo "<p style='color:green'>✅ Composer dependencies installed</p>";
    
    try {
        require_once 'vendor/autoload.php';
        echo "<p style='color:green'>✅ Autoloader works</p>";
    } catch (Exception $e) {
        echo "<p style='color:red'>❌ Autoloader error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:red'>❌ Run 'composer install' first</p>";
}

echo "<h2>Step 4: File Permissions</h2>";
$uploadDir = __DIR__ . '/uploads/';
if (is_writable($uploadDir)) {
    echo "<p style='color:green'>✅ uploads/ directory is writable</p>";
} else {
    echo "<p style='color:red'>❌ uploads/ directory not writable</p>";
}

echo "<h2>Step 5: Database Connection</h2>";
try {
    require_once 'includes/Database.php';
    $db = new Database();
    $conn = $db->getConnection();
    echo "<p style='color:green'>✅ Database connection successful</p>";
    
    // Check if students table exists
    $result = $conn->query("SHOW TABLES LIKE 'students'");
    if ($result->rowCount() > 0) {
        echo "<p style='color:green'>✅ Students table exists</p>";
        
        // Check if face_id column exists
        $result = $conn->query("DESCRIBE students");
        $columns = $result->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('face_id', $columns)) {
            echo "<p style='color:green'>✅ face_id column exists</p>";
        } else {
            echo "<p style='color:orange'>⚠️ face_id column missing. Run database/migrate-to-rekognition.sql</p>";
        }
    } else {
        echo "<p style='color:red'>❌ Students table missing. Import database/schema.sql</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Database error: " . $e->getMessage() . "</p>";
}

echo "<h2>Step 6: AWS Configuration</h2>";
if (file_exists('config-aws.php')) {
    echo "<p style='color:green'>✅ AWS config file exists</p>";
    
    require_once 'config-aws.php';
    echo "<p>Access Key: " . substr(AWS_ACCESS_KEY, 0, 8) . "...</p>";
    echo "<p>Region: " . AWS_REGION . "</p>";
    echo "<p>Collection: " . AWS_COLLECTION_ID . "</p>";
} else {
    echo "<p style='color:red'>❌ config-aws.php missing</p>";
}

echo "<h2>✅ Next Steps</h2>";
echo "<ol>";
echo "<li><strong>Fix AWS credentials</strong> - See fix-aws-credentials.md</li>";
echo "<li><strong>Run database migration</strong> - Import database/migrate-to-rekognition.sql</li>";
echo "<li><strong>Test AWS connection</strong> - Run php simple-aws-test.php</li>";
echo "<li><strong>Create collection</strong> - Run php setup-collection.php</li>";
echo "<li><strong>Test registration</strong> - Use your frontend or Postman</li>";
echo "</ol>";

echo "<hr>";
echo "<p><small>System Test | " . date('Y-m-d H:i:s') . "</small></p>";
?>