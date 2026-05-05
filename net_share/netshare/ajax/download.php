<?php
/**
 * ============================================================
 *  ajax/download.php — File Download Handler
 *  NetShare | Network File Transfer System
 * ============================================================
 *
 *  NETWORKING CONCEPTS:
 *  - This is an HTTP GET response that streams file bytes.
 *  - Sets Content-Type header so the browser knows the format.
 *  - Sets Content-Disposition: attachment to force download.
 *  - Sets Content-Length so the browser can show progress.
 *  - Uses chunked readfile() to stream over TCP without loading
 *    the entire file into PHP memory — efficient LAN transfer.
 *  - Logs the transfer with IP address and calculated speed.
 * ============================================================
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

start_session();

// Auth guard
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized. Please log in.');
}

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];
$ip       = get_client_ip();

// ── Validate file ID ───────────────────────────────────────
$file_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$file_id) {
    http_response_code(400);
    die('Invalid file ID.');
}

// ── Fetch file record from database ───────────────────────
$stmt = $conn->prepare("SELECT * FROM files WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $file_id);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$file) {
    http_response_code(404);
    die('File not found.');
}

// ── Check physical file exists on server disk ──────────────
$file_path = UPLOAD_DIR . $file['stored_name'];
if (!file_exists($file_path) || !is_readable($file_path)) {
    log_transfer($conn, $user_id, $username, $file['original_name'], format_size($file['file_size']), 'download', 'failed', '0 KB/s', $ip);
    http_response_code(404);
    die('File no longer exists on server.');
}

// ── Set MIME type based on extension ──────────────────────
$mime_map = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'doc'  => 'application/msword',
];
$mime = $mime_map[$file['file_type']] ?? 'application/octet-stream';

// ── Record start time ──────────────────────────────────────
$start_time = microtime(true);

// ── Send HTTP Headers ──────────────────────────────────────
/**
 * NETWORKING CONCEPT — HTTP Response Headers:
 * Before sending the file bytes, we send metadata headers.
 * These tell the client browser:
 *   Content-Type:        what kind of data is coming
 *   Content-Length:      how many bytes to expect (for progress)
 *   Content-Disposition: "attachment" = save to disk, don't display
 *   Accept-Ranges:       allows resume downloads
 */
header('Content-Type: ' . $mime);
header('Content-Length: ' . $file['file_size']);
header('Content-Disposition: attachment; filename="' . addslashes($file['original_name']) . '"');
header('Accept-Ranges: bytes');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Transfer-By: NetShare/1.0');   // Custom header (shows in DevTools)
header('X-Client-IP: ' . $ip);           // Echo back client IP in response

// Disable output buffering — stream directly to client over TCP
if (ob_get_level()) ob_end_clean();

// ── Stream file to client in chunks ───────────────────────
/**
 * NETWORKING CONCEPT — Chunked Streaming:
 * readfile() reads the file in internal chunks and sends each
 * chunk directly into the TCP output buffer. This is how all
 * web servers (Apache, Nginx) serve static files:
 *   disk → PHP buffer → TCP socket → client browser
 * The client's browser shows download progress because it
 * knows Content-Length and tracks bytes received.
 */
$bytes_sent = readfile($file_path);

// ── Calculate actual transfer speed ───────────────────────
$end_time  = microtime(true);
$duration  = max($end_time - $start_time, 0.001);
$speed_str = calculate_speed($bytes_sent ?: $file['file_size'], $duration);

// ── Determine status (did all bytes actually send?) ────────
$status = ($bytes_sent == $file['file_size']) ? 'success' : 'failed';

// ── Log the download event ─────────────────────────────────
log_transfer($conn, $user_id, $username, $file['original_name'], format_size($file['file_size']), 'download', $status, $speed_str, $ip);
