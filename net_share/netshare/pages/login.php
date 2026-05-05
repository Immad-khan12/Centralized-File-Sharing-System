<?php
/**
 * ============================================================
 *  login.php — User Authentication Page
 *  NetShare | Network File Transfer System
 * ============================================================
 *
 *  NETWORKING CONCEPT:
 *  Authentication is the first step in secure network comms.
 *  Credentials travel over HTTP (TCP/IP). In production,
 *  you'd use HTTPS (TLS layer over TCP) to encrypt them.
 *  PHP sessions use a cookie (PHPSESSID) to maintain state
 *  across stateless HTTP requests — simulating a "connection"
 *  that HTTP doesn't natively have.
 * ============================================================
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

start_session();

// Already logged in? Go to dashboard
if (!empty($_SESSION['user_id'])) {
    redirect('/netshare/pages/dashboard.php');
}

$error   = '';
$success = '';
$tab     = 'login'; // which tab is shown by default

// ── Handle LOGIN ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $tab      = 'login';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        // Prepared statement prevents SQL injection
        $stmt = $conn->prepare("SELECT id, username, password_hash FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password_hash'])) {
            // ── Start session (server-side state) ─────────
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['ip']       = get_client_ip();

            // Update last_login and IP in database
            $ip = get_client_ip();
            $stmt2 = $conn->prepare("UPDATE users SET last_login = NOW(), ip_address = ? WHERE id = ?");
            $stmt2->bind_param('si', $ip, $user['id']);
            $stmt2->execute();
            $stmt2->close();

            redirect('/netshare/pages/dashboard.php');
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

// ── Handle REGISTER ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $tab      = 'register';
    $username = trim($_POST['reg_username'] ?? '');
    $email    = trim($_POST['reg_email'] ?? '');
    $password = $_POST['reg_password'] ?? '';
    $confirm  = $_POST['reg_confirm'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        $stmt->get_result()->num_rows > 0 ? $exists = true : $exists = false;
        $stmt->close();

        if ($exists) {
            $error = 'Username or email already taken.';
        } else {
            // Hash password with bcrypt (cost 10 is default, very secure)
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $ip   = get_client_ip();

            $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, ip_address) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $username, $email, $hash, $ip);
            if ($stmt->execute()) {
                $success = 'Account created! You can now log in.';
                $tab = 'login';
            } else {
                $error = 'Registration failed. Please try again.';
            }
            $stmt->close();
        }
    }
}

$client_ip = get_client_ip();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NetShare — Sign In</title>
    <link rel="stylesheet" href="/netshare/assets/css/style.css">
    <!-- Bootstrap 5 (utility classes & JS components) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="ns-wrapper" style="position:relative;z-index:1;">
<div class="ns-auth-page">
<div class="ns-auth-box anim-fadein">

    <!-- Logo -->
    <div class="ns-auth-logo">
        <h1>◈ NETSHARE</h1>
        <p>Network File Transfer System &nbsp;·&nbsp; LAN Edition</p>
    </div>

    <!-- Network info banner -->
    <div class="ns-network-banner">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        Your IP: <code><?= e($client_ip) ?></code> &nbsp; — accessible over LAN
    </div>

    <!-- Alert -->
    <?php if ($error): ?>
    <div class="ns-alert ns-alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="ns-alert ns-alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <!-- Auth card -->
    <div class="ns-card">
        <!-- Tabs -->
        <div class="ns-auth-tabs">
            <div class="ns-auth-tab <?= $tab === 'login' ? 'active' : '' ?>"
                 onclick="switchTab('login')">Sign In</div>
            <div class="ns-auth-tab <?= $tab === 'register' ? 'active' : '' ?>"
                 onclick="switchTab('register')">Register</div>
        </div>

        <div class="ns-card-body">

            <!-- ── LOGIN FORM ─────────────────────────── -->
            <div id="tab-login" style="display: <?= $tab==='login' ? 'block' : 'none' ?>">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="login">

                    <div class="ns-form-group">
                        <label class="ns-label">Username or Email</label>
                        <input type="text" name="username" class="ns-input"
                               placeholder="user@netshare.local"
                               value="<?= e($_POST['username'] ?? '') ?>" required>
                    </div>

                    <div class="ns-form-group">
                        <label class="ns-label">Password</label>
                        <input type="password" name="password" class="ns-input"
                               placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="ns-btn ns-btn-primary ns-btn-block mt-2">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                            <polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
                        </svg>
                        Connect to Network
                    </button>
                </form>

                <p class="text-dim mt-2" style="font-size:11px; text-align:center">
                    Default test account: <code style="color:var(--accent)">admin / admin123</code>
                </p>
            </div>

            <!-- ── REGISTER FORM ──────────────────────── -->
            <div id="tab-register" style="display: <?= $tab==='register' ? 'block' : 'none' ?>">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="register">

                    <div class="ns-form-group">
                        <label class="ns-label">Username</label>
                        <input type="text" name="reg_username" class="ns-input"
                               placeholder="yourname"
                               value="<?= e($_POST['reg_username'] ?? '') ?>" required>
                    </div>

                    <div class="ns-form-group">
                        <label class="ns-label">Email</label>
                        <input type="email" name="reg_email" class="ns-input"
                               placeholder="you@example.com"
                               value="<?= e($_POST['reg_email'] ?? '') ?>" required>
                    </div>

                    <div class="ns-form-group">
                        <label class="ns-label">Password <span class="text-dim">(min. 6 chars)</span></label>
                        <input type="password" name="reg_password" class="ns-input"
                               placeholder="••••••••" required minlength="6">
                    </div>

                    <div class="ns-form-group">
                        <label class="ns-label">Confirm Password</label>
                        <input type="password" name="reg_confirm" class="ns-input"
                               placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="ns-btn ns-btn-primary ns-btn-block mt-2">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        Create Account
                    </button>
                </form>
            </div>

        </div>
    </div>

    <!-- Footer note -->
    <p class="text-dim mt-2" style="font-size:10px; text-align:center; letter-spacing:0.06em">
        COMPUTER NETWORKS PROJECT &nbsp;·&nbsp;
        CLIENT-SERVER ARCHITECTURE &nbsp;·&nbsp;
        HTTP/1.1 OVER LAN
    </p>

</div>
</div>
</div><!-- ns-wrapper -->

<script>
function switchTab(name) {
    document.getElementById('tab-login').style.display    = name === 'login'    ? 'block' : 'none';
    document.getElementById('tab-register').style.display = name === 'register' ? 'block' : 'none';
    document.querySelectorAll('.ns-auth-tab').forEach((el, i) => {
        el.classList.toggle('active', (i === 0 && name === 'login') || (i === 1 && name === 'register'));
    });
}
</script>
<canvas id="networkCanvas" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;pointer-events:none;"></canvas>
<script src="/netshare/assets/js/network-bg.js"></script>
</body>
</html>
