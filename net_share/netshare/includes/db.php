<?php
/**
 * ============================================================
 *  db.php — Database Connection (MySQL via MySQLi)
 *  NetShare | Network File Transfer System
 * ============================================================
 *
 *  NETWORKING CONCEPT:
 *  MySQL runs as a SERVER process on your machine (localhost).
 *  PHP acts as a CLIENT, connecting to MySQL over a local TCP
 *  socket (port 3306). This is a client-server model within
 *  the application layer itself — exactly like how a browser
 *  (client) talks to Apache (server) over port 80/443.
 * ============================================================
 */

// ── Database credentials ──────────────────────────────────
define('DB_HOST', 'localhost');   // MySQL server host
define('DB_USER', 'root');        // XAMPP default username
define('DB_PASS', '');            // XAMPP default password (blank)
define('DB_NAME', 'netshare');    // Our application database

// ── Establish connection ──────────────────────────────────
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// ── Handle connection failure ─────────────────────────────
if ($conn->connect_error) {
    http_response_code(503);
    die(json_encode([
        'success' => false,
        'message' => 'DB connection failed: ' . $conn->connect_error
    ]));
}

// ── UTF-8 support ─────────────────────────────────────────
$conn->set_charset('utf8mb4');
