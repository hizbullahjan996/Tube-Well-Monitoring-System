<?php
/**
 * HydroLogic OS – Water Usage Management (Admin)
 * ================================================
 * Full CRUD for daily water usage records.
 * Supports filtering by tube well and date range.
 */

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_role('Admin');

$page_title  = 'Water Usage Monitoring';
$active_page = 'water_usage';

// ── Fetch all active tube wells for dropdowns ────────────
$wells_result = mysqli_query($conn, "SELECT id, well_code, tube_well_name FROM tube_wells WHERE status='Active' ORDER BY tube_well_name");
$wells_list   = mysqli_fetch_all($wells_result, MYSQLI_ASSOC);

// ── Handle Add ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'add') {
    validate_csrf();
    $tw_id  = (int)($_POST['tube_well_id'] ?? 0);
    $date   = trim($_POST['usage_date']    ?? '');
    $amount = (float)($_POST['water_amount'] ?? 0);
    $remark = trim($_POST['remarks']       ?? '');

    if ($tw_id && $date && $amount > 0) {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO water_usage (tube_well_id, usage_date, water_amount, remarks, recorded_by) VALUES (?,?,?,?,?)"
        );
        mysqli_stmt_bind_param($stmt, 'isdsi', $tw_id, $date, $amount, $remark, $_SESSION['user_id']);
        mysqli_stmt_execute($stmt) ? set_flash('success', 'Water usage record added.') : set_flash('error', 'Failed to add record.');
        mysqli_stmt_close($stmt);
    } else {
        set_flash('error', 'Please fill in all required fields with valid values.');
    }
    header('Location: ' . base_url('admin/water_usage.php'));
    exit;
}

// ── Handle Edit ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'edit') {
    validate_csrf();
    $id     = (int)($_POST['id']            ?? 0);
    $tw_id  = (int)($_POST['tube_well_id']  ?? 0);
    $date   = trim($_POST['usage_date']     ?? '');
    $amount = (float)($_POST['water_amount'] ?? 0);
    $remark = trim($_POST['remarks']        ?? '');

    if ($id && $tw_id && $date && $amount > 0) {
        $stmt = mysqli_prepare($conn,
            "UPDATE water_usage SET tube_well_id=?, usage_date=?, water_amount=?, remarks=? WHERE id=?"
        );
        mysqli_stmt_bind_param($stmt, 'isdsi', $tw_id, $date, $amount, $remark, $id);
        mysqli_stmt_execute($stmt) ? set_flash('success', 'Record updated.') : set_flash('error', 'Update failed.');
        mysqli_stmt_close($stmt);
    } else {
        set_flash('error', 'Invalid data supplied.');
    }
    header('Location: ' . base_url('admin/water_usage.php'));
    exit;
}

// ── Handle Delete ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'delete') {
    validate_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $stmt = mysqli_prepare($conn, "DELETE FROM water_usage WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt) ? set_flash('success', 'Record deleted.') : set_flash('error', 'Delete failed.');
        mysqli_stmt_close($stmt);
    }
    header('Location: ' . base_url('admin/water_usage.php'));
    exit;
}

// ── Filters ─────────────────────────────────────────────────
$filter_well  = (int)($_GET['well_id']    ?? 0);
$filter_from  = trim($_GET['date_from'] ?? '');
$filter_to    = trim($_GET['date_to']   ?? '');
$page_num     = max(1, (int)($_GET['page'] ?? 1));

// Build WHERE clause dynamically
$conditions = [];
$bind_types = '';
$bind_vals  = [];

if ($filter_well) {
    $conditions[] = 'wu.tube_well_id = ?';
    $bind_types  .= 'i';
    $bind_vals[]  = $filter_well;
}
if ($filter_from) {
    $conditions[] = 'wu.usage_date >= ?';
    $bind_types  .= 's';
    $bind_vals[]  = $filter_from;
}
if ($filter_to) {
    $conditions[] = 'wu.usage_date <= ?';
    $bind_types  .= 's';
    $bind_vals[]  = $filter_to;
}
$where_sql = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

// Stats
$r        = mysqli_query($conn, "SELECT COALESCE(SUM(water_amount),0) AS total FROM water_usage WHERE MONTH(usage_date)=MONTH(CURDATE())");
$month_total = mysqli_fetch_assoc($r)['total'] ?? 0;

$r        = mysqli_query($conn, "SELECT COALESCE(SUM(water_amount),0) AS total FROM water_usage WHERE usage_date = CURDATE()");
$today_total = mysqli_fetch_assoc($r)['total'] ?? 0;

// Count for pagination
$count_sql  = "SELECT COUNT(*) AS total FROM water_usage wu JOIN tube_wells tw ON tw.id=wu.tube_well_id $where_sql";
$count_stmt = mysqli_prepare($conn, $count_sql);
if ($bind_types) mysqli_stmt_bind_param($count_stmt, $bind_types, ...$bind_vals);
mysqli_stmt_execute($count_stmt);
$total_rows = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'] ?? 0;
mysqli_stmt_close($count_stmt);

$pager  = paginate($total_rows, RECORDS_PER_PAGE, $page_num);
$offset = $pager['offset'];
$limit  = RECORDS_PER_PAGE;

// Fetch records
$list_sql  = "SELECT wu.*, tw.tube_well_name, tw.well_code
              FROM water_usage wu JOIN tube_wells tw ON tw.id=wu.tube_well_id
              $where_sql ORDER BY wu.usage_date DESC, wu.id DESC LIMIT ? OFFSET ?";
$list_stmt = mysqli_prepare($conn, $list_sql);
$paged_types = $bind_types . 'ii';
$paged_vals  = array_merge($bind_vals, [$limit, $offset]);
mysqli_stmt_bind_param($list_stmt, $paged_types, ...$paged_vals);
mysqli_stmt_execute($list_stmt);
$records = mysqli_stmt_get_result($list_stmt);
mysqli_stmt_close($list_stmt);

$pagination_url = base_url('admin/water_usage.php') . '?well_id=' . $filter_well . '&date_from=' . urlencode($filter_from) . '&date_to=' . urlencode($filter_to) . '&page=';

require_once __DIR__ . '/../includes/header.php';
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="ml-sidebar-width min-h-screen flex flex-col">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <div class="p-lg flex-1">
        <?= render_flash() ?>

        <!-- Page Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-xl gap-md">
            <div>
                <h2 class="font-h2 text-h2 text-primary">Water Usage Monitoring</h2>
                <p class="text-on-surface-variant font-body-md">Real-time tracking and logging for industrial well systems.</p>
            </div>
            <button onclick="openModal('add-modal')"
                    class="flex items-center gap-sm bg-primary text-on-primary px-lg py-md rounded hover:opacity-90 transition-opacity shadow-sm">
                <span class="material-symbols-outlined">add</span>
                <span class="font-body-md font-semibold">Record Water Usage</span>
            </button>
        </div>

        <!-- Stat Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-lg mb-xl">
            <div class="bg-surface border border-outline-variant p-lg rounded-xl shadow-sm">
                <div class="flex items-center justify-between mb-md">
                    <span class="text-label-sm font-label-sm text-on-surface-variant uppercase">Today's Usage</span>
                    <span class="material-symbols-outlined text-secondary">water_drop</span>
                </div>
                <div class="flex items-baseline gap-xs">
                    <span class="text-h2 font-h2 text-primary"><?= number_format($today_total, 0) ?></span>
                    <span class="text-label-sm font-label-sm text-on-surface-variant">Liters</span>
                </div>
            </div>
            <div class="bg-surface border border-outline-variant p-lg rounded-xl shadow-sm">
                <div class="flex items-center justify-between mb-md">
                    <span class="text-label-sm font-label-sm text-on-surface-variant uppercase">Month Total</span>
                    <span class="material-symbols-outlined text-primary">calendar_month</span>
                </div>
                <div class="flex items-baseline gap-xs">
                    <span class="text-h2 font-h2 text-primary"><?= number_format($month_total / 1000, 1) ?>k</span>
                    <span class="text-label-sm font-label-sm text-on-surface-variant">Liters – <?= date('F') ?></span>
                </div>
            </div>
            <div class="bg-surface border border-outline-variant p-lg rounded-xl shadow-sm">
                <div class="flex items-center justify-between mb-md">
                    <span class="text-label-sm font-label-sm text-on-surface-variant uppercase">Total Records</span>
                    <span class="material-symbols-outlined text-outline">table_rows</span>
                </div>
                <div class="flex items-baseline gap-xs">
                    <span class="text-h2 font-h2 text-primary"><?= $total_rows ?></span>
                    <span class="text-label-sm font-label-sm text-on-surface-variant">Entries</span>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" action="" class="bg-surface border border-outline-variant p-md rounded-xl mb-lg flex flex-wrap items-end gap-md">
            <div class="flex-1 min-w-[180px]">
                <label class="block text-label-sm font-label-sm text-on-surface-variant mb-xs">Tube Well</label>
                <select name="well_id" class="w-full border border-outline-variant rounded bg-surface-container-lowest px-md py-sm text-body-md focus:ring-2 focus:ring-primary/20 outline-none">
                    <option value="0">All Wells</option>
                    <?php foreach ($wells_list as $w): ?>
                    <option value="<?= $w['id'] ?>" <?= $filter_well === (int)$w['id'] ? 'selected' : '' ?>>
                        <?= sanitize($w['well_code']) ?> – <?= sanitize($w['tube_well_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 min-w-[150px]">
                <label class="block text-label-sm font-label-sm text-on-surface-variant mb-xs">From Date</label>
                <input type="date" name="date_from" value="<?= sanitize($filter_from) ?>"
                       class="w-full border border-outline-variant rounded bg-surface-container-lowest px-md py-sm text-body-md focus:ring-2 focus:ring-primary/20 outline-none">
            </div>
            <div class="flex-1 min-w-[150px]">
                <label class="block text-label-sm font-label-sm text-on-surface-variant mb-xs">To Date</label>
                <input type="date" name="date_to" value="<?= sanitize($filter_to) ?>"
                       class="w-full border border-outline-variant rounded bg-surface-container-lowest px-md py-sm text-body-md focus:ring-2 focus:ring-primary/20 outline-none">
            </div>
            <div class="flex-shrink-0 flex gap-sm">
                <button type="submit" class="bg-surface-container-high text-primary px-lg py-sm rounded border border-outline-variant hover:bg-surface-container-highest transition-colors flex items-center gap-sm">
                    <span class="material-symbols-outlined">filter_list</span>
                    <span class="font-body-md font-semibold">Apply</span>
                </button>
                <a href="<?= base_url('admin/water_usage.php') ?>" class="bg-surface text-on-surface-variant px-md py-sm rounded border border-outline-variant hover:bg-surface-container-low transition-colors flex items-center">
                    <span class="material-symbols-outlined text-[18px]">restart_alt</span>
                </a>
            </div>
        </form>

        <!-- Records Table -->
        <div class="bg-surface border border-outline-variant rounded-xl overflow-hidden shadow-sm">
            <div class="px-lg py-md border-b border-surface-container flex items-center justify-between">
                <h3 class="font-h3 text-h3 text-primary">Usage Records</h3>
                <span class="text-label-sm font-label-sm text-on-surface-variant">
                    <?= $pager['offset'] + 1 ?>–<?= min($pager['offset'] + RECORDS_PER_PAGE, $total_rows) ?> of <?= $total_rows ?>
                </span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low/50">
                            <th class="px-lg py-md text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Date</th>
                            <th class="px-lg py-md text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Tube Well</th>
                            <th class="px-lg py-md text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Amount (L)</th>
                            <th class="px-lg py-md text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Remarks</th>
                            <th class="px-lg py-md text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container">
                        <?php if (mysqli_num_rows($records) === 0): ?>
                        <tr>
                            <td colspan="5" class="px-lg py-xl text-center text-on-surface-variant">
                                <span class="material-symbols-outlined text-[40px] block mb-sm text-outline">opacity</span>
                                No records found for the selected filters.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php while ($rec = mysqli_fetch_assoc($records)): ?>
                        <tr class="hover:bg-surface-container-lowest transition-colors">
                            <td class="px-lg py-md font-data-mono text-data-mono"><?= date('Y-m-d', strtotime($rec['usage_date'])) ?></td>
                            <td class="px-lg py-md">
                                <div class="flex items-center gap-sm">
                                    <div class="w-2 h-2 rounded-full bg-secondary"></div>
                                    <span class="font-body-md font-semibold text-primary"><?= sanitize($rec['well_code']) ?> – <?= sanitize($rec['tube_well_name']) ?></span>
                                </div>
                            </td>
                            <td class="px-lg py-md font-data-mono text-primary font-bold"><?= number_format($rec['water_amount'], 2) ?> L</td>
                            <td class="px-lg py-md text-body-md text-on-surface-variant italic"><?= sanitize($rec['remarks'] ?? '—') ?></td>
                            <td class="px-lg py-md text-right">
                                <div class="flex items-center justify-end gap-sm">
                                    <button title="Edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($rec), ENT_QUOTES) ?>)"
                                            class="p-xs hover:bg-surface-container-high rounded text-on-surface-variant transition-colors">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                    <form method="POST" action="" onsubmit="return confirm('Delete this record?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_action" value="delete">
                                        <input type="hidden" name="id" value="<?= $rec['id'] ?>">
                                        <button type="submit" title="Delete"
                                                class="p-xs hover:bg-error-container hover:text-error rounded text-on-surface-variant transition-colors">
                                            <span class="material-symbols-outlined text-[20px]">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-lg py-md bg-surface-container-lowest border-t border-surface-container flex items-center justify-between">
                <span class="text-label-sm text-on-surface-variant">Page <?= $pager['current_page'] ?> of <?= $pager['total_pages'] ?></span>
                <div class="flex gap-xs">
                    <?php if ($pager['current_page'] > 1): ?>
                    <a href="<?= $pagination_url . ($pager['current_page']-1) ?>" class="px-sm py-xs border border-outline-variant rounded bg-surface font-body-md text-on-surface-variant hover:bg-surface-container-low">‹</a>
                    <?php endif; ?>
                    <?= render_pagination($pager, $pagination_url) ?>
                    <?php if ($pager['current_page'] < $pager['total_pages']): ?>
                    <a href="<?= $pagination_url . ($pager['current_page']+1) ?>" class="px-sm py-xs border border-outline-variant rounded bg-surface font-body-md text-on-surface-variant hover:bg-surface-container-low">›</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</main>

<!-- ADD MODAL -->
<div id="add-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-primary/40 backdrop-blur-sm hidden">
    <div class="bg-surface w-full max-w-lg rounded-xl shadow-2xl border border-outline-variant overflow-hidden">
        <div class="bg-primary p-lg flex justify-between items-center">
            <h3 class="text-on-primary font-h3 text-h3">Record Water Usage</h3>
            <button onclick="closeModal('add-modal')" class="text-on-primary opacity-70 hover:opacity-100">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="" class="p-lg space-y-lg">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="add">
            <div>
                <label class="block text-label-sm font-label-sm text-on-surface-variant mb-xs uppercase">Tube Well Source *</label>
                <select name="tube_well_id" required class="w-full border border-outline-variant rounded px-md py-sm text-body-md focus:ring-2 focus:ring-secondary outline-none">
                    <option value="">Select a well...</option>
                    <?php foreach ($wells_list as $w): ?>
                    <option value="<?= $w['id'] ?>"><?= sanitize($w['well_code']) ?> – <?= sanitize($w['tube_well_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-label-sm font-label-sm text-on-surface-variant mb-xs uppercase">Usage Date *</label>
                <input type="date" name="usage_date" required value="<?= date('Y-m-d') ?>"
                       class="w-full border border-outline-variant rounded px-md py-sm text-body-md focus:ring-2 focus:ring-secondary outline-none">
            </div>
            <div>
                <label class="block text-label-sm font-label-sm text-on-surface-variant mb-xs uppercase">Amount (Liters) *</label>
                <div class="relative">
                    <input type="number" name="water_amount" required min="0.01" step="0.01" placeholder="0.00"
                           class="w-full border border-outline-variant rounded px-md py-sm text-body-md pr-12 focus:ring-2 focus:ring-secondary outline-none">
                    <span class="absolute right-4 top-2.5 text-on-surface-variant text-label-sm">LTRS</span>
                </div>
            </div>
            <div>
                <label class="block text-label-sm font-label-sm text-on-surface-variant mb-xs uppercase">Remarks</label>
                <textarea name="remarks" rows="3" placeholder="Optional notes..."
                          class="w-full border border-outline-variant rounded px-md py-sm text-body-md focus:ring-2 focus:ring-secondary outline-none"></textarea>
            </div>
            <div class="flex gap-md pt-md">
                <button type="button" onclick="closeModal('add-modal')"
                        class="flex-1 px-lg py-md border border-outline-variant rounded font-semibold hover:bg-surface-container-low">Cancel</button>
                <button type="submit"
                        class="flex-1 px-lg py-md bg-secondary text-on-primary rounded font-semibold hover:opacity-90">Submit Record</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div id="edit-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-primary/40 backdrop-blur-sm hidden">
    <div class="bg-surface w-full max-w-lg rounded-xl shadow-2xl border border-outline-variant overflow-hidden">
        <div class="bg-primary p-lg flex justify-between items-center">
            <h3 class="text-on-primary font-h3 text-h3">Edit Usage Record</h3>
            <button onclick="closeModal('edit-modal')" class="text-on-primary opacity-70 hover:opacity-100">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="" class="p-lg space-y-lg">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <div>
                <label class="block text-label-sm font-label-sm text-on-surface-variant mb-xs uppercase">Tube Well Source *</label>
                <select name="tube_well_id" id="edit-well" required class="w-full border border-outline-variant rounded px-md py-sm text-body-md focus:ring-2 focus:ring-secondary outline-none">
                    <?php foreach ($wells_list as $w): ?>
                    <option value="<?= $w['id'] ?>"><?= sanitize($w['well_code']) ?> – <?= sanitize($w['tube_well_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-label-sm font-label-sm text-on-surface-variant mb-xs uppercase">Usage Date *</label>
                <input type="date" name="usage_date" id="edit-date" required
                       class="w-full border border-outline-variant rounded px-md py-sm text-body-md focus:ring-2 focus:ring-secondary outline-none">
            </div>
            <div>
                <label class="block text-label-sm font-label-sm text-on-surface-variant mb-xs uppercase">Amount (Liters) *</label>
                <input type="number" name="water_amount" id="edit-amount" required min="0.01" step="0.01"
                       class="w-full border border-outline-variant rounded px-md py-sm text-body-md focus:ring-2 focus:ring-secondary outline-none">
            </div>
            <div>
                <label class="block text-label-sm font-label-sm text-on-surface-variant mb-xs uppercase">Remarks</label>
                <textarea name="remarks" id="edit-remarks" rows="3"
                          class="w-full border border-outline-variant rounded px-md py-sm text-body-md focus:ring-2 focus:ring-secondary outline-none"></textarea>
            </div>
            <div class="flex gap-md pt-md">
                <button type="button" onclick="closeModal('edit-modal')"
                        class="flex-1 px-lg py-md border border-outline-variant rounded font-semibold hover:bg-surface-container-low">Cancel</button>
                <button type="submit"
                        class="flex-1 px-lg py-md bg-secondary text-on-primary rounded font-semibold hover:opacity-90">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
document.querySelectorAll('[id$="-modal"]').forEach(m => m.addEventListener('click', e => { if(e.target===m) closeModal(m.id); }));

function openEditModal(rec) {
    document.getElementById('edit-id').value     = rec.id;
    document.getElementById('edit-well').value   = rec.tube_well_id;
    document.getElementById('edit-date').value   = rec.usage_date;
    document.getElementById('edit-amount').value = rec.water_amount;
    document.getElementById('edit-remarks').value= rec.remarks ?? '';
    openModal('edit-modal');
}
</script>
