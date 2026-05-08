<?php
/**
 * HydroLogic OS – User Management (Admin Only)
 * ==============================================
 * Full CRUD for system users with role assignment
 * and password hashing. Protects own account from deletion.
 */

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_role('Admin');

$page_title  = 'User Management';
$active_page = 'users';

// ── Handle Add ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'add') {
    validate_csrf();
    $name     = trim($_POST['name']     ?? '');
    $email    = strtolower(trim($_POST['email']    ?? ''));
    $password = trim($_POST['password'] ?? '');
    $role     = trim($_POST['role']     ?? 'Operator');

    if ($name && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($password) >= 6) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $hash, $role);
        if (mysqli_stmt_execute($stmt)) {
            set_flash('success', "User '{$name}' created successfully.");
        } else {
            set_flash('error', 'Failed to create user. Email may already be in use.');
        }
        mysqli_stmt_close($stmt);
    } else {
        set_flash('error', 'Invalid input. Ensure email is valid and password has at least 6 characters.');
    }
    header('Location: ' . base_url('admin/users.php')); exit;
}

// ── Handle Edit ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'edit') {
    validate_csrf();
    $id    = (int)($_POST['id']     ?? 0);
    $name  = trim($_POST['name']    ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $role  = trim($_POST['role']    ?? 'Operator');
    $new_pw= trim($_POST['new_password'] ?? '');

    if ($id && $name && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if ($new_pw) {
            // Update with new password
            if (strlen($new_pw) < 6) {
                set_flash('error', 'Password must be at least 6 characters.');
                header('Location: ' . base_url('admin/users.php')); exit;
            }
            $hash = password_hash($new_pw, PASSWORD_BCRYPT);
            $stmt = mysqli_prepare($conn, "UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'ssssi', $name, $email, $role, $hash, $id);
        } else {
            // Update without changing password
            $stmt = mysqli_prepare($conn, "UPDATE users SET name=?, email=?, role=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'sssi', $name, $email, $role, $id);
        }
        mysqli_stmt_execute($stmt) ? set_flash('success', 'User updated.') : set_flash('error', 'Update failed. Email may conflict.');
        mysqli_stmt_close($stmt);
    } else {
        set_flash('error', 'Invalid data supplied.');
    }
    header('Location: ' . base_url('admin/users.php')); exit;
}

// ── Handle Delete ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'delete') {
    validate_csrf();
    $id = (int)($_POST['id'] ?? 0);

    if ($id === (int)$_SESSION['user_id']) {
        set_flash('error', 'You cannot delete your own account.');
    } elseif ($id) {
        $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt) ? set_flash('success', 'User deleted.') : set_flash('error', 'Delete failed.');
        mysqli_stmt_close($stmt);
    }
    header('Location: ' . base_url('admin/users.php')); exit;
}

// ── Handle Toggle Active ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'toggle') {
    validate_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id !== (int)$_SESSION['user_id']) {
        $stmt = mysqli_prepare($conn, "UPDATE users SET is_active = NOT is_active WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        set_flash('success', 'User status updated.');
    }
    header('Location: ' . base_url('admin/users.php')); exit;
}

// ── Fetch Users ─────────────────────────────────────────────
$page_num   = max(1, (int)($_GET['page'] ?? 1));
$filter_role= trim($_GET['role'] ?? '');

$conds=[]; $bt=''; $bv=[];
if ($filter_role) { $conds[]='role=?'; $bt.='s'; $bv[]=$filter_role; }
$where_sql = $conds ? 'WHERE '.implode(' AND ',$conds) : '';

$cs = mysqli_prepare($conn,"SELECT COUNT(*) AS total FROM users $where_sql");
if ($bt) mysqli_stmt_bind_param($cs,$bt,...$bv);
mysqli_stmt_execute($cs);
$total_rows = mysqli_fetch_assoc(mysqli_stmt_get_result($cs))['total'] ?? 0;
mysqli_stmt_close($cs);

$pager  = paginate($total_rows, RECORDS_PER_PAGE, $page_num);
$limit  = RECORDS_PER_PAGE;
$offset = $pager['offset'];

$ls = mysqli_prepare($conn,"SELECT * FROM users $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?");
$pt = $bt.'ii'; $pv = array_merge($bv,[$limit,$offset]);
mysqli_stmt_bind_param($ls,$pt,...$pv);
mysqli_stmt_execute($ls);
$users = mysqli_stmt_get_result($ls);
mysqli_stmt_close($ls);

$pagination_url = base_url('admin/users.php').'?role='.urlencode($filter_role).'&page=';

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
                <h2 class="font-h2 text-h2 text-primary">User Management</h2>
                <p class="text-on-surface-variant font-body-md">Add, edit, and manage system users and access roles.</p>
            </div>
            <button onclick="openModal('add-modal')"
                    class="flex items-center gap-sm bg-primary text-on-primary px-lg py-md rounded hover:opacity-90 shadow-sm">
                <span class="material-symbols-outlined">person_add</span>
                <span class="font-body-md font-semibold">Add User</span>
            </button>
        </div>

        <!-- Filter -->
        <form method="GET" action="" class="bg-surface border border-outline-variant p-md rounded-xl mb-lg flex items-center gap-md">
            <label class="font-label-sm text-on-surface-variant uppercase">Role:</label>
            <select name="role" onchange="this.form.submit()" class="border border-outline-variant rounded px-md py-sm outline-none">
                <option value="" <?= !$filter_role?'selected':'' ?>>All Roles</option>
                <option value="Admin"    <?= $filter_role==='Admin'   ?'selected':'' ?>>Admin</option>
                <option value="Operator" <?= $filter_role==='Operator'?'selected':'' ?>>Operator</option>
            </select>
            <span class="text-on-surface-variant font-label-sm ml-auto"><?= $total_rows ?> user<?= $total_rows!==1?'s':'' ?></span>
        </form>

        <!-- Table -->
        <div class="bg-surface border border-outline-variant rounded-xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant">
                            <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Name</th>
                            <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Email</th>
                            <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Role</th>
                            <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Status</th>
                            <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase">Created</th>
                            <th class="px-lg py-md text-label-sm text-on-surface-variant uppercase text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/30">
                        <?php if (mysqli_num_rows($users)===0): ?>
                        <tr><td colspan="6" class="px-lg py-xl text-center text-on-surface-variant">
                            <span class="material-symbols-outlined text-[40px] block mb-sm text-outline">group</span>No users found.
                        </td></tr>
                        <?php else: while ($user = mysqli_fetch_assoc($users)):
                            $is_self = ((int)$user['id'] === (int)$_SESSION['user_id']);
                        ?>
                        <tr class="hover:bg-surface-container-lowest transition-colors <?= $is_self ? 'bg-primary/5' : '' ?>">
                            <td class="px-lg py-md">
                                <div class="flex items-center gap-sm">
                                    <div class="w-8 h-8 rounded-full bg-primary-container flex items-center justify-center flex-shrink-0">
                                        <span class="text-on-primary-container font-bold text-[12px]"><?= strtoupper(substr($user['name'],0,2)) ?></span>
                                    </div>
                                    <div>
                                        <span class="font-semibold text-primary"><?= sanitize($user['name']) ?></span>
                                        <?php if ($is_self): ?><span class="ml-xs text-[10px] bg-secondary/10 text-secondary px-1 rounded font-bold">YOU</span><?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-lg py-md text-on-surface-variant font-body-md"><?= sanitize($user['email']) ?></td>
                            <td class="px-lg py-md">
                                <?php if ($user['role']==='Admin'): ?>
                                <span class="px-2 py-1 bg-primary/10 text-primary text-[10px] font-black uppercase rounded">Admin</span>
                                <?php else: ?>
                                <span class="px-2 py-1 bg-surface-container-highest text-on-surface-variant text-[10px] font-black uppercase rounded">Operator</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-lg py-md">
                                <?php if ($user['is_active']): ?>
                                <span class="inline-flex items-center gap-xs text-secondary text-label-sm font-bold"><span class="w-2 h-2 rounded-full bg-secondary animate-pulse"></span>Active</span>
                                <?php else: ?>
                                <span class="inline-flex items-center gap-xs text-error text-label-sm font-bold"><span class="w-2 h-2 rounded-full bg-error"></span>Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-lg py-md text-on-surface-variant text-label-sm"><?= date('d M Y', strtotime($user['created_at'])) ?></td>
                            <td class="px-lg py-md text-right">
                                <div class="flex items-center justify-end gap-sm">
                                    <button onclick="openEditModal(<?= htmlspecialchars(json_encode($user),ENT_QUOTES) ?>)"
                                            class="p-xs hover:bg-surface-container-high rounded text-on-surface-variant transition-colors" title="Edit">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                    <?php if (!$is_self): ?>
                                    <!-- Toggle Active/Disable -->
                                    <form method="POST" action="">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_action" value="toggle">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <button type="submit" title="<?= $user['is_active'] ? 'Disable' : 'Enable' ?>"
                                                class="p-xs hover:bg-surface-container-high rounded text-on-surface-variant transition-colors">
                                            <span class="material-symbols-outlined text-[20px]"><?= $user['is_active'] ? 'person_off' : 'person' ?></span>
                                        </button>
                                    </form>
                                    <!-- Delete -->
                                    <form method="POST" action="" onsubmit="return confirm('Permanently delete this user?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_action" value="delete">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="p-xs hover:bg-error-container hover:text-error rounded text-on-surface-variant transition-colors">
                                            <span class="material-symbols-outlined text-[20px]">delete</span>
                                        </button>
                                    </form>
                                    <?php endif; ?>
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
            <h3 class="text-on-primary font-h3 text-h3">Add New User</h3>
            <button onclick="closeModal('add-modal')" class="text-on-primary opacity-70 hover:opacity-100"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" action="" class="p-lg space-y-lg">
            <?= csrf_field() ?><input type="hidden" name="_action" value="add">
            <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Full Name *</label>
                <input type="text" name="name" required class="w-full border border-outline-variant rounded px-md py-sm outline-none" placeholder="e.g. Ahmed Khan"></div>
            <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Email *</label>
                <input type="email" name="email" required class="w-full border border-outline-variant rounded px-md py-sm outline-none" placeholder="user@hydrologic.io"></div>
            <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Password * (min 6 chars)</label>
                <input type="password" name="password" required minlength="6" class="w-full border border-outline-variant rounded px-md py-sm outline-none"></div>
            <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Role *</label>
                <select name="role" class="w-full border border-outline-variant rounded px-md py-sm outline-none">
                    <option value="Operator">Operator</option><option value="Admin">Admin</option>
                </select>
            </div>
            <div class="flex gap-md pt-md">
                <button type="button" onclick="closeModal('add-modal')" class="flex-1 px-lg py-md border border-outline-variant rounded font-semibold hover:bg-surface-container-low">Cancel</button>
                <button type="submit" class="flex-1 px-lg py-md bg-secondary text-on-primary rounded font-semibold hover:opacity-90">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div id="edit-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-primary/40 backdrop-blur-sm hidden">
    <div class="bg-surface w-full max-w-lg rounded-xl shadow-2xl border border-outline-variant overflow-hidden">
        <div class="bg-primary p-lg flex justify-between items-center">
            <h3 class="text-on-primary font-h3 text-h3">Edit User</h3>
            <button onclick="closeModal('edit-modal')" class="text-on-primary opacity-70 hover:opacity-100"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" action="" class="p-lg space-y-lg">
            <?= csrf_field() ?><input type="hidden" name="_action" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Full Name *</label>
                <input type="text" name="name" id="edit-name" required class="w-full border border-outline-variant rounded px-md py-sm outline-none"></div>
            <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Email *</label>
                <input type="email" name="email" id="edit-email" required class="w-full border border-outline-variant rounded px-md py-sm outline-none"></div>
            <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">New Password (leave blank to keep current)</label>
                <input type="password" name="new_password" minlength="6" class="w-full border border-outline-variant rounded px-md py-sm outline-none" placeholder="••••••"></div>
            <div><label class="block text-label-sm text-on-surface-variant mb-xs uppercase">Role *</label>
                <select name="role" id="edit-role" class="w-full border border-outline-variant rounded px-md py-sm outline-none">
                    <option value="Operator">Operator</option><option value="Admin">Admin</option>
                </select>
            </div>
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
function openEditModal(u){
    document.getElementById('edit-id').value   =u.id;
    document.getElementById('edit-name').value =u.name;
    document.getElementById('edit-email').value=u.email;
    document.getElementById('edit-role').value =u.role;
    openModal('edit-modal');
}
</script>
