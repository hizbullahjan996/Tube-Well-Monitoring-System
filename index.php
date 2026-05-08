<?php
/**
 * HydroLogic OS – Entry Point
 * =============================
 * Redirects to the appropriate dashboard based on login state.
 */
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'Admin') {
        header('Location: ' . base_url('admin/dashboard.php'));
    } else {
        header('Location: ' . base_url('operator/dashboard.php'));
    }
} else {
    header('Location: ' . base_url('login.php'));
}
exit;
