<?php
// Amazon Rekognition API Wrapper for Face Attendance System
// Location: C:\xampp\htdocs\face-attendance-api\includes\AmazonRekognition.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config-aws.php';

class AmazonRekognition {
    private $client;
    private $collectionId;
    
    public function __construct() {
        $this->client = new \Aws\Rekognition\RekognitionClient([
            'region'      => AWS_REGION,
            'version'     => 'latest',
            'credentials' => [
                'key'    => AWS_ACCESS_KEY,
                'secret' => AWS_SECRET_KEY,
            ]
        ]);
        
        $this->collectionId = AWS_COLLECTION_ID;
        $this->ensureCollectionExists();
    }
    
    /**
     * Ensure collection exists in AWS Rekognition
     */
    private function ensureCollectionExists() {
        try {
            $this->client->describeCollection([
                'CollectionId' => $this->collectionId
            ]);
        } catch (\Aws\Rekognition\Exception\RekognitionException $e) {
            if ($e->getAwsErrorCode() === 'ResourceNotFoundException') {
                try {
                    $this->client->createCollection([
                        'CollectionId' => $this->collectionId
                    ]);
                } catch (Exception $ce) {
                    error_log('Failed to create AWS collection: ' . $ce->getMessage());
                }
            }
        } catch (Exception $e) {
            error_log('AWS collection check error: ' . $e->getMessage());
        }
    }
    
    /**
     * Reset and recreate fresh collection (clears ALL faces)
     */
    public function resetCollection() {
        try {
            try {
                $this->client->deleteCollection([
                    'CollectionId' => $this->collectionId
                ]);
            } catch (Exception $e) {
                // Ignore if didn't exist
            }
            
            $this->client->createCollection([
                'CollectionId' => $this->collectionId
            ]);
            
            return ['success' => true, 'message' => 'AWS Rekognition collection created fresh and clean.'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Index a student face with high-precision quality check
     */
    public function indexFace($image_base64, $externalImageId) {
        $imageBytes = base64_decode($image_base64);
        
        try {
            // Validate face is detected
            $detectResult = $this->client->detectFaces([
                'Image' => ['Bytes' => $imageBytes],
                'Attributes' => ['DEFAULT']
            ]);
            
            if (empty($detectResult['FaceDetails'])) {
                return ['success' => false, 'error' => 'No face detected in the image. Please ensure your face is clearly visible with good lighting.'];
            }
            
            $faceDetail = $detectResult['FaceDetails'][0];
            $faceConfidence = $faceDetail['Confidence'];
            
            if ($faceConfidence < 85) {
                return ['success' => false, 'error' => "Face quality too low (" . round($faceConfidence) . "%). Please use better lighting and face the camera directly."];
            }
            
            // Index face into AWS collection
            $result = $this->client->indexFaces([
                'CollectionId' => $this->collectionId,
                'Image' => ['Bytes' => $imageBytes],
                'ExternalImageId' => (string)$externalImageId,
                'MaxFaces' => 1,
                'QualityFilter' => 'AUTO',
            ]);
            
            if (empty($result['FaceRecords'])) {
                return ['success' => false, 'error' => 'Face quality too low for biometric registration. Please improve lighting and try again.'];
            }
            
            $faceRecord = $result['FaceRecords'][0];
            return [
                'success' => true,
                'face_id' => $faceRecord['Face']['FaceId'],
                'confidence' => $faceRecord['Face']['Confidence']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Fast duplicate check during registration
     */
    public function searchFaceForDuplicateCheck($image_base64) {
        $imageBytes = base64_decode($image_base64);
        
        try {
            $result = $this->client->searchFacesByImage([
                'CollectionId' => $this->collectionId,
                'Image' => ['Bytes' => $imageBytes],
                'FaceMatchThreshold' => 75,
                'MaxFaces' => 5
            ]);
            
            if (empty($result['FaceMatches'])) {
                return ['success' => true, 'matched' => false];
            }
            
            $bestMatch = $result['FaceMatches'][0];
            foreach ($result['FaceMatches'] as $match) {
                if ($match['Similarity'] > $bestMatch['Similarity']) {
                    $bestMatch = $match;
                }
            }
            
            return [
                'success' => true,
                'matched' => true,
                'face_id' => $bestMatch['Face']['FaceId'],
                'confidence' => $bestMatch['Similarity'],
                'external_image_id' => $bestMatch['Face']['ExternalImageId'] ?? null,
                'all_matches' => count($result['FaceMatches'])
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Fast & accurate search for attendance verification
     */
    public function searchFace($image_base64) {
        $imageBytes = base64_decode($image_base64);
        
        try {
            $result = $this->client->searchFacesByImage([
                'CollectionId' => $this->collectionId,
                'Image' => ['Bytes' => $imageBytes],
                'FaceMatchThreshold' => 70, // Optimized for fast & accurate matching
                'MaxFaces' => 1
            ]);
            
            if (empty($result['FaceMatches'])) {
                return ['success' => true, 'matched' => false];
            }
            
            $match = $result['FaceMatches'][0];
            return [
                'success' => true,
                'matched' => true,
                'face_id' => $match['Face']['FaceId'],
                'confidence' => $match['Similarity'],
                'external_image_id' => $match['Face']['ExternalImageId'] ?? null
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'matched' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete face by ID
     */
    public function deleteFace($faceId) {
        try {
            $result = $this->client->deleteFaces([
                'CollectionId' => $this->collectionId,
                'FaceIds' => [$faceId]
            ]);
            return ['success' => true, 'deleted' => $result['DeletedFaces']];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>