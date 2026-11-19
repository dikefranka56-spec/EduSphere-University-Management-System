<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| EDUSPHERE CONFIGURATION
|--------------------------------------------------------------------------
| XAMPP defaults:
| Database: edusphere
| Username: root
| Password: (blank)
|--------------------------------------------------------------------------
*/

define('DB_HOST', 'sql311.infinityfree.com');
define('DB_NAME', 'if0_43447483_edusphere');
define('DB_USER', 'if0_43447483');
define('DB_PASS', 'XuDYbCHbdDRh9FE');

define('APP_NAME', 'EduSphere');
define('BASE_URL', '');

if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log('EduSphere database error: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Check config.php and confirm that the edusphere database has been imported.');
}

/*
|--------------------------------------------------------------------------
| OUTPUT ESCAPING
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| CSRF PROTECTION
|--------------------------------------------------------------------------
*/

function csrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $submitted = $_POST['csrf'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';

    if (
        !is_string($submitted) ||
        !is_string($stored) ||
        $stored === '' ||
        !hash_equals($stored, $submitted)
    ) {
        http_response_code(419);
        exit('Security check failed. Refresh the page and try again.');
    }
}

/*
|--------------------------------------------------------------------------
| AUTHENTICATED USER
|--------------------------------------------------------------------------
*/

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function current_role(): string
{
    return (string)($_SESSION['role'] ?? '');
}

/*
|--------------------------------------------------------------------------
| AUDIT LOGGING
|--------------------------------------------------------------------------
*/

function audit(string $action, string $details = ''): void
{
    global $pdo;

    $userId = current_user_id();

    if (!$userId) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, details, ip_address)
             VALUES (?, ?, ?, ?)'
        );

        $stmt->execute([
            $userId,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (PDOException $e) {
        error_log('EduSphere audit error: ' . $e->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
| ACADEMIC SESSION AND SEMESTER
|--------------------------------------------------------------------------
*/

function current_session(): ?array
{
    global $pdo;

    $stmt = $pdo->query(
        'SELECT * FROM academic_sessions WHERE is_current = 1 ORDER BY id DESC LIMIT 1'
    );

    return $stmt->fetch() ?: null;
}

function current_semester(): ?array
{
    global $pdo;

    $stmt = $pdo->query(
        'SELECT * FROM semesters WHERE is_current = 1 ORDER BY id DESC LIMIT 1'
    );

    return $stmt->fetch() ?: null;
}

/*
|--------------------------------------------------------------------------
| STUDENT AND LECTURER PROFILES
|--------------------------------------------------------------------------
*/

function get_student(): ?array
{
    global $pdo;

    $userId = current_user_id();

    if (!$userId || current_role() !== 'student') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM students WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);

    return $stmt->fetch() ?: null;
}

function get_lecturer(): ?array
{
    global $pdo;

    $userId = current_user_id();

    if (!$userId || current_role() !== 'lecturer') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM lecturers WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);

    return $stmt->fetch() ?: null;
}

/*
|--------------------------------------------------------------------------
| GRADING
|--------------------------------------------------------------------------
*/

function grade_for(float $score): ?array
{
    global $pdo;

    if ($score < 0 || $score > 100) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM grading_rules
         WHERE ? BETWEEN min_score AND max_score
         ORDER BY min_score DESC
         LIMIT 1'
    );

    $stmt->execute([$score]);

    return $stmt->fetch() ?: null;
}

/*
|--------------------------------------------------------------------------
| NOTIFICATIONS
|--------------------------------------------------------------------------
*/

function notify(int $userId, string $message): void
{
    global $pdo;

    $stmt = $pdo->prepare(
        'INSERT INTO notifications (user_id, message) VALUES (?, ?)'
    );

    $stmt->execute([$userId, mb_substr($message, 0, 300)]);
}

/*
|--------------------------------------------------------------------------
| SAFE REDIRECT
|--------------------------------------------------------------------------
*/

function redirect(string $page): never
{
    header('Location: ' . $page);
    exit;
}
