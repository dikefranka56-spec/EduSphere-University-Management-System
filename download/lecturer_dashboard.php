<?php
$page_title = 'Lecturer Dashboard';
require_once __DIR__ . '/header.php';
require_role('lecturer');

$lecturer = get_lecturer();
$session = current_session();

if (!$lecturer) {
    echo '<div class="alert error">Your lecturer profile was not found. Contact the administrator.</div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

$courseCount = 0;
$studentCount = 0;
$assessmentCount = 0;

if ($session) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM course_assignments
         WHERE lecturer_id = ? AND session_id = ?"
    );
    $stmt->execute([$lecturer['id'], $session['id']]);
    $courseCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT e.student_id)
         FROM enrollments e
         INNER JOIN course_assignments ca
            ON ca.course_id = e.course_id AND ca.session_id = e.session_id
         WHERE ca.lecturer_id = ?
           AND e.session_id = ?
           AND e.status = 'approved'"
    );
    $stmt->execute([$lecturer['id'], $session['id']]);
    $studentCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT a.id)
         FROM assessments a
         INNER JOIN course_assignments ca ON ca.course_id = a.course_id
         WHERE ca.lecturer_id = ? AND ca.session_id = ?"
    );
    $stmt->execute([$lecturer['id'], $session['id']]);
    $assessmentCount = (int)$stmt->fetchColumn();
}
?>

<section class="cards">
    <div class="card">
        <h3>Staff ID</h3>
        <div class="big"><?= e($lecturer['staff_id']) ?></div>
    </div>

    <div class="card">
        <h3>Assigned Courses</h3>
        <div class="big"><?= $courseCount ?></div>
    </div>

    <div class="card">
        <h3>Students in My Courses</h3>
        <div class="big"><?= $studentCount ?></div>
    </div>

    <div class="card">
        <h3>Assessments</h3>
        <div class="big"><?= $assessmentCount ?></div>
    </div>
</section>

<section class="card">
    <h3>Academic information</h3>
    <p><strong>Current session:</strong> <?= e($session['name'] ?? 'Not configured') ?></p>
    <p><strong>Lecturer:</strong> <?= e($_SESSION['full_name'] ?? '') ?></p>
    <p><strong>Title:</strong> <?= e($lecturer['title'] ?? '—') ?></p>
    <p><strong>Qualification:</strong> <?= e($lecturer['qualification'] ?? '—') ?></p>
</section>

<section class="card">
    <h3>Teaching workspace</h3>
    <p>Access the tools for your assigned courses.</p>

    <p>
        <a class="btn" href="courses.php">My Courses</a>
        <a class="btn teal" href="attendance.php">Record Attendance</a>
        <a class="btn ghost" href="results.php">Manage Results</a>
        <a class="btn ghost" href="assessments.php">Assessments</a>
        <a class="btn ghost" href="timetable.php">My Timetable</a>
    </p>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>