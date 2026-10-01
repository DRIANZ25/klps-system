<?php
require_once __DIR__ . '/config/config.php';
if (!empty($_SESSION['user'])) {
    redirect($_SESSION['user']['role'] === 'admin' ? 'admin/dashboard.php' : 'employee/dashboard.php');
}
redirect('register.php');
?>
