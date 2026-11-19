<?php
$page_title = 'Student Dashboard';
require_once __DIR__ . '/header.php';
require_role('student');

$student = get_student();

if (!$student) {
    echo '<div class="alert error">Your student profile was not found. Contact the administrator.</div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

$session = current_session();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM enrollments
     WHERE student_id = ? AND status = 'approved'"
);
$stmt->execute([$student['id']]);
$approvedCourses = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_count
     FROM attendance WHERE student_id = ?"
);
$stmt->execute([$student['id']]);
$attendance = $stmt->fetch();

$attendancePercent = (int)$attendance['total'] > 0
    ? round(((int)$attendance['present_count'] / (int)$attendance['total']) * 100, 1)
    : 0;

$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount - amount_paid), 0)
     FROM fee_invoices
     WHERE student_id = ? AND status <> 'paid'"
);
$stmt->execute([$student['id']]);
$outstandingFees = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM results
     WHERE student_id = ? AND is_published = 1"
);
$stmt->execute([$student['id']]);
$publishedResults = (int)$stmt->fetchColumn();

$departmentId = $student['department_id'] ?? 0;

$stmt = $pdo->prepare(
    "SELECT a.*, d.name AS department_name
     FROM announcements a
     LEFT JOIN departments d ON d.id = a.department_id
     WHERE a.audience IN ('all', 'students')
        OR (a.audience = 'department' AND a.department_id = ?)
     ORDER BY a.created_at DESC LIMIT 5"
);
$stmt->execute([$departmentId]);
$announcements = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT e.*, c.code, c.title, c.credit_units
     FROM enrollments e
     INNER JOIN courses c ON c.id = e.course_id
     WHERE e.student_id = ?
     ORDER BY e.id DESC LIMIT 6"
);
$stmt->execute([$student['id']]);
$recentCourses = $stmt->fetchAll();
?>

<section class="cards">
    <div class="card">
        <h3>Matriculation Number</h3>
        <div class="big"><?= e($student['matric_no']) ?></div>
        <div class="sub">Level <?= e($student['level']) ?></div>
    </div>

    <div class="card">
        <h3>Approved Courses</h3>
        <div class="big"><?= $approvedCourses ?></div>
        <div class="sub">Approved enrolments</div>
    </div>

    <div class="card">
        <h3>Attendance</h3>
        <div class="big"><?= number_format($attendancePercent, 1) ?>%</div>
        <div class="sub">Based on recorded attendance</div>
    </div>

    <div class="card">
        <h3>Published Results</h3>
        <div class="big"><?= $publishedResults ?></div>
        <div class="sub">Published assessment records</div>
    </div>

    <div class="card">
        <h3>Outstanding Fees</h3>
        <div class="big">₦<?= number_format($outstandingFees, 2) ?></div>
        <div class="sub">Unpaid invoice balance</div>
    </div>
</section>

<section class="card">
    <h3>My recent course registrations</h3>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Course</th><th>Credit Units</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php if (!$recentCourses): ?>
                <tr><td colspan="3">You have not registered for any courses yet.</td></tr>
            <?php else: ?>
                <?php foreach ($recentCourses as $course): ?>
                    <tr>
                        <td><?= e($course['code'] . ' — ' . $course['title']) ?></td>
                        <td><?= (int)$course['credit_units'] ?></td>
                        <td><span class="badge"><?= e(ucfirst($course['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a class="btn" href="enrollments.php">Manage Course Registration</a>
</section>

<section class="card">
    <h3>Latest announcements</h3>

    <?php if (!$announcements): ?>
        <p class="muted">There are no announcements available.</p>
    <?php else: ?>
        <?php foreach ($announcements as $announcement): ?>
            <article>
                <h4><?= e($announcement['title']) ?></h4>
                <p><?= nl2br(e($announcement['body'])) ?></p>
                <p class="muted"><?= e($announcement['created_at']) ?></p>
            </article>
            <hr>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>