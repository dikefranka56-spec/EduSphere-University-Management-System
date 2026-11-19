<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/*
|--------------------------------------------------------------------------
| REQUIRE LOGIN
|--------------------------------------------------------------------------
*/

function require_login(): void
{
    if (!current_user_id()) {
        redirect('login.php');
    }

    global $pdo;

    // Confirm that the account still exists and is active.
    $stmt = $pdo->prepare(
        'SELECT id, role, status FROM users WHERE id = ? LIMIT 1'
    );

    $stmt->execute([current_user_id()]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'active') {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        redirect('login.php');
    }

    // Never rely solely on a role stored in the browser session.
    $_SESSION['role'] = $user['role'];
}

/*
|--------------------------------------------------------------------------
| REQUIRE A SPECIFIC ROLE
|--------------------------------------------------------------------------
*/

function require_role($roles): void
{
    require_login();

    $allowed = (array)$roles;

    if (!in_array(current_role(), $allowed, true)) {
        http_response_code(403);
        exit('Access denied. You do not have permission to access this page.');
    }
}

/*
|--------------------------------------------------------------------------
| REQUIRE ADMIN
|--------------------------------------------------------------------------
*/

function require_admin(): void
{
    require_role('admin');
}

/*
|--------------------------------------------------------------------------
| REQUIRE STUDENT
|--------------------------------------------------------------------------
*/

function require_student(): void
{
    require_role('student');

    if (!get_student()) {
        http_response_code(403);
        exit('Student profile not found. Contact the administrator.');
    }
}

/*
|--------------------------------------------------------------------------
| REQUIRE LECTURER
|--------------------------------------------------------------------------
*/

function require_lecturer(): void
{
    require_role('lecturer');

    if (!get_lecturer()) {
        http_response_code(403);
        exit('Lecturer profile not found. Contact the administrator.');
    }
}

/*
|--------------------------------------------------------------------------
| CHECK LECTURER COURSE ASSIGNMENT
|--------------------------------------------------------------------------
*/

function lecturer_can_access_course(int $courseId, ?int $sessionId = null): bool
{
    global $pdo;

    if (current_role() !== 'lecturer') {
        return false;
    }

    $lecturer = get_lecturer();

    if (!$lecturer) {
        return false;
    }

    if ($sessionId === null) {
        $session = current_session();

        if (!$session) {
            return false;
        }

        $sessionId = (int)$session['id'];
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM course_assignments
         WHERE course_id = ?
           AND lecturer_id = ?
           AND session_id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $courseId,
        $lecturer['id'],
        $sessionId
    ]);

    return (bool)$stmt->fetch();
}

/*
|--------------------------------------------------------------------------
| CHECK STUDENT ENROLMENT
|--------------------------------------------------------------------------
*/

function student_is_enrolled(int $courseId, ?int $sessionId = null): bool
{
    global $pdo;

    $student = get_student();

    if (!$student) {
        return false;
    }

    if ($sessionId === null) {
        $session = current_session();

        if (!$session) {
            return false;
        }

        $sessionId = (int)$session['id'];
    }

    $stmt = $pdo->prepare(
        "SELECT id
         FROM enrollments
         WHERE student_id = ?
           AND course_id = ?
           AND session_id = ?
           AND status = 'approved'
         LIMIT 1"
    );

    $stmt->execute([
        $student['id'],
        $courseId,
        $sessionId
    ]);

    return (bool)$stmt->fetch();
}
