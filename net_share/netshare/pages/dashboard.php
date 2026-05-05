<?php
/**
 * ============================================================
 *  dashboard.php — Main File Transfer Hub
 *  NetShare | Network File Transfer System
 * ============================================================
 *
 *  This page is the "server node interface" — it shows all
 *  files available for download and provides the upload form.
 *  It demonstrates:
 *  - Server-side session validation (auth guard)
 *  - Serving file metadata from database
 *  - Client-server architecture (this PHP IS the server logic)
 * ============================================================
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login(); // ← Redirect to login if not authenticated

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];
$ip       = get_client_ip();

// ── Fetch all files from server ────────────────────────────
$files_result = $conn->query("
    SELECT f.*, u.username AS uploader
    FROM files f
    JOIN users u ON f.user_id = u.id
    ORDER BY f.uploaded_at DESC
");
$files = $files_result->fetch_all(MYSQLI_ASSOC);

// ── Fetch quick stats ──────────────────────────────────────
$stats = $conn->query("
    SELECT
        COUNT(*)                             AS total_files,
        COALESCE(SUM(file_size), 0)          AS total_bytes,
        (SELECT COUNT(*) FROM transfer_logs WHERE status='success') AS success_count,
        (SELECT COUNT(*) FROM transfer_logs WHERE status='failed')  AS failed_count,
        (SELECT COUNT(DISTINCT user_id) FROM transfer_logs)         AS active_users
    FROM files
")->fetch_assoc();

$total_size = format_size((int)$stats['total_bytes']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NetShare — Dashboard</title>
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
        <li><a href="/netshare/pages/dashboard.php" class="active">Dashboard</a></li>
        <li><a href="/netshare/pages/logs.php">Transfer Logs</a></li>
        <li><a href="/netshare/pages/network.php">Network Info</a></li>
    </ul>

    <div class="ns-nav-user">
        <span class="ns-ip-badge">
            MY IP: <span><?= e($ip) ?></span>
        </span>
        <span class="text-muted" style="font-size:11px">
            ◈ <?= e($username) ?>
        </span>
        <a href="/netshare/pages/logout.php" class="ns-btn ns-btn-outline ns-btn-sm">Disconnect</a>
    </div>
</nav>

<!-- ── Main Content ─────────────────────────────────────── -->
<div class="ns-container">

    <!-- Page Header -->
    <div class="ns-page-header">
        <div>
            <div class="ns-breadcrumb"><span>NETSHARE</span> / DASHBOARD</div>
            <h2 class="ns-page-title">File Transfer Hub</h2>
            <p class="ns-page-subtitle">
                Client-Server File Exchange Node &nbsp;·&nbsp;
                HTTP over LAN &nbsp;·&nbsp;
                Server time: <?= date('Y-m-d H:i:s') ?>
            </p>
        </div>
    </div>

    <!-- Stats Bar -->
    <div class="ns-stats-grid anim-fadein">
        <div class="ns-stat">
            <div class="ns-stat-value"><?= $stats['total_files'] ?></div>
            <div class="ns-stat-label">Files on Server</div>
        </div>
        <div class="ns-stat">
            <div class="ns-stat-value"><?= $total_size ?></div>
            <div class="ns-stat-label">Total Storage</div>
        </div>
        <div class="ns-stat">
            <div class="ns-stat-value" style="color:var(--success)"><?= $stats['success_count'] ?></div>
            <div class="ns-stat-label">Successful Transfers</div>
        </div>
        <div class="ns-stat">
            <div class="ns-stat-value" style="color:var(--danger)"><?= $stats['failed_count'] ?></div>
            <div class="ns-stat-label">Failed Transfers</div>
        </div>
        <div class="ns-stat">
            <div class="ns-stat-value" style="color:var(--info)"><?= $stats['active_users'] ?></div>
            <div class="ns-stat-label">Active Users</div>
        </div>
    </div>

    <!-- Main Grid: Upload + File List -->
    <div style="display: grid; grid-template-columns: 380px 1fr; gap: 1.5rem; align-items: start;"
         class="flex-wrap">

        <!-- ── LEFT: Upload Panel ──────────────────────── -->
        <div class="anim-fadein anim-fadein-delay-1">
            <div class="ns-card">
                <div class="ns-card-header">
                    <span class="ns-card-title">Upload File</span>
                    <span class="text-dim" style="font-size:10px">HTTP POST · multipart/form-data</span>
                </div>
                <div class="ns-card-body">

                    <!-- Info: allowed types -->
                    <div class="ns-alert ns-alert-info mb-2" style="font-size:11px">
                        Allowed: <strong>PDF, JPG, PNG, DOCX</strong> &nbsp;·&nbsp; Max: <strong>10 MB</strong>
                    </div>

                    <!-- Upload Form — submitted via AJAX in main.js -->
                    <form id="uploadForm" enctype="multipart/form-data">
                        <!-- CSRF token (simple session-based) -->
                        <?php
                        if (empty($_SESSION['csrf_token'])) {
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        }
                        ?>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                        <!-- Drop Zone -->
                        <div class="ns-dropzone" id="dropzone">
                            <input type="file" id="fileInput" name="upload_file"
                                   accept=".pdf,.jpg,.jpeg,.png,.docx">
                            <div class="ns-dropzone-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="17 8 12 3 7 8"/>
                                    <line x1="12" y1="3" x2="12" y2="15"/>
                                </svg>
                            </div>
                            <div class="ns-dropzone-title">Drop file here</div>
                            <div class="ns-dropzone-hint">or click to browse</div>
                        </div>
                        <div class="ns-file-chosen" id="fileChosen"></div>

                        <!-- Progress Bar (hidden until upload starts) -->
                        <div class="ns-progress-wrap" id="progressWrap">
                            <div class="ns-progress-header">
                                <span>Transmitting over TCP/IP</span>
                                <span id="progressPct">0%</span>
                            </div>
                            <div class="ns-progress-bar-track">
                                <div class="ns-progress-bar-fill" id="progressBar"></div>
                            </div>
                            <div class="ns-progress-stats">
                                <span id="progressBytes">0 B / 0 B</span>
                                <span id="speedDisplay">— KB/s</span>
                            </div>
                        </div>

                        <!-- Status message area -->
                        <div id="uploadStatus"></div>

                        <button type="submit" id="submitBtn"
                                class="ns-btn ns-btn-primary ns-btn-block mt-2">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            Upload File
                        </button>
                    </form>

                    <!-- Terminal log panel -->
                    <div class="ns-terminal mt-2" id="terminalLog"></div>

                </div>
            </div>

            <!-- Network info card -->
            <div class="ns-card mt-2">
                <div class="ns-card-header">
                    <span class="ns-card-title">Your Network Node</span>
                </div>
                <div class="ns-card-body" style="font-size:11px; line-height:2">
                    <div class="d-flex justify-between">
                        <span class="text-dim">Client IP</span>
                        <code class="text-accent"><?= e($ip) ?></code>
                    </div>
                    <div class="d-flex justify-between">
                        <span class="text-dim">Server Host</span>
                        <code class="text-accent"><?= e($_SERVER['HTTP_HOST'] ?? 'localhost') ?></code>
                    </div>
                    <div class="d-flex justify-between">
                        <span class="text-dim">Protocol</span>
                        <code class="text-accent">HTTP/1.1</code>
                    </div>
                    <div class="d-flex justify-between">
                        <span class="text-dim">Session ID</span>
                        <code class="text-muted"><?= substr(session_id(), 0, 16) ?>…</code>
                    </div>
                    <div class="d-flex justify-between">
                        <span class="text-dim">User Agent</span>
                        <span class="text-muted" style="font-size:10px; text-align:right; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">
                            <?= e(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 40)) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── RIGHT: File List ────────────────────────── -->
        <div class="anim-fadein anim-fadein-delay-2">
            <div class="ns-card">
                <div class="ns-card-header">
                    <span class="ns-card-title">Server File Store</span>
                    <span class="text-dim" style="font-size:10px">
                        <?= count($files) ?> file<?= count($files) !== 1 ? 's' : '' ?> available
                    </span>
                </div>

                <?php if (empty($files)): ?>
                <div class="ns-card-body" style="text-align:center; padding:3rem; color:var(--text-dim)">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="margin-bottom:1rem">
                        <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/>
                        <polyline points="13 2 13 9 20 9"/>
                    </svg>
                    <p style="font-size:12px; letter-spacing:0.06em">No files on server yet.<br>Upload the first one!</p>
                </div>
                <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="ns-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>File Name</th>
                                <th>Size</th>
                                <th>Uploaded By</th>
                                <th>Upload Time</th>
                                <th>From IP</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($files as $f): ?>
                            <?php
                            $ext = strtolower($f['file_type']);
                            $size = format_size($f['file_size']);
                            ?>
                            <tr>
                                <td>
                                    <span class="ns-filetype ns-filetype-<?= e($ext) ?>">
                                        <?= e(strtoupper($ext)) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="filename" title="<?= e($f['original_name']) ?>">
                                        <?= e($f['original_name']) ?>
                                    </span>
                                </td>
                                <td class="text-muted"><?= e($size) ?></td>
                                <td class="text-muted">
                                    <?php if ($f['user_id'] == $user_id): ?>
                                        <span class="text-accent">◈ <?= e($f['uploader']) ?></span>
                                    <?php else: ?>
                                        <?= e($f['uploader']) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted" style="font-size:11px; white-space:nowrap">
                                    <?= e($f['uploaded_at']) ?>
                                </td>
                                <td>
                                    <code style="font-size:10px; color:var(--text-dim)">
                                        <?= e($f['upload_ip'] ?? '—') ?>
                                    </code>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <!-- Download: HTTP GET request to server -->
                                        <a href="/netshare/ajax/download.php?id=<?= $f['id'] ?>"
                                           class="ns-btn ns-btn-outline ns-btn-sm"
                                           title="Download via HTTP GET">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                                <polyline points="7 10 12 15 17 10"/>
                                                <line x1="12" y1="15" x2="12" y2="3"/>
                                            </svg>
                                            GET
                                        </a>
                                        <?php if ($f['user_id'] == $user_id): ?>
                                        <button class="ns-btn ns-btn-danger ns-btn-sm"
                                                data-delete-id="<?= $f['id'] ?>"
                                                data-delete-name="<?= e($f['original_name']) ?>">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                <polyline points="3 6 5 6 21 6"/>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                                            </svg>
                                            DEL
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- grid -->

</div><!-- container -->
</div><!-- wrapper -->

<script src="/netshare/assets/js/main.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
