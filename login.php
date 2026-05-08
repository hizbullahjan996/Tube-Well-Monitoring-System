<?php
/**
 * HydroLogic OS – Login Page
 * ===========================
 * Handles user authentication with prepared statements.
 * Redirects authenticated users based on their role.
 */

require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect already-logged-in users
if (!empty($_SESSION['user_id'])) {
    $redirect = ($_SESSION['role'] === 'Admin') ? 'admin/dashboard.php' : 'operator/dashboard.php';
    header('Location: ' . base_url($redirect));
    exit;
}

$error = '';

// ── Handle POST login submission ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // Prepared statement – prevents SQL injection
        $stmt = mysqli_prepare($conn, "SELECT id, name, email, password, role FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($user && password_verify($password, $user['password'])) {
            // ✔ Valid credentials – create session
            session_regenerate_id(true);   // Prevent session fixation
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email']= $user['email'];
            $_SESSION['role']      = $user['role'];

            // Role-based redirect
            if ($user['role'] === 'Admin') {
                header('Location: ' . base_url('admin/dashboard.php'));
            } else {
                header('Location: ' . base_url('operator/dashboard.php'));
            }
            exit;
        } else {
            $error = 'Invalid credentials. Please check your email and password.';
        }
    }
}

$csrf = generate_csrf();
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="description" content="HydroLogic OS – Secure login portal for the Industrial Tube Well Monitoring System.">
    <title>Login – HydroLogic OS</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode:"class",
            theme:{extend:{
                "colors":{
                    "on-tertiary-container":"#a38c6a","secondary":"#006c49","on-surface-variant":"#45474c","surface-variant":"#e4e2e3","primary":"#091426","error-container":"#ffdad6","on-background":"#1b1b1d","surface-container-highest":"#e4e2e3","on-secondary-fixed-variant":"#005236","on-secondary-container":"#00714d","outline":"#75777d","surface":"#fbf8fa","surface-container":"#f0edef","inverse-primary":"#bcc7de","on-secondary-fixed":"#002113","tertiary":"#1e1200","inverse-on-surface":"#f3f0f2","on-tertiary-fixed-variant":"#564427","on-primary":"#ffffff","on-error":"#ffffff","tertiary-container":"#35260c","inverse-surface":"#303032","surface-tint":"#545f73","tertiary-fixed-dim":"#ddc39d","secondary-container":"#6cf8bb","error":"#ba1a1a","primary-fixed-dim":"#bcc7de","outline-variant":"#c5c6cd","surface-container-high":"#eae7e9","on-error-container":"#93000a","surface-container-low":"#f5f3f4","on-secondary":"#ffffff","on-primary-container":"#8590a6","secondary-fixed-dim":"#4edea3","surface-container-lowest":"#ffffff","on-surface":"#1b1b1d","primary-fixed":"#d8e3fb","on-primary-fixed":"#111c2d","tertiary-fixed":"#fadfb8","on-tertiary-fixed":"#271902","background":"#fbf8fa","secondary-fixed":"#6ffbbe","primary-container":"#1e293b","surface-dim":"#dcd9db","on-tertiary":"#ffffff","surface-bright":"#fbf8fa","on-primary-fixed-variant":"#3c475a"
                },
                "borderRadius":{"DEFAULT":"0.125rem","lg":"0.25rem","xl":"0.5rem","full":"0.75rem"},
                "spacing":{"sidebar-width":"260px","2xl":"48px","xs":"4px","md":"16px","sm":"8px","gutter":"20px","unit":"4px","xl":"32px","lg":"24px"},
                "fontFamily":{"h1":["Inter"],"data-mono":["JetBrains Mono"],"body-lg":["Inter"],"h2":["Inter"],"body-md":["Inter"],"h3":["Inter"],"label-sm":["Inter"]},
                "fontSize":{"h1":["30px",{"lineHeight":"38px","letterSpacing":"-0.02em","fontWeight":"700"}],"data-mono":["14px",{"lineHeight":"20px","fontWeight":"500"}],"body-lg":["16px",{"lineHeight":"24px","fontWeight":"400"}],"h2":["24px",{"lineHeight":"32px","letterSpacing":"-0.01em","fontWeight":"600"}],"body-md":["14px",{"lineHeight":"20px","fontWeight":"400"}],"h3":["20px",{"lineHeight":"28px","fontWeight":"600"}],"label-sm":["12px",{"lineHeight":"16px","letterSpacing":"0.05em","fontWeight":"600"}]}
            }}
        }
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; }
        .form-input-focus:focus { outline:none; box-shadow:0 0 0 2px #d8e3fb,0 0 0 4px rgba(30,41,59,0.1); border-color:#1e293b; }
        body { font-family:'Inter',sans-serif; }
    </style>
</head>
<body class="bg-background font-body-md text-on-background min-h-screen flex flex-col relative overflow-hidden">

<!-- Background Industrial Aesthetic Layer -->
<div class="absolute inset-0 z-0">
    <div class="absolute inset-0 bg-gradient-to-br from-primary/10 via-background to-surface-container-high/40 opacity-80"></div>
    <div class="absolute inset-0 opacity-10 mix-blend-overlay" style="background-image:radial-gradient(#1e293b 0.5px,transparent 0.5px);background-size:24px 24px;"></div>
</div>

<!-- Main Content Canvas -->
<main class="relative z-10 flex-grow flex items-center justify-center p-md">
    <div class="w-full max-w-[440px]">

        <!-- Branding Header -->
        <div class="flex flex-col items-center mb-xl">
            <div class="w-16 h-16 bg-primary-container rounded-xl flex items-center justify-center shadow-lg mb-md">
                <span class="material-symbols-outlined text-primary-fixed text-[40px]">water_pump</span>
            </div>
            <h1 class="font-h1 text-h1 text-primary tracking-tight">HydroLogic OS</h1>
            <p class="font-body-md text-on-surface-variant mt-xs">Industrial Tube Well Control Portal</p>
        </div>

        <!-- Login Card -->
        <div class="bg-surface-container-lowest border border-outline-variant shadow-sm rounded-xl overflow-hidden">
            <div class="px-xl py-lg border-b border-surface-container-high">
                <h2 class="font-h3 text-h3 text-on-surface">System Access</h2>
                <p class="font-label-sm text-on-surface-variant uppercase mt-xs">Authenticated Session Required</p>
            </div>

            <!-- Error Flash -->
            <?php if ($error): ?>
            <div class="mx-xl mt-lg px-md py-sm bg-error-container border-l-4 border-error rounded-lg flex items-start gap-sm" role="alert">
                <span class="material-symbols-outlined text-error text-[18px] mt-xs" style="font-variation-settings:'FILL' 1;">error</span>
                <p class="font-body-md text-on-error-container"><?= sanitize($error) ?></p>
            </div>
            <?php endif; ?>

            <form class="p-xl space-y-lg" method="POST" action="" id="login-form" novalidate>
                <!-- CSRF Token -->
                <?= csrf_field() ?>

                <!-- Email Field -->
                <div class="space-y-sm">
                    <label class="font-label-sm text-on-surface-variant block uppercase" for="email">Work Email</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-md top-1/2 -translate-y-1/2 text-outline">mail</span>
                        <input
                            class="w-full pl-12 pr-md py-md bg-surface border border-outline-variant rounded-lg font-body-md form-input-focus placeholder:text-outline-variant"
                            id="email"
                            name="email"
                            placeholder="admin@hydrologic.io"
                            required
                            type="email"
                            value="<?= sanitize($_POST['email'] ?? '') ?>"
                            autocomplete="email"
                        />
                    </div>
                </div>

                <!-- Password Field -->
                <div class="space-y-sm">
                    <div class="flex justify-between items-center">
                        <label class="font-label-sm text-on-surface-variant block uppercase" for="password">Security Key</label>
                    </div>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-md top-1/2 -translate-y-1/2 text-outline">lock</span>
                        <input
                            class="w-full pl-12 pr-12 py-md bg-surface border border-outline-variant rounded-lg font-body-md form-input-focus placeholder:text-outline-variant"
                            id="password"
                            name="password"
                            placeholder="••••••••••••"
                            required
                            type="password"
                            autocomplete="current-password"
                        />
                        <!-- Toggle password visibility -->
                        <button type="button" id="toggle-pass" class="absolute right-md top-1/2 -translate-y-1/2 text-outline hover:text-primary transition-colors">
                            <span class="material-symbols-outlined text-[20px]" id="pass-eye-icon">visibility</span>
                        </button>
                    </div>
                </div>

                <!-- Submit -->
                <div class="pt-sm">
                    <button
                        class="w-full bg-primary-container text-on-primary px-lg py-md rounded-lg font-h3 flex items-center justify-center gap-sm hover:bg-primary transition-all active:scale-[0.98] shadow-md border-t border-white/10"
                        type="submit"
                        id="submit-btn"
                    >
                        Initialize Session
                        <span class="material-symbols-outlined">login</span>
                    </button>
                </div>
            </form>

            <!-- Status Bar -->
            <div class="px-xl py-md bg-surface-container-low flex items-center gap-sm">
                <div class="w-2 h-2 rounded-full bg-secondary animate-pulse"></div>
                <span class="font-label-sm text-on-surface-variant">System Gateway: Online &amp; Secure</span>
            </div>
        </div>

        <!-- Footer Meta -->
        <div class="mt-xl flex flex-col items-center gap-md">
            <p class="font-label-sm text-outline-variant">© <?= date('Y') ?> Industrial Control Systems <?= APP_VERSION ?></p>
          
  
        </div>
    </div>
</main>

<script>
    // Toggle password visibility
    document.getElementById('toggle-pass').addEventListener('click', function() {
        const input = document.getElementById('password');
        const icon  = document.getElementById('pass-eye-icon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.textContent = 'visibility_off';
        } else {
            input.type = 'password';
            icon.textContent = 'visibility';
        }
    });
</script>
</body>
</html>
