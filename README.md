# ◈ NetShare — Network File Transfer System

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)
![XAMPP](https://img.shields.io/badge/XAMPP-Compatible-FB7A24?style=for-the-badge&logo=xampp&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

> **Computer Networks Final Project** — A LAN-based file transfer web application demonstrating Client-Server Architecture, HTTP Protocol, TCP/IP, and real-time transfer speed calculation.

---

## 📸 Screenshots

| Login Page | Dashboard | Transfer Logs | Network Info |
|-----------|-----------|---------------|--------------|
| User Auth with IP display | File Upload & Download Hub | Audit Log Viewer | Live Network Data |

---

## 🌐 Networking Concepts Demonstrated

| Concept | How It's Used in NetShare |
|--------|--------------------------|
| **Client-Server Model** | Browser (client) sends HTTP requests to Apache/PHP (server) |
| **TCP/IP** | Every file upload/download travels over TCP on port 80 |
| **HTTP Protocol** | POST for uploads, GET for downloads, JSON responses |
| **IP Address Detection** | `get_client_ip()` reads `REMOTE_ADDR` & proxy headers |
| **Session Management** | PHP sessions simulate stateful connection over stateless HTTP |
| **Chunked Transfer** | Files are read/written in 512KB chunks (like FTP blocks) |
| **Transfer Speed** | `file_size / duration` = real bandwidth measurement |
| **CSRF Protection** | Token-based security on all POST requests |
| **Content-Type Headers** | Correct MIME types set for all file downloads |

---

## 📁 Project Structure

```
netshare/
│
├── 📄 index.php                  ← Entry point: redirects to login or dashboard
├── 📄 database.sql               ← Complete MySQL schema + test data
├── 📄 .htaccess                  ← Apache URL & security rules
│
├── 📁 includes/
│   ├── 📄 db.php                 ← MySQL connection (Client-Server on port 3306)
│   └── 📄 functions.php          ← All shared helper functions
│
├── 📁 pages/
│   ├── 📄 login.php              ← Login & Registration page
│   ├── 📄 dashboard.php          ← Main file transfer hub
│   ├── 📄 network.php            ← Live networking concepts visualizer
│   ├── 📄 logs.php               ← Transfer audit log viewer
│   └── 📄 logout.php             ← Session destroy & redirect
│
├── 📁 ajax/
│   ├── 📄 upload.php             ← AJAX file upload handler (HTTP POST)
│   ├── 📄 download.php           ← File download streamer (HTTP GET)
│   ├── 📄 delete.php             ← File deletion handler
│   └── 📄 check_session.php      ← Session keepalive/ping endpoint
│
├── 📁 assets/
│   ├── 📁 css/
│   │   └── 📄 style.css          ← Full custom UI stylesheet
│   └── 📁 js/
│       ├── 📄 main.js            ← AJAX upload, progress bar, terminal log
│       └── 📄 network-bg.js      ← Animated network canvas background
│
└── 📁 uploads/
    └── 📄 .htaccess              ← Blocks direct PHP execution in uploads
```

---

## ⚙️ Functions — A to Z

### `includes/db.php`
| Function / Action | Description |
|------------------|-------------|
| `new mysqli(...)` | Connects PHP (client) to MySQL server on port 3306 |
| `$conn->set_charset('utf8mb4')` | Ensures Unicode support |
| `http_response_code(503)` | Returns error if DB is unreachable |

---

### `includes/functions.php`
| Function | Parameters | Returns | Description |
|----------|-----------|---------|-------------|
| `start_session()` | — | void | Safely starts PHP session (checks if already active) |
| `redirect($path)` | `string $path` | void | Sends HTTP Location header and exits |
| `require_login()` | — | void | Auth guard — redirects to login if session is empty |
| `get_client_ip()` | — | `string` | Detects real client IP from HTTP headers (handles proxies) |
| `calculate_speed($bytes, $seconds)` | `int, float` | `string` | Calculates transfer speed: `bytes/time` → "4.72 MB/s" |
| `format_size($bytes)` | `int` | `string` | Converts bytes to human-readable: B / KB / MB |
| `e($str)` | `string` | `string` | XSS prevention: `htmlspecialchars()` wrapper |
| `validate_file($file)` | `array $_FILES` | `array` | Checks file type, size, and PHP upload error codes |
| `log_transfer(...)` | `conn, user_id, username, filename, filesize, type, status, speed, ip` | void | Inserts transfer event into `transfer_logs` table |

---

### `ajax/upload.php`
| Step | Action | Networking Concept |
|------|--------|-------------------|
| 1 | `REQUEST_METHOD !== 'POST'` check | Only POST allowed (HTTP method enforcement) |
| 2 | Session auth check | Stateful auth over stateless HTTP |
| 3 | CSRF token verification | Prevents cross-site request forgery |
| 4 | `validate_file($_FILES)` | Server-side validation of multipart/form-data |
| 5 | `bin2hex(random_bytes(6))` | Generates unique safe filename (prevents path traversal) |
| 6 | 512KB chunked write loop | Simulates chunked TCP file transfer |
| 7 | `calculate_speed()` | Measures actual upload bandwidth |
| 8 | `log_transfer()` | Saves audit record to database |
| 9 | Returns JSON response | Client-server response format |

---

### `ajax/download.php`
| Step | Action | Networking Concept |
|------|--------|-------------------|
| 1 | Validate `?id=` parameter | Input sanitization |
| 2 | Fetch file record from DB | Server retrieves file metadata |
| 3 | Check physical file on disk | Verifies file integrity |
| 4 | Set `Content-Type` header | HTTP MIME type declaration |
| 5 | Set `Content-Length` header | Tells browser how many bytes to expect |
| 6 | Set `Content-Disposition: attachment` | Forces file save (not browser display) |
| 7 | Set `Accept-Ranges: bytes` | Enables resume downloads |
| 8 | `readfile()` chunked stream | Streams file bytes over TCP to client |
| 9 | `calculate_speed()` | Measures download throughput |
| 10 | `log_transfer()` | Records download event |

---

### `ajax/delete.php`
| Function | Description |
|----------|-------------|
| Ownership check (`user_id` match) | Only file owner can delete |
| `@unlink($path)` | Removes physical file from disk |
| `DELETE FROM files` | Removes DB record |
| `redirect()` | Returns to dashboard after deletion |

---

### `ajax/check_session.php`
| Returns | Description |
|---------|-------------|
| `authenticated: true/false` | Session validity |
| `username` | Logged-in user |
| `session_id` | Partial session ID |
| `server_time` | Current server timestamp |
| `client_ip` | Requesting client's IP |

---

### `pages/login.php`
| Action | Description |
|--------|-------------|
| `LOGIN POST` | Validates credentials with prepared statement (SQL injection safe) |
| `password_verify()` | Bcrypt hash comparison |
| `$_SESSION` setup | Stores `user_id`, `username`, `ip` on successful login |
| `UPDATE last_login` | Records login timestamp and IP |
| `REGISTER POST` | Creates new user with `password_hash(PASSWORD_BCRYPT)` |
| Duplicate check | Prevents same username/email |
| `switchTab()` JS | Client-side tab switching |

---

### `pages/dashboard.php`
| Section | Description |
|---------|-------------|
| Stats query | Total files, storage, success/failed transfers, active users |
| File list query | JOIN files + users, ordered by upload time |
| Upload form | CSRF token, AJAX submit via `main.js` |
| Progress bar | Real-time upload progress with speed display |
| Terminal log | Shows transfer events like a network console |
| Network info card | Displays client IP, server host, protocol, session ID |
| Download button | HTTP GET request to `ajax/download.php?id=X` |

---

### `pages/network.php`
Shows live networking data:
- Client IP, Server IP, Port, Protocol (HTTP/HTTPS)
- OSI Model layer explanation
- TCP/IP stack visualization
- HTTP headers dump
- Active sessions count (last 15 minutes)

---

### `pages/logs.php`
| Feature | Description |
|---------|-------------|
| Filter by type | Upload / Download / All |
| Filter by status | Success / Failed / All |
| Shows | Username, Filename, Size, Type, Status, Speed, IP, Time |
| Color coding | Green = success, Red = failed |

---

## 🗄️ Database Schema

### Table: `users`
| Column | Type | Description |
|--------|------|-------------|
| `id` | INT UNSIGNED | Primary key |
| `username` | VARCHAR(50) | Unique username |
| `email` | VARCHAR(100) | Unique email |
| `password_hash` | VARCHAR(255) | Bcrypt hashed password |
| `ip_address` | VARCHAR(45) | LAN IP at registration |
| `last_login` | TIMESTAMP | Last login time |

### Table: `files`
| Column | Type | Description |
|--------|------|-------------|
| `id` | INT UNSIGNED | Primary key |
| `user_id` | INT UNSIGNED | FK → users.id |
| `original_name` | VARCHAR(255) | Client's original filename |
| `stored_name` | VARCHAR(255) | Unique safe filename on disk |
| `file_type` | VARCHAR(10) | Extension: pdf, jpg, png, docx |
| `file_size` | BIGINT | Size in bytes |
| `upload_ip` | VARCHAR(45) | Client IP at upload time |

### Table: `transfer_logs`
| Column | Type | Description |
|--------|------|-------------|
| `id` | INT UNSIGNED | Primary key |
| `user_id` | INT UNSIGNED | FK → users.id |
| `username` | VARCHAR(50) | Denormalized for log integrity |
| `file_name` | VARCHAR(255) | Original filename |
| `file_size` | VARCHAR(20) | Human-readable size |
| `transfer_type` | ENUM | 'upload' or 'download' |
| `status` | ENUM | 'success' or 'failed' |
| `speed` | VARCHAR(30) | e.g. "4.72 MB/s" |
| `ip_address` | VARCHAR(45) | Client LAN IP |

---

## 🚀 Installation & Setup

### Requirements
- XAMPP (Apache + PHP 8.x + MySQL)
- Web browser

### Steps

**1. Clone / Download**
```bash
git clone https://github.com/Immad-khan12/netshare.git
```

**2. Place in XAMPP**
```
C:/xampp/htdocs/netshare/
```

**3. Import Database**
- Open `http://localhost/phpmyadmin`
- Click **New** → create database `netshare`
- Click **Import** → select `database.sql` → Go

**4. Start XAMPP**
- Start **Apache** and **MySQL** in XAMPP Control Panel

**5. Open in Browser**
```
http://localhost/netshare/
```

**6. Login with test account**
```
Username: admin
Password: admin123
```

---

## 🔐 Security Features

- ✅ **SQL Injection Prevention** — All queries use prepared statements
- ✅ **XSS Prevention** — All output escaped with `htmlspecialchars()`
- ✅ **CSRF Protection** — Token verified on every POST
- ✅ **File Type Whitelist** — Only PDF, JPG, PNG, DOCX allowed
- ✅ **Safe Filenames** — Random hex names prevent path traversal
- ✅ **Auth Guards** — Every page checks `require_login()`
- ✅ **Upload Directory** — `.htaccess` blocks PHP execution

---

## 👨‍💻 Developer

**Muhammad Immad Shahzad**
- 📧 immadshahzad216@gmail.com
- 🔗 [LinkedIn](https://linkedin.com/in/immad-shahzad-010511347)
- 🐙 [GitHub](https://github.com/Immad-khan12)
- 🌐 [Portfolio](https://warm-faloodeh-6585fe.netlify.app)

---

