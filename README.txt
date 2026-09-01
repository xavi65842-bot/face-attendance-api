# 🎓 Face Attendance System - Amazon Rekognition Edition

A complete face recognition attendance system powered by **Amazon Rekognition** for accurate and reliable student attendance tracking.

## 🚀 Features

- **Amazon Rekognition Integration**: Enterprise-grade face recognition
- **High Accuracy**: 80%+ confidence threshold for attendance
- **Duplicate Prevention**: Prevents multiple registrations of same person
- **Real-time Recognition**: Fast face matching and attendance marking
- **Session Management**: Lecturer-controlled attendance sessions
- **Comprehensive Reporting**: Detailed attendance reports and analytics

## 📋 System Requirements

- PHP 7.4+ with PDO extension
- MySQL 5.7+ or MariaDB 10.3+
- AWS Account with Rekognition access
- Composer for dependency management
- Web server (Apache/Nginx)

## 🛠️ Installation & Setup

### 1. AWS Setup (Already Done ✅)
- AWS Account created
- IAM user with AmazonRekognitionFullAccess
- Access keys generated
- Region: eu-north-1 (Stockholm)

### 2. Database Setup
```sql
-- Run this in phpMyAdmin or MySQL client
-- Import: database/schema.sql (creates all tables)
-- Then run: database/migrate-to-rekognition.sql (adds face_id column)
```

### 3. Configuration Files
Your AWS configuration is already set in `config-aws.php`:
- Access Key: AKIAY4AGEMEG34K7KB3Z
- Region: eu-north-1
- Collection: school_attendance

### 4. Test Your Setup
```bash
# Visit in browser:
http://localhost/face-attendance-api/test-rekognition.php
```

## 🔧 API Endpoints

### Student Registration
```
POST /api/register.php
Content-Type: application/json

{
  "student_id": "LCSMT-NGA-005-ADM-1001234",
  "full_name": "John Doe",
  "department": "Computer Software Engineering",
  "year_intake": "2024 September",
  "semester": 1,
  "image": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQ..."
}
```

### Face Recognition & Attendance
```
POST /api/recognize.php
Content-Type: application/json

{
  "image": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQ..."
}
```

## 📊 Confidence Thresholds

- **80%+**: Minimum confidence for attendance marking
- **90%+**: Excellent recognition quality
- **70-79%**: Recognition attempted but rejected (too low)
- **<70%**: Face not recognized

## 🎯 Frontend Integration

### Registration Form
```javascript
// Remove face-api.js dependencies
// Send image directly to register.php
const formData = {
  student_id: "LCSMT-NGA-005-ADM-1001234",
  full_name: "John Doe",
  department: "Computer Software Engineering",
  year_intake: "2024 September",
  semester: 1,
  image: base64Image  // Just the image, no face descriptors needed
};

fetch('/api/register.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(formData)
});
```

### Attendance Capture
```javascript
// Remove face-api.js dependencies
// Send captured image directly to recognize.php
const data = {
  image: base64Image  // Just the image
};

fetch('/api/recognize.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(data)
});
```

## 🗂️ Files Removed (No Longer Needed)

### Face-API.js Related:
- `nextjs-integration/` folder (entire folder)
- `models/` folder (entire folder)
- `test-face-recognition.html`
- `download-models.html`

### Face++ Related:
- `includes/FacePlusPlus.php`
- `check-faceplus.php`
- `reset-faceset.php`

## 🔍 Testing & Verification

### 1. Test AWS Connection
```bash
http://localhost/face-attendance-api/test-rekognition.php
```

### 2. Test Student Registration
```bash
# Use your frontend or test with Postman
POST http://localhost/face-attendance-api/api/register.php
```

### 3. Test Face Recognition
```bash
# Use your frontend or test with Postman
POST http://localhost/face-attendance-api/api/recognize.php
```

## 🚨 Error Handling

### Common Issues:

1. **"Face registration failed"**
   - Check image quality (clear face, good lighting)
   - Ensure face is clearly visible
   - Try different angle or lighting

2. **"AWS Connection failed"**
   - Verify AWS credentials in config-aws.php
   - Check internet connection
   - Confirm AWS region is correct

3. **"Face recognition confidence too low"**
   - Improve lighting conditions
   - Face camera directly
   - Remove glasses/masks if possible

## 📈 Performance & Limits

- **AWS Rekognition Limits**: 20M faces per collection
- **Recognition Speed**: ~1-2 seconds per image
- **Accuracy**: 99%+ with good quality images
- **Concurrent Users**: Scales with your server capacity

## 🔒 Security Features

- Face data stored securely in AWS
- No local face model files needed
- Encrypted API communications
- Duplicate registration prevention
- Session-based attendance control

## 📞 Support

If you encounter issues:
1. Run `test-rekognition.php` for diagnostics
2. Check AWS CloudWatch logs
3. Verify database schema is updated
4. Ensure all dependencies are installed

---

**Powered by Amazon Rekognition** | Last Updated: April 2026