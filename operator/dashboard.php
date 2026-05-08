<?php
/**
 * HydroLogic OS – Operator Dashboard
 * =====================================
 * Read-only overview for field operators.
 */

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

// Allow both Admin and Operator to view this
$page_title  = 'Operator Dashboard';
$active_page = 'dashboard';

// Stats
$r = mysqli_query($conn,"SELECT COUNT(*) AS c FROM tube_wells WHERE status='Active'");
$active_wells = mysqli_fetch_assoc($r)['c'] ?? 0;

$r = mysqli_query($conn,"SELECT COUNT(*) AS c FROM maintenance WHERE status IN ('Pending','In Progress')");
$open_tickets = mysqli_fetch_assoc($r)['c'] ?? 0;

$r = mysqli_query($conn,"SELECT COALESCE(SUM(water_amount),0) AS t FROM water_usage WHERE usage_date=CURDATE()");
$today_water = mysqli_fetch_assoc($r)['t'] ?? 0;

$r = mysqli_query($conn,"SELECT COALESCE(SUM(units_consumed),0) AS t FROM electricity_usage WHERE MONTH(usage_date)=MONTH(CURDATE())");
$month_elec = mysqli_fetch_assoc($r)['t'] ?? 0;

// Recent water logs (last 5 by current operator)
$uid = (int)$_SESSION['user_id'];
$stmt = mysqli_prepare($conn,
    "SELECT wu.*, tw.tube_well_name, tw.well_code FROM water_usage wu
     JOIN tube_wells tw ON tw.id=wu.tube_well_id
     WHERE wu.recorded_by=? ORDER BY wu.created_at DESC LIMIT 5"
);
mysqli_stmt_bind_param($stmt,'i',$uid);
mysqli_stmt_execute($stmt);
$my_logs = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

// Recent open maintenance tickets
$stmt2 = mysqli_prepare($conn,
    "SELECT m.*, tw.tube_well_name, tw.well_code FROM maintenance m
     JOIN tube_wells tw ON tw.id=m.tube_well_id
     WHERE m.status != 'Completed' ORDER BY m.created_at DESC LIMIT 5"
);
mysqli_stmt_execute($stmt2);
$open_maint = mysqli_stmt_get_result($stmt2);
mysqli_stmt_close($stmt2);

// Sidebar links for operator
$nav_items = [
    ['label'=>'Dashboard',   'icon'=>'dashboard',       'href'=>'../operator/dashboard.php',  'key'=>'dashboard',   'roles'=>['Admin','Operator']],
    ['label'=>'Tube Wells',  'icon'=>'water_pump',      'href'=>'../admin/tube_wells.php',    'key'=>'tube_wells',  'roles'=>['Admin','Operator']],
    ['label'=>'Water Usage', 'icon'=>'opacity',         'href'=>'../operator/water_usage.php','key'=>'water_usage', 'roles'=>['Admin','Operator']],
    ['label'=>'Electricity', 'icon'=>'bolt',            'href'=>'../admin/electricity.php',   'key'=>'electricity', 'roles'=>['Admin','Operator']],
    ['label'=>'Maintenance', 'icon'=>'settings_suggest','href'=>'../operator/maintenance.php','key'=>'maintenance', 'roles'=>['Admin','Operator']],
];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="ml-sidebar-width min-h-screen flex flex-col">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="p-lg space-y-lg flex-1">
        <?= render_flash() ?>

        <!-- Welcome -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-h1 text-h1 text-primary">Welcome back, <?= current_user_name() ?></h2>
                <p class="text-on-surface-variant font-body-md">Field Operator Dashboard – <?= date('l, d F Y') ?></p>
            </div>
            <div class="flex gap-sm">
                <a href="<?= base_url('operator/water_usage.php') ?>"
                   class="flex items-center gap-sm bg-secondary text-on-secondary px-lg py-md rounded-lg shadow-md hover:opacity-90">
                    <span class="material-symbols-outlined">add</span>
                    <span class="font-semibold">Log Water Usage</span>
                </a>
                <a href="<?= base_url('operator/maintenance.php') ?>"
                   class="flex items-center gap-sm bg-surface border border-outline-variant text-primary px-lg py-md rounded-lg hover:bg-surface-container-low">
                    <span class="material-symbols-outlined">report_problem</span>
                    <span class="font-semibold">Report Issue</span>
                </a>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-lg">
            <div class="bg-surface-container-lowest border border-outline-variant p-lg rounded-xl shadow-sm">
                <div class="p-sm bg-secondary/10 rounded-lg text-secondary w-fit mb-md">
                    <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">check_circle</span>
                </div>
                <p class="text-label-sm text-on-surface-variant uppercase">Active Wells</p>
                <h3 class="text-h1 font-h1 text-primary mt-xs"><?= $active_wells ?></h3>
            </div>
            <div class="bg-surface-container-lowest border border-outline-variant p-lg rounded-xl shadow-sm">
                <div class="p-sm bg-tertiary-fixed/30 rounded-lg text-tertiary w-fit mb-md">
                    <span class="material-symbols-outlined">opacity</span>
                </div>
                <p class="text-label-sm text-on-surface-variant uppercase">Today's Water</p>
                <h3 class="text-h1 font-h1 text-primary mt-xs"><?= number_format($today_water/1000,1) ?>k</h3>
                <p class="text-label-sm text-on-surface-variant">Liters</p>
            </div>
            <div class="bg-surface-container-lowest border border-outline-variant p-lg rounded-xl shadow-sm">
                <div class="p-sm bg-error-container/50 rounded-lg text-error w-fit mb-md">
                    <span class="material-symbols-outlined">bolt</span>
                </div>
                <p class="text-label-sm text-on-surface-variant uppercase">Month kWh</p>
                <h3 class="text-h1 font-h1 text-primary mt-xs"><?= number_format($month_elec,0) ?></h3>
            </div>
            <div class="bg-surface-container-lowest border border-<?= $open_tickets>0?'error/20':'outline-variant' ?> p-lg rounded-xl shadow-sm">
                <div class="p-sm bg-<?= $open_tickets>0?'error':'surface-container-high' ?>/30 rounded-lg text-<?= $open_tickets>0?'error':'outline' ?> w-fit mb-md">
                    <span class="material-symbols-outlined">settings_suggest</span>
                </div>
                <p class="text-label-sm text-on-surface-variant uppercase">Open Tickets</p>
                <h3 class="text-h1 font-h1 text-<?= $open_tickets>0?'error':'primary' ?> mt-xs"><?= $open_tickets ?></h3>
            </div>
        </div>

        <!-- Two-column grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-lg">

            <!-- My Recent Water Logs -->
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm overflow-hidden">
                <div class="px-lg py-md border-b border-surface-container-high flex justify-between items-center">
                    <h4 class="font-h3 text-h3 text-primary">My Recent Logs</h4>
                    <a href="<?= base_url('operator/water_usage.php') ?>" class="text-secondary text-label-sm font-bold hover:underline">View All</a>
                </div>
                <div class="divide-y divide-surface-container-high">
                    <?php if (mysqli_num_rows($my_logs) === 0): ?>
                    <div class="px-lg py-xl text-center text-on-surface-variant">
                        <span class="material-symbols-outlined text-[32px] block mb-sm text-outline">opacity</span>
                        No water usage entries yet.
                    </div>
                    <?php else: while ($log = mysqli_fetch_assoc($my_logs)): ?>
                    <div class="px-lg py-md flex items-center justify-between">
                        <div>
                            <p class="font-body-md font-semibold text-primary"><?= sanitize($log['well_code']) ?></p>
                            <p class="text-label-sm text-on-surface-variant"><?= date('d M Y', strtotime($log['usage_date'])) ?></p>
                        </div>
                        <span class="font-data-mono text-primary font-bold"><?= number_format($log['water_amount'],0) ?> L</span>
                    </div>
                    <?php endwhile; endif; ?>
                </div>
            </div>

            <!-- Open Maintenance Tickets -->
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm overflow-hidden">
                <div class="px-lg py-md border-b border-surface-container-high flex justify-between items-center">
                    <h4 class="font-h3 text-h3 text-primary">Open Maintenance</h4>
                    <a href="<?= base_url('operator/maintenance.php') ?>" class="text-secondary text-label-sm font-bold hover:underline">Report Issue</a>
                </div>
                <div class="divide-y divide-surface-container-high">
                    <?php if (mysqli_num_rows($open_maint) === 0): ?>
                    <div class="px-lg py-xl text-center text-on-surface-variant">
                        <span class="material-symbols-outlined text-[32px] block mb-sm text-outline">check_circle</span>
                        All systems nominal!
                    </div>
                    <?php else: while ($mt = mysqli_fetch_assoc($open_maint)): ?>
                    <div class="px-lg py-md flex items-center justify-between gap-md">
                        <div class="flex-1 min-w-0">
                            <p class="font-body-md font-semibold text-primary truncate"><?= sanitize($mt['well_code']) ?> – <?= sanitize(substr($mt['issue'],0,40)) ?>...</p>
                            <p class="text-label-sm text-on-surface-variant"><?= date('d M', strtotime($mt['maintenance_date'])) ?></p>
                        </div>
                        <div class="flex-shrink-0"><?= severity_badge($mt['severity']) ?></div>
                    </div>
                    <?php endwhile; endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</main>
