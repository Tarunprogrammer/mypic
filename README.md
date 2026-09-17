# 📸 FaceMatch - AI Face Recognition Photo Retrieval & Management Portal

A self-hosted photo management platform built with **PHP, MySQL, HTML5, CSS3, JavaScript, and [face-api.js](https://github.com/justadudewhohacks/face-api.js)**.

Users can scan their face (via live webcam or selfie upload) to instantly find and download all event photos they appear in, while administrators can batch-upload photos with automatic facial detection and vector embedding extraction.

---

## 🌟 Key Features

- **100% Free & Open-Source AI Recognition**: Uses pre-trained deep learning convolutional neural network models (`ssd_mobilenetv1`, `face_landmark_68`, `face_recognition`) in the browser. Zero paid cloud API costs.
- **Live Webcam Scanner & Selfie Upload**: Real-time face detection oval guide, instant capture, and photo search across database.
- **Euclidean Vector Comparison**: 128-dimensional biometric facial embedding vectors are stored in MySQL and compared with high performance ($O(n)$ in milliseconds).
- **Batch Photo Uploader**: Organizers can drag and drop multiple event photos. The AI detects all faces and indexes them automatically.
- **Event & Album Management**: Organize photos by events (e.g. "Annual Gala", "Convocation", "Wedding").
- **Batch ZIP Downloader**: Download all matched photos in a single `.zip` file with original high resolution.
- **Fullscreen Lightbox**: Preview full photos with zoom before downloading.
- **Customizable Sensitivity**: Adjust face matching threshold (Euclidean distance from strict `0.45` to lenient `0.65`).

---

## 🛠️ Requirements & Tech Stack

- **Web Server**: Apache / Nginx (e.g. XAMPP, WAMP, LAMP)
- **PHP**: 7.4 or 8.x (with `pdo_mysql`, `gd`, and `zip` extensions)
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Frontend**: HTML5, CSS3 (Modern Glassmorphism & Responsive Design), Vanilla JavaScript
- **AI / ML Engine**: TensorFlow.js / face-api.js (Local pre-trained models in `assets/models/`)

---

## 🚀 Quick Setup Instructions

1. **Place Project in Web Directory**:
   Place the `mypic` folder in your server root (e.g., `C:\xampp\htdocs\mypic`).

2. **Start Apache & MySQL**:
   Open XAMPP Control Panel and start **Apache** and **MySQL**.

3. **One-Click Database Installation**:
   Open your browser and navigate to:
   ```
   http://localhost/mypic/config/install.php
   ```
   Click **🚀 Install Database & Tables**. This will automatically create the `mypic_db` database, tables, and seed the default admin account.

4. **Default Admin Login**:
   - **URL**: `http://localhost/mypic/admin/login.php`
   - **Username**: `admin`
   - **Password**: `admin123`

---

## 📂 Project Architecture

```
mypic/
├── admin/                     # Admin Portal Pages
│   ├── header.php             # Admin layout header & navigation
│   ├── footer.php             # Admin layout footer & modals
│   ├── index.php              # Dashboard stats & recent uploads
│   ├── login.php              # Admin authentication screen
│   ├── logout.php             # Admin logout handler
│   ├── events.php             # Events & Albums management
│   ├── upload.php             # Batch photo uploader with client AI
│   ├── photos.php             # Photo library & index inspection
│   └── settings.php           # Sensitivity threshold & profile settings
├── api/                       # Backend REST APIs
│   ├── db.php                 # PDO database connector & helpers
│   ├── auth.php               # Login, profile, & session API
│   ├── events.php             # Event CRUD endpoints
│   ├── upload_photo.php       # Image saving & face vector ingestion
│   ├── search_faces.php       # Euclidean vector distance search engine
│   ├── delete_photo.php       # Photo & file cleanup API
│   └── download_zip.php       # Multi-photo ZIP compressor & streamer
├── assets/                    # Static Assets
│   ├── css/
│   │   ├── style.css          # Main styling & dark glassmorphism
│   │   └── admin.css          # Admin panel layout & tables
│   ├── js/
│   │   ├── face-api.min.js    # Pre-compiled face-api.js library
│   │   ├── face-scanner.js    # Webcam & selfie face scanner
│   │   ├── admin-uploader.js  # Batch AI ingestion pipeline
│   │   └── main.js            # Toast notifications & lightbox modal
│   └── models/                # Pre-trained Neural Network Weights
├── config/
│   ├── config.php             # Global configuration & constants
│   └── install.php            # Auto-installer & diagnostic tool
├── database.sql               # MySQL database schema & seed data
├── index.php                  # Public face scanner & photo finder
└── README.md                  # Documentation
```

---

## 🎯 How It Works

1. **Photo Ingestion**: When an admin uploads an image, `face-api.js` locates all faces using SSD MobileNet V1, computes 68 facial landmark coordinates, and generates a normalized 128-float embedding vector for each face.
2. **Database Storage**: The vectors are serialized into JSON and stored in the `photo_faces` table linked to the photo record.
3. **Face Search**: When an attendee scans their face via webcam or uploads a selfie, their 128-float vector is extracted and compared against all indexed vectors in MySQL:
   $$\text{Euclidean Distance} = \sqrt{\sum_{i=1}^{128} (u_i - p_i)^2}$$
4. **Matching**: Any photo with a distance $\le \text{Threshold}$ (default $0.55$) is returned and ranked by confidence score.

---

## 🔒 Privacy & Security

- User webcam selfies are processed **in-memory** within the browser to extract coordinate numbers; the raw selfie is never saved to the server.
- Passwords are encrypted using PHP `password_hash()` with `BCRYPT`.
- All database queries use PDO prepared statements to prevent SQL injection.
