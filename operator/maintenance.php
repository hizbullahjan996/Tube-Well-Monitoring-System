<?php
/**
 * HydroLogic OS – Operator: Maintenance Ticket Submission
 * ==========================================================
 * Operators can submit new maintenance tickets and view their own.
 */

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$page_title  = 'Report Maintenance Issue';
$active_page = 'maintenance';
$uid = (int)$_SESSION['user_id'];

// Tube wells dropdown
$wells_r    = mysqli_query($conn,"SELECT id, well_code, tube_well_name FROM tube_wells ORDER BY tube_well_name");
$wells_list = mysqli_fetch_all($wells_r, MYSQLI_ASSOC);

// ── Handle Submit ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='add') {
    validate_csrf();
    $tw_id    = (int)($_POST['tube_well_id']     ?? 0);
    $issue    = trim($_POST['issue']              ?? '');
    $date     = trim($_POST['maintenance_date']   ?? '');
    $severity = trim($_POST['severity']           ?? 'Routine');
    $remarks  = trim($_POST['remarks']            ?? '');

    if ($tw_id && $issue && $date) {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO maintenance (tube_well_id,issue,maintenance_date,status,severity,remarks,reported_by)
             VALUES (?,?,?,'Pending',?,?,?)"
        );
        mysqli_stmt_bind_param($stmt,'issssi',$tw_id,$issue,$date,$severity,$remarks,$uid);
        mysqli_stmt_execute($stmt) ? set_flash('success','Maintenance ticket submitted. Pending admin review.') : set_flash('error','Failed to submit ticket.');
        mysqli_stmt_close($stmt);
    } else {
        set_flash('error','Please fill all required fields.');
    }
    header('Location: '.base_url('operator/maintenance.php')); exit;
}

// ── Fetch My Tickets ────────────────────────────────────────
$page_num = max(1,(int)($_GET['page']??1));
$cs = mysqli_prepare($conn,"SELECT COUNT(*) AS total FROM maintenance WHERE reported_by=?");
mysqli_stmt_bind_param($cs,'i',$uid);
mysqli_stmt_execute($cs);
$total_rows = mysqli_fetch_assoc(mysqli_stmt_get_result($cs))['total'] ?? 0;
mysqli_stmt_close($cs);

$pager  = paginate($total_rows, RECORDS_PER_PAGE, $page_num);
$limit  = RECORDS_PER_PAGE;
$offset = $pager['offset'];

$ls = mysqli_prepare($conn,
    "SELECT m.*, tw.tube_well_name, tw.well_code FROM maintenance m
     JOIN tube_wells tw ON tw.id=m.tube_well_id
     WHERE m.reported_by=? ORDER BY m.created_at DESC LIMIT ? OFFSET ?"
);
mysqli_stmt_bind_param($ls,'iii',$uid,$limit,$offset);
mysqli_stmt_execute($ls);
$records = mysqli_stmt_get_result($ls);
mysqli_stmt_close($ls);

$pagination_url = base_url('operator/maintenance.php').'?page=';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="ml-sidebar-width min-h-screen flex flex-col">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="p-lg flex-1">
        <?= render_flash() ?>

        <div class="flex flex-col md:flex-row md:items-center justify-between mb-xl gap-md">
            <div>
                <h2 class="font-h2 text-h2 text-primary">Report Maintenance Issue</h2>
                <p class="text-on-surface-variant font-body-md">Submit a maintenance ticket for review by the admin team.</p>
            </div>
            <button onclick="openModal('add-modal')"
                    class="flex items-center gap-sm bg-error text-on-error px-lg py-md rounded shadow-sm hover:opacity-90">
                <span class="material-symbols-outlined">report_problem</span>
                <span class="font-body-md font-semibold">Report Issue</span>
            </button>
        </div>

        <!-- My Tickets Table -->
        <div class="bg-surface border border-outline-variant rounded-xl overflow-hidden shadow-sm">
            <div class="px-lg py-md border-b border-surface-container flex items-center justify-between">
                <h3 class="font-h3 text-h3 text-primary">My Submitted Tickets</h3>
                <span class="text-label-sm text-on-surface-variant"><?= $total_rows ?> total</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead><tr class="bg-surface-container-low/50">
                        <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Ticket</th>
                        <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Tube Well</th>
                        <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Issue</th>
                        <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Date</th>
                        <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Severity</th>
                        <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Status</th>
                    </tr></thead>
                    <tbody class="divide-y divide-surface-container">
                        <?php if (mysqli_num_rows($records)===0): ?>
                        <tr><td colspan="6" class="px-lg py-xl text-center text-on-surface-variant">
                            <span class="material-symbols-outlined text-[40px] block mb-sm text-outline">settings_suggest</span>
                            No tickets submitted yet.
                        </td></tr>
                        <?php else: while ($rec=mysqli_fetch_assoc($records)): ?>
                        <tr class="hover:bg-surface-container-lowest transition-colors">
                            <td class="px-lg py-md font-data-mono text-primary font-bold">#TK-<?= str_pad($rec['id'],4,'0',STR_PAD_LEFT) ?></td>
                            <td class="px-lg py-md font-semibold text-primary"><?= sanitize($rec['well_code']) ?></td>
                            <td class="px-lg py-md text-on-surface-variant max-w-xs truncate"><?= sanitize($rec['issue']) ?></td>
                            <td class="px-lg py-md text-label-sm"><?= date('Y-m-d',strtotime($rec['maintenance_date'])) ?></td>
                            <td class="px-lg py-md"><?= severity_badge($rec['severity']) ?></td>
                            <td class="px-lg py-md"><?= ticket_status_badge($rec['status']) ?></td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-lg py-md bg-surface-container-lowest border-t border-surface-container flex items-center justify-between">
                <span class="text-label-sm text-on-surface-variant">Page <?= $pager['current_page'] ?> of <?= $pager['total_pages'] ?></span>
                <div class="flex gap-xs">
                    <?php if ($pager['current_page']>1): ?><a href="<?= $pagination_url.($pager['current_page']-1) ?>" class="px-sm py-xs border border-outline-variant rounded bg-surface text-on-surface-variant hover:bg-surface-container-low">‹</a><?php endif; ?>
                    <?= render_pagination($pager,$pagination_url) ?>
                    <?php if ($pager['current_page']<$pager['total_pages']): ?><a href="<?= $pagination_url.($pager['current_page']+1) ?>" class="px-sm py-xs border border-outline-variant rounded bg-surface text-on-surface-variant hover:bg-surface-container-low">›</a><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</main>

<!-- REPORT MODAL -->
<div id="add-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-primary/40 backdrop-blur-sm hidden">
    <div class="bg-surface w-full max-w-lg rounded-xl shadow-2xl border border-outline-variant overflow-hidden">
        <div class="bg-primary p-lg flex justify-between items-center">
            <h3 class="text-on-primary font-h3 text-h3">Report Maintenance Issue</h3>
            <button onclick="closeModal('add-modal')" class="text-on-primary opacity-70 hover:opacity-100"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" action="" class="p-lg space-y-lg">
            <?= csrf_field() ?><input type="hidden" name="_action" value="add">
            <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Tube Well *</label>
                <select name="tube_well_id" required class="w-full border border-outline-variant rounded px-md py-sm outline-none">
                    <option value="">Select a well...</option>
                    <?php foreach ($wells_list as $w): ?><option value="<?= $w['id'] ?>"><?= sanitize($w['well_code']) ?> – <?= sanitize($w['tube_well_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Issue Description *</label>
                <textarea name="issue" required rows="4" class="w-full border border-outline-variant rounded px-md py-sm outline-none" placeholder="Describe the problem clearly..."></textarea></div>
            <div class="grid grid-cols-2 gap-md">
                <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Incident Date *</label>
                    <input type="date" name="maintenance_date" required value="<?= date('Y-m-d') ?>" class="w-full border border-outline-variant rounded px-md py-sm outline-none"></div>
                <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Severity *</label>
                    <select name="severity" class="w-full border border-outline-variant rounded px-md py-sm outline-none">
                        <option value="Routine">Routine</option>
                        <option value="Warning">Warning</option>
                        <option value="Critical">Critical</option>
                    </select>
                </div>
            </div>
            <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Additional Remarks</label>
                <textarea name="remarks" rows="2" class="w-full border border-outline-variant rounded px-md py-sm outline-none" placeholder="Any extra details..."></textarea></div>
            <div class="flex gap-md pt-md">
                <button type="button" onclick="closeModal('add-modal')" class="flex-1 px-lg py-md border border-outline-variant rounded font-semibold hover:bg-surface-container-low">Cancel</button>
                <button type="submit" class="flex-1 px-lg py-md bg-error text-on-error rounded font-semibold hover:opacity-90">Submit Report</button>
            </div>
        </form>
    </div>
</div>
<script>
function openModal(id){document.getElementById(id).classList.remove('hidden');}
function closeModal(id){document.getElementById(id).classList.add('hidden');}
document.getElementById('add-modal').addEventListener('click',e=>{if(e.target===document.getElementById('add-modal'))closeModal('add-modal');});
</script>
