<?php
declare(strict_types=1);

// Start output buffering
ob_start();

session_start();

// ===== DETECT ENVIRONMENT =====
$isRailway = getenv('RAILWAY_ENVIRONMENT') !== false || getenv('RAILWAY_SERVICE_ID') !== false;

if ($isRailway) {
    // ===== RAILWAY PRODUCTION SETTINGS =====
    $db_host = 'mysql.railway.internal';
    $db_name = 'railway';
    $db_user = 'root';
    $db_pass = 'iIAHQDKmlhkcBtoLHozYwdLFsWioZjcy';
} else {
    // ===== LOCAL XAMPP SETTINGS =====
    $db_host = '127.0.0.1';
    $db_name = 'klps';
    $db_user = 'root';
    $db_pass = 'LanceAdrian1221';
}

// For debugging - shows errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// AI API settings
const AI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent';
const AI_API_KEY = 'AIzaSyCCKuXCwf_VEF6HxTezvmbVs2UIwX33p24';
const AI_MODEL = 'gemini-3.6-flash';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $isRailway = getenv('RAILWAY_ENVIRONMENT') !== false || getenv('RAILWAY_SERVICE_ID') !== false;
        
        if ($isRailway) {
            $host = 'mysql.railway.internal';
            $name = 'railway';
            $user = 'root';
            $pass = 'iIAHQDKmlhkcBtoLHozYwdLFsWioZjcy';
        } else {
            $host = '127.0.0.1';
            $name = 'klps';
            $user = 'root';
            $pass = 'LanceAdrian1221';
        }
        
        $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            die("Database Error: " . $e->getMessage());
        }
    }
    return $pdo;
}

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Location: ' . $url);
    exit;
}

function require_login(): void {
    if (empty($_SESSION['user'])) {
        redirect('login.php');
    }
}

function require_role(string $role): void {
    require_login();
    if ($_SESSION['user']['role'] !== $role) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function current_user(): array {
    return $_SESSION['user'] ?? [];
}
?>