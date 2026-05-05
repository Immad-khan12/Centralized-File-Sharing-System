<?php
/**
 * ============================================================
 *  ajax/delete.php — File Deletion Handler
 *  NetShare | Network File Transfer System
 * ============================================================
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

start_session();

if (empty($_SESSION['user_id'])) {
    redirect('/netshare/pages/login.php');
}

$user_id = $_SESSION['user_id'];
$file_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$file_id) {
    redirect('/netshare/pages/dashboard.php');
}

// Only allow deleting own files
$stmt = $conn->prepare("SELECT stored_name, original_name FROM files WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param('ii', $file_id, $user_id);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($file) {
    // Delete from disk
    $path = UPLOAD_DIR . $file['stored_name'];
    if (file_exists($path)) {
        @unlink($path);
    }
    // Delete DB record
    $del = $conn->prepare("DELETE FROM files WHERE id = ? AND user_id = ?");
    $del->bind_param('ii', $file_id, $user_id);
    $del->execute();
    $del->close();
}

redirect('/netshare/pages/dashboard.php');
