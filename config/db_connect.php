<?php
/**
 * HydroLogic OS – Database Connection
 * =====================================
 * Establishes a MySQLi connection using procedural style.
 * All queries throughout the app use prepared statements
 * to prevent SQL injection.
 *
 * Usage: require_once __DIR__ . '/../config/db_connect.php';
 */

// ── Database Credentials ────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // XAMPP default – change for production
define('DB_PASS', '');           // XAMPP default – change for production
define('DB_NAME', 'hydrologic_os');
define('DB_CHARSET', 'utf8mb4');

// ── Application Constants ───────────────────────────────────
define('APP_NAME',    'HydroLogic OS');
define('APP_VERSION', 'v2.4.0');
define('RECORDS_PER_PAGE', 10);       // Default pagination size
define('CSRF_TOKEN_NAME', '_csrf_token');

// ── Session Bootstrap ────────────────────────────────────────
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── MySQLi Connection ────────────────────────────────────────
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    // In production, log this error – never expose credentials
    error_log('Database connection failed: ' . mysqli_connect_error());
    die('<div style="font-family:monospace;padding:2rem;color:#ba1a1a;">
            <strong>System Error:</strong> Unable to connect to the database. 
            Please check your XAMPP MySQL service and database credentials in 
            <code>config/db_connect.php</code>.
         </div>');
}

// Set the connection charset to prevent encoding issues
mysqli_set_charset($conn, DB_CHARSET);
