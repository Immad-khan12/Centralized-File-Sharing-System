# NetShare — Network-Based File Transfer System
### Computer Networks University Project
**Stack:** Core PHP · MySQL · Bootstrap 5 · JavaScript (AJAX) · XAMPP

---

## COMPLETE PROJECT STRUCTURE

```
netshare/
├── index.php                  ← Root redirect (login or dashboard)
├── database.sql               ← Run this first in phpMyAdmin
├── .htaccess                  ← Apache config (upload limits, security)
├── README.md                  ← This file
│
├── includes/
│   ├── db.php                 ← MySQL connection (port 3306, TCP)
│   └── functions.php          ← Helpers: IP detection, validation, logging
│
├── pages/
│   ├── login.php              ← Login + Register (combined, tabbed)
│   ├── dashboard.php          ← File upload + file list hub
│   ├── logs.php               ← Transfer audit log viewer
│   ├── network.php            ← Networking concepts explainer page
│   └── logout.php             ← Session destroyer
│
├── ajax/
│   ├── upload.php             ← AJAX endpoint: receives HTTP POST file
│   ├── download.php           ← Streams file to browser via HTTP GET
│   ├── delete.php             ← Deletes file from disk + DB
│   └── check_session.php      ← Session keepalive ping
│
├── assets/
│   ├── css/style.css          ← Full custom stylesheet (dark industrial)
│   └── js/
│       ├── main.js            ← AJAX upload, progress bar, drag-drop
│       └── network-bg.js      ← Animated network canvas (login page)
│
└── uploads/                   ← Uploaded files stored here (auto-created)
    └── .htaccess              ← Blocks direct PHP execution in uploads
```

---

## STEP-BY-STEP SETUP GUIDE

### Step 1 — Install XAMPP
Download from: https://www.apachefriends.org/
Install and open XAMPP Control Panel.
Start **Apache** and **MySQL**.

### Step 2 — Copy Project Files
Copy the entire `netshare/` folder to:
```
C:\xampp\htdocs\netshare\
```

### Step 3 — Create Database
1. Open browser → go to `http://localhost/phpmyadmin`
2. Click **SQL** tab
3. Paste the entire contents of `database.sql`
4. Click **Go**
5. You should see: "Tables created successfully!"

### Step 4 — Test Locally
Open browser → `http://localhost/netshare/`
- Default test account: **admin / password**
- Or register a new account

### Step 5 — Enable LAN Access (Test from other devices)

**Find your server's LAN IP:**
- Open Command Prompt → type `ipconfig`
- Find `IPv4 Address` under your Wi-Fi or Ethernet adapter
- Example: `192.168.1.105`

**Allow through Windows Firewall:**
- Search "Windows Firewall" → Advanced Settings
- Inbound Rules → New Rule → Port → TCP → Port 80 → Allow

**Access from other device on same Wi-Fi:**
```
http://192.168.1.105/netshare/
```

**Test between two computers:**
1. Computer A runs XAMPP (the server)
2. Computer B opens browser to `http://[Computer-A-IP]/netshare/`
3. Both can login and transfer files simultaneously!

---

## NETWORKING CONCEPTS IMPLEMENTED

### 1. Client-Server Architecture
- **Client:** Browser (any device on the LAN)
- **Server:** XAMPP/Apache running PHP on port 80
- Multiple clients connect simultaneously via TCP/IP

### 2. HTTP Request/Response
- `GET` requests: loading pages, downloading files
- `POST` requests: login form, file upload (multipart/form-data)
- JSON responses from AJAX endpoints

### 3. IP Address Tracking
- `$_SERVER['REMOTE_ADDR']` captures client LAN IP
- Stored in `transfer_logs.ip_address` for every transfer
- Function `get_client_ip()` handles proxy/VPN scenarios

### 4. Chunk-Based Transfer
- Upload handler reads file in **512KB chunks** (`fread` loop)
- Simulates TCP segment-based data transfer
- Progress bar tracks bytes sent via XHR `onprogress` events

### 5. Transfer Speed Measurement
- Formula: `speed = file_size_bytes / duration_seconds`
- Displayed as KB/s or MB/s
- Stored in `transfer_logs.speed` column

### 6. Session-Based Authentication
- `session_start()` creates unique session ID per client
- Stored in browser cookie `PHPSESSID`
- Server maps ID → user data → authenticated state

### 7. Error Handling
- PHP `UPLOAD_ERR_PARTIAL` → network interruption during upload
- XHR `onerror` event → TCP connection failure/server down
- All failures logged with `status = 'failed'`

### 8. LAN Communication
- Works on any local network (home, office, lab)
- No internet required — pure LAN operation
- Other devices connect using server's private IP (192.168.x.x)

---

## FOR PRESENTATION

Key points to explain:
1. Show the **Network Info page** — displays live HTTP headers
2. Upload a file and show the **progress bar** (chunk simulation)
3. Open **Transfer Logs** — show IP tracking and speed recording
4. Open browser DevTools → Network tab while uploading
   → Show the actual HTTP POST request to `ajax/upload.php`
5. Access from a **second device** on same Wi-Fi to prove LAN works

---

## TROUBLESHOOTING

| Problem | Solution |
|---------|----------|
| "DB connection failed" | Start MySQL in XAMPP, check credentials in `includes/db.php` |
| Upload fails silently | Check `php.ini`: `upload_max_filesize=10M`, `post_max_size=12M` |
| Can't access from other device | Check Windows Firewall, confirm same Wi-Fi network |
| Files not saving | Check `uploads/` folder permissions (should be writable) |
| Login page white screen | Check PHP errors: `http://localhost/netshare/?XDEBUG_SESSION=1` |

