<?php
/**
 * HydroLogic OS – Electricity Consumption Management (Admin)
 * ============================================================
 * CRUD for daily electricity usage records with cost calculation.
 */

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_role('Admin');

$page_title  = 'Electricity Consumption';
$active_page = 'electricity';

// Tube well dropdown
$wells_result = mysqli_query($conn, "SELECT id, well_code, tube_well_name FROM tube_wells ORDER BY tube_well_name");
$wells_list   = mysqli_fetch_all($wells_result, MYSQLI_ASSOC);

// ── Handle Add ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'add') {
    validate_csrf();
    $tw_id  = (int)($_POST['tube_well_id']   ?? 0);
    $date   = trim($_POST['usage_date']       ?? '');
    $units  = (float)($_POST['units_consumed'] ?? 0);
    $rate   = (float)($_POST['cost_per_unit']  ?? 0);
    $remark = trim($_POST['remarks']           ?? '');

    if ($tw_id && $date && $units > 0 && $rate >= 0) {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO electricity_usage (tube_well_id, usage_date, units_consumed, cost_per_unit, remarks, recorded_by)
             VALUES (?,?,?,?,?,?)"
        );
        mysqli_stmt_bind_param($stmt, 'isddsi', $tw_id, $date, $units, $rate, $remark, $_SESSION['user_id']);
        mysqli_stmt_execute($stmt) ? set_flash('success', 'Electricity record added.') : set_flash('error', 'Failed to add record.');
        mysqli_stmt_close($stmt);
    } else {
        set_flash('error', 'Please fill in all required fields.');
    }
    header('Location: ' . base_url('admin/electricity.php')); exit;
}

// ── Handle Edit ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'edit') {
    validate_csrf();
    $id     = (int)($_POST['id']             ?? 0);
    $tw_id  = (int)($_POST['tube_well_id']   ?? 0);
    $date   = trim($_POST['usage_date']      ?? '');
    $units  = (float)($_POST['units_consumed']?? 0);
    $rate   = (float)($_POST['cost_per_unit'] ?? 0);
    $remark = trim($_POST['remarks']          ?? '');

    if ($id && $tw_id && $date && $units > 0) {
        $stmt = mysqli_prepare($conn,
            "UPDATE electricity_usage SET tube_well_id=?, usage_date=?, units_consumed=?, cost_per_unit=?, remarks=? WHERE id=?"
        );
        mysqli_stmt_bind_param($stmt, 'iddsi', $tw_id, $date, $units, $rate, $remark, $id);
        // Note: total_cost is generated column – no need to set it
        $stmt2 = mysqli_prepare($conn,
            "UPDATE electricity_usage SET tube_well_id=?, usage_date=?, units_consumed=?, cost_per_unit=?, remarks=? WHERE id=?"
        );
        mysqli_stmt_bind_param($stmt2, 'isdds i', $tw_id, $date, $units, $rate, $remark, $id);
        // Use correct bind
        $stmt_upd = mysqli_prepare($conn, "UPDATE electricity_usage SET tube_well_id=?, usage_date=?, units_consumed=?, cost_per_unit=?, remarks=? WHERE id=?");
        mysqli_stmt_bind_param($stmt_upd, 'isddsi', $tw_id, $date, $units, $rate, $remark, $id);
        mysqli_stmt_execute($stmt_upd) ? set_flash('success', 'Record updated.') : set_flash('error', 'Update failed.');
        mysqli_stmt_close($stmt_upd);
    }
    header('Location: ' . base_url('admin/electricity.php')); exit;
}

// ── Handle Delete ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'delete') {
    validate_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $stmt = mysqli_prepare($conn, "DELETE FROM electricity_usage WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt) ? set_flash('success', 'Record deleted.') : set_flash('error', 'Delete failed.');
        mysqli_stmt_close($stmt);
    }
    header('Location: ' . base_url('admin/electricity.php')); exit;
}

// ── Stats ───────────────────────────────────────────────────
$r         = mysqli_query($conn, "SELECT COALESCE(SUM(units_consumed),0) AS u, COALESCE(SUM(total_cost),0) AS c FROM electricity_usage WHERE MONTH(usage_date)=MONTH(CURDATE())");
$month_row = mysqli_fetch_assoc($r);

// ── Filters + Pagination ────────────────────────────────────
$filter_well = (int)($_GET['well_id'] ?? 0);
$filter_from = trim($_GET['date_from'] ?? '');
$filter_to   = trim($_GET['date_to']   ?? '');
$page_num    = max(1,(int)($_GET['page'] ?? 1));

$conds = []; $btypes=''; $bvals=[];
if ($filter_well) { $conds[]='eu.tube_well_id=?'; $btypes.='i'; $bvals[]=$filter_well; }
if ($filter_from) { $conds[]='eu.usage_date>=?';  $btypes.='s'; $bvals[]=$filter_from; }
if ($filter_to)   { $conds[]='eu.usage_date<=?';  $btypes.='s'; $bvals[]=$filter_to; }
$where_sql = $conds ? 'WHERE '.implode(' AND ',$conds) : '';

$cs = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM electricity_usage eu $where_sql");
if ($btypes) mysqli_stmt_bind_param($cs,$btypes,...$bvals);
mysqli_stmt_execute($cs);
$total_rows = mysqli_fetch_assoc(mysqli_stmt_get_result($cs))['total'] ?? 0;
mysqli_stmt_close($cs);

$pager  = paginate($total_rows, RECORDS_PER_PAGE, $page_num);
$limit  = RECORDS_PER_PAGE;
$offset = $pager['offset'];

$ls = mysqli_prepare($conn,
    "SELECT eu.*, tw.tube_well_name, tw.well_code FROM electricity_usage eu
     JOIN tube_wells tw ON tw.id=eu.tube_well_id
     $where_sql ORDER BY eu.usage_date DESC, eu.id DESC LIMIT ? OFFSET ?"
);
$pt = $btypes.'ii'; $pv = array_merge($bvals,[$limit,$offset]);
mysqli_stmt_bind_param($ls,$pt,...$pv);
mysqli_stmt_execute($ls);
$records = mysqli_stmt_get_result($ls);
mysqli_stmt_close($ls);

$pagination_url = base_url('admin/electricity.php').'?well_id='.$filter_well.'&date_from='.urlencode($filter_from).'&date_to='.urlencode($filter_to).'&page=';

require_once __DIR__ . '/../includes/header.php';
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<main class="ml-sidebar-width min-h-screen flex flex-col">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="p-lg flex-1">
        <?= render_flash() ?>

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-xl gap-md">
            <div>
                <h2 class="font-h2 text-h2 text-primary">Electricity Consumption</h2>
                <p class="text-on-surface-variant font-body-md">Track power usage and calculate operational costs per well.</p>
            </div>
            <button onclick="openModal('add-modal')"
                    class="flex items-center gap-sm bg-primary text-on-primary px-lg py-md rounded hover:opacity-90 shadow-sm">
                <span class="material-symbols-outlined">add</span>
                <span class="font-body-md font-semibold">Log Electricity Usage</span>
            </button>
        </div>

        <!-- Stat Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-lg mb-xl">
            <div class="bg-surface border border-outline-variant p-lg rounded-xl shadow-sm">
                <div class="flex items-center justify-between mb-md">
                    <span class="text-label-sm text-on-surface-variant uppercase">Month kWh</span>
                    <span class="material-symbols-outlined text-error">bolt</span>
                </div>
                <span class="text-h2 font-h2 text-primary"><?= number_format($month_row['u'],1) ?></span>
                <span class="text-label-sm text-on-surface-variant ml-xs">Units – <?= date('F') ?></span>
            </div>
            <div class="bg-surface border border-outline-variant p-lg rounded-xl shadow-sm">
                <div class="flex items-center justify-between mb-md">
                    <span class="text-label-sm text-on-surface-variant uppercase">Month Cost</span>
                    <span class="material-symbols-outlined text-secondary">payments</span>
                </div>
                <span class="text-h2 font-h2 text-primary">PKR <?= number_format($month_row['c'],0) ?></span>
            </div>
            <div class="bg-surface border border-outline-variant p-lg rounded-xl shadow-sm">
                <div class="flex items-center justify-between mb-md">
                    <span class="text-label-sm text-on-surface-variant uppercase">Total Records</span>
                    <span class="material-symbols-outlined text-outline">receipt_long</span>
                </div>
                <span class="text-h2 font-h2 text-primary"><?= $total_rows ?></span>
                <span class="text-label-sm text-on-surface-variant ml-xs">Entries</span>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" action="" class="bg-surface border border-outline-variant p-md rounded-xl mb-lg flex flex-wrap items-end gap-md">
            <div class="flex-1 min-w-[180px]">
                <label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Tube Well</label>
                <select name="well_id" class="w-full border border-outline-variant rounded bg-surface-container-lowest px-md py-sm text-body-md outline-none">
                    <option value="0">All Wells</option>
                    <?php foreach ($wells_list as $w): ?>
                    <option value="<?= $w['id'] ?>" <?= $filter_well===$w['id'] ? 'selected' : '' ?>><?= sanitize($w['well_code']) ?> – <?= sanitize($w['tube_well_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 min-w-[140px]">
                <label class="block text-label-sm text-on-surface-variant mb-xs uppercase">From</label>
                <input type="date" name="date_from" value="<?= sanitize($filter_from) ?>" class="w-full border border-outline-variant rounded px-md py-sm outline-none">
            </div>
            <div class="flex-1 min-w-[140px]">
                <label class="block text-label-sm text-on-surface-variant mb-xs uppercase">To</label>
                <input type="date" name="date_to" value="<?= sanitize($filter_to) ?>" class="w-full border border-outline-variant rounded px-md py-sm outline-none">
            </div>
            <div class="flex-shrink-0 flex gap-sm">
                <button type="submit" class="bg-surface-container-high text-primary px-lg py-sm rounded border border-outline-variant hover:bg-surface-container-highest flex items-center gap-sm">
                    <span class="material-symbols-outlined">filter_list</span> Apply
                </button>
                <a href="<?= base_url('admin/electricity.php') ?>" class="bg-surface text-on-surface-variant px-md py-sm rounded border border-outline-variant hover:bg-surface-container-low flex items-center">
                    <span class="material-symbols-outlined text-[18px]">restart_alt</span>
                </a>
            </div>
        </form>

        <!-- Table -->
        <div class="bg-surface border border-outline-variant rounded-xl overflow-hidden shadow-sm">
            <div class="px-lg py-md border-b border-surface-container flex items-center justify-between">
                <h3 class="font-h3 text-h3 text-primary">Electricity Records</h3>
                <span class="text-label-sm text-on-surface-variant"><?= $pager['offset']+1 ?>–<?= min($pager['offset']+RECORDS_PER_PAGE,$total_rows) ?> of <?= $total_rows ?></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low/50">
                            <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Date</th>
                            <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Tube Well</th>
                            <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Units (kWh)</th>
                            <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Rate (PKR)</th>
                            <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Total Cost</th>
                            <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Remarks</th>
                            <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container">
                        <?php if (mysqli_num_rows($records) === 0): ?>
                        <tr><td colspan="7" class="px-lg py-xl text-center text-on-surface-variant">
                            <span class="material-symbols-outlined text-[40px] block mb-sm text-outline">bolt</span>No records found.
                        </td></tr>
                        <?php else: while ($rec = mysqli_fetch_assoc($records)): ?>
                        <tr class="hover:bg-surface-container-lowest transition-colors">
                            <td class="px-lg py-md font-data-mono text-data-mono"><?= date('Y-m-d', strtotime($rec['usage_date'])) ?></td>
                            <td class="px-lg py-md">
                                <div class="flex items-center gap-sm">
                                    <div class="w-2 h-2 rounded-full bg-error"></div>
                                    <span class="font-body-md font-semibold text-primary"><?= sanitize($rec['well_code']) ?></span>
                                </div>
                                <span class="text-[11px] text-on-surface-variant"><?= sanitize($rec['tube_well_name']) ?></span>
                            </td>
                            <td class="px-lg py-md font-data-mono text-primary font-bold"><?= number_format($rec['units_consumed'],2) ?></td>
                            <td class="px-lg py-md font-data-mono"><?= number_format($rec['cost_per_unit'],2) ?></td>
                            <td class="px-lg py-md font-data-mono text-secondary font-bold">PKR <?= number_format($rec['total_cost'],2) ?></td>
                            <td class="px-lg py-md text-on-surface-variant italic text-body-md"><?= sanitize($rec['remarks'] ?? '—') ?></td>
                            <td class="px-lg py-md text-right">
                                <div class="flex items-center justify-end gap-sm">
                                    <button onclick="openEditModal(<?= htmlspecialchars(json_encode($rec),ENT_QUOTES) ?>)"
                                            class="p-xs hover:bg-surface-container-high rounded text-on-surface-variant transition-colors">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                    <form method="POST" action="" onsubmit="return confirm('Delete this record?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_action" value="delete">
                                        <input type="hidden" name="id" value="<?= $rec['id'] ?>">
                                        <button type="submit" class="p-xs hover:bg-error-container hover:text-error rounded text-on-surface-variant transition-colors">
                                            <span class="material-symbols-outlined text-[20px]">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-lg py-md bg-surface-container-lowest border-t border-surface-container flex items-center justify-between">
                <span class="text-label-sm text-on-surface-variant">Page <?= $pager['current_page'] ?> of <?= $pager['total_pages'] ?></span>
                <div class="flex gap-xs">
                    <?php if ($pager['current_page']>1): ?><a href="<?= $pagination_url.($pager['current_page']-1) ?>" class="px-sm py-xs border border-outline-variant rounded bg-surface text-on-surface-variant hover:bg-surface-container-low">‹</a><?php endif; ?>
                    <?= render_pagination($pager, $pagination_url) ?>
                    <?php if ($pager['current_page']<$pager['total_pages']): ?><a href="<?= $pagination_url.($pager['current_page']+1) ?>" class="px-sm py-xs border border-outline-variant rounded bg-surface text-on-surface-variant hover:bg-surface-container-low">›</a><?php endif; ?>
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
            <h3 class="text-on-primary font-h3 text-h3">Log Electricity Usage</h3>
            <button onclick="closeModal('add-modal')" class="text-on-primary opacity-70 hover:opacity-100"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" action="" class="p-lg space-y-lg">
            <?= csrf_field() ?><input type="hidden" name="_action" value="add">
            <div>
                <label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Tube Well *</label>
                <select name="tube_well_id" required class="w-full border border-outline-variant rounded px-md py-sm text-body-md outline-none">
                    <option value="">Select a well...</option>
                    <?php foreach ($wells_list as $w): ?><option value="<?= $w['id'] ?>"><?= sanitize($w['well_code']) ?> – <?= sanitize($w['tube_well_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Usage Date *</label>
                <input type="date" name="usage_date" required value="<?= date('Y-m-d') ?>" class="w-full border border-outline-variant rounded px-md py-sm outline-none"></div>
            <div class="grid grid-cols-2 gap-md">
                <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Units (kWh) *</label>
                    <input type="number" name="units_consumed" required min="0.01" step="0.01" placeholder="0.00" class="w-full border border-outline-variant rounded px-md py-sm outline-none"></div>
                <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Rate (PKR/kWh) *</label>
                    <input type="number" name="cost_per_unit" required min="0" step="0.01" value="18.50" class="w-full border border-outline-variant rounded px-md py-sm outline-none"></div>
            </div>
            <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Remarks</label>
                <textarea name="remarks" rows="2" class="w-full border border-outline-variant rounded px-md py-sm outline-none"></textarea></div>
            <div class="flex gap-md pt-md">
                <button type="button" onclick="closeModal('add-modal')" class="flex-1 px-lg py-md border border-outline-variant rounded font-semibold hover:bg-surface-container-low">Cancel</button>
                <button type="submit" class="flex-1 px-lg py-md bg-secondary text-on-primary rounded font-semibold hover:opacity-90">Submit</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div id="edit-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-primary/40 backdrop-blur-sm hidden">
    <div class="bg-surface w-full max-w-lg rounded-xl shadow-2xl border border-outline-variant overflow-hidden">
        <div class="bg-primary p-lg flex justify-between items-center">
            <h3 class="text-on-primary font-h3 text-h3">Edit Electricity Record</h3>
            <button onclick="closeModal('edit-modal')" class="text-on-primary opacity-70 hover:opacity-100"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" action="" class="p-lg space-y-lg">
            <?= csrf_field() ?><input type="hidden" name="_action" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <div>
                <label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Tube Well *</label>
                <select name="tube_well_id" id="edit-well" required class="w-full border border-outline-variant rounded px-md py-sm outline-none">
                    <?php foreach ($wells_list as $w): ?><option value="<?= $w['id'] ?>"><?= sanitize($w['well_code']) ?> – <?= sanitize($w['tube_well_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Usage Date *</label>
                <input type="date" name="usage_date" id="edit-date" required class="w-full border border-outline-variant rounded px-md py-sm outline-none"></div>
            <div class="grid grid-cols-2 gap-md">
                <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Units (kWh) *</label>
                    <input type="number" name="units_consumed" id="edit-units" required min="0.01" step="0.01" class="w-full border border-outline-variant rounded px-md py-sm outline-none"></div>
                <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Rate (PKR/kWh)</label>
                    <input type="number" name="cost_per_unit" id="edit-rate" min="0" step="0.01" class="w-full border border-outline-variant rounded px-md py-sm outline-none"></div>
            </div>
            <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Remarks</label>
                <textarea name="remarks" id="edit-remarks" rows="2" class="w-full border border-outline-variant rounded px-md py-sm outline-none"></textarea></div>
            <div class="flex gap-md pt-md">
                <button type="button" onclick="closeModal('edit-modal')" class="flex-1 px-lg py-md border border-outline-variant rounded font-semibold hover:bg-surface-container-low">Cancel</button>
                <button type="submit" class="flex-1 px-lg py-md bg-secondary text-on-primary rounded font-semibold hover:opacity-90">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.remove('hidden');}
function closeModal(id){document.getElementById(id).classList.add('hidden');}
document.querySelectorAll('[id$="-modal"]').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)closeModal(m.id);}));
function openEditModal(rec){
    document.getElementById('edit-id').value=rec.id;
    document.getElementById('edit-well').value=rec.tube_well_id;
    document.getElementById('edit-date').value=rec.usage_date;
    document.getElementById('edit-units').value=rec.units_consumed;
    document.getElementById('edit-rate').value=rec.cost_per_unit;
    document.getElementById('edit-remarks').value=rec.remarks??'';
    openModal('edit-modal');
}
</script>
