<?php
// Amazon Rekognition API Wrapper for Face Attendance System
// Location: C:\xampp\htdocs\face-attendance-api\includes\AmazonRekognition.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config-aws.php';

use Aws\Rekognition\RekognitionClient;

class AmazonRekognition {
    private $client;
    private $collectionId;
    
    public function __construct() {
        // Initialize AWS Rekognition client
        $this->client = new RekognitionClient([
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
        $this->collectionId = AWS_COLLECTION_ID;
    }
    
    /**
     * Create a face collection (run once during setup)
     */
    public function createCollection() {
        try {
            $result = $this->client->createCollection([
                'CollectionId' => $this->collectionId,
            ]);
            return [
                'success' => true,
                'message' => 'Collection created successfully',
                'collection_arn' => $result['CollectionArn']
            ];
        } catch (Exception $e) {
            // Collection might already exist
            if (strpos($e->getMessage(), 'ResourceAlreadyExistsException') !== false) {
                return [
                    'success' => true,
                    'message' => 'Collection already exists'
                ];
            }
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Index a face during student registration
     * Uses DetectFaces first to validate, then indexFaces with duplicate detection built-in
     * @param string $image_base64 - Base64 encoded image
     * @param string $externalImageId - Student ID (to link face to student)
     * @return array - Contains FaceId and confidence
     */
    public function indexFace($image_base64, $externalImageId) {
        $imageBytes = base64_decode($image_base64);
        
        try {
            // STEP A: Detect faces first to validate image quality
            $detectResult = $this->client->detectFaces([
                'Image' => ['Bytes' => $imageBytes],
                'Attributes' => ['DEFAULT']
            ]);
            
            if (empty($detectResult['FaceDetails'])) {
                return ['success' => false, 'error' => 'No face detected in the image. Please ensure your face is clearly visible.'];
            }
            
            $faceDetail = $detectResult['FaceDetails'][0];
            $faceConfidence = $faceDetail['Confidence'];
            
            if ($faceConfidence < 90) {
                return ['success' => false, 'error' => "Face quality too low ({$faceConfidence}%). Please use better lighting and face the camera directly."];
            }
            
            // STEP B: Index the face
            $result = $this->client->indexFaces([
                'CollectionId' => $this->collectionId,
                'Image' => ['Bytes' => $imageBytes],
                'ExternalImageId' => (string)$externalImageId,
                'MaxFaces' => 1,
                'QualityFilter' => 'HIGH',  // Only accept high quality faces
            ]);
            
            if (empty($result['FaceRecords'])) {
                return ['success' => false, 'error' => 'Face quality too low for registration. Please improve lighting and try again.'];
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
     * Search for a face in the collection — used for DUPLICATE CHECK during registration
     * Returns matched=true even at 60% similarity to block duplicates broadly
     */
    public function searchFaceForDuplicateCheck($image_base64) {
        $imageBytes = base64_decode($image_base64);
        
        try {
            // First detect if there's actually a face in the image
            $detectResult = $this->client->detectFaces([
                'Image' => ['Bytes' => $imageBytes],
                'Attributes' => ['DEFAULT']
            ]);
            
            if (empty($detectResult['FaceDetails'])) {
                return [
                    'success' => false,
                    'error' => 'No face detected in image for duplicate check'
                ];
            }
            
            // Now search the collection
            $result = $this->client->searchFacesByImage([
                'CollectionId' => $this->collectionId,
                'Image' => ['Bytes' => $imageBytes],
                'FaceMatchThreshold' => 70,  // 70% threshold for duplicate detection
                'MaxFaces' => 5
            ]);
            
            if (empty($result['FaceMatches'])) {
                return ['success' => true, 'matched' => false];
            }
            
            // Get the best match (highest similarity)
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
            
        } catch (\Aws\Rekognition\Exception\RekognitionException $e) {
            // InvalidParameterException means no face in image — treat as error, NOT as "no match"
            if (strpos($e->getMessage(), 'InvalidParameterException') !== false ||
                strpos($e->getMessage(), 'no faces') !== false) {
                return [
                    'success' => false,
                    'error' => 'No face detected in image. Please ensure your face is clearly visible.'
                ];
            }
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Search for a face in the collection — used for ATTENDANCE recognition
     */
    public function searchFace($image_base64) {
        $imageBytes = base64_decode($image_base64);
        
        try {
            $result = $this->client->searchFacesByImage([
                'CollectionId' => $this->collectionId,
                'Image' => ['Bytes' => $imageBytes],
                'FaceMatchThreshold' => 80,  // 80% threshold for attendance
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
            
        } catch (\Aws\Rekognition\Exception\RekognitionException $e) {
            if (strpos($e->getMessage(), 'InvalidParameterException') !== false ||
                strpos($e->getMessage(), 'no faces') !== false) {
                return [
                    'success' => false,
                    'matched' => false,
                    'error' => 'No face detected in image. Please face the camera directly.'
                ];
            }
            return [
                'success' => false,
                'matched' => false,
                'error' => $e->getMessage()
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
     * Delete a face from collection (when student is deleted)
     * @param string $faceId - Face ID to delete
     */
    public function deleteFace($faceId) {
        try {
            $result = $this->client->deleteFaces([
                'CollectionId' => $this->collectionId,
                'FaceIds' => [$faceId]
            ]);
            return [
                'success' => true,
                'deleted' => $result['DeletedFaces']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * List all faces in the collection (for debugging)
     */
    public function listFaces() {
        try {
            $result = $this->client->listFaces([
                'CollectionId' => $this->collectionId,
                'MaxResults' => 100
            ]);
            return [
                'success' => true,
                'faces' => $result['Faces']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
?>