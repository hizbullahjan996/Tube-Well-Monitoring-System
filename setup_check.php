<?php
/**
 * HydroLogic OS – Setup/Installation Checker
 * ============================================
 * Run this ONCE after importing the schema to verify the setup.
 * Delete or rename this file after setup is complete.
 *
 * URL: http://localhost/Tube-Well-Monitoring%20-System/setup_check.php
 */

// Basic security: allow access only from localhost
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    http_response_code(403);
    die('Access denied. This script can only be run from localhost.');
}

require_once __DIR__ . '/config/db_connect.php';

$checks = [];

// 1. Database connection
$checks[] = ['Database Connection', $conn ? 'pass' : 'fail', $conn ? 'Connected to hydrologic_os' : 'Failed to connect'];

// 2. Tables exist
$tables = ['users','tube_wells','water_usage','electricity_usage','maintenance'];
foreach ($tables as $tbl) {
    $r = mysqli_query($conn,"SHOW TABLES LIKE '$tbl'");
    $exists = mysqli_num_rows($r) > 0;
    $checks[] = ["Table: $tbl", $exists?'pass':'fail', $exists?'Table exists':'MISSING – import schema.sql'];
}

// 3. Default users exist
$r = mysqli_query($conn,"SELECT COUNT(*) AS c FROM users WHERE email IN ('admin@hydrologic.io','operator@hydrologic.io')");
$ucnt = mysqli_fetch_assoc($r)['c'] ?? 0;
$checks[] = ['Default Users', $ucnt>=2?'pass':'warn', "Found $ucnt of 2 default accounts"];

// 4. Sample data
$r = mysqli_query($conn,"SELECT COUNT(*) AS c FROM tube_wells");
$wcnt = mysqli_fetch_assoc($r)['c'] ?? 0;
$checks[] = ['Sample Tube Wells', $wcnt>0?'pass':'warn', "$wcnt tube wells in database"];

// 5. PHP version
$phpver = PHP_VERSION;
$checks[] = ['PHP Version', version_compare($phpver,'7.4','>=') ? 'pass':'warn', "Running PHP $phpver (7.4+ recommended)"];

// 6. Session support
$checks[] = ['Session Support', session_status()!==PHP_SESSION_DISABLED?'pass':'fail', 'Sessions are '.( session_status()===PHP_SESSION_DISABLED?'DISABLED':'enabled')];

// 7. Verify password hash
$r2 = mysqli_query($conn,"SELECT password FROM users WHERE email='admin@hydrologic.io' LIMIT 1");
$row= mysqli_fetch_assoc($r2);
$hash_ok = $row && password_verify('admin123', $row['password']);
$checks[] = ['Password Hash Verify', $hash_ok?'pass':'fail', $hash_ok?'admin123 verifies correctly':'Hash mismatch – re-import schema.sql'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>HydroLogic OS – Setup Check</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-8">
<div class="w-full max-w-2xl">
    <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
        <div class="bg-slate-900 px-8 py-6">
            <h1 class="text-white text-2xl font-bold">🔧 HydroLogic OS – Setup Verification</h1>
            <p class="text-slate-400 text-sm mt-1">Run this page after importing the database schema.</p>
        </div>
        <div class="p-8 space-y-3">
            <?php foreach ($checks as [$label, $status, $msg]): ?>
            <div class="flex items-center gap-4 p-4 rounded-xl border <?= $status==='pass'?'bg-green-50 border-green-200':($status==='warn'?'bg-yellow-50 border-yellow-200':'bg-red-50 border-red-200') ?>">
                <span class="text-2xl"><?= $status==='pass'?'✅':($status==='warn'?'⚠️':'❌') ?></span>
                <div>
                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($label) ?></p>
                    <p class="text-sm text-gray-500 font-mono"><?= htmlspecialchars($msg) ?></p>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-xl">
                <p class="font-bold text-blue-800">Next Steps:</p>
                <ol class="text-sm text-blue-700 mt-2 space-y-1 list-decimal list-inside">
                    <li>If all checks pass, <a href="login.php" class="underline font-bold">go to Login</a></li>
                    <li>Login as Admin: <code class="bg-blue-100 px-1 rounded">admin@hydrologic.io</code> / <code class="bg-blue-100 px-1 rounded">admin123</code></li>
                    <li>Login as Operator: <code class="bg-blue-100 px-1 rounded">operator@hydrologic.io</code> / <code class="bg-blue-100 px-1 rounded">operator123</code></li>
                    <li><strong>Delete this file</strong> (setup_check.php) after verifying!</li>
                </ol>
            </div>
        </div>
    </div>
</div>
</body>
</html>
