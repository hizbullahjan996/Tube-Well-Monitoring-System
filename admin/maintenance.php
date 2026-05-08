<?php
/**
 * HydroLogic OS – Maintenance Management (Admin)
 * ================================================
 * Full CRUD for maintenance tickets with severity/status workflow.
 */

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_role('Admin');

$page_title  = 'Maintenance Management';
$active_page = 'maintenance';

// Tube wells dropdown
$wells_r    = mysqli_query($conn, "SELECT id, well_code, tube_well_name FROM tube_wells ORDER BY tube_well_name");
$wells_list = mysqli_fetch_all($wells_r, MYSQLI_ASSOC);

// ── Handle Add ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'add') {
    validate_csrf();
    $tw_id    = (int)($_POST['tube_well_id']      ?? 0);
    $issue    = trim($_POST['issue']               ?? '');
    $date     = trim($_POST['maintenance_date']    ?? '');
    $status   = trim($_POST['status']              ?? 'Pending');
    $severity = trim($_POST['severity']            ?? 'Routine');
    $tech     = trim($_POST['technician_name']     ?? '');
    $remarks  = trim($_POST['remarks']             ?? '');

    if ($tw_id && $issue && $date) {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO maintenance (tube_well_id, issue, maintenance_date, status, severity, technician_name, remarks, reported_by)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        mysqli_stmt_bind_param($stmt, 'issssssi', $tw_id, $issue, $date, $status, $severity, $tech, $remarks, $_SESSION['user_id']);
        mysqli_stmt_execute($stmt) ? set_flash('success', 'Maintenance ticket created.') : set_flash('error', 'Failed to create ticket.');
        mysqli_stmt_close($stmt);
    } else {
        set_flash('error', 'Please fill all required fields.');
    }
    header('Location: ' . base_url('admin/maintenance.php')); exit;
}

// ── Handle Edit ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'edit') {
    validate_csrf();
    $id       = (int)($_POST['id']                ?? 0);
    $tw_id    = (int)($_POST['tube_well_id']      ?? 0);
    $issue    = trim($_POST['issue']              ?? '');
    $date     = trim($_POST['maintenance_date']   ?? '');
    $status   = trim($_POST['status']             ?? 'Pending');
    $severity = trim($_POST['severity']           ?? 'Routine');
    $tech     = trim($_POST['technician_name']    ?? '');
    $remarks  = trim($_POST['remarks']            ?? '');

    if ($id && $tw_id && $issue && $date) {
        // Update core fields
        $stmt = mysqli_prepare($conn,
            "UPDATE maintenance SET tube_well_id=?, issue=?, maintenance_date=?, status=?, severity=?, technician_name=?, remarks=? WHERE id=?"
        );
        // type string: i s s s s s s i  = 8 chars for 8 variables
        mysqli_stmt_bind_param($stmt, 'issssssi', $tw_id, $issue, $date, $status, $severity, $tech, $remarks, $id);
        if (mysqli_stmt_execute($stmt)) {
            // Set resolved_at when marking Completed (only if not already set)
            if ($status === 'Completed') {
                $rs = mysqli_prepare($conn, "UPDATE maintenance SET resolved_at=NOW() WHERE id=? AND resolved_at IS NULL");
                mysqli_stmt_bind_param($rs, 'i', $id);
                mysqli_stmt_execute($rs);
                mysqli_stmt_close($rs);
            }
            set_flash('success', 'Ticket updated.');
        } else {
            set_flash('error', 'Update failed.');
        }
        mysqli_stmt_close($stmt);
    }
    header('Location: ' . base_url('admin/maintenance.php')); exit;
}

// ── Handle Delete ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'delete') {
    validate_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $stmt = mysqli_prepare($conn, "DELETE FROM maintenance WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt) ? set_flash('success', 'Ticket deleted.') : set_flash('error', 'Delete failed.');
        mysqli_stmt_close($stmt);
    }
    header('Location: ' . base_url('admin/maintenance.php')); exit;
}

// ── Filters + Pagination ────────────────────────────────────
$filter_status   = trim($_GET['status']   ?? '');
$filter_severity = trim($_GET['severity'] ?? '');
$page_num        = max(1, (int)($_GET['page'] ?? 1));

$conds=[]; $bt=''; $bv=[];
if ($filter_status)   { $conds[]='m.status=?';   $bt.='s'; $bv[]=$filter_status; }
if ($filter_severity) { $conds[]='m.severity=?';  $bt.='s'; $bv[]=$filter_severity; }
$where_sql = $conds ? 'WHERE '.implode(' AND ',$conds) : '';

$cs = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM maintenance m $where_sql");
if ($bt) mysqli_stmt_bind_param($cs,$bt,...$bv);
mysqli_stmt_execute($cs);
$total_rows = mysqli_fetch_assoc(mysqli_stmt_get_result($cs))['total'] ?? 0;
mysqli_stmt_close($cs);

$pager  = paginate($total_rows, RECORDS_PER_PAGE, $page_num);
$limit  = RECORDS_PER_PAGE;
$offset = $pager['offset'];

$ls = mysqli_prepare($conn,
    "SELECT m.*, tw.tube_well_name, tw.well_code FROM maintenance m
     JOIN tube_wells tw ON tw.id=m.tube_well_id
     $where_sql ORDER BY m.created_at DESC LIMIT ? OFFSET ?"
);
$pt = $bt.'ii'; $pv = array_merge($bv,[$limit,$offset]);
mysqli_stmt_bind_param($ls,$pt,...$pv);
mysqli_stmt_execute($ls);
$records = mysqli_stmt_get_result($ls);
mysqli_stmt_close($ls);

// Stats
$r_pending   = mysqli_query($conn,"SELECT COUNT(*) AS c FROM maintenance WHERE status='Pending'");
$r_progress  = mysqli_query($conn,"SELECT COUNT(*) AS c FROM maintenance WHERE status='In Progress'");
$r_completed = mysqli_query($conn,"SELECT COUNT(*) AS c FROM maintenance WHERE status='Completed'");
$r_critical  = mysqli_query($conn,"SELECT COUNT(*) AS c FROM maintenance WHERE severity='Critical' AND status!='Completed'");

$cnt_pending   = mysqli_fetch_assoc($r_pending)['c']   ?? 0;
$cnt_progress  = mysqli_fetch_assoc($r_progress)['c']  ?? 0;
$cnt_completed = mysqli_fetch_assoc($r_completed)['c'] ?? 0;
$cnt_critical  = mysqli_fetch_assoc($r_critical)['c']  ?? 0;

$pagination_url = base_url('admin/maintenance.php').'?status='.urlencode($filter_status).'&severity='.urlencode($filter_severity).'&page=';

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
                <h2 class="font-h2 text-h2 text-primary">Maintenance Management</h2>
                <p class="text-on-surface-variant font-body-md">Track repair tickets, issue workflows, and field technician assignments.</p>
            </div>
            <button onclick="openModal('add-modal')"
                    class="flex items-center gap-sm bg-primary text-on-primary px-lg py-md rounded hover:opacity-90 shadow-sm">
                <span class="material-symbols-outlined">add</span>
                <span class="font-body-md font-semibold">New Ticket</span>
            </button>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-lg mb-xl">
            <div class="bg-surface border border-outline-variant p-md rounded-xl shadow-sm">
                <span class="font-label-sm text-on-surface-variant uppercase block mb-sm">Pending</span>
                <span class="font-h2 text-h2 text-primary"><?= $cnt_pending ?></span>
            </div>
            <div class="bg-surface border border-outline-variant p-md rounded-xl shadow-sm">
                <span class="font-label-sm text-on-surface-variant uppercase block mb-sm">In Progress</span>
                <span class="font-h2 text-h2 text-primary"><?= $cnt_progress ?></span>
            </div>
            <div class="bg-surface border border-outline-variant p-md rounded-xl shadow-sm">
                <span class="font-label-sm text-on-surface-variant uppercase block mb-sm">Completed</span>
                <span class="font-h2 text-h2 text-secondary"><?= $cnt_completed ?></span>
            </div>
            <div class="bg-surface border border-error/20 p-md rounded-xl shadow-sm">
                <span class="font-label-sm text-on-surface-variant uppercase block mb-sm">Critical Open</span>
                <span class="font-h2 text-h2 text-error"><?= $cnt_critical ?></span>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" action="" class="bg-surface border border-outline-variant p-md rounded-xl mb-lg flex flex-wrap items-end gap-md">
            <div class="flex-1 min-w-[150px]">
                <label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Status</label>
                <select name="status" class="w-full border border-outline-variant rounded px-md py-sm outline-none">
                    <option value="" <?= !$filter_status ? 'selected':'' ?>>All Statuses</option>
                    <option value="Pending"     <?= $filter_status==='Pending'     ?'selected':'' ?>>Pending</option>
                    <option value="In Progress" <?= $filter_status==='In Progress' ?'selected':'' ?>>In Progress</option>
                    <option value="Completed"   <?= $filter_status==='Completed'   ?'selected':'' ?>>Completed</option>
                </select>
            </div>
            <div class="flex-1 min-w-[150px]">
                <label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Severity</label>
                <select name="severity" class="w-full border border-outline-variant rounded px-md py-sm outline-none">
                    <option value="" <?= !$filter_severity ? 'selected':'' ?>>All Severities</option>
                    <option value="Routine"  <?= $filter_severity==='Routine'  ?'selected':'' ?>>Routine</option>
                    <option value="Warning"  <?= $filter_severity==='Warning'  ?'selected':'' ?>>Warning</option>
                    <option value="Critical" <?= $filter_severity==='Critical' ?'selected':'' ?>>Critical</option>
                </select>
            </div>
            <div class="flex-shrink-0 flex gap-sm">
                <button type="submit" class="bg-surface-container-high text-primary px-lg py-sm rounded border border-outline-variant hover:bg-surface-container-highest flex items-center gap-sm">
                    <span class="material-symbols-outlined">filter_list</span> Filter
                </button>
                <a href="<?= base_url('admin/maintenance.php') ?>" class="bg-surface text-on-surface-variant px-md py-sm rounded border border-outline-variant hover:bg-surface-container-low flex items-center">
                    <span class="material-symbols-outlined text-[18px]">restart_alt</span>
                </a>
            </div>
        </form>

        <!-- Table -->
        <div class="bg-surface border border-outline-variant rounded-xl overflow-hidden shadow-sm">
            <div class="px-lg py-md border-b border-surface-container flex items-center justify-between">
                <h3 class="font-h3 text-h3 text-primary">Maintenance Tickets</h3>
                <span class="text-label-sm text-on-surface-variant"><?= $pager['offset']+1 ?>–<?= min($pager['offset']+RECORDS_PER_PAGE,$total_rows) ?> of <?= $total_rows ?></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant">
                            <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Ticket ID</th>
                            <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Tube Well</th>
                            <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Issue</th>
                            <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Date</th>
                            <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Technician</th>
                            <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Severity</th>
                            <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Status</th>
                            <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container-high">
                        <?php if (mysqli_num_rows($records) === 0): ?>
                        <tr><td colspan="8" class="px-lg py-xl text-center text-on-surface-variant">
                            <span class="material-symbols-outlined text-[40px] block mb-sm text-outline">check_circle</span>No tickets found for the selected filters.
                        </td></tr>
                        <?php else: while ($rec = mysqli_fetch_assoc($records)): ?>
                        <tr class="hover:bg-surface-container-lowest transition-colors">
                            <td class="px-lg py-md font-data-mono text-primary font-bold">#TK-<?= str_pad($rec['id'],4,'0',STR_PAD_LEFT) ?></td>
                            <td class="px-lg py-md">
                                <div class="flex items-center gap-sm">
                                    <span class="w-2 h-2 rounded-full <?= $rec['severity']==='Critical'?'bg-error':($rec['severity']==='Warning'?'bg-tertiary-fixed-dim':'bg-secondary') ?>"></span>
                                    <span class="font-medium"><?= sanitize($rec['well_code']) ?></span>
                                </div>
                                <span class="text-[11px] text-on-surface-variant"><?= sanitize($rec['tube_well_name']) ?></span>
                            </td>
                            <td class="px-lg py-md text-on-surface-variant max-w-xs truncate"><?= sanitize($rec['issue']) ?></td>
                            <td class="px-lg py-md text-label-sm whitespace-nowrap"><?= date('Y-m-d', strtotime($rec['maintenance_date'])) ?></td>
                            <td class="px-lg py-md text-body-md"><?= sanitize($rec['technician_name'] ?? '—') ?></td>
                            <td class="px-lg py-md"><?= severity_badge($rec['severity']) ?></td>
                            <td class="px-lg py-md"><?= ticket_status_badge($rec['status']) ?></td>
                            <td class="px-lg py-md text-right">
                                <div class="flex items-center justify-end gap-sm">
                                    <button onclick="openEditModal(<?= htmlspecialchars(json_encode($rec),ENT_QUOTES) ?>)"
                                            class="p-xs hover:bg-surface-container-high rounded text-on-surface-variant transition-colors" title="Edit">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                    <form method="POST" action="" onsubmit="return confirm('Delete this ticket?');">
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
    <div class="bg-surface w-full max-w-lg rounded-xl shadow-2xl border border-outline-variant overflow-hidden max-h-screen overflow-y-auto">
        <div class="bg-primary p-lg flex justify-between items-center">
            <h3 class="text-on-primary font-h3 text-h3">Create Maintenance Ticket</h3>
            <button onclick="closeModal('add-modal')" class="text-on-primary opacity-70 hover:opacity-100"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" action="" class="p-lg space-y-lg">
            <?= csrf_field() ?><input type="hidden" name="_action" value="add">
            <div>
                <label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Tube Well *</label>
                <select name="tube_well_id" required class="w-full border border-outline-variant rounded px-md py-sm outline-none">
                    <option value="">Select a well...</option>
                    <?php foreach ($wells_list as $w): ?><option value="<?= $w['id'] ?>"><?= sanitize($w['well_code']) ?> – <?= sanitize($w['tube_well_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Issue Description *</label>
                <textarea name="issue" required rows="3" class="w-full border border-outline-variant rounded px-md py-sm outline-none" placeholder="Describe the problem in detail..."></textarea>
            </div>
            <div class="grid grid-cols-2 gap-md">
                <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Date *</label>
                    <input type="date" name="maintenance_date" required value="<?= date('Y-m-d') ?>" class="w-full border border-outline-variant rounded px-md py-sm outline-none"></div>
                <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Severity *</label>
                    <select name="severity" class="w-full border border-outline-variant rounded px-md py-sm outline-none">
                        <option value="Routine">Routine</option><option value="Warning">Warning</option><option value="Critical">Critical</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-md">
                <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Status</label>
                    <select name="status" class="w-full border border-outline-variant rounded px-md py-sm outline-none">
                        <option value="Pending">Pending</option><option value="In Progress">In Progress</option><option value="Completed">Completed</option>
                    </select>
                </div>
                <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Technician</label>
                    <input type="text" name="technician_name" class="w-full border border-outline-variant rounded px-md py-sm outline-none" placeholder="Assigned technician"></div>
            </div>
            <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Remarks</label>
                <textarea name="remarks" rows="2" class="w-full border border-outline-variant rounded px-md py-sm outline-none"></textarea></div>
            <div class="flex gap-md pt-md">
                <button type="button" onclick="closeModal('add-modal')" class="flex-1 px-lg py-md border border-outline-variant rounded font-semibold hover:bg-surface-container-low">Cancel</button>
                <button type="submit" class="flex-1 px-lg py-md bg-secondary text-on-primary rounded font-semibold hover:opacity-90">Create Ticket</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div id="edit-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-primary/40 backdrop-blur-sm hidden">
    <div class="bg-surface w-full max-w-lg rounded-xl shadow-2xl border border-outline-variant overflow-hidden max-h-screen overflow-y-auto">
        <div class="bg-primary p-lg flex justify-between items-center">
            <h3 class="text-on-primary font-h3 text-h3">Edit Maintenance Ticket</h3>
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
            <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Issue Description *</label>
                <textarea name="issue" id="edit-issue" required rows="3" class="w-full border border-outline-variant rounded px-md py-sm outline-none"></textarea></div>
            <div class="grid grid-cols-2 gap-md">
                <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Date *</label>
                    <input type="date" name="maintenance_date" id="edit-date" required class="w-full border border-outline-variant rounded px-md py-sm outline-none"></div>
                <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Severity *</label>
                    <select name="severity" id="edit-severity" class="w-full border border-outline-variant rounded px-md py-sm outline-none">
                        <option value="Routine">Routine</option><option value="Warning">Warning</option><option value="Critical">Critical</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-md">
                <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Status</label>
                    <select name="status" id="edit-status" class="w-full border border-outline-variant rounded px-md py-sm outline-none">
                        <option value="Pending">Pending</option><option value="In Progress">In Progress</option><option value="Completed">Completed</option>
                    </select>
                </div>
                <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Technician</label>
                    <input type="text" name="technician_name" id="edit-tech" class="w-full border border-outline-variant rounded px-md py-sm outline-none"></div>
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
    document.getElementById('edit-issue').value=rec.issue;
    document.getElementById('edit-date').value=rec.maintenance_date;
    document.getElementById('edit-severity').value=rec.severity;
    document.getElementById('edit-status').value=rec.status;
    document.getElementById('edit-tech').value=rec.technician_name??'';
    document.getElementById('edit-remarks').value=rec.remarks??'';
    openModal('edit-modal');
}
</script>
