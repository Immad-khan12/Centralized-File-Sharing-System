<?php
/**
 * ============================================================
 *  ajax/upload.php — File Upload Handler (AJAX Endpoint)
 *  NetShare | Network File Transfer System
 * ============================================================
 *
 *  NETWORKING CONCEPTS:
 *  - This script is the SERVER side of the client-server model.
 *  - The browser sends an HTTP POST request with the file
 *    encoded as multipart/form-data (RFC 2046).
 *  - PHP receives the file in $_FILES after Apache reassembles
 *    all TCP segments into the complete file in /tmp/.
 *  - We then validate, store, measure speed, and log the event.
 *  - Response is JSON — parsed by main.js on the client side.
 * ============================================================
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Only respond to POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method Not Allowed']));
}

// Must be authenticated
start_session();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Not authenticated. Please log in.']));
}

// Always return JSON
header('Content-Type: application/json');

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];
$ip       = get_client_ip();

// ── CSRF Verification ──────────────────────────────────────
$csrf = $_POST['csrf_token'] ?? '';
if (empty($csrf) || $csrf !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Security token mismatch. Please refresh and try again.']);
    exit;
}

// ── Check file was actually sent ───────────────────────────
if (!isset($_FILES['upload_file']) || $_FILES['upload_file']['error'] === UPLOAD_ERR_NO_FILE) {
    log_transfer($conn, $user_id, $username, 'none', '0 B', 'upload', 'failed', '0 KB/s', $ip);
    echo json_encode(['success' => false, 'message' => 'No file selected.']);
    exit;
}

$file = $_FILES['upload_file'];

// ── Validate file (type, size, PHP errors) ─────────────────
$errors = validate_file($file);
if (!empty($errors)) {
    log_transfer($conn, $user_id, $username, $file['name'], format_size($file['size']), 'upload', 'failed', '0 KB/s', $ip);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// ── Prepare upload directory ───────────────────────────────
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// ── Generate safe unique filename ─────────────────────────
// SECURITY: Never use the original filename directly on disk.
// Prevents path traversal attacks and filename collisions.
$ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$stored_name = date('Ymd_His') . '_u' . $user_id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$dest_path   = UPLOAD_DIR . $stored_name;

// ── CHUNK-BASED FILE WRITE (Simulated) ────────────────────
/**
 * NETWORKING CONCEPT — Chunk Transfer Simulation:
 * Real protocols (FTP, BitTorrent, HTTP/2) break files into
 * chunks for transmission. Here we simulate this by reading
 * the temp file in CHUNK_SIZE blocks and writing each chunk
 * to disk. This mirrors how a real FTP server would reassemble
 * received TCP segments into the final file.
 *
 * CHUNK_SIZE = 512KB (same as typical FTP block size)
 */
$CHUNK_SIZE  = 512 * 1024; // 512 KB per chunk
$start_time  = microtime(true);
$total_written = 0;
$chunks_sent   = 0;
$transfer_ok   = false;

$src = fopen($file['tmp_name'], 'rb');
$dst = fopen($dest_path, 'wb');

if ($src && $dst) {
    while (!feof($src)) {
        $chunk   = fread($src, $CHUNK_SIZE); // Read one chunk
        if ($chunk === false) break;
        $written = fwrite($dst, $chunk);     // Write chunk to disk
        if ($written === false) break;
        $total_written += $written;
        $chunks_sent++;
    }
    fclose($src);
    fclose($dst);

    // Verify complete transfer (size must match exactly)
    if ($total_written === (int)$file['size']) {
        $transfer_ok = true;
    } else {
        // Incomplete transfer — delete partial file
        @unlink($dest_path);
    }
} else {
    if ($src) fclose($src);
    if ($dst) fclose($dst);
    @unlink($dest_path);
}

// ── Calculate transfer metrics ─────────────────────────────
$end_time    = microtime(true);
$duration    = max($end_time - $start_time, 0.001); // seconds (avoid div/0)
$speed_str   = calculate_speed($file['size'], $duration);

// ── Determine transfer status ──────────────────────────────
$status = $transfer_ok ? 'success' : 'failed';

if (!$transfer_ok) {
    log_transfer($conn, $user_id, $username, $file['name'], format_size($file['size']), 'upload', 'failed', $speed_str, $ip);
    echo json_encode([
        'success' => false,
        'message' => 'File transfer incomplete. Possible network interruption during write.'
    ]);
    exit;
}

// ── Save file record to database ───────────────────────────
$stmt = $conn->prepare("
    INSERT INTO files
        (user_id, original_name, stored_name, file_type, file_size, upload_ip, uploaded_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param('isssis',
    $user_id,
    $file['name'],
    $stored_name,
    $ext,
    $file['size'],
    $ip
);
$stmt->execute();
$file_id = $conn->insert_id;
$stmt->close();

// ── Log the successful transfer ────────────────────────────
log_transfer($conn, $user_id, $username, $file['name'], format_size($file['size']), 'upload', 'success', $speed_str, $ip);

// ── Return success JSON to client ─────────────────────────
echo json_encode([
    'success'     => true,
    'message'     => 'File transferred successfully.',
    'file_id'     => $file_id,
    'file_name'   => $file['name'],
    'file_size'   => format_size($file['size']),
    'file_type'   => strtoupper($ext),
    'speed'       => $speed_str,
    'duration'    => round($duration, 3) . 's',
    'chunks'      => $chunks_sent,
    'chunk_size'  => '512 KB',
    'client_ip'   => $ip,
    'status'      => 'SUCCESS',
]);
