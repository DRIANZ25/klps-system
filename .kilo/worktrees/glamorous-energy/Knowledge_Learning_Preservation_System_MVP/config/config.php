<?php
declare(strict_types=1);

// Start output buffering to prevent header errors
ob_start();

session_start();

const DB_HOST = '127.0.0.1';
const DB_NAME = 'klps';
const DB_USER = 'root';
const DB_PASS = '';

const AI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent';
const AI_API_KEY = 'AIzaSyCCKuXCwf_VEF6HxTezvmbVs2UIwX33p24';
const AI_MODEL = 'gemini-3.6-flash';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
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