<?php
$page_title = 'User Management';
$page_css = ['admin-shared.css'];
$page_js  = ['admin-shared.js', 'admin-users.js'];
require_once __DIR__ . '/../config/config.php';
require_role('admin');                         // role check BEFORE any HTML is sent
/* ---- shared helpers for admin/knowledge.php and admin/users.php ---- */
/**
 * Shared helpers for admin/knowledge.php and admin/users.php.
 * Include right after config.php + require_role(), BEFORE handling POST actions.
 * Page CSS/JS are declared via $page_css / $page_js and loaded by the partials.
 */
if (!function_exists('am_csrf')) {

    function am_csrf(): string {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf'];
    }
    function am_check_csrf(): void {
        if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
            http_response_code(419);
            exit('Your session expired. Go back, reload the page and try again.');
        }
    }
    function am_back(string $page, array $allowed): never {
        parse_str((string)($_POST['qs'] ?? ''), $q);
        $q = array_filter(array_intersect_key($q, array_flip($allowed)), fn($v) => is_string($v) && $v !== '');
        redirect($page . ($q ? '?' . http_build_query($q) : ''));
    }
    function am_log(PDO $pdo, string $uid, string $action, string $details): void {
        try { $pdo->prepare('INSERT INTO activity_logs (activity_logs_id, user_id, action, details) VALUES (?, ?, ?, ?)')->execute([new_id('activity_logs'), $uid, $action, $details]); }
        catch (PDOException $e) { error_log('activity log: ' . $e->getMessage()); }
    }
    function am_time(string $ts): string {
        $d = time() - strtotime($ts);
        if ($d < 60) return 'just now';
        if ($d < 3600) return floor($d / 60) . 'm ago';
        if ($d < 86400) return floor($d / 3600) . 'h ago';
        if ($d < 604800) return floor($d / 86400) . 'd ago';
        return date('M j, Y', strtotime($ts));
    }
    function am_avatar(?string $name, ?string $pic, int $s = 36): string {
        $st = "width:{$s}px;height:{$s}px;font-size:" . round($s * .36) . 'px';
        $f = $pic ? basename($pic) : '';
        if ($f && is_file(__DIR__ . '/../uploads/profiles/' . $f))
            return '<img class="am-av" style="' . $st . '" src="' . e('../uploads/profiles/' . $f) . '" alt="">';
        return '<span class="am-av" style="' . $st . '" aria-hidden="true">' . e(mb_strtoupper(mb_substr($name ?: '?', 0, 1))) . '</span>';
    }
    /** A state-changing action as a POST form + CSRF token (replaces GET links). */
    function am_form(string $action, array $fields, string $icon, string $label, string $cls = '', string $confirm = '', bool $disabled = false): string {
        $h = '<form method="post" class="am-inline"' . ($confirm ? ' onsubmit="return confirm(' . e(json_encode($confirm)) . ')"' : '') . '>'
           . '<input type="hidden" name="csrf" value="' . e(am_csrf()) . '">'
           . '<input type="hidden" name="action" value="' . e($action) . '">'
           . '<input type="hidden" name="qs" value="' . e($_SERVER['QUERY_STRING'] ?? '') . '">';
        foreach ($fields as $k => $v) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e((string)$v) . '">';
        return $h . '<button type="submit" class="' . e($cls) . '" title="' . e($label) . '" aria-label="' . e($label) . '"' . ($disabled ? ' disabled' : '') . '>'
             . '<i class="fas ' . e($icon) . '"></i><span class="am-lbl">' . e($label) . '</span></button></form>';
    }
    function am_flash(): void {
        foreach (['success' => 'fa-circle-check', 'error' => 'fa-circle-exclamation'] as $t => $ic) {
            if (isset($_SESSION[$t])) {
                echo '<div class="am-toast ' . $t . '" role="status"><i class="fas ' . $ic . '"></i><span>' . e($_SESSION[$t]) . '</span></div>';
                unset($_SESSION[$t]);
            }
        }
    }
}

$pdo = db();
$me  = id_param($_SESSION['user']['users_id'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    am_check_csrf();
    $action = $_POST['action'] ?? '';
    $id     = id_param($_POST['id'] ?? '');
    try {
        if ($action === 'update_user') {
            $name   = trim($_POST['full_name'] ?? '');
            $email  = trim($_POST['email'] ?? '');
            $role   = $_POST['role'] ?? 'employee';
            $dept   = id_param($_POST['department_id'] ?? '');
            $active = isset($_POST['is_active']) ? 1 : 0;
            if ($id === $me) { $role = 'admin'; $active = 1; }

            if (!$id || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['admin', 'employee'], true)) {
                $_SESSION['error'] = 'Enter a name and a valid email address.';
            } else {
                $pdo->prepare('UPDATE users SET full_name = ?, email = ?, role = ?, department_id = ?, is_active = ? WHERE users_id = ?')
                    ->execute([$name, $email, $role, $dept ?: null, $active, $id]);
                am_log($pdo, $me, 'update_user', "Updated user #{$id}");
                $_SESSION['success'] = 'User updated.';
            }
        } elseif ($action === 'toggle_active' && $id) {
            if ($id === $me) {
                $_SESSION['error'] = 'You cannot change your own status.';
            } else {
                $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE users_id = ?')->execute([$id]);
                am_log($pdo, $me, 'update_user', "Toggled active state of user #{$id}");
                $_SESSION['success'] = 'User status updated.';
            }
        } elseif ($action === 'delete' && $id) {
            if ($id === $me) {
                $_SESSION['error'] = 'You cannot delete your own account.';
            } else {
                $pdo->prepare('DELETE FROM users WHERE users_id = ?')->execute([$id]);
                am_log($pdo, $me, 'delete_user', "Deleted user #{$id}");
                $_SESSION['success'] = 'User deleted.';
            }
        }
    } catch (PDOException $e) {
        error_log('users admin: ' . $e->getMessage());
        $_SESSION['error'] = $e->getCode() === '23000'
            ? 'That email is already used by another account, or the user still has linked records.'
            : 'That change could not be saved. Try again.';
    }
    am_back('users.php', ['q', 'role', 'status']);
}

$search       = trim($_GET['q'] ?? '');
$roleFilter   = in_array($_GET['role'] ?? '', ['admin', 'employee'], true) ? $_GET['role'] : '';
$statusFilter = in_array($_GET['status'] ?? '', ['active', 'inactive'], true) ? $_GET['status'] : '';

$sql = 'SELECT u.users_id, u.full_name, u.email, u.role, u.is_active, u.created_at, u.last_seen, u.profile_picture, u.department_id,
               d.name AS department_name,
               (SELECT COUNT(*) FROM knowledge k WHERE k.created_by = u.users_id) AS article_count
         FROM users u LEFT JOIN departments d ON d.departments_id = u.department_id WHERE 1=1';
$params = [];
if ($search !== '')       { $sql .= ' AND (u.full_name LIKE ? OR u.email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($roleFilter !== '')   { $sql .= ' AND u.role = ?'; $params[] = $roleFilter; }
if ($statusFilter !== '') { $sql .= ' AND u.is_active = ' . ($statusFilter === 'active' ? '1' : '0'); }
$sql .= ' ORDER BY u.created_at DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
$departments = get_departments();

require_once __DIR__ . '/../partials/header.php';
$filtered = $search !== '' || $roleFilter !== '' || $statusFilter !== '';
?>
<div class="am-page">
    <div class="am-bg" aria-hidden="true"><i></i><i></i></div>
    <?php am_flash(); ?>

    <div class="am-head">
        <div>
            <h1><span class="am-badge"><i class="fas fa-users-gear"></i></span> User management</h1>
            <p>Edit accounts, switch them on or off, or remove them.</p>
        </div>
        <div class="am-count"><i class="fas fa-users"></i> <span><b><?= count($users) ?></b> shown</span></div>
    </div>

    <form method="get" class="am-toolbar" role="search">
        <div class="am-search">
            <i class="fas fa-search"></i>
            <input class="am-input" type="text" name="q" value="<?= e($search) ?>" placeholder="Search by name or email" aria-label="Search users">
        </div>
        <select name="role" class="am-select" aria-label="Filter by role" onchange="this.form.submit()">
            <option value="">All roles</option>
            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="employee" <?= $roleFilter === 'employee' ? 'selected' : '' ?>>Employee</option>
        </select>
        <select name="status" class="am-select" aria-label="Filter by status" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
        <button type="submit" class="am-btn primary"><i class="fas fa-filter"></i> Filter</button>
        <?php if ($filtered): ?><a href="users.php" class="am-btn"><i class="fas fa-xmark"></i> Clear</a><?php endif; ?>
    </form>

    <div class="am-tablewrap">
        <?php if ($users): ?>
        <div class="am-scroll">
            <table class="am-table">
                <thead><tr>
                    <th>User</th><th>Role</th><th class="am-hide-md">Department</th><th>Status</th>
                    <th>Presence</th>
                    <th class="am-hide-md">Articles</th><th class="am-hide-md">Joined</th><th style="text-align:right">Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($users as $u):
  $uid = $u['users_id']; $self = $uid === $me;
                    $payload = ['id' => $uid, 'full_name' => $u['full_name'], 'email' => $u['email'], 'role' => $u['role'],
                                'department_id' => $u['department_id'] ?? null, 'is_active' => (int)$u['is_active'], 'self' => $self]; ?>
                    <tr>
                        <td>
                            <div class="am-who">
                                <?= am_avatar($u['full_name'], $u['profile_picture'], 38) ?>
                                <div style="min-width:0">
                                    <span class="am-title" style="max-width:230px"><?= e($u['full_name']) ?><?= $self ? ' <small style="color:var(--cyan);font-weight:400">(you)</small>' : '' ?></span>
                                    <div class="am-sub" style="max-width:230px"><?= e($u['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="am-pill <?= $u['role'] === 'admin' ? 'admin' : 'employee' ?>"><?= e(ucfirst($u['role'])) ?></span></td>
                        <td class="am-hide-md"><?= $u['department_name'] ? '<span class="am-pill dept">' . e($u['department_name']) . '</span>' : '<span class="am-sub">—</span>' ?></td>
                        <td><span class="am-pill <?= $u['is_active'] ? 'active' : 'inactive' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td><?php
                            $pres = klps_presence($u['last_seen'] ?? null, $self);
                            if (!$u['is_active']) {
                                // A disabled account has no meaningful presence.
                                echo '<span class="am-presence is-off"><i class="am-dot"></i>Account off</span>';
                            } else {
                                echo '<span class="am-presence is-' . e($pres['state']) . '"'
                                   . ' data-pres data-ts="' . e(klps_iso($u['last_seen'] ?? null)) . '"'
                                   . ' title="' . e($u['last_seen'] ? 'Last active ' . date('M j, Y \\a\\t g:i A', strtotime($u['last_seen'])) : 'Never signed in') . '">'
                                   . '<i class="am-dot"></i>' . e($pres['label']) . '</span>';
                            }
                        ?></td>
                        <td class="am-hide-md am-num"><?= (int)$u['article_count'] ?></td>
                        <td class="am-hide-md am-sub" style="white-space:nowrap"><span class="am-ago" data-ts="<?= e(klps_iso($u['created_at'])) ?>" title="<?= e(date('M j, Y', strtotime($u['created_at']))) ?>"><?= e(am_time($u['created_at'])) ?></span></td>
                        <td>
                            <div class="am-actions">
                                <button type="button" class="am-ibtn" data-user="<?= e(json_encode($payload)) ?>" title="Edit user" aria-label="Edit <?= e($u['full_name']) ?>"><i class="fas fa-pen"></i></button>
                                <?= am_form('toggle_active', ['id' => $uid], $u['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off', $u['is_active'] ? 'Deactivate user' : 'Activate user', 'am-ibtn', 'Change this user\'s active status?', $self) ?>
                                <?= am_form('delete', ['id' => $uid], 'fa-trash', 'Delete user', 'am-ibtn danger', 'Delete this user permanently? Their articles, chats and comments are deleted too.', $self) ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="am-empty">
                <i class="fas fa-users-slash"></i>
                <h3><?= $filtered ? 'No users match' : 'No users yet' ?></h3>
                <p><?= $filtered ? 'Change or clear the filters to see more.' : 'Accounts appear here after people register.' ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit dialog -->
<div class="am-overlay" id="amEdit">
    <div class="am-modal" role="dialog" aria-modal="true" aria-labelledby="amEditTitle">
        <button type="button" class="am-x" data-close aria-label="Close">&times;</button>
        <h2 id="amEditTitle"><i class="fas fa-pen-to-square"></i> Edit user</h2>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(am_csrf()) ?>">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="qs" value="<?= e($_SERVER['QUERY_STRING'] ?? '') ?>">
            <input type="hidden" name="id" id="euId">
            <div class="am-row2">
                <div class="am-field"><label for="euName">Full name</label><input class="am-input" type="text" name="full_name" id="euName" required></div>
                <div class="am-field"><label for="euEmail">Email</label><input class="am-input" type="email" name="email" id="euEmail" required></div>
            </div>
            <div class="am-row2">
                <div class="am-field"><label for="euRole">Role</label>
                    <select class="am-select" name="role" id="euRole"><option value="employee">Employee</option><option value="admin">Admin</option></select></div>
                <div class="am-field"><label for="euDept">Department</label>
                    <select class="am-select" name="department_id" id="euDept">
                        <option value="">None</option>
                        <?php foreach ($departments as $d): ?><option value="<?= e($d['id']) ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
                    </select></div>
            </div>
            <div class="am-field">
                <div class="am-check"><input type="checkbox" name="is_active" id="euActive" value="1"><label for="euActive">Account is active</label></div>
                <p class="am-note" id="euSelfNote" hidden>This is your account, so it always stays an active admin.</p>
            </div>
            <div class="am-modal-actions">
                <button type="button" class="am-btn" data-close>Cancel</button>
                <button type="submit" class="am-btn primary"><i class="fas fa-floppy-disk"></i> Save changes</button>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>