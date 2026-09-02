<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
$autoloadExists = file_exists($autoloadPath);

if ($autoloadExists) {
    require_once $autoloadPath;
}

$classExists = class_exists('\Aws\Rekognition\RekognitionClient');

echo json_encode([
    'autoload_path' => $autoloadPath,
    'autoload_exists' => $autoloadExists,
    'class_exists' => $classExists,
    'vendor_contents' => is_dir(dirname(__DIR__) . '/vendor') ? scandir(dirname(__DIR__) . '/vendor') : null
]);
?>