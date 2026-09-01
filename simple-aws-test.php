<?php
// Simple AWS SDK Test
echo "Testing AWS SDK...\n";

require_once 'vendor/autoload.php';
require_once 'config-aws.php';

use Aws\Rekognition\RekognitionClient;

try {
    echo "✅ AWS SDK loaded successfully\n";
    echo "✅ Config loaded successfully\n";
    
    $client = new RekognitionClient([
        'version' => 'latest',
        'region'  => AWS_REGION,
        'credentials' => [
            'key'    => AWS_ACCESS_KEY,
            'secret' => AWS_SECRET_KEY,
        ],
        'http' => [
            'verify' => false  // Disable SSL verification for local development
        ]
    ]);
    
    echo "✅ Rekognition client created successfully\n";
    echo "Region: " . AWS_REGION . "\n";
    echo "Access Key: " . substr(AWS_ACCESS_KEY, 0, 8) . "...\n";
    
    // Try to list collections (this will test connectivity)
    $result = $client->listCollections();
    echo "✅ AWS connection successful!\n";
    echo "Collections found: " . count($result['CollectionIds']) . "\n";
    
    if (in_array(AWS_COLLECTION_ID, $result['CollectionIds'])) {
        echo "✅ Collection '" . AWS_COLLECTION_ID . "' exists\n";
    } else {
        echo "⚠️ Collection '" . AWS_COLLECTION_ID . "' not found. Run setup-collection.php\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Error type: " . get_class($e) . "\n";
}
?>