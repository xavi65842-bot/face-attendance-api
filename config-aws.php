<?php
// AWS Configuration - Reads from environment variables for security
// For local development, set these in your .env file
// For Railway/production, set these in the dashboard Environment Variables

$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = array_map('trim', explode('=', $line, 2));
        $value = trim($value, '"' . "'");
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

define('AWS_ACCESS_KEY', getenv('AWS_ACCESS_KEY') ?: '');
define('AWS_SECRET_KEY', getenv('AWS_SECRET_KEY') ?: '');
define('AWS_REGION', getenv('AWS_REGION') ?: 'us-east-1');
define('AWS_COLLECTION_ID', getenv('AWS_COLLECTION_ID') ?: 'school_attendance');
?>
