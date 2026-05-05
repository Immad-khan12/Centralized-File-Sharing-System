<?php
// Root index — redirect to login or dashboard
require_once __DIR__ . '/includes/functions.php';
start_session();
if (!empty($_SESSION['user_id'])) {
    redirect('/netshare/pages/dashboard.php');
} else {
    redirect('/netshare/pages/login.php');
}
