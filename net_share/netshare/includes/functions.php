<?php
/**
 * ============================================================
 *  functions.php — Shared Helper Utilities
 *  NetShare | Network File Transfer System
 * ============================================================
 */

// ── Session startup (call once at app entry) ──────────────
function start_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// ── Redirect helper ───────────────────────────────────────
function redirect($path) {
    header("Location: $path");
    exit();
}

// ── Auth guard: redirect to login if not logged in ────────
function require_login() {
    start_session();
    if (empty($_SESSION['user_id'])) {
        redirect('/netshare/pages/login.php');
    }
}

// ─────────────────────────────────────────────────────────
//  IP ADDRESS DETECTION
//  NETWORKING CONCEPT:
//  Every device on a network has an IP address. When a client
//  sends an HTTP request, their IP is included in the TCP
//  packet headers. PHP exposes these via $_SERVER variables.
//  Behind proxies/load-balancers, the real IP may be in
//  X-Forwarded-For header instead of REMOTE_ADDR.
// ─────────────────────────────────────────────────────────
function get_client_ip() {
    // Check for IP passed through proxy headers first
    $keys = [
        'HTTP_X_FORWARDED_FOR',   // Standard proxy header
        'HTTP_X_REAL_IP',          // Nginx reverse proxy
        'HTTP_CLIENT_IP',          // Some proxies
        'REMOTE_ADDR'              // Direct connection (most common on LAN)
    ];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            // X-Forwarded-For can contain a chain: "client, proxy1, proxy2"
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0'; // Fallback
}

// ─────────────────────────────────────────────────────────
//  TRANSFER SPEED CALCULATOR
//  NETWORKING CONCEPT:
//  Transfer speed = Data Size / Time taken
//  This simulates how network bandwidth is measured.
//  In real networking: throughput = bits transferred / seconds
//  We store start_time before upload and compare after.
// ─────────────────────────────────────────────────────────
function calculate_speed($file_size_bytes, $duration_seconds) {
    if ($duration_seconds <= 0) $duration_seconds = 0.01; // avoid division by zero
    $speed_bps  = $file_size_bytes / $duration_seconds;   // bytes per second
    $speed_kbps = $speed_bps / 1024;                       // KB/s
    $speed_mbps = $speed_kbps / 1024;                      // MB/s

    if ($speed_mbps >= 1) {
        return round($speed_mbps, 2) . ' MB/s';
    }
    return round($speed_kbps, 2) . ' KB/s';
}

// ── Human-readable file size ──────────────────────────────
function format_size($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

// ── Sanitize output to prevent XSS ───────────────────────
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// ── Allowed file types & max size ────────────────────────
define('ALLOWED_TYPES', ['pdf', 'jpg', 'jpeg', 'png', 'docx']);
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB in bytes
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// ── Validate uploaded file ────────────────────────────────
function validate_file($file) {
    $errors = [];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // 1. File type check
    if (!in_array($ext, ALLOWED_TYPES)) {
        $errors[] = 'File type .' . $ext . ' is not allowed. Accepted: ' . implode(', ', ALLOWED_TYPES);
    }

    // 2. File size check (HTTP layer check — server-side authoritative)
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = 'File exceeds 10 MB limit (your file: ' . format_size($file['size']) . ')';
    }

    // 3. Check for PHP upload errors (network/incomplete transfer)
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded (network interruption!).',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Server failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A server extension blocked the upload.',
    ];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = $upload_errors[$file['error']] ?? 'Unknown upload error.';
    }

    return $errors;
}

// ── Log a transfer event to the database ─────────────────
function log_transfer($conn, $user_id, $username, $filename, $filesize, $type, $status, $speed, $ip) {
    $stmt = $conn->prepare("
        INSERT INTO transfer_logs
            (user_id, username, file_name, file_size, transfer_type, status, speed, ip_address, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param('isssssss',
        $user_id, $username, $filename, $filesize, $type, $status, $speed, $ip
    );
    $stmt->execute();
    $stmt->close();
}
