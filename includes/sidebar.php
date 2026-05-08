<?php
/**
 * HydroLogic OS – Sidebar Navigation Include
 * ============================================
 * Role-aware sidebar. Shows "User Management" only to Admins.
 *
 * Variables expected:
 *   $active_page (string) – one of: dashboard, tube_wells, water_usage,
 *                            electricity, maintenance, users
 */
$active_page = $active_page ?? '';
$role        = current_role();

// ── Navigation items (label, icon, href, page-key, roles-allowed)
$nav_items = [
    ['label' => 'Dashboard',       'icon' => 'dashboard',        'href' => '../admin/dashboard.php',   'key' => 'dashboard',     'roles' => ['Admin']],
    ['label' => 'Tube Wells',      'icon' => 'water_pump',       'href' => '../admin/tube_wells.php',  'key' => 'tube_wells',    'roles' => ['Admin','Operator']],
    ['label' => 'Water Usage',     'icon' => 'opacity',          'href' => '../admin/water_usage.php', 'key' => 'water_usage',   'roles' => ['Admin','Operator']],
    ['label' => 'Electricity',     'icon' => 'bolt',             'href' => '../admin/electricity.php', 'key' => 'electricity',   'roles' => ['Admin','Operator']],
    ['label' => 'Maintenance',     'icon' => 'settings_suggest', 'href' => '../admin/maintenance.php', 'key' => 'maintenance',   'roles' => ['Admin','Operator']],
    ['label' => 'User Management', 'icon' => 'group',            'href' => '../admin/users.php',       'key' => 'users',         'roles' => ['Admin']],
];

// Operator-specific hrefs override admin hrefs
if ($role === 'Operator') {
    $nav_items[0]['href'] = '../operator/dashboard.php';
    $nav_items[2]['href'] = '../operator/water_usage.php';
    $nav_items[4]['href'] = '../operator/maintenance.php';
}
?>
<!-- ── Sidebar Navigation ─────────────────────────────────── -->
<aside class="fixed h-screen left-0 top-0 w-sidebar-width bg-primary border-r border-outline-variant shadow-sm flex flex-col p-md z-50">

    <!-- Brand / Logo -->
    <div class="mb-xl px-sm">
        <div class="flex items-center gap-sm mb-xs">
            <div class="w-10 h-10 bg-on-primary rounded flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-primary">water_pump</span>
            </div>
            <div>
                <h1 class="font-h3 text-h3 font-black text-on-primary tracking-tight leading-tight">Industrial Control</h1>
                <p class="text-[10px] uppercase tracking-widest text-on-primary/60 font-bold"><?= APP_NAME ?> <?= APP_VERSION ?></p>
            </div>
        </div>
    </div>

    <!-- Nav Items -->
    <nav class="flex-1 space-y-xs overflow-y-auto">
        <?php foreach ($nav_items as $item): ?>
            <?php if (!in_array($role, $item['roles'])) continue; ?>
            <?php
                $is_active = ($active_page === $item['key']);
                $cls = $is_active
                    ? 'sidebar-active rounded-lg'
                    : 'text-on-primary/70 hover:text-on-primary hover:bg-on-primary/10 rounded-lg';
            ?>
            <a href="<?= $item['href'] ?>"
               class="flex items-center gap-md px-md py-sm cursor-pointer transition-all duration-200 <?= $cls ?>">
                <span class="material-symbols-outlined"><?= $item['icon'] ?></span>
                <span class="font-body-md"><?= $item['label'] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Bottom: Support + Sign Out -->
    <div class="pt-md border-t border-on-primary/10 space-y-xs">
        <!-- Logged-in user mini card -->
        <div class="flex items-center gap-sm px-md py-sm mb-sm">
            <div class="w-8 h-8 rounded-full bg-secondary flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-on-primary text-[16px]">person</span>
            </div>
            <div class="min-w-0">
                <p class="text-on-primary font-bold text-body-md leading-tight truncate"><?= current_user_name() ?></p>
                <p class="text-on-primary/50 text-[10px] uppercase tracking-wider"><?= sanitize($role) ?></p>
            </div>
        </div>

        <a href="../logout.php"
           class="flex items-center gap-md px-md py-sm cursor-pointer text-on-primary/70 hover:text-on-primary hover:bg-on-primary/10 rounded-lg transition-all duration-200"
           onclick="return confirm('Are you sure you want to sign out?')">
            <span class="material-symbols-outlined">logout</span>
            <span class="font-body-md">Sign Out</span>
        </a>
    </div>
</aside>
