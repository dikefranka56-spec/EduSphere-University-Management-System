<?php
$page_title = 'Attendance Management';
require_once __DIR__ . '/header.php';
require_role(['admin', 'lecturer', 'student']);

$role = $_SESSION['role'];
$session = current_session();
$student = $role === 'student' ? get_student() : null;
$rows = [];

if ($role === 'student' && !$student) {
    $_SESSION['flash'] = 'Your student profile could not be found.';
    redirect('student_dashboard.php');
}

/* Attendance entry is restricted to administrators and assigned lecturers. */
if (in_array($role, ['admin', 'lecturer'], true) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    csrf_check();

    $courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
    $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $date = $_POST['att_date'] ?? '';
    $status = $_POST['status'] ?? '';

    $validDate = DateTime::createFromFormat('Y-m-d', $date);
    $validStatuses = ['present', 'absent', 'late', 'excused'];

    if (!$courseId || !$studentId || !$session || !$validDate || $validDate->format('Y-m-d') !== $date || !in_array($status, $validStatuses, true)) {
        $_SESSION['flash'] = 'Enter valid attendance details.';
        redirect('attendance.php');
    }

    try {
        $enrolment = $pdo->prepare(
            "SELECT id FROM enrollments
             WHERE student_id = ? AND course_id = ? AND session_id = ?
             AND status = 'approved' LIMIT 1"
        );
        $enrolment->execute([$studentId, $courseId, $session['id']]);

        if (!$enrolment->fetchColumn()) {
            throw new RuntimeException('The student does not have an approved enrolment in this course for the current session.');
        }

        if ($role === 'lecturer') {
            $lecturer = get_lecturer();

            if (!$lecturer) {
                throw new RuntimeException('Lecturer profile not found.');
            }

            $assigned = $pdo->prepare(
                "SELECT COUNT(*) FROM course_assignments
                 WHERE course_id = ? AND lecturer_id = ? AND session_id = ?"
            );
            $assigned->execute([$courseId, $lecturer['id'], $session['id']]);

            if ((int)$assigned->fetchColumn() < 1) {
                throw new RuntimeException('You are not assigned to this course.');
            }
        }

        $save = $pdo->prepare(
            "INSERT INTO attendance
             (student_id, course_id, session_id, att_date, status, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), recorded_by = VALUES(recorded_by)"
        );
        $save->execute([
            $studentId,
            $courseId,
            $session['id'],
            $date,
            $status,
            $_SESSION['user_id']
        ]);

        audit('attendance_record', 'Attendance for student ' . $studentId . ', course ' . $courseId . ', date ' . $date);
        $_SESSION['flash'] = 'Attendance saved successfully.';
    } catch (Throwable $e) {
        $_SESSION['flash'] = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Attendance could not be saved. Check the database and try again.';
    }

    redirect('attendance.php');
}

/* Load courses available to the current user. */
if ($role === 'admin') {
    $courses = $pdo->query("SELECT id, code, title FROM courses ORDER BY code")->fetchAll();
    $students = $pdo->query(
        "SELECT st.id, st.matric_no, u.full_name
         FROM students st INNER JOIN users u ON u.id = st.user_id
         WHERE u.status = 'active' ORDER BY u.full_name"
    )->fetchAll();

    $rows = $pdo->query(
        "SELECT a.*, c.code, c.title, u.full_name, st.matric_no
         FROM attendance a
         INNER JOIN courses c ON c.id = a.course_id
         INNER JOIN students st ON st.id = a.student_id
         INNER JOIN users u ON u.id = st.user_id
         ORDER BY a.att_date DESC, a.id DESC LIMIT 500"
    )->fetchAll();
} elseif ($role === 'lecturer') {
    $lecturer = get_lecturer();

    if ($lecturer && $session) {
        $stmt = $pdo->prepare(
            "SELECT c.id, c.code, c.title
             FROM courses c
             INNER JOIN course_assignments ca ON ca.course_id = c.id
             WHERE ca.lecturer_id = ? AND ca.session_id = ?
             ORDER BY c.code"
        );
        $stmt->execute([$lecturer['id'], $session['id']]);
        $courses = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            "SELECT DISTINCT st.id, st.matric_no, u.full_name
             FROM students st
             INNER JOIN users u ON u.id = st.user_id
             INNER JOIN enrollments e ON e.student_id = st.id
             INNER JOIN course_assignments ca
                ON ca.course_id = e.course_id AND ca.session_id = e.session_id
             WHERE ca.lecturer_id = ? AND e.status = 'approved'
               AND e.session_id = ?
             ORDER BY u.full_name"
        );
        $stmt->execute([$lecturer['id'], $session['id']]);
        $students = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            "SELECT a.*, c.code, c.title, u.full_name, st.matric_no
             FROM attendance a
             INNER JOIN courses c ON c.id = a.course_id
             INNER JOIN students st ON st.id = a.student_id
             INNER JOIN users u ON u.id = st.user_id
             INNER JOIN course_assignments ca
                ON ca.course_id = a.course_id AND ca.session_id = a.session_id
             WHERE ca.lecturer_id = ?
             ORDER BY a.att_date DESC, a.id DESC LIMIT 500"
        );
        $stmt->execute([$lecturer['id']]);
        $rows = $stmt->fetchAll();
    } else {
        $courses = [];
        $students = [];
    }
} else {
    $stmt = $pdo->prepare(
        "SELECT a.*, c.code, c.title
         FROM attendance a
         INNER JOIN courses c ON c.id = a.course_id
         WHERE a.student_id = ?
         ORDER BY a.att_date DESC, a.id DESC"
    );
    $stmt->execute([$student['id']]);
    $rows = $stmt->fetchAll();
}

?>

<?php if (in_array($role, ['admin', 'lecturer'], true)): ?>
<section class="card">
    <h3>Record attendance</h3>

    <?php if (!$session): ?>
        <p>Configure a current academic session before recording attendance.</p>
    <?php elseif (!$courses || !$students): ?>
        <p>No eligible courses or students are available.</p>
    <?php else: ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="mark_attendance" value="1">

        <div class="grid3">
            <div>
                <label>Course</label>
                <select name="course_id" required>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= (int)$course['id'] ?>"><?= e($course['code'] . ' — ' . $course['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Student</label>
                <select name="student_id" required>
                    <?php foreach ($students as $studentItem): ?>
                        <option value="<?= (int)$studentItem['id'] ?>">
                            <?= e($studentItem['full_name'] . ' (' . $studentItem['matric_no'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Date</label>
                <input name="att_date" type="date" value="<?= e(date('Y-m-d')) ?>" required>
            </div>

            <div>
                <label>Attendance status</label>
                <select name="status" required>
                    <option value="present">Present</option>
                    <option value="absent">Absent</option>
                    <option value="late">Late</option>
                    <option value="excused">Excused</option>
                </select>
            </div>
        </div>

        <br>
        <button class="btn" type="submit">Save Attendance</button>
    </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Course</th>
                <?php if ($role !== 'student'): ?><th>Student</th><?php endif; ?>
                <th>Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="<?= $role === 'student' ? 3 : 4 ?>">No attendance records found.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $item): ?>
                <tr>
                    <td><?= e($item['code'] . ' — ' . ($item['title'] ?? '')) ?></td>
                    <?php if ($role !== 'student'): ?>
                        <td><?= e(($item['full_name'] ?? '') . ' (' . ($item['matric_no'] ?? '') . ')') ?></td>
                    <?php endif; ?>
                    <td><?= e($item['att_date']) ?></td>
                    <td><?= e(ucfirst($item['status'])) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>