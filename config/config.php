<?php
declare(strict_types=1);

ob_start();

session_start();

// ============================================================
// LOAD LOCAL SECRETS (dev only — never committed to git)
// ============================================================
if (is_file(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// ============================================================
// TIMEZONE
// ============================================================
const APP_TIMEZONE   = 'Asia/Singapore';
const APP_UTC_OFFSET = '+08:00';

date_default_timezone_set(APP_TIMEZONE);

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================================
// GROQ AI API SETTINGS
// ============================================================
const AI_API_URL = 'https://api.groq.com/openai/v1/chat/completions';
const AI_MODEL   = 'openai/gpt-oss-120b';

// Read from env var (Railway) or config.local.php (local dev).
define('AI_API_KEY', getenv('AI_API_KEY') !== false ? getenv('AI_API_KEY') : '');

// ============================================================
// GMAIL SMTP SETTINGS
// ============================================================
const SMTP_HOST      = 'smtp.gmail.com';
const SMTP_PORT      = 587;
const SMTP_SECURE    = 'tls';
const SMTP_USER      = 'lancetejero05@gmail.com';
const SMTP_FROM      = 'lancetejero05@gmail.com';
const SMTP_FROM_NAME = 'KLPS';

define('SMTP_PASS', getenv('SMTP_PASS') !== false ? getenv('SMTP_PASS') : '');

// ============================================================
// DATABASE
// ============================================================
function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = getenv('MYSQLHOST')     ?: '127.0.0.1';
    $port = getenv('MYSQLPORT')     ?: '3306';
    $name = getenv('MYSQLDATABASE') ?: 'klps';
    $user = getenv('MYSQLUSER')     ?: 'root';
    $pass = getenv('MYSQLPASSWORD') ?: '';

    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        die("Database Error: " . $e->getMessage());
    }

    try {
        $pdo->exec("SET time_zone = '" . APP_UTC_OFFSET . "'");
    } catch (PDOException $e) {
        error_log('Could not set MySQL session time_zone: ' . $e->getMessage());
    }

    return $pdo;
}

// ============================================================
// ASSET PATHS
// ============================================================
function asset(string $path): string {
    static $base = null;
    if ($base === null) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
        $dir    = rtrim(str_replace('\\', '/', dirname($script)), '/');
        $base   = in_array(basename($dir), ['admin', 'employee'], true)
            ? rtrim(str_replace('\\', '/', dirname($dir)), '/')
            : $dir;
        if ($base === '/' || $base === '.') {
            $base = '';
        }
    }
    $path  = ltrim($path, '/');
    $url   = $base . '/assets/' . $path;
    $file  = __DIR__ . '/../assets/' . $path;
    if (is_file($file)) {
        $url .= '?v=' . filemtime($file);
    }
    return $url;
}

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function app_url(string $path = ''): string {
    static $base = null;
    if ($base === null) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
        $dir    = rtrim(str_replace('\\', '/', dirname($script)), '/');
        $base   = in_array(basename($dir), ['admin', 'employee'], true)
            ? rtrim(str_replace('\\', '/', dirname($dir)), '/')
            : $dir;
        if ($base === '/' || $base === '.') {
            $base = '';
        }
    }
    return $base . '/' . ltrim($path, '/');
}

function id_param(mixed $value): string {
    $value = trim((string) ($value ?? ''));
    return ($value === '' || $value === '0') ? '' : $value;
}

// ============================================================
// READABLE STRING IDs
// ============================================================
const ID_TABLES = [
    'departments'          => ['D',  'departments_id'],
    'users'                => ['U',  'users_id'],
    'knowledge_categories' => ['C',  'knowledge_categories_id'],
    'knowledge'            => ['K',  'knowledge_id'],
    'ai_conversations'     => ['CV', 'ai_conversations_id'],
    'ai_messages'          => ['M',  'ai_messages_id'],
    'notifications'        => ['N',  'notifications_id'],
    'knowledge_comments'   => ['CM', 'knowledge_comments_id'],
    'knowledge_saves'      => ['S',  'knowledge_saves_id'],
    'knowledge_views'      => ['V',  'knowledge_views_id'],
    'knowledge_votes'      => ['VT', 'knowledge_votes_id'],
    'activity_logs'        => ['AL', 'activity_logs_id'],
    'email_otp'            => ['OTP', 'id'],
];

function new_id(string $table, int $width = 3): string {
    if (!isset(ID_TABLES[$table])) {
        throw new InvalidArgumentException("Unknown ID table: $table");
    }
    [$prefix, $column] = ID_TABLES[$table];
    $pdo = db();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $sql = "SELECT MAX(CAST(SUBSTRING(`$column`, " . (strlen($prefix) + 1) . ") AS UNSIGNED))
                FROM `$table`
                WHERE `$column` REGEXP ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['^' . $prefix . '[0-9]+$']);
        $next = (int) $stmt->fetchColumn() + 1;
        $id = $prefix . str_pad((string) $next, $width, '0', STR_PAD_LEFT);

        $check = $pdo->prepare("SELECT 1 FROM `$table` WHERE `$column` = ?");
        $check->execute([$id]);
        if (!$check->fetchColumn()) {
            return $id;
        }
    }
    throw new RuntimeException("Could not allocate a new ID for $table");
}

function klps_time_ago(?string $ts): string {
    if (!$ts) return '';
    $t = strtotime($ts);
    if ($t === false) return '';
    $diff = time() - $t;
    if ($diff < 0)    return 'just now';
    if ($diff < 60)   return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    if ($diff < 172800) return 'yesterday';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M j, Y', $t);
}

function klps_presence(?string $lastSeen, bool $signedIn = false): array {
    $t = $lastSeen ? strtotime($lastSeen) : false;
    if ($t === false) return ['state' => 'never', 'label' => 'Never signed in', 'minutes' => null];

    $mins = max(0, (int) floor((time() - $t) / 60));

    if ($signedIn || $mins < 1) {
        return ['state' => 'online', 'label' => 'Active just now', 'minutes' => 0];
    }
    if ($mins < 60) {
        return ['state' => 'recent', 'label' => 'Active ' . $mins . ' min ago', 'minutes' => $mins];
    }
    $hours = (int) floor($mins / 60);
    if ($hours < 24) {
        return ['state' => 'today', 'label' => 'Active ' . $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago', 'minutes' => $mins];
    }
    $days = (int) floor($hours / 24);
    if ($days <= 7) {
        return ['state' => 'old', 'label' => 'Active ' . $days . ' day' . ($days === 1 ? '' : 's') . ' ago', 'minutes' => $mins];
    }
    return ['state' => 'stale', 'label' => 'Last seen ' . date('M j, Y', $t), 'minutes' => $mins];
}

function klps_touch_presence(string $userId): void {
    if ($userId === '') return;
    try {
        db()->prepare('UPDATE users SET last_seen = NOW() WHERE users_id = ?')->execute([$userId]);
    } catch (PDOException $e) {
    }
}

function klps_iso(?string $ts): string {
    $t = $ts ? strtotime($ts) : false;
    return ($t === false) ? '' : date('c', $t);
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
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $base = '';
        if (preg_match('#^(/[^/]+)/#', $script, $m)) {
            $base = $m[1];
        }
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Location: ' . $base . '/login.php');
        exit;
    }
    klps_touch_presence((string) ($_SESSION['user']['users_id'] ?? ''));
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

function get_departments(): array {
    try {
        return db()->query('SELECT departments_id AS id, name FROM departments ORDER BY name')->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function get_department_name(?string $departmentId): ?string {
    if (!$departmentId) return null;
    try {
        $stmt = db()->prepare('SELECT name FROM departments WHERE departments_id = ?');
        $stmt->execute([$departmentId]);
        $name = $stmt->fetchColumn();
        return $name ?: null;
    } catch (PDOException $e) {
        return null;
    }
}