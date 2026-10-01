<?php
$page_title = 'Knowledge Management';
require_once __DIR__ . '/../partials/header.php';

$stmt = db()->query('SELECT k.id,k.title,k.status,k.updated_at,u.full_name AS author,k.view_count
                     FROM knowledge k JOIN users u ON u.id=k.created_by ORDER BY k.updated_at DESC');
$rows = $stmt->fetchAll();
?>
<h1>Knowledge Management</h1>
<div class="card">
<div class="table-responsive">
<table class="table mb-0">
<thead><tr><th>Title</th><th>Author</th><th>Status</th><th>Views</th><th>Updated</th></tr></thead>
<tbody>
<?php foreach ($rows as $k): ?>
<tr>
<td><?= e($k['title']) ?></td><td><?= e($k['author']) ?></td><td><?= e($k['status']) ?></td><td><?= (int)$k['view_count'] ?></td><td><?= e($k['updated_at']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
