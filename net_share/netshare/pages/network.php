<?php
/**
 * ============================================================
 *  network.php — Networking Concepts Visualizer
 *  NetShare | Network File Transfer System
 * ============================================================
 *  This page explains how networking works in this project.
 *  Perfect for presentation to professors/reviewers.
 * ============================================================
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$ip       = get_client_ip();
$username = $_SESSION['username'];

// ── Gather all live network data ───────────────────────────
$server_ip   = $_SERVER['SERVER_ADDR']   ?? gethostbyname(gethostname());
$server_port = $_SERVER['SERVER_PORT']   ?? '80';
$host        = $_SERVER['HTTP_HOST']     ?? 'localhost';
$protocol    = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'HTTPS' : 'HTTP';
$user_agent  = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$request_uri = $_SERVER['REQUEST_URI']  ?? '/';
$method      = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$accept      = $_SERVER['HTTP_ACCEPT']  ?? '*/*';

// ── Live active sessions (count users online now) ──────────
$active = $conn->query("
    SELECT COUNT(*) AS c FROM transfer_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NetShare — Network Info</title>
    <link rel="stylesheet" href="/netshare/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        .concept-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.2rem 1.5rem;
            margin-bottom: 1rem;
            border-left: 3px solid var(--accent);
        }
        .concept-card h4 {
            font-family: var(--font-display);
            font-size: 0.9rem;
            color: var(--accent);
            letter-spacing: 0.1em;
            margin-bottom: 0.5rem;
        }
        .concept-card p {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.7;
            margin-bottom: 0.5rem;
        }
        .concept-card .implementation {
            font-size: 11px;
            color: var(--text-dim);
            border-top: 1px solid var(--border);
            padding-top: 0.5rem;
            margin-top: 0.5rem;
        }
        .concept-card .implementation code {
            color: var(--success);
            background: rgba(46,213,115,0.08);
            padding: 1px 5px;
            border-radius: 2px;
        }
        .packet-diagram {
            display: flex;
            gap: 0;
            font-size: 10px;
            font-family: var(--font-mono);
            margin: 0.8rem 0;
        }
        .packet-field {
            border: 1px solid var(--border-bright);
            padding: 6px 10px;
            text-align: center;
        }
        .packet-field:first-child { border-radius: 3px 0 0 3px; }
        .packet-field:last-child { border-radius: 0 3px 3px 0; }
        .packet-field .label { color: var(--text-dim); font-size: 9px; display: block; }
        .packet-field .val { color: var(--accent); margin-top: 2px; }
        .pf-src { background: rgba(30,144,255,0.08); }
        .pf-dst { background: rgba(0,212,170,0.08); }
        .pf-data { background: rgba(255,165,2,0.08); flex:1; }
        .pf-status { background: rgba(46,213,115,0.08); }
    </style>
</head>
<body>
<div class="ns-wrapper">

<nav class="ns-navbar">
    <a href="/netshare/pages/dashboard.php" class="ns-brand">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="3" width="20" height="14" rx="2"/>
            <line x1="8" y1="21" x2="16" y2="21"/>
            <line x1="12" y1="17" x2="12" y2="21"/>
        </svg>
        NET<span class="slash">/</span>SHARE
    </a>
    <ul class="ns-nav-links">
        <li><a href="/netshare/pages/dashboard.php">Dashboard</a></li>
        <li><a href="/netshare/pages/logs.php">Transfer Logs</a></li>
        <li><a href="/netshare/pages/network.php" class="active">Network Info</a></li>
    </ul>
    <div class="ns-nav-user">
        <span class="ns-ip-badge">MY IP: <span><?= e($ip) ?></span></span>
        <span class="text-muted" style="font-size:11px">◈ <?= e($username) ?></span>
        <a href="/netshare/pages/logout.php" class="ns-btn ns-btn-outline ns-btn-sm">Disconnect</a>
    </div>
</nav>

<div class="ns-container">

    <div class="ns-page-header">
        <div>
            <div class="ns-breadcrumb"><span>NETSHARE</span> / NETWORK INFO</div>
            <h2 class="ns-page-title">Network Analysis</h2>
            <p class="ns-page-subtitle">Live connection data · Networking concepts · LAN architecture</p>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem">

        <!-- ── LEFT: Live Connection Data ─────────────────── -->
        <div>
            <div class="ns-card anim-fadein mb-3">
                <div class="ns-card-header">
                    <span class="ns-card-title">Live HTTP Request Headers</span>
                </div>
                <div class="ns-terminal" style="max-height:none; padding:1rem">
                    <span class="log-line"><span class="log-info">──── REQUEST ────────────────────────────</span></span>
                    <span class="log-line"><span class="log-ok"><?= e($method) ?></span> <span class="log-info"><?= e($request_uri) ?></span> HTTP/1.1</span>
                    <span class="log-line"><span class="log-time">Host:</span>            <?= e($host) ?></span>
                    <span class="log-line"><span class="log-time">Client-IP:</span>       <span class="log-ok"><?= e($ip) ?></span></span>
                    <span class="log-line"><span class="log-time">Accept:</span>          <?= e(substr($accept, 0, 50)) ?>…</span>
                    <span class="log-line"><span class="log-time">User-Agent:</span>      <?= e(substr($user_agent, 0, 60)) ?>…</span>
                    <span class="log-line"><span class="log-time">Session-ID:</span>      <?= substr(session_id(), 0, 32) ?>…</span>
                    <br>
                    <span class="log-line"><span class="log-info">──── SERVER ─────────────────────────────</span></span>
                    <span class="log-line"><span class="log-time">Server-IP:</span>       <span class="log-ok"><?= e($server_ip) ?></span></span>
                    <span class="log-line"><span class="log-time">Port:</span>            <?= e($server_port) ?></span>
                    <span class="log-line"><span class="log-time">Protocol:</span>        <?= e($protocol) ?>/1.1</span>
                    <span class="log-line"><span class="log-time">PHP-Version:</span>     <?= phpversion() ?></span>
                    <span class="log-line"><span class="log-time">Server-Time:</span>     <?= date('Y-m-d H:i:s T') ?></span>
                    <br>
                    <span class="log-line"><span class="log-info">──── LAN ACCESS URL ─────────────────────</span></span>
                    <span class="log-line"><span class="log-ok">http://<?= e($server_ip) ?>:<?= e($server_port) ?>/netshare/</span></span>
                    <span class="log-line text-dim" style="font-size:10px">(Share this URL with other devices on the same network)</span>
                </div>
            </div>

            <!-- HTTP Packet Diagram -->
            <div class="ns-card anim-fadein anim-fadein-delay-1">
                <div class="ns-card-header">
                    <span class="ns-card-title">HTTP Transfer Packet (Simplified)</span>
                </div>
                <div class="ns-card-body">
                    <p style="font-size:11px; color:var(--text-dim); margin-bottom:0.8rem">
                        When you upload a file, it travels over the LAN as:
                    </p>
                    <div class="packet-diagram">
                        <div class="packet-field pf-src">
                            <span class="label">SRC IP</span>
                            <div class="val"><?= e($ip) ?></div>
                        </div>
                        <div class="packet-field pf-dst">
                            <span class="label">DST IP:PORT</span>
                            <div class="val"><?= e($server_ip) ?>:<?= e($server_port) ?></div>
                        </div>
                        <div class="packet-field pf-data">
                            <span class="label">PAYLOAD (file chunks)</span>
                            <div class="val">multipart/form-data</div>
                        </div>
                        <div class="packet-field pf-status">
                            <span class="label">ACK</span>
                            <div class="val">200 OK</div>
                        </div>
                    </div>
                    <p style="font-size:10px; color:var(--text-dim)">
                        TCP breaks this into segments → reassembled by server → stored to disk
                    </p>
                </div>
            </div>
        </div>

        <!-- ── RIGHT: Networking Concepts ──────────────────── -->
        <div class="anim-fadein anim-fadein-delay-2">
            <h3 style="font-family:var(--font-display); font-size:0.9rem; color:var(--text-dim);
                       letter-spacing:0.12em; text-transform:uppercase; margin-bottom:1rem">
                Networking Concepts Implemented
            </h3>

            <div class="concept-card">
                <h4>1. Client-Server Architecture</h4>
                <p>
                    Your browser is the <strong>client</strong>. Apache (XAMPP) + PHP is the <strong>server</strong>.
                    The client sends HTTP requests; the server processes and responds.
                    Multiple clients (devices on LAN) can connect to the same server simultaneously.
                </p>
                <div class="implementation">
                    <strong>In this project:</strong> PHP files act as the server logic.
                    <code>dashboard.php</code> serves the UI.
                    <code>upload.php</code> handles incoming file data.
                    <code>download.php</code> sends files back to clients.
                </div>
            </div>

            <div class="concept-card">
                <h4>2. HTTP Request / Response</h4>
                <p>
                    HTTP (HyperText Transfer Protocol) runs on top of TCP/IP.
                    Every page load, upload, and download is an HTTP request.
                    Uploads use <strong>HTTP POST</strong> with multipart/form-data encoding.
                    Downloads use <strong>HTTP GET</strong> with Content-Disposition headers.
                </p>
                <div class="implementation">
                    <strong>In this project:</strong> <code>XMLHttpRequest</code> in main.js sends
                    POST requests for uploads. The download button triggers GET requests.
                    PHP reads <code>$_FILES</code> (upload) and sends <code>readfile()</code> (download).
                </div>
            </div>

            <div class="concept-card">
                <h4>3. LAN Communication</h4>
                <p>
                    On a Local Area Network, devices share an IP subnet (e.g. 192.168.1.x).
                    Any device on the same Wi-Fi/Ethernet network can reach the server
                    using its LAN IP address instead of "localhost".
                </p>
                <div class="implementation">
                    <strong>In this project:</strong> Access via
                    <code>http://<?= e($server_ip) ?>/netshare/</code>
                    from any device on the same router. No internet required.
                </div>
            </div>

            <div class="concept-card">
                <h4>4. IP Address Tracking</h4>
                <p>
                    Every TCP connection has a source IP. PHP reads this from
                    <code>$_SERVER['REMOTE_ADDR']</code> (or proxy headers).
                    This is how web servers know who is connecting from where.
                </p>
                <div class="implementation">
                    <strong>In this project:</strong> <code>get_client_ip()</code> in
                    functions.php captures the IP on every login, upload, and download,
                    then stores it in the database for auditing.
                </div>
            </div>

            <div class="concept-card">
                <h4>5. Chunk-Based Data Transfer</h4>
                <p>
                    TCP automatically segments large files into packets (~1500 bytes each
                    per Ethernet MTU). The browser's XHR API fires <code>onprogress</code>
                    events as TCP ACK signals confirm each chunk was received.
                    Our progress bar reflects real TCP acknowledgment progress.
                </p>
                <div class="implementation">
                    <strong>In this project:</strong> <code>xhr.upload.onprogress</code>
                    in main.js tracks <code>event.loaded</code> vs <code>event.total</code>
                    — the actual bytes acknowledged through the TCP stack.
                </div>
            </div>

            <div class="concept-card">
                <h4>6. Transfer Speed Measurement</h4>
                <p>
                    Network bandwidth = bits transferred ÷ time. We record the start
                    timestamp and calculate bytes/second. This is how tools like
                    <em>iperf</em> and <em>Speedtest</em> measure network throughput.
                </p>
                <div class="implementation">
                    <strong>In this project:</strong> <code>calculate_speed()</code> in
                    functions.php divides file_size (bytes) by duration (seconds).
                    Result is shown in KB/s or MB/s in the UI and stored in transfer_logs.
                </div>
            </div>

            <div class="concept-card">
                <h4>7. Error Handling / Network Reliability</h4>
                <p>
                    Networks are unreliable. TCP handles segment retransmission, but
                    application-level errors (partial uploads, disk failures) must be
                    caught manually. PHP's <code>UPLOAD_ERR_PARTIAL</code> flag detects
                    incomplete transfers caused by network drops.
                </p>
                <div class="implementation">
                    <strong>In this project:</strong> <code>validate_file()</code> checks
                    for all PHP upload error codes. <code>xhr.onerror</code> in JS handles
                    TCP connection drops. All failures are logged with status="failed".
                </div>
            </div>

            <div class="concept-card">
                <h4>8. Session-Based Authentication</h4>
                <p>
                    HTTP is stateless — each request is independent. Sessions solve this
                    by storing a session ID in a browser cookie (PHPSESSID). The server
                    maps this ID to stored user data. This is how all web authentication works.
                </p>
                <div class="implementation">
                    <strong>In this project:</strong> PHP <code>session_start()</code> creates
                    sessions. <code>require_login()</code> checks <code>$_SESSION['user_id']</code>
                    on every protected page before serving content.
                </div>
            </div>

        </div>
    </div>

</div>
</div>

<script src="/netshare/assets/js/main.js"></script>
</body>
</html>
