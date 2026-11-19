<?php
$page_title = 'Course Registration & Enrolments';
require_once __DIR__ . '/header.php';
require_role(['admin', 'student']);

$role = $_SESSION['role'];
$session = current_session();
$semester = current_semester();
$student = $role === 'student' ? get_student() : null;

if ($role === 'student' && !$student) {
    $_SESSION['flash'] = 'Your student profile could not be found. Contact the administrator.';
    redirect('student_dashboard.php');
}

/* Student course registration */
if ($role === 'student' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    csrf_check();

    $courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);

    if (!$courseId || !$session || !$semester) {
        $_SESSION['flash'] = 'A valid course, current academic session and semester are required.';
        redirect('enrollments.php');
    }

    try {
        $pdo->beginTransaction();

        $settings = $pdo->query(
            "SELECT max_credit_units, allow_registration FROM settings ORDER BY id ASC LIMIT 1"
        )->fetch();

        if (!$settings || (int)$settings['allow_registration'] !== 1 || (int)$session['registration_open'] !== 1) {
            throw new RuntimeException('Course registration is currently closed.');
        }

        $courseStmt = $pdo->prepare(
            "SELECT * FROM courses WHERE id = ? LIMIT 1"
        );
        $courseStmt->execute([$courseId]);
        $course = $courseStmt->fetch();

        if (!$course) {
            throw new RuntimeException('The selected course does not exist.');
        }

        $duplicate = $pdo->prepare(
            "SELECT COUNT(*) FROM enrollments
             WHERE student_id = ? AND course_id = ? AND session_id = ? AND semester_id = ?"
        );
        $duplicate->execute([
            $student['id'],
            $courseId,
            $session['id'],
            $semester['id']
        ]);

        if ((int)$duplicate->fetchColumn() > 0) {
            throw new RuntimeException('You have already registered for this course in this semester.');
        }

        /* Check that the student has not already registered for the course
           in another semester of the same academic session. */
        $sameSession = $pdo->prepare(
            "SELECT COUNT(*) FROM enrollments
             WHERE student_id = ? AND course_id = ? AND session_id = ?
             AND status <> 'rejected'"
        );
        $sameSession->execute([$student['id'], $courseId, $session['id']]);

        if ((int)$sameSession->fetchColumn() > 0) {
            throw new RuntimeException('This course is already on your registration for this academic session.');
        }

        /* Check prerequisite enrolment, if the course has one. */
        if (!empty($course['prerequisite_id'])) {
            $prerequisite = $pdo->prepare(
                "SELECT COUNT(*) FROM enrollments
                 WHERE student_id = ? AND course_id = ?
                 AND status = 'approved'"
            );
            $prerequisite->execute([$student['id'], $course['prerequisite_id']]);

            if ((int)$prerequisite->fetchColumn() < 1) {
                throw new RuntimeException('You must have an approved enrolment in the prerequisite course before registering for this course.');
            }
        }

        $usedStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(c.credit_units), 0)
             FROM enrollments e
             INNER JOIN courses c ON c.id = e.course_id
             WHERE e.student_id = ? AND e.session_id = ?
             AND e.status <> 'rejected'"
        );
        $usedStmt->execute([$student['id'], $session['id']]);
        $usedUnits = (int)$usedStmt->fetchColumn();

        $maxUnits = max(1, (int)$settings['max_credit_units']);
        $newUnits = (int)$course['credit_units'];

        if ($newUnits < 1) {
            throw new RuntimeException('The selected course has an invalid credit-unit value. Contact the administrator.');
        }

        if (($usedUnits + $newUnits) > $maxUnits) {
            throw new RuntimeException(
                'Credit limit exceeded. You currently have ' . $usedUnits .
                ' units, and the maximum allowed is ' . $maxUnits . '.'
            );
        }

        $insert = $pdo->prepare(
            "INSERT INTO enrollments
             (student_id, course_id, session_id, semester_id, status)
             VALUES (?, ?, ?, ?, 'pending')"
        );
        $insert->execute([
            $student['id'],
            $courseId,
            $session['id'],
            $semester['id']
        ]);

        audit('enrollment_create', 'Student ' . $student['matric_no'] . ' registered for course ' . $courseId);

        $pdo->commit();
        $_SESSION['flash'] = 'Course registration submitted. It is awaiting approval.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['flash'] = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Registration could not be completed. Check the database and try again.';
    }

    redirect('enrollments.php');
}

/* Administrator approval or rejection */
if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    csrf_check();

    $enrollmentId = filter_input(INPUT_POST, 'enrollment_id', FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? '';

    if (!$enrollmentId || !in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $_SESSION['flash'] = 'Invalid enrolment update.';
        redirect('enrollments.php');
    }

    $check = $pdo->prepare("SELECT id FROM enrollments WHERE id = ? LIMIT 1");
    $check->execute([$enrollmentId]);

    if (!$check->fetchColumn()) {
        $_SESSION['flash'] = 'The selected enrolment could not be found.';
        redirect('enrollments.php');
    }

    $update = $pdo->prepare("UPDATE enrollments SET status = ? WHERE id = ?");
    $update->execute([$status, $enrollmentId]);

    audit('enrollment_status_update', 'Enrolment ' . $enrollmentId . ' changed to ' . $status);
    $_SESSION['flash'] = 'Enrolment status updated.';
    redirect('enrollments.php');
}

if ($role === 'student') {
    $courseStmt = $pdo->query(
        "SELECT c.*, d.name AS department_name
         FROM courses c
         LEFT JOIN departments d ON d.id = c.department_id
         ORDER BY c.code"
    );
    $courses = $courseStmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT e.*, c.code, c.title, c.credit_units,
                s.name AS session_name, sem.name AS semester_name
         FROM enrollments e
         INNER JOIN courses c ON c.id = e.course_id
         LEFT JOIN academic_sessions s ON s.id = e.session_id
         LEFT JOIN semesters sem ON sem.id = e.semester_id
         WHERE e.student_id = ?
         ORDER BY e.id DESC"
    );
    $stmt->execute([$student['id']]);
    $rows = $stmt->fetchAll();

    $usedUnits = 0;
    if ($session) {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(c.credit_units), 0)
             FROM enrollments e
             INNER JOIN courses c ON c.id = e.course_id
             WHERE e.student_id = ? AND e.session_id = ?
             AND e.status <> 'rejected'"
        );
        $stmt->execute([$student['id'], $session['id']]);
        $usedUnits = (int)$stmt->fetchColumn();
    }

    $settings = $pdo->query(
        "SELECT max_credit_units, allow_registration FROM settings ORDER BY id ASC LIMIT 1"
    )->fetch();
    $maxUnits = (int)($settings['max_credit_units'] ?? 24);
    $registrationOpen = $session && $semester
        && (int)$session['registration_open'] === 1
        && (int)($settings['allow_registration'] ?? 0) === 1;
} else {
    $rows = $pdo->query(
        "SELECT e.*, c.code, c.title, c.credit_units,
                st.matric_no, u.full_name,
                s.name AS session_name, sem.name AS semester_name
         FROM enrollments e
         INNER JOIN courses c ON c.id = e.course_id
         INNER JOIN students st ON st.id = e.student_id
         INNER JOIN users u ON u.id = st.user_id
         LEFT JOIN academic_sessions s ON s.id = e.session_id
         LEFT JOIN semesters sem ON sem.id = e.semester_id
         ORDER BY e.id DESC LIMIT 500"
    )->fetchAll();
}
?>

<?php if ($role === 'student'): ?>
<section class="card">
    <h3>Register for a course</h3>

    <p class="muted">
        Academic session: <?= e($session['name'] ?? 'Not configured') ?><br>
        Semester: <?= e($semester['name'] ?? 'Not configured') ?><br>
        Credit units registered: <?= (int)$usedUnits ?> / <?= (int)$maxUnits ?>
    </p>

    <?php if (!$registrationOpen): ?>
        <div class="alert">Registration is currently closed or the academic session/semester has not been configured.</div>
    <?php elseif (!$courses): ?>
        <p>No courses are available for registration.</p>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
            <input type="hidden" name="register" value="1">

            <label>Select course</label>
            <select name="course_id" required>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= (int)$course['id'] ?>">
                        <?= e(
                            $course['code'] . ' — ' . $course['title'] .
                            ' (' . $course['credit_units'] . ' units)'
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <br><br>
            <button class="btn" type="submit">Submit Course Registration</button>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Course</th>
                <?php if ($role === 'admin'): ?><th>Student</th><?php endif; ?>
                <th>Credit Units</th>
                <th>Academic Session</th>
                <th>Semester</th>
                <th>Status</th>
                <?php if ($role === 'admin'): ?><th>Update</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="<?= $role === 'admin' ? 7 : 6 ?>">No course registrations found.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $item): ?>
                <tr>
                    <td><?= e($item['code'] . ' — ' . $item['title']) ?></td>

                    <?php if ($role === 'admin'): ?>
                        <td><?= e($item['full_name'] . ' (' . $item['matric_no'] . ')') ?></td>
                    <?php endif; ?>

                    <td><?= (int)$item['credit_units'] ?></td>
                    <td><?= e($item['session_name'] ?? '-') ?></td>
                    <td><?= e($item['semester_name'] ?? '-') ?></td>
                    <td><span class="badge"><?= e(ucfirst($item['status'])) ?></span></td>

                    <?php if ($role === 'admin'): ?>
                        <td>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                                <input type="hidden" name="update_status" value="1">
                                <input type="hidden" name="enrollment_id" value="<?= (int)$item['id'] ?>">

                                <select name="status">
                                    <option value="pending" <?= $item['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="approved" <?= $item['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                                    <option value="rejected" <?= $item['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                </select>

                                <button class="btn sm" type="submit">Update</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>