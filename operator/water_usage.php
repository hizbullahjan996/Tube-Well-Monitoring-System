<?php
/**
 * HydroLogic OS – Operator: Water Usage Entry
 * =============================================
 * Operators can add water usage records. They can also edit/delete
 * their own records only.
 */

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$page_title  = 'Record Water Usage';
$active_page = 'water_usage';
$uid = (int)$_SESSION['user_id'];

// Tube wells for dropdown
$wells_r    = mysqli_query($conn,"SELECT id, well_code, tube_well_name FROM tube_wells WHERE status='Active' ORDER BY tube_well_name");
$wells_list = mysqli_fetch_all($wells_r, MYSQLI_ASSOC);

// ── Add Record ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='add') {
    validate_csrf();
    $tw_id  = (int)($_POST['tube_well_id']  ?? 0);
    $date   = trim($_POST['usage_date']     ?? '');
    $amount = (float)($_POST['water_amount'] ?? 0);
    $remark = trim($_POST['remarks']        ?? '');

    if ($tw_id && $date && $amount > 0) {
        $stmt = mysqli_prepare($conn,"INSERT INTO water_usage (tube_well_id,usage_date,water_amount,remarks,recorded_by) VALUES (?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt,'isdsi',$tw_id,$date,$amount,$remark,$uid);
        mysqli_stmt_execute($stmt) ? set_flash('success','Usage recorded successfully.') : set_flash('error','Failed to record usage.');
        mysqli_stmt_close($stmt);
    } else {
        set_flash('error','Please fill all required fields with valid values.');
    }
    header('Location: '.base_url('operator/water_usage.php')); exit;
}

// ── Delete Own Record ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='delete') {
    validate_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $stmt = mysqli_prepare($conn,"DELETE FROM water_usage WHERE id=? AND recorded_by=?");
        mysqli_stmt_bind_param($stmt,'ii',$id,$uid);
        mysqli_stmt_execute($stmt) ? set_flash('success','Record deleted.') : set_flash('error','Delete failed.');
        mysqli_stmt_close($stmt);
    }
    header('Location: '.base_url('operator/water_usage.php')); exit;
}

// ── Fetch My Records ────────────────────────────────────────
$page_num   = max(1,(int)($_GET['page']??1));
$pager_pre  = ['offset'=>($page_num-1)*RECORDS_PER_PAGE];

$cs = mysqli_prepare($conn,"SELECT COUNT(*) AS total FROM water_usage WHERE recorded_by=?");
mysqli_stmt_bind_param($cs,'i',$uid);
mysqli_stmt_execute($cs);
$total_rows = mysqli_fetch_assoc(mysqli_stmt_get_result($cs))['total'] ?? 0;
mysqli_stmt_close($cs);

$pager  = paginate($total_rows, RECORDS_PER_PAGE, $page_num);
$limit  = RECORDS_PER_PAGE;
$offset = $pager['offset'];

$ls = mysqli_prepare($conn,
    "SELECT wu.*, tw.tube_well_name, tw.well_code FROM water_usage wu
     JOIN tube_wells tw ON tw.id=wu.tube_well_id
     WHERE wu.recorded_by=? ORDER BY wu.usage_date DESC, wu.id DESC LIMIT ? OFFSET ?"
);
mysqli_stmt_bind_param($ls,'iii',$uid,$limit,$offset);
mysqli_stmt_execute($ls);
$records = mysqli_stmt_get_result($ls);
mysqli_stmt_close($ls);

$pagination_url = base_url('operator/water_usage.php').'?page=';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="ml-sidebar-width min-h-screen flex flex-col">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <div class="p-lg flex-1">
        <?= render_flash() ?>

        <div class="flex flex-col md:flex-row md:items-center justify-between mb-xl gap-md">
            <div>
                <h2 class="font-h2 text-h2 text-primary">Record Water Usage</h2>
                <p class="text-on-surface-variant font-body-md">Log daily water consumption from your assigned wells.</p>
            </div>
            <button onclick="openModal('add-modal')"
                    class="flex items-center gap-sm bg-secondary text-on-secondary px-lg py-md rounded shadow-sm hover:opacity-90">
                <span class="material-symbols-outlined">add</span>
                <span class="font-body-md font-semibold">New Entry</span>
            </button>
        </div>

        <!-- Records Table -->
        <div class="bg-surface border border-outline-variant rounded-xl overflow-hidden shadow-sm">
            <div class="px-lg py-md border-b border-surface-container flex items-center justify-between">
                <h3 class="font-h3 text-h3 text-primary">My Usage Records</h3>
                <span class="text-label-sm text-on-surface-variant"><?= $total_rows ?> total records</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead><tr class="bg-surface-container-low/50">
                        <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Date</th>
                        <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Tube Well</th>
                        <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Amount (L)</th>
                        <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Remarks</th>
                        <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase text-right">Actions</th>
                    </tr></thead>
                    <tbody class="divide-y divide-surface-container">
                        <?php if (mysqli_num_rows($records)===0): ?>
                        <tr><td colspan="5" class="px-lg py-xl text-center text-on-surface-variant">
                            <span class="material-symbols-outlined text-[40px] block mb-sm text-outline">opacity</span>
                            No records yet. Start logging water usage!
                        </td></tr>
                        <?php else: while ($rec=mysqli_fetch_assoc($records)): ?>
                        <tr class="hover:bg-surface-container-lowest transition-colors">
                            <td class="px-lg py-md font-data-mono text-data-mono"><?= date('Y-m-d',strtotime($rec['usage_date'])) ?></td>
                            <td class="px-lg py-md">
                                <div class="flex items-center gap-sm">
                                    <div class="w-2 h-2 rounded-full bg-secondary"></div>
                                    <span class="font-semibold text-primary"><?= sanitize($rec['well_code']) ?> – <?= sanitize($rec['tube_well_name']) ?></span>
                                </div>
                            </td>
                            <td class="px-lg py-md font-data-mono text-primary font-bold"><?= number_format($rec['water_amount'],2) ?> L</td>
                            <td class="px-lg py-md text-on-surface-variant italic"><?= sanitize($rec['remarks']??'—') ?></td>
                            <td class="px-lg py-md text-right">
                                <form method="POST" action="" onsubmit="return confirm('Delete this record?');">
                                    <?= csrf_field() ?><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= $rec['id'] ?>">
                                    <button type="submit" class="p-xs hover:bg-error-container hover:text-error rounded text-on-surface-variant transition-colors">
                                        <span class="material-symbols-outlined text-[20px]">delete</span>
                                    </button>
                                </form>
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
                    <?= render_pagination($pager,$pagination_url) ?>
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
            <h3 class="text-on-primary font-h3 text-h3">Record Water Usage</h3>
            <button onclick="closeModal('add-modal')" class="text-on-primary opacity-70 hover:opacity-100"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" action="" class="p-lg space-y-lg">
            <?= csrf_field() ?><input type="hidden" name="_action" value="add">
            <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Tube Well Source *</label>
                <select name="tube_well_id" required class="w-full border border-outline-variant rounded px-md py-sm text-body-md outline-none">
                    <option value="">Select a well...</option>
                    <?php foreach ($wells_list as $w): ?><option value="<?= $w['id'] ?>"><?= sanitize($w['well_code']) ?> – <?= sanitize($w['tube_well_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Usage Date *</label>
                <input type="date" name="usage_date" required value="<?= date('Y-m-d') ?>" class="w-full border border-outline-variant rounded px-md py-sm outline-none"></div>
            <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Amount (Liters) *</label>
                <div class="relative">
                    <input type="number" name="water_amount" required min="0.01" step="0.01" placeholder="0.00" class="w-full border border-outline-variant rounded px-md py-sm pr-12 outline-none">
                    <span class="absolute right-4 top-2.5 text-on-surface-variant text-label-sm">LTRS</span>
                </div>
            </div>
            <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Remarks</label>
                <textarea name="remarks" rows="3" class="w-full border border-outline-variant rounded px-md py-sm outline-none" placeholder="Any observations..."></textarea></div>
            <div class="flex gap-md pt-md">
                <button type="button" onclick="closeModal('add-modal')" class="flex-1 px-lg py-md border border-outline-variant rounded font-semibold hover:bg-surface-container-low">Cancel</button>
                <button type="submit" class="flex-1 px-lg py-md bg-secondary text-on-primary rounded font-semibold hover:opacity-90">Submit Record</button>
            </div>
        </form>
    </div>
</div>
<script>
function openModal(id){document.getElementById(id).classList.remove('hidden');}
function closeModal(id){document.getElementById(id).classList.add('hidden');}
document.getElementById('add-modal').addEventListener('click',e=>{if(e.target===document.getElementById('add-modal'))closeModal('add-modal');});
</script>
