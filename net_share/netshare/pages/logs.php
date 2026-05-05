<?php
/**
 * ============================================================
 *  logs.php — Transfer Audit Log Viewer
 *  NetShare | Network File Transfer System
 * ============================================================
 *
 *  NETWORKING CONCEPT:
 *  Transfer logs are the equivalent of a network switch's
 *  port statistics or a router's syslog. Every data transfer
 *  event (upload/download) is recorded with:
 *  - Source IP address (which device on the LAN initiated it)
 *  - Timestamp (when the transfer happened)
 *  - Transfer speed (bandwidth consumed)
 *  - Status (did the transfer complete successfully?)
 *
 *  Real systems like FTP servers, HTTP servers (Apache access
 *  logs), and network monitoring tools do exactly this.
 * ============================================================
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];
$ip       = get_client_ip();

// ── Filtering ──────────────────────────────────────────────
$filter_type   = $_GET['type']   ?? 'all';   // upload / download / all
$filter_status = $_GET['status'] ?? 'all';   // success / failed / all

$where_clauses = [];
$params        = [];
$types         = '';

if ($filter_type !== 'all') {
    $where_clauses[] = 'transfer_type = ?';
    $params[] = $filter_type;
    $types .= 's';
}
if ($filter_status !== 'all') {
    $where_clauses[] = 'status = ?';
    $params[] = $filter_status;
    $types .= 's';
}

$where = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// ── Fetch logs ─────────────────────────────────────────────
$sql = "SELECT * FROM transfer_logs $where ORDER BY created_at DESC LIMIT 200";
if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $logs = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// ── Summary stats ──────────────────────────────────────────
$summary = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'success') AS successes,
        SUM(status = 'failed')  AS failures,
        SUM(transfer_type = 'upload')   AS uploads,
        SUM(transfer_type = 'download') AS downloads
    FROM transfer_logs
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NetShare — Transfer Logs</title>
    <link rel="stylesheet" href="/netshare/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="ns-wrapper">

<!-- ── Navbar ───────────────────────────────────────────── -->
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
        <li><a href="/netshare/pages/logs.php" class="active">Transfer Logs</a></li>
        <li><a href="/netshare/pages/network.php">Network Info</a></li>
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
            <div class="ns-breadcrumb"><span>NETSHARE</span> / TRANSFER LOGS</div>
            <h2 class="ns-page-title">Network Audit Log</h2>
            <p class="ns-page-subtitle">
                All upload &amp; download events &nbsp;·&nbsp; IP tracking &nbsp;·&nbsp; Speed monitoring
            </p>
        </div>
        <!-- Filter controls -->
        <div class="d-flex gap-1 flex-wrap">
            <a href="?type=all&status=all"
               class="ns-btn ns-btn-sm <?= ($filter_type==='all'&&$filter_status==='all') ? 'ns-btn-primary' : 'ns-btn-outline' ?>">
               All</a>
            <a href="?type=upload&status=all"
               class="ns-btn ns-btn-sm <?= $filter_type==='upload' ? 'ns-btn-primary' : 'ns-btn-outline' ?>">
               ↑ Uploads</a>
            <a href="?type=download&status=all"
               class="ns-btn ns-btn-sm <?= $filter_type==='download' ? 'ns-btn-primary' : 'ns-btn-outline' ?>">
               ↓ Downloads</a>
            <a href="?type=all&status=success"
               class="ns-btn ns-btn-sm <?= $filter_status==='success' ? 'ns-btn-primary' : 'ns-btn-outline' ?>">
               ✓ Success</a>
            <a href="?type=all&status=failed"
               class="ns-btn ns-btn-sm <?= $filter_status==='failed' ? 'ns-btn-primary' : 'ns-btn-outline' ?>">
               ✗ Failed</a>
        </div>
    </div>

    <!-- Summary stats -->
    <div class="ns-stats-grid anim-fadein mb-3">
        <div class="ns-stat">
            <div class="ns-stat-value"><?= $summary['total'] ?? 0 ?></div>
            <div class="ns-stat-label">Total Transfers</div>
        </div>
        <div class="ns-stat">
            <div class="ns-stat-value" style="color:var(--success)"><?= $summary['successes'] ?? 0 ?></div>
            <div class="ns-stat-label">Successful</div>
        </div>
        <div class="ns-stat">
            <div class="ns-stat-value" style="color:var(--danger)"><?= $summary['failures'] ?? 0 ?></div>
            <div class="ns-stat-label">Failed</div>
        </div>
        <div class="ns-stat">
            <div class="ns-stat-value"><?= $summary['uploads'] ?? 0 ?></div>
            <div class="ns-stat-label">Uploads</div>
        </div>
        <div class="ns-stat">
            <div class="ns-stat-value"><?= $summary['downloads'] ?? 0 ?></div>
            <div class="ns-stat-label">Downloads</div>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="ns-card anim-fadein anim-fadein-delay-1">
        <div class="ns-card-header">
            <span class="ns-card-title">Transfer History</span>
            <span class="text-dim" style="font-size:10px">showing <?= count($logs) ?> records</span>
        </div>

        <?php if (empty($logs)): ?>
        <div class="ns-card-body" style="text-align:center; color:var(--text-dim); padding:3rem; font-size:12px">
            No transfer records found for the selected filter.
        </div>
        <?php else: ?>
        <div style="overflow-x:auto">
            <table class="ns-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Direction</th>
                        <th>File Name</th>
                        <th>Size</th>
                        <th>Speed</th>
                        <th>Source IP</th>
                        <th>Timestamp</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $i => $log): ?>
                    <tr>
                        <td class="text-dim" style="font-size:10px"><?= $log['id'] ?></td>
                        <td>
                            <span style="color:var(--accent); font-size:12px">
                                ◈ <?= e($log['username']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log['transfer_type'] === 'upload'): ?>
                                <span class="ns-badge ns-badge-info">↑ UPLOAD</span>
                            <?php else: ?>
                                <span class="ns-badge ns-badge-warning">↓ DOWNLOAD</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="filename" title="<?= e($log['file_name']) ?>">
                                <?= e($log['file_name']) ?>
                            </span>
                        </td>
                        <td class="text-muted" style="white-space:nowrap"><?= e($log['file_size']) ?></td>
                        <td>
                            <code style="font-size:11px; color:var(--accent)">
                                <?= e($log['speed'] ?? '—') ?>
                            </code>
                        </td>
                        <td>
                            <code style="font-size:10px; color:var(--text-dim)">
                                <?= e($log['ip_address'] ?? '—') ?>
                            </code>
                        </td>
                        <td class="text-muted" style="font-size:11px; white-space:nowrap">
                            <?= e($log['created_at']) ?>
                        </td>
                        <td>
                            <?php if ($log['status'] === 'success'): ?>
                                <span class="ns-badge ns-badge-success">✓ SUCCESS</span>
                            <?php else: ?>
                                <span class="ns-badge ns-badge-danger">✗ FAILED</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>

<script src="/netshare/assets/js/main.js"></script>
</body>
</html>
