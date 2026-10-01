<?php
$page_title = 'User Management';
require_once __DIR__ . '/../partials/header.php';

$stmt = db()->query('SELECT u.id,u.full_name,u.email,u.role,u.is_active,u.created_at,d.name AS department FROM users u LEFT JOIN departments d ON d.id=u.department_id ORDER BY u.created_at DESC');
$users = $stmt->fetchAll();
?>
<h1>User Management</h1>
<div class="card">
<div class="table-responsive">
<table class="table mb-0">
<thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($users as $u): ?>
<tr>
<td><?= e($u['full_name']) ?></td><td><?= e($u['email']) ?></td><td><?= e($u['role']) ?></td><td><?= e($u['department'] ?? '') ?></td>
<td><?= $u['is_active'] ? 'Active' : 'Inactive' ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
