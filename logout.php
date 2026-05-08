<?php
/**
 * HydroLogic OS – Logout Handler
 * ================================
 * Destroys the current session and redirects to login.
 */
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

// Unset all session variables
$_SESSION = [];

// Destroy the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// Destroy the session
session_destroy();

// Redirect to login with a logout confirmation
header('Location: ' . base_url('login.php') . '?logout=1');
exit;
