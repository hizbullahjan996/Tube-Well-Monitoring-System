<?php
/**
 * HydroLogic OS – Admin Dashboard
 * =================================
 * Displays live telemetry stats, Chart.js analytics,
 * system health, and recent maintenance tickets.
 */

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_role('Admin');

$page_title  = 'Dashboard';
$active_page = 'dashboard';

// ── Live Stats Queries ──────────────────────────────────────

// Total tube wells
$r = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tube_wells");
$total_wells = mysqli_fetch_assoc($r)['total'] ?? 0;

// Active wells
$r = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM tube_wells WHERE status = 'Active'");
$active_wells = mysqli_fetch_assoc($r)['cnt'] ?? 0;

// Maintenance wells
$r = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM tube_wells WHERE status = 'Under Maintenance'");
$maint_wells = mysqli_fetch_assoc($r)['cnt'] ?? 0;

// Total water usage this month (Liters)
$r = mysqli_query($conn, "SELECT COALESCE(SUM(water_amount),0) AS total FROM water_usage WHERE MONTH(usage_date)=MONTH(CURDATE()) AND YEAR(usage_date)=YEAR(CURDATE())");
$month_water = mysqli_fetch_assoc($r)['total'] ?? 0;

// Total electricity this month (kWh)
$r = mysqli_query($conn, "SELECT COALESCE(SUM(units_consumed),0) AS total, COALESCE(SUM(total_cost),0) AS cost FROM electricity_usage WHERE MONTH(usage_date)=MONTH(CURDATE()) AND YEAR(usage_date)=YEAR(CURDATE())");
$elec_row = mysqli_fetch_assoc($r);
$month_elec = $elec_row['total'] ?? 0;
$month_cost = $elec_row['cost']  ?? 0;

// Pending maintenance tickets
$r = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM maintenance WHERE status IN ('Pending','In Progress')");
$open_tickets = mysqli_fetch_assoc($r)['cnt'] ?? 0;

// ── Chart Data: Last 7 days water usage ────────────────────
$chart_labels = [];
$chart_data   = [];
for ($i = 6; $i >= 0; $i--) {
    $date  = date('Y-m-d', strtotime("-$i days"));
    $label = date('D d', strtotime($date));
    $stmt  = mysqli_prepare($conn, "SELECT COALESCE(SUM(water_amount),0) AS total FROM water_usage WHERE usage_date = ?");
    mysqli_stmt_bind_param($stmt, 's', $date);
    mysqli_stmt_execute($stmt);
    $res   = mysqli_stmt_get_result($stmt);
    $row   = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    $chart_labels[] = $label;
    $chart_data[]   = (float)($row['total'] ?? 0);
}

// ── Recent Maintenance Tickets ──────────────────────────────
$stmt = mysqli_prepare($conn,
    "SELECT m.id, m.issue, m.maintenance_date, m.status, m.severity,
            m.technician_name, tw.tube_well_name, tw.well_code
     FROM maintenance m
     JOIN tube_wells tw ON tw.id = m.tube_well_id
     ORDER BY m.created_at DESC LIMIT 5"
);
mysqli_stmt_execute($stmt);
$recent_tickets = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

// Helper format
function fmt_number($n): string {
    if ($n >= 1_000_000) return number_format($n / 1_000_000, 1) . 'M';
    if ($n >= 1_000)     return number_format($n / 1_000, 1) . 'k';
    return number_format($n, 0);
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- ── Main Content ──────────────────────────────────────── -->
<main class="ml-sidebar-width min-h-screen flex flex-col">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <!-- Page Canvas -->
    <div class="p-lg space-y-lg flex-1">
        <?= render_flash() ?>

        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-h1 text-h1 text-primary">System Overview</h2>
                <p class="text-on-surface-variant font-body-md">Real-time telemetry and infrastructure status — <?= date('l, d F Y') ?></p>
            </div>
            <a href="<?= base_url('admin/tube_wells.php') ?>?action=add"
               class="inline-flex items-center gap-sm bg-secondary text-on-secondary px-lg py-md rounded-full shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all font-bold">
                <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">add</span>
                Deploy New Unit
            </a>
        </div>

        <!-- Metric Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-lg">

            <!-- Total Tube Wells -->
            <div class="bg-surface-container-lowest border border-outline-variant p-lg rounded-xl shadow-sm group hover:border-secondary transition-all">
                <div class="flex justify-between items-start mb-md">
                    <div class="p-sm bg-primary/5 rounded-lg text-primary">
                        <span class="material-symbols-outlined">water_pump</span>
                    </div>
                    <span class="text-secondary font-bold text-label-sm flex items-center gap-xs">
                        <span class="material-symbols-outlined text-[14px]">trending_up</span>+2%
                    </span>
                </div>
                <p class="text-on-surface-variant text-label-sm uppercase tracking-wider font-semibold">Total Tube Wells</p>
                <h3 class="text-h1 font-h1 mt-xs"><?= $total_wells ?></h3>
                <div class="mt-md h-1 bg-surface-container-high rounded-full overflow-hidden">
                    <div class="h-full bg-primary" style="width:<?= $total_wells > 0 ? min(100, round($active_wells/$total_wells*100)) : 0 ?>%"></div>
                </div>
            </div>

            <!-- Active Units -->
            <div class="bg-surface-container-lowest border border-outline-variant p-lg rounded-xl shadow-sm group hover:border-secondary transition-all">
                <div class="flex justify-between items-start mb-md">
                    <div class="p-sm bg-secondary/10 rounded-lg text-secondary">
                        <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">check_circle</span>
                    </div>
                    <span class="text-on-surface-variant font-bold text-label-sm">Live</span>
                </div>
                <p class="text-on-surface-variant text-label-sm uppercase tracking-wider font-semibold">Active Units</p>
                <h3 class="text-h1 font-h1 mt-xs"><?= $active_wells ?></h3>
                <p class="text-label-sm text-on-surface-variant mt-sm">
                    <?= $total_wells > 0 ? number_format($active_wells/$total_wells*100,1) : 0 ?>% operational rate
                </p>
            </div>

            <!-- Water Usage (Monthly) -->
            <div class="bg-surface-container-lowest border border-outline-variant p-lg rounded-xl shadow-sm group hover:border-secondary transition-all">
                <div class="flex justify-between items-start mb-md">
                    <div class="p-sm bg-tertiary-fixed/30 rounded-lg text-tertiary">
                        <span class="material-symbols-outlined">opacity</span>
                    </div>
                    <span class="text-on-surface-variant font-bold text-label-sm">Monthly</span>
                </div>
                <p class="text-on-surface-variant text-label-sm uppercase tracking-wider font-semibold">Water Usage (L)</p>
                <h3 class="text-h1 font-h1 mt-xs"><?= fmt_number($month_water) ?></h3>
                <p class="text-label-sm text-on-surface-variant mt-sm"><?= date('F Y') ?></p>
            </div>

            <!-- Electricity / Open Tickets -->
            <div class="bg-surface-container-lowest border border-outline-variant p-lg rounded-xl shadow-sm group hover:border-secondary transition-all">
                <div class="flex justify-between items-start mb-md">
                    <div class="p-sm bg-error-container/50 rounded-lg text-error">
                        <span class="material-symbols-outlined">bolt</span>
                    </div>
                    <span class="text-secondary font-bold text-label-sm flex items-center gap-xs">
                        <span class="material-symbols-outlined text-[14px]">trending_down</span>-5%
                    </span>
                </div>
                <p class="text-on-surface-variant text-label-sm uppercase tracking-wider font-semibold">Electricity (kWh)</p>
                <h3 class="text-h1 font-h1 mt-xs"><?= fmt_number($month_elec) ?></h3>
                <p class="text-label-sm text-on-surface-variant mt-sm">Est. Cost: PKR <?= number_format($month_cost, 0) ?></p>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-lg">

            <!-- Water Usage Trends Chart (spans 2 cols) -->
            <div class="lg:col-span-2 bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm flex flex-col overflow-hidden">
                <div class="px-lg py-md border-b border-surface-container-high flex justify-between items-center">
                    <div>
                        <h4 class="font-h3 text-h3 text-primary">Water Usage Trends</h4>
                        <p class="text-label-sm text-on-surface-variant">Last 7 days volumetric extraction (Liters)</p>
                    </div>
                </div>
                <div class="flex-1 p-lg" style="min-height:300px;">
                    <canvas id="waterUsageChart" style="max-height:280px;"></canvas>
                </div>
            </div>

            <!-- System Health Column -->
            <div class="space-y-lg">
                <!-- System Health Widget -->
                <div class="bg-primary text-on-primary p-lg rounded-xl shadow-sm overflow-hidden relative">
                    <div class="relative z-10">
                        <h4 class="font-h3 text-h3 mb-md">System Health</h4>
                        <div class="space-y-md">
                            <div class="flex justify-between items-center">
                                <span class="text-body-md opacity-70">Active Wells</span>
                                <span class="text-secondary-fixed-dim font-bold flex items-center gap-xs">
                                    <span class="w-2 h-2 rounded-full bg-secondary-fixed-dim animate-pulse"></span>
                                    <?= $active_wells ?> / <?= $total_wells ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-body-md opacity-70">Under Maintenance</span>
                                <span class="text-on-primary font-bold"><?= $maint_wells ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-body-md opacity-70">Open Tickets</span>
                                <span class="text-on-primary font-bold"><?= $open_tickets ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-body-md opacity-70">Monthly Cost</span>
                                <span class="text-on-primary font-bold">PKR <?= number_format($month_cost,0) ?></span>
                            </div>
                        </div>
                        <a href="<?= base_url('admin/maintenance.php') ?>"
                           class="mt-lg block w-full py-2 bg-on-primary text-primary rounded-lg font-bold text-center hover:bg-secondary-fixed transition-colors">
                            View All Tickets
                        </a>
                    </div>
                    <div class="absolute -right-10 -bottom-10 opacity-10">
                        <span class="material-symbols-outlined text-[160px]">settings_input_component</span>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm p-md">
                    <h4 class="font-h3 text-h3 text-primary mb-md">Quick Stats</h4>
                    <div class="space-y-sm">
                        <div class="flex items-center justify-between py-sm border-b border-surface-container-high">
                            <span class="font-body-md text-on-surface-variant">Avg Daily Water</span>
                            <span class="font-data-mono text-primary font-bold">
                                <?= fmt_number(array_sum($chart_data) / max(1, count($chart_data))) ?> L
                            </span>
                        </div>
                        <div class="flex items-center justify-between py-sm border-b border-surface-container-high">
                            <span class="font-body-md text-on-surface-variant">Month kWh</span>
                            <span class="font-data-mono text-primary font-bold"><?= number_format($month_elec, 1) ?></span>
                        </div>
                        <div class="flex items-center justify-between py-sm">
                            <span class="font-body-md text-on-surface-variant">Offline Wells</span>
                            <span class="font-data-mono text-error font-bold"><?= $total_wells - $active_wells - $maint_wells ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Maintenance Tickets Table -->
        <div class="lg:col-span-3 bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm overflow-hidden">
            <div class="px-lg py-md border-b border-surface-container-high flex justify-between items-center">
                <div>
                    <h4 class="font-h3 text-h3 text-primary">Recent Maintenance Requests</h4>
                    <p class="text-label-sm text-on-surface-variant">Active field tickets and urgent repair logs</p>
                </div>
                <a href="<?= base_url('admin/maintenance.php') ?>" class="text-secondary font-bold text-body-md hover:underline">View All Tickets</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-surface-container-low border-b border-outline-variant">
                        <tr>
                            <th class="px-lg py-md text-label-sm font-bold text-on-surface-variant uppercase tracking-wider">Ticket ID</th>
                            <th class="px-lg py-md text-label-sm font-bold text-on-surface-variant uppercase tracking-wider">Tube Well</th>
                            <th class="px-lg py-md text-label-sm font-bold text-on-surface-variant uppercase tracking-wider">Issue Description</th>
                            <th class="px-lg py-md text-label-sm font-bold text-on-surface-variant uppercase tracking-wider">Date</th>
                            <th class="px-lg py-md text-label-sm font-bold text-on-surface-variant uppercase tracking-wider">Severity</th>
                            <th class="px-lg py-md text-label-sm font-bold text-on-surface-variant uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container-high">
                        <?php if (mysqli_num_rows($recent_tickets) === 0): ?>
                        <tr>
                            <td colspan="6" class="px-lg py-xl text-center text-on-surface-variant font-body-md">
                                <span class="material-symbols-outlined text-[40px] block mb-sm text-outline">check_circle</span>
                                No maintenance tickets found. All systems nominal.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php while ($ticket = mysqli_fetch_assoc($recent_tickets)): ?>
                        <tr class="hover:bg-surface-container-lowest transition-colors">
                            <td class="px-lg py-md font-data-mono text-primary font-bold">#TK-<?= str_pad($ticket['id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td class="px-lg py-md">
                                <div class="flex items-center gap-sm">
                                    <span class="w-2 h-2 rounded-full <?= $ticket['severity'] === 'Critical' ? 'bg-error' : ($ticket['severity'] === 'Warning' ? 'bg-tertiary-fixed-dim' : 'bg-secondary') ?>"></span>
                                    <span class="font-medium"><?= sanitize($ticket['well_code']) ?></span>
                                </div>
                                <span class="text-[11px] text-on-surface-variant"><?= sanitize($ticket['tube_well_name']) ?></span>
                            </td>
                            <td class="px-lg py-md text-on-surface-variant max-w-xs truncate"><?= sanitize($ticket['issue']) ?></td>
                            <td class="px-lg py-md text-label-sm whitespace-nowrap"><?= date('Y-m-d', strtotime($ticket['maintenance_date'])) ?></td>
                            <td class="px-lg py-md"><?= severity_badge($ticket['severity']) ?></td>
                            <td class="px-lg py-md"><?= ticket_status_badge($ticket['status']) ?></td>
                        </tr>
                        <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- end p-lg -->
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</main>

<script>
// ── Chart.js: Water Usage Line Chart ──────────────────────
(function() {
    const labels = <?= json_encode($chart_labels) ?>;
    const data   = <?= json_encode($chart_data) ?>;
    const ctx    = document.getElementById('waterUsageChart').getContext('2d');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Water Usage (Liters)',
                data: data,
                borderColor:     '#006c49',
                backgroundColor: 'rgba(0,108,73,0.08)',
                borderWidth: 2.5,
                pointBackgroundColor: '#006c49',
                pointRadius: 5,
                pointHoverRadius: 7,
                tension: 0.4,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ' + Number(ctx.raw).toLocaleString() + ' L'
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(0,0,0,0.04)' },
                    ticks: { font: { family: 'JetBrains Mono', size: 11 }, color: '#75777d' }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.04)' },
                    ticks: {
                        font: { family: 'JetBrains Mono', size: 11 }, color: '#75777d',
                        callback: v => v >= 1000 ? (v/1000).toFixed(1)+'k' : v
                    }
                }
            }
        }
    });
})();
</script>
