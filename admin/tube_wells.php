<?php
/**
 * HydroLogic OS – Tube Well Management (Admin)
 * ==============================================
 * Lists all tube wells with pagination, status filter,
 * and handles Add/Edit/Delete via modal forms.
 */

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_role('Admin');

$page_title  = 'Tube Well Management';
$active_page = 'tube_wells';

// ── Handle Add ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'add') {
    validate_csrf();

    $code      = strtoupper(trim($_POST['well_code']        ?? ''));
    $name      = trim($_POST['tube_well_name']   ?? '');
    $location  = trim($_POST['location']         ?? '');
    $inst_date = trim($_POST['installation_date'] ?? '');
    $status    = trim($_POST['status']           ?? 'Active');
    $desc      = trim($_POST['description']      ?? '');

    if ($code && $name && $location && $inst_date) {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO tube_wells (well_code, tube_well_name, location, installation_date, status, description, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, 'ssssssi', $code, $name, $location, $inst_date, $status, $desc, $_SESSION['user_id']);
        if (mysqli_stmt_execute($stmt)) {
            set_flash('success', "Tube well '{$name}' added successfully.");
        } else {
            set_flash('error', 'Failed to add tube well. Well code may already exist.');
        }
        mysqli_stmt_close($stmt);
    } else {
        set_flash('error', 'Please fill in all required fields.');
    }
    header('Location: ' . base_url('admin/tube_wells.php'));
    exit;
}

// ── Handle Edit ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'edit') {
    validate_csrf();

    $id        = (int)($_POST['id']                 ?? 0);
    $code      = strtoupper(trim($_POST['well_code']         ?? ''));
    $name      = trim($_POST['tube_well_name']    ?? '');
    $location  = trim($_POST['location']          ?? '');
    $inst_date = trim($_POST['installation_date']  ?? '');
    $status    = trim($_POST['status']            ?? 'Active');
    $desc      = trim($_POST['description']       ?? '');

    if ($id && $code && $name && $location && $inst_date) {
        $stmt = mysqli_prepare($conn,
            "UPDATE tube_wells SET well_code=?, tube_well_name=?, location=?, installation_date=?, status=?, description=?
             WHERE id=?"
        );
        mysqli_stmt_bind_param($stmt, 'ssssssi', $code, $name, $location, $inst_date, $status, $desc, $id);
        if (mysqli_stmt_execute($stmt)) {
            set_flash('success', "Tube well updated successfully.");
        } else {
            set_flash('error', 'Update failed. Well code may conflict with an existing record.');
        }
        mysqli_stmt_close($stmt);
    } else {
        set_flash('error', 'Invalid data – please check all fields.');
    }
    header('Location: ' . base_url('admin/tube_wells.php'));
    exit;
}

// ── Handle Delete ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'delete') {
    validate_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $stmt = mysqli_prepare($conn, "DELETE FROM tube_wells WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        if (mysqli_stmt_execute($stmt)) {
            set_flash('success', 'Tube well deleted successfully.');
        } else {
            set_flash('error', 'Failed to delete tube well.');
        }
        mysqli_stmt_close($stmt);
    }
    header('Location: ' . base_url('admin/tube_wells.php'));
    exit;
}

// ── Filters + Pagination ────────────────────────────────────
$filter_status = trim($_GET['status'] ?? '');
$page_num      = max(1, (int)($_GET['page'] ?? 1));

// Count total for pagination
$where = '';
$params = [];
$types  = '';
if ($filter_status !== '') {
    $where    = "WHERE status = ?";
    $params[] = $filter_status;
    $types    = 's';
}

$count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM tube_wells $where");
if ($types) mysqli_stmt_bind_param($count_stmt, $types, ...$params);
mysqli_stmt_execute($count_stmt);
$total_rows = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'] ?? 0;
mysqli_stmt_close($count_stmt);

$pager  = paginate($total_rows, RECORDS_PER_PAGE, $page_num);
$offset = $pager['offset'];
$limit  = RECORDS_PER_PAGE;

$list_stmt = mysqli_prepare($conn,
    "SELECT * FROM tube_wells $where ORDER BY created_at DESC LIMIT ? OFFSET ?"
);
if ($types) {
    $params_with_pagination = array_merge($params, [$limit, $offset]);
    mysqli_stmt_bind_param($list_stmt, $types . 'ii', ...$params_with_pagination);
} else {
    mysqli_stmt_bind_param($list_stmt, 'ii', $limit, $offset);
}
mysqli_stmt_execute($list_stmt);
$wells = mysqli_stmt_get_result($list_stmt);
mysqli_stmt_close($list_stmt);

// For edit: fetch specific well data
$edit_well = null;
if (isset($_GET['edit'])) {
    $eid  = (int)$_GET['edit'];
    $stmt = mysqli_prepare($conn, "SELECT * FROM tube_wells WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $eid);
    mysqli_stmt_execute($stmt);
    $edit_well = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

$pagination_url = base_url('admin/tube_wells.php') . '?status=' . urlencode($filter_status) . '&page=';

require_once __DIR__ . '/../includes/header.php';
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="ml-sidebar-width min-h-screen flex flex-col">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <div class="p-lg flex-1">
        <?= render_flash() ?>

        <!-- Page Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-lg mb-xl">
            <div>
                <h2 class="font-h1 text-h1 text-primary mb-xs">Tube Well Management</h2>
                <p class="font-body-md text-on-surface-variant max-w-2xl">Monitor and manage industrial water extraction infrastructure.</p>
            </div>
            <button onclick="openModal('add-modal')"
                    class="inline-flex items-center gap-sm bg-primary text-on-primary px-lg py-md rounded-lg font-h3 hover:bg-primary-container transition-all active:scale-95 shadow-lg">
                <span class="material-symbols-outlined">add</span>
                <span>Add New Tube Well</span>
            </button>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-lg mb-lg">
            <?php
            $stmts_stats = [
                ['Total Wells',  'water_drop',  'secondary', 'SELECT COUNT(*) AS c FROM tube_wells'],
                ['Active',       'check_circle','secondary', "SELECT COUNT(*) AS c FROM tube_wells WHERE status='Active'"],
                ['Maintenance',  'build',       'tertiary',  "SELECT COUNT(*) AS c FROM tube_wells WHERE status='Under Maintenance'"],
                ['Inactive',     'warning',     'error',     "SELECT COUNT(*) AS c FROM tube_wells WHERE status='Inactive'"],
            ];
            foreach ($stmts_stats as [$label, $icon, $color, $query]):
                $r = mysqli_query($conn, $query);
                $c = mysqli_fetch_assoc($r)['c'] ?? 0;
            ?>
            <div class="bg-surface border border-outline-variant p-md rounded-xl shadow-sm">
                <div class="flex justify-between items-start mb-sm">
                    <span class="font-label-sm text-on-surface-variant uppercase tracking-wider"><?= $label ?></span>
                    <span class="material-symbols-outlined text-<?= $color ?>"><?= $icon ?></span>
                </div>
                <div class="flex items-baseline gap-sm">
                    <span class="font-h1 text-h1 text-primary"><?= $c ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Table Card -->
        <div class="bg-surface border border-outline-variant rounded-xl shadow-sm overflow-hidden">

            <!-- Filters -->
            <div class="px-lg py-md border-b border-outline-variant flex flex-wrap items-center justify-between gap-md bg-surface-container-lowest">
                <form method="GET" action="" class="flex items-center gap-md">
                    <div class="flex items-center gap-sm bg-surface-container-low px-md py-sm rounded-lg border border-outline-variant/30">
                        <span class="material-symbols-outlined text-sm text-on-surface-variant">filter_list</span>
                        <span class="font-label-sm text-on-surface-variant">Filter:</span>
                        <select name="status" onchange="this.form.submit()"
                                class="bg-transparent border-none font-body-md text-primary focus:ring-0 cursor-pointer py-0">
                            <option value="" <?= $filter_status === '' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="Active"             <?= $filter_status === 'Active' ? 'selected' : '' ?>>Active</option>
                            <option value="Under Maintenance"  <?= $filter_status === 'Under Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                            <option value="Inactive"           <?= $filter_status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </form>
                <span class="font-body-md text-on-surface-variant">
                    Showing <?= min($pager['offset'] + 1, $total_rows) ?>–<?= min($pager['offset'] + RECORDS_PER_PAGE, $total_rows) ?> of <?= $total_rows ?> wells
                </span>
            </div>

            <!-- Main Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant">
                            <th class="px-lg py-md font-label-sm text-on-surface-variant uppercase tracking-wider">Code</th>
                            <th class="px-lg py-md font-label-sm text-on-surface-variant uppercase tracking-wider">Name &amp; Description</th>
                            <th class="px-lg py-md font-label-sm text-on-surface-variant uppercase tracking-wider">Location</th>
                            <th class="px-lg py-md font-label-sm text-on-surface-variant uppercase tracking-wider">Installed</th>
                            <th class="px-lg py-md font-label-sm text-on-surface-variant uppercase tracking-wider">Status</th>
                            <th class="px-lg py-md font-label-sm text-on-surface-variant uppercase tracking-wider text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/30">
                        <?php if (mysqli_num_rows($wells) === 0): ?>
                        <tr>
                            <td colspan="6" class="px-lg py-xl text-center text-on-surface-variant font-body-md">
                                <span class="material-symbols-outlined text-[40px] block mb-sm text-outline">water_pump</span>
                                No tube wells found. <a href="#" onclick="openModal('add-modal')" class="text-secondary underline">Add one now</a>.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php while ($well = mysqli_fetch_assoc($wells)): ?>
                        <tr class="hover:bg-surface-container-lowest transition-colors group">
                            <td class="px-lg py-md">
                                <span class="font-data-mono text-data-mono bg-surface-container-high px-sm py-xs rounded"><?= sanitize($well['well_code']) ?></span>
                            </td>
                            <td class="px-lg py-md">
                                <div class="flex flex-col">
                                    <span class="font-body-md font-semibold text-primary"><?= sanitize($well['tube_well_name']) ?></span>
                                    <span class="text-label-sm text-on-surface-variant truncate max-w-xs"><?= sanitize(substr($well['description'] ?? '', 0, 60)) ?></span>
                                </div>
                            </td>
                            <td class="px-lg py-md">
                                <div class="flex items-center gap-xs text-on-surface-variant">
                                    <span class="material-symbols-outlined text-sm">location_on</span>
                                    <span class="font-body-md"><?= sanitize($well['location']) ?></span>
                                </div>
                            </td>
                            <td class="px-lg py-md font-body-md text-on-surface-variant"><?= date('M d, Y', strtotime($well['installation_date'])) ?></td>
                            <td class="px-lg py-md"><?= well_status_badge($well['status']) ?></td>
                            <td class="px-lg py-md text-right">
                                <div class="flex items-center justify-end gap-sm">
                                    <!-- Edit -->
                                    <button title="Edit"
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($well), ENT_QUOTES) ?>)"
                                            class="p-xs hover:bg-surface-container-high rounded text-on-surface-variant transition-colors">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                    <!-- Delete -->
                                    <form method="POST" action="" onsubmit="return confirm('Delete this tube well? All related records will also be removed.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_action" value="delete">
                                        <input type="hidden" name="id" value="<?= $well['id'] ?>">
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

            <!-- Pagination Footer -->
            <div class="px-lg py-md border-t border-outline-variant flex items-center justify-between bg-surface-container-low">
                <span class="font-body-md text-on-surface-variant">
                    Page <?= $pager['current_page'] ?> of <?= $pager['total_pages'] ?>
                </span>
                <div class="flex items-center gap-xs">
                    <?php if ($pager['current_page'] > 1): ?>
                    <a href="<?= $pagination_url . ($pager['current_page'] - 1) ?>"
                       class="inline-flex items-center gap-xs px-md py-sm border border-outline-variant rounded bg-surface hover:bg-surface-container-lowest transition-colors text-on-surface-variant font-body-md">
                        <span class="material-symbols-outlined">chevron_left</span> Previous
                    </a>
                    <?php endif; ?>

                    <?= render_pagination($pager, $pagination_url) ?>

                    <?php if ($pager['current_page'] < $pager['total_pages']): ?>
                    <a href="<?= $pagination_url . ($pager['current_page'] + 1) ?>"
                       class="inline-flex items-center gap-xs px-md py-sm border border-outline-variant rounded bg-surface hover:bg-surface-container-lowest transition-colors text-on-surface-variant font-body-md">
                        Next <span class="material-symbols-outlined">chevron_right</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</main>

<!-- ═══════════════════════════════════════════════════════
     ADD MODAL
═══════════════════════════════════════════════════════ -->
<div id="add-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-primary/40 backdrop-blur-sm hidden">
    <div class="bg-surface w-full max-w-lg rounded-xl shadow-2xl border border-outline-variant overflow-hidden">
        <div class="bg-primary p-lg flex justify-between items-center">
            <h3 class="text-on-primary font-h3 text-h3">Add New Tube Well</h3>
            <button onclick="closeModal('add-modal')" class="text-on-primary opacity-70 hover:opacity-100">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="" class="p-lg space-y-lg">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="add">

            <div class="grid grid-cols-2 gap-md">
                <div>
                    <label class="block font-label-sm text-on-surface-variant uppercase mb-xs">Well Code *</label>
                    <input type="text" name="well_code" required placeholder="e.g. TW-011-A"
                           class="w-full border border-outline-variant rounded px-md py-sm font-body-md focus:ring-2 focus:ring-secondary focus:border-transparent outline-none">
                </div>
                <div>
                    <label class="block font-label-sm text-on-surface-variant uppercase mb-xs">Status *</label>
                    <select name="status" class="w-full border border-outline-variant rounded px-md py-sm font-body-md focus:ring-2 focus:ring-secondary outline-none">
                        <option value="Active">Active</option>
                        <option value="Under Maintenance">Under Maintenance</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block font-label-sm text-on-surface-variant uppercase mb-xs">Well Name *</label>
                <input type="text" name="tube_well_name" required placeholder="e.g. Northern Well Alpha"
                       class="w-full border border-outline-variant rounded px-md py-sm font-body-md focus:ring-2 focus:ring-secondary outline-none">
            </div>
            <div>
                <label class="block font-label-sm text-on-surface-variant uppercase mb-xs">Location *</label>
                <input type="text" name="location" required placeholder="e.g. Sector 4, Zone G"
                       class="w-full border border-outline-variant rounded px-md py-sm font-body-md focus:ring-2 focus:ring-secondary outline-none">
            </div>
            <div>
                <label class="block font-label-sm text-on-surface-variant uppercase mb-xs">Installation Date *</label>
                <input type="date" name="installation_date" required
                       class="w-full border border-outline-variant rounded px-md py-sm font-body-md focus:ring-2 focus:ring-secondary outline-none">
            </div>
            <div>
                <label class="block font-label-sm text-on-surface-variant uppercase mb-xs">Description</label>
                <textarea name="description" rows="3" placeholder="Optional notes about this well..."
                          class="w-full border border-outline-variant rounded px-md py-sm font-body-md focus:ring-2 focus:ring-secondary outline-none"></textarea>
            </div>
            <div class="flex gap-md pt-md">
                <button type="button" onclick="closeModal('add-modal')"
                        class="flex-1 px-lg py-md border border-outline-variant rounded font-semibold hover:bg-surface-container-low transition-colors">
                    Cancel
                </button>
                <button type="submit"
                        class="flex-1 px-lg py-md bg-secondary text-on-primary rounded font-semibold shadow-sm hover:opacity-90 transition-opacity">
                    Add Tube Well
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     EDIT MODAL
═══════════════════════════════════════════════════════ -->
<div id="edit-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-primary/40 backdrop-blur-sm hidden">
    <div class="bg-surface w-full max-w-lg rounded-xl shadow-2xl border border-outline-variant overflow-hidden">
        <div class="bg-primary p-lg flex justify-between items-center">
            <h3 class="text-on-primary font-h3 text-h3">Edit Tube Well</h3>
            <button onclick="closeModal('edit-modal')" class="text-on-primary opacity-70 hover:opacity-100">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="" class="p-lg space-y-lg" id="edit-form">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="edit">
            <input type="hidden" name="id" id="edit-id">

            <div class="grid grid-cols-2 gap-md">
                <div>
                    <label class="block font-label-sm text-on-surface-variant uppercase mb-xs">Well Code *</label>
                    <input type="text" name="well_code" id="edit-code" required
                           class="w-full border border-outline-variant rounded px-md py-sm font-body-md focus:ring-2 focus:ring-secondary outline-none">
                </div>
                <div>
                    <label class="block font-label-sm text-on-surface-variant uppercase mb-xs">Status *</label>
                    <select name="status" id="edit-status" class="w-full border border-outline-variant rounded px-md py-sm font-body-md focus:ring-2 focus:ring-secondary outline-none">
                        <option value="Active">Active</option>
                        <option value="Under Maintenance">Under Maintenance</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block font-label-sm text-on-surface-variant uppercase mb-xs">Well Name *</label>
                <input type="text" name="tube_well_name" id="edit-name" required
                       class="w-full border border-outline-variant rounded px-md py-sm font-body-md focus:ring-2 focus:ring-secondary outline-none">
            </div>
            <div>
                <label class="block font-label-sm text-on-surface-variant uppercase mb-xs">Location *</label>
                <input type="text" name="location" id="edit-location" required
                       class="w-full border border-outline-variant rounded px-md py-sm font-body-md focus:ring-2 focus:ring-secondary outline-none">
            </div>
            <div>
                <label class="block font-label-sm text-on-surface-variant uppercase mb-xs">Installation Date *</label>
                <input type="date" name="installation_date" id="edit-date" required
                       class="w-full border border-outline-variant rounded px-md py-sm font-body-md focus:ring-2 focus:ring-secondary outline-none">
            </div>
            <div>
                <label class="block font-label-sm text-on-surface-variant uppercase mb-xs">Description</label>
                <textarea name="description" id="edit-desc" rows="3"
                          class="w-full border border-outline-variant rounded px-md py-sm font-body-md focus:ring-2 focus:ring-secondary outline-none"></textarea>
            </div>
            <div class="flex gap-md pt-md">
                <button type="button" onclick="closeModal('edit-modal')"
                        class="flex-1 px-lg py-md border border-outline-variant rounded font-semibold hover:bg-surface-container-low transition-colors">
                    Cancel
                </button>
                <button type="submit"
                        class="flex-1 px-lg py-md bg-secondary text-on-primary rounded font-semibold shadow-sm hover:opacity-90 transition-opacity">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

// Close modal when clicking backdrop
document.querySelectorAll('[id$="-modal"]').forEach(modal => {
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(modal.id); });
});

// Populate Edit Modal
function openEditModal(well) {
    document.getElementById('edit-id').value       = well.id;
    document.getElementById('edit-code').value     = well.well_code;
    document.getElementById('edit-name').value     = well.tube_well_name;
    document.getElementById('edit-location').value = well.location;
    document.getElementById('edit-date').value     = well.installation_date;
    document.getElementById('edit-desc').value     = well.description ?? '';
    document.getElementById('edit-status').value   = well.status;
    openModal('edit-modal');
}
</script>
