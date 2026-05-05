<?php
/**
 * logout.php — Session Termination
 * Destroys the PHP session and redirects to login.
 *
 * NETWORKING CONCEPT:
 * "Logging out" = destroying the server-side session state.
 * The session cookie becomes invalid. Next request from that
 * browser will be treated as a new, unauthenticated client.
 */

require_once __DIR__ . '/../includes/functions.php';
start_session();
session_destroy();
redirect('/netshare/pages/login.php');
