<?php
/**
 * check_session.php — Session status check (AJAX polling)
 * Returns JSON with current user info.
 * NETWORKING: Simulates a "keepalive" ping to the server.
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');
start_session();

if (!empty($_SESSION['user_id'])) {
    echo json_encode([
        'authenticated' => true,
        'username'      => $_SESSION['username'],
        'session_id'    => substr(session_id(), 0, 8) . '...',
        'server_time'   => date('Y-m-d H:i:s'),
        'client_ip'     => get_client_ip(),
    ]);
} else {
    http_response_code(401);
    echo json_encode(['authenticated' => false]);
}
