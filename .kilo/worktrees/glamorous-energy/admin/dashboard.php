<?php
$page_title = 'Admin Dashboard';
require_once __DIR__ . '/../partials/header.php';

$pdo = db();
$stats = [];
$stats['users'] = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$stats['knowledge'] = (int)$pdo->query('SELECT COUNT(*) FROM knowledge')->fetchColumn();
$stats['conversations'] = (int)$pdo->query('SELECT COUNT(*) FROM ai_conversations')->fetchColumn();
$stats['departments'] = (int)$pdo->query('SELECT COUNT(*) FROM departments')->fetchColumn();

// Recent users
$recentUsers = $pdo->query('SELECT u.*, d.name AS department_name FROM users u LEFT JOIN departments d ON d.id = u.department_id ORDER BY u.created_at DESC LIMIT 5')->fetchAll();

// Recent knowledge
$recentKnowledge = $pdo->query('SELECT k.*, u.full_name FROM knowledge k JOIN users u ON k.created_by = u.id ORDER BY k.created_at DESC LIMIT 5')->fetchAll();

// System activity
$activityLogs = $pdo->query('SELECT al.*, u.full_name FROM activity_logs al JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 10')->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title ?? 'KLPS') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body{background:#000;position:relative;overflow:hidden}.sidebar{min-height:100vh;background:#172033}.sidebar a{color:#dbe4f0;text-decoration:none;display:block;padding:.7rem 1rem;border-radius:.4rem}.sidebar a:hover{background:#24324a}.brand{font-weight:700;color:#fff}.card{border:0;box-shadow:0 2px 12px rgba(0,0,0,.06)}
        
        /* Neural Network Background */
        .neural-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }
        
        .neural-node {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(0, 255, 136, 0.3);
            box-shadow: 0 0 15px rgba(0, 255, 255, 255, 0.2);
            animation: float 6s ease-in-out infinite;
        }
        
        .neural-node::after {
            content: '';
            position: absolute;
            width: 6px;
            height: 6px;
            background: #ffffff;
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation: pulse 2s ease-in-out infinite;
        }
        
        .neural-connection {
            position: absolute;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            pointer-events: none;
            animation: pulseConnection 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
            }
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 0.7;
            }
            50% {
                transform: translate(-50%, -50%) scale(1.3);
                opacity: 1;
            }
        }
        
        @keyframes pulseConnection {
            0%, 100% {
                opacity: 0.2;
                transform: scale(0.8);
            }
            50% {
                opacity: 0.5;
                transform: scale(1.2);
            }
        }
        
        /* Enhanced Admin Dashboard Styles */
        .stats-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 16px -4px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .stats-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .stats-icon-users {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stats-icon-knowledge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .stats-icon-conversations {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }
        
        .stats-icon-departments {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
        }
        
        .stats-label {
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 0.5rem;
        }
        
        .stats-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1;
        }
        
        .stats-trend {
            font-size: 0.875rem;
            font-weight: 500;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
        }
        
        .stats-trend.positive {
            color: #10b981;
        }
        
        .stats-trend.negative {
            color: #ef4444;
        }
        
        .stats-trend i {
            margin-right: 0.25rem;
        }
        
        .card-header {
            background: transparent;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0;
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #334155;
            display: flex;
            align-items: center;
            margin-bottom: 0;
        }
        
        .card-title i {
            margin-right: 0.5rem;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            color: #334155;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
        }
        
        .table td {
            vertical-align: middle;
            padding: 1rem;
            border-top: 1px solid #e2e8f0;
        }
        
        .table tbody tr:hover {
            background-color: #f8fafc;
        }
        
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-active {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .status-inactive {
            background-color: #fef2f2;
            color: #991b1b;
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            text-decoration: none;
            margin: 0 0.25rem;
            transition: all 0.2s ease;
        }
        
        .action-btn-view {
            background-color: #dbeafe;
            color: #2563eb;
        }
        
        .action-btn-edit {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .action-btn-delete {
            background-color: #fecaca;
            color: #991b1b;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .progress-container {
            height: 8px;
            background-color: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(to right, #667eea, #764ba2);
            border-radius: 4px;
            transition: width 0.6s ease;
        }
        
        .activity-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: flex-start;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }
        
        .activity-icon-login {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .activity-icon-create {
            background-color: #dbeafe;
            color: #2563eb;
        }
        
        .activity-icon-update {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .activity-icon-delete {
            background-color: #fecaca;
            color: #991b1b;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-user {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }
        
        .activity-action {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        
        .activity-time {
            font-size: 0.75rem;
            color: #94a3b8;
        }
        
        .btn-primary {
            background: linear-gradient(to right, #667eea, #764ba2);
            border: none;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -5px rgba(102, 126, 234, 0.4);
            background: linear-gradient(to right, #5a67d8, #6b46c1);
        }
        
        .btn-outline-primary {
            border-color: #667eea;
            color: #667eea;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            background-color: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            color: #cbd5e1;
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #334155;
        }
        
        @media (max-width: 768px) {
            .stats-row {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .table-responsive {
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }
        }
    </style>
</head>
<body>
    <!-- Neural Network Background -->
    <div class="neural-background" id="neuralBackground"></div>
    
    <div class="container-fluid">
        <div class="row">
            <aside class="col-md-2 sidebar p-3">
                <div class="brand mb-4">KLPS</div>
                <small class="text-secondary"><?= e($user['full_name']) ?></small>
                <hr class="border-secondary">
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="users.php">Users</a>
                    <a href="knowledge.php">Knowledge</a>
                <?php else: ?>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="ai_chat.php">AI Assistant</a>
                    <a href="knowledge.php">Knowledge</a>
                    <a href="chat_history.php">Chat History</a>
                    <a href="my_contributions.php">My Contributions</a>
                <?php endif; ?>
                <a href="../logout.php">Logout</a>
            </aside>
            <main class="col-md-10 p-4">
                <!-- Welcome Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h2><i class="fas fa-user-shield me-2"></i>Admin Dashboard</h2>
                                <p class="text-muted">Overview of system performance and user activity</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-icon stats-icon-users">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stats-label">Total Users</div>
                            <div class="stats-value"><?= $stats['users'] ?></div>
                            <div class="stats-trend positive">
                                <i class="fas fa-arrow-up"></i> +5% this month
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-icon stats-icon-knowledge">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="stats-label">Knowledge Articles</div>
                            <div class="stats-value"><?= $stats['knowledge'] ?></div>
                            <div class="stats-trend positive">
                                <i class="fas fa-arrow-up"></i> +12% this month
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-icon stats-icon-conversations">
                                <i class="fas fa-comments"></i>
                            </div>
                            <div class="stats-label">AI Conversations</div>
                            <div class="stats-value"><?= $stats['conversations'] ?></div>
                            <div class="stats-trend positive">
                                <i class="fas fa-arrow-up"></i> +8% this month
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-icon stats-icon-departments">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="stats-label">Departments</div>
                            <div class="stats-value"><?= $stats['departments'] ?></div>
                            <div class="stats-trend positive">
                                <i class="fas fa-arrow-up"></i> +3% this month
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="fas fa-list me-2"></i>Recent Users
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($recentUsers)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Role</th>
                                                    <th>Department</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentUsers as $userItem): ?>
                                                    <tr>
                                                        <td><?= e($userItem['full_name']) ?></td>
                                                        <td><?= e($userItem['email']) ?></td>
                                                        <td>
                                                            <span class="status-badge <?= $userItem['role'] === 'admin' ? 'status-active' : 'status-inactive' ?>">
                                                                <?= ucfirst($userItem['role']) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= e($userItem['department_name'] ?? 'N/A') ?></td>
                                                        <td>
                                                            <span class="status-badge status-active">Active</span>
                                                        </td>
                                                        <td>
                                                            <a href="#" class="action-btn action-btn-view" title="View">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="#" class="action-btn action-btn-edit" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="#" class="action-btn action-btn-delete" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <h3>No users found</h3>
                                        <p>No recent users to display.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="fas fa-chart-line me-2"></i>System Activity
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($activityLogs)): ?>
                                    <?php foreach ($activityLogs as $log): ?>
                                        <div class="activity-item">
                                            <div class="activity-icon activity-icon-<?= strtolower(str_replace(' ', '-', $log['action'])) ?>">
                                                <i class="fas fa-<?= 
                                                    ($log['action'] === 'login') ? 'sign-in-alt' :
                                                    (str_contains($log['action'], 'create')) ? 'plus' :
                                                    (str_contains($log['action'], 'update')) ? 'edit' :
                                                    (str_contains($log['action'], 'delete')) ? 'trash' :
                                                    'info-circle'
                                                ?>"></i>
                                            </div>
                                            <div class="activity-content">
                                                <div class="activity-user"><?= e($log['full_name']) ?></div>
                                                <div class="activity-action"><?= ucfirst(str_replace('_', ' ', $log['action'])) ?></div>
                                                <div class="activity-time"><?= date('M d, H:i', strtotime($log['created_at'])) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-info-circle"></i>
                                        <h3>No recent activity</h3>
                                        <p>No system activity to display.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Knowledge -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="fas fa-newspaper me-2"></i>Recent Knowledge Contributions
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($recentKnowledge)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Title</th>
                                                    <th>Author</th>
                                                    <th>Created</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentKnowledge as $item): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="truncate-text" style="max-width: 200px;"><?= e($item['title']) ?></div>
                                                        </td>
                                                        <td><?= e($item['full_name']) ?></td>
                                                        <td><?= date('M d, Y', strtotime($item['created_at'])) ?></td>
                                                        <td>
                                                            <a href="#" class="action-btn action-btn-view" title="View">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="#" class="action-btn action-btn-edit" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="#" class="action-btn action-btn-delete" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-folder-open"></i>
                                        <h3>No knowledge contributions</h3>
                                        <p>No recent knowledge contributions to display.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php require_once __DIR__ . '/../partials/footer.php'; ?>
    <script>
        // Generate neural network background
        document.addEventListener('DOMContentLoaded', function() {
            const neuralBackground = document.getElementById('neuralBackground');
            
            // Create nodes
            for (let i = 0; i < 20; i++) {
                const node = document.createElement('div');
                node.className = 'neural-node';
                node.style.left = Math.random() * 100 + '%';
                node.style.top = Math.random() * 100 + '%';
                node.style.width = (Math.random() * 8 + 4) + 'px';
                node.style.height = node.style.width;
                node.style.animationDelay = (Math.random() * 6) + 's';
                node.style.animationDuration = (Math.random() * 4 + 6) + 's';
                neuralBackground.appendChild(node);
            }
            
            // Create connections between nodes
            const nodes = neuralBackground.querySelectorAll('.neural-node');
            nodes.forEach((node, index) => {
                // Connect to 2-3 random other nodes
                const connections = Math.floor(Math.random() * 2) + 2;
                for (let j = 0; j < connections; j++) {
                    const targetIndex = Math.floor(Math.random() * nodes.length);
                    if (targetIndex !== index) {
                        const connection = document.createElement('div');
                        connection.className = 'neural-connection';
                        
                        const rect1 = node.getBoundingClientRect();
                        const rect2 = nodes[targetIndex].getBoundingClientRect();
                        
                        const x1 = rect1.left + rect1.width / 2;
                        const y1 = rect1.top + rect1.height / 2;
                        const x2 = rect2.left + rect2.width / 2;
                        const y2 = rect2.top + rect2.height / 2;
                        
                        const length = Math.sqrt(Math.pow(x2 - x1, 2) + Math.pow(y2 - y1, 2));
                        const angle = Math.atan2(y2 - y1, x2 - x1) * 180 / Math.PI;
                        
                        connection.style.position = 'absolute';
                        connection.style.width = length + 'px';
                        connection.style.height = '1px';
                        connection.style.left = x1 + 'px';
                        connection.style.top = y1 + 'px';
                        connection.style.transform = `rotate(${angle}deg)`;
                        connection.style.transformOrigin = '0 0';
                        connection.style.animationDelay = (Math.random() * 3) + 's';
                        connection.style.animationDuration = (Math.random() * 2 + 3) + 's';
                        
                        neuralBackground.appendChild(connection);
                    }
                }
            });
        });
    </script>
</body>
</html>