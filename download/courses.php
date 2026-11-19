<?php
$page_title = 'Courses';
require_once __DIR__ . '/header.php';
require_role(['admin', 'lecturer']);

$role = $_SESSION['role'];
$session = current_session();

if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    csrf_check();

    $code = strtoupper(trim($_POST['code'] ?? ''));
    $title = trim($_POST['title'] ?? '');
    $credits = filter_input(INPUT_POST, 'credit_units', FILTER_VALIDATE_INT);
    $departmentId = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
    $level = filter_input(INPUT_POST, 'level', FILTER_VALIDATE_INT);
    $semesterId = !empty($_POST['semester_id']) ? (int)$_POST['semester_id'] : null;
    $description = trim($_POST['description'] ?? '');

    if ($code === '' || $title === '' || !$credits || $credits < 1 || $credits > 6 || !$departmentId || !in_array($level, [100, 200, 300, 400], true)) {
        $_SESSION['flash'] = 'Enter valid course details. Credit units must be between 1 and 6.';
        redirect('courses.php');
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO courses (code, title, credit_units, department_id, level, semester_id, description)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$code, $title, $credits, $departmentId, $level, $semesterId, $description]);

        audit('course_create', 'Created course ' . $code);
        $_SESSION['flash'] = 'Course created successfully.';
    } catch (PDOException $e) {
        $_SESSION['flash'] = 'Course could not be created. The course code may already exist or the selected department/semester is invalid.';
    }

    redirect('courses.php');
}

if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_lecturer'])) {
    csrf_check();

    $courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
    $lecturerId = filter_input(INPUT_POST, 'lecturer_id', FILTER_VALIDATE_INT);

    if (!$courseId || !$lecturerId || !$session) {
        $_SESSION['flash'] = 'Select a valid course, lecturer and current academic session.';
        redirect('courses.php');
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO course_assignments (course_id, lecturer_id, session_id)
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$courseId, $lecturerId, $session['id']]);

        audit('course_assignment', 'Assigned lecturer ' . $lecturerId . ' to course ' . $courseId);
        $_SESSION['flash'] = 'Lecturer assigned to course successfully.';
    } catch (PDOException $e) {
        $_SESSION['flash'] = 'Assignment could not be saved. Check that the course and lecturer exist and that this assignment is not already present.';
    }

    redirect('courses.php');
}

$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
$semesters = $pdo->query(
    "SELECT s.id, s.name, a.name AS session_name
     FROM semesters s INNER JOIN academic_sessions a ON a.id = s.session_id
     ORDER BY a.id DESC, s.id DESC"
)->fetchAll();
$lecturers = $pdo->query(
    "SELECT l.id, l.staff_id, u.full_name
     FROM lecturers l INNER JOIN users u ON u.id = l.user_id
     WHERE u.status = 'active' ORDER BY u.full_name"
)->fetchAll();

if ($role === 'lecturer') {
    $lecturer = get_lecturer();

    if ($lecturer && $session) {
        $stmt = $pdo->prepare(
            "SELECT c.*, d.name AS department_name
             FROM courses c
             LEFT JOIN departments d ON d.id = c.department_id
             INNER JOIN course_assignments ca ON ca.course_id = c.id
             WHERE ca.lecturer_id = ? AND ca.session_id = ?
             ORDER BY c.code"
        );
        $stmt->execute([$lecturer['id'], $session['id']]);
        $courses = $stmt->fetchAll();
    } else {
        $courses = [];
    }
} else {
    $courses = $pdo->query(
        "SELECT c.*, d.name AS department_name
         FROM courses c
         LEFT JOIN departments d ON d.id = c.department_id
         ORDER BY c.code"
    )->fetchAll();
}
?>

<?php if ($role === 'admin'): ?>
<section class="card">
    <h3>Create a course</h3>

    <?php if (!$departments): ?>
        <p>Create at least one department before adding courses.</p>
    <?php else: ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="add_course" value="1">

        <div class="grid3">
            <div><label>Course code</label><input name="code" maxlength="20" required placeholder="CSC101"></div>
            <div><label>Course title</label><input name="title" maxlength="180" required></div>
            <div><label>Credit units</label><input name="credit_units" type="number" min="1" max="6" value="3" required></div>

            <div>
                <label>Department</label>
                <select name="department_id" required>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= (int)$department['id'] ?>"><?= e($department['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Level</label>
                <select name="level">
                    <?php foreach ([100, 200, 300, 400] as $level): ?>
                        <option value="<?= $level ?>"><?= $level ?> Level</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Semester</label>
                <select name="semester_id">
                    <option value="">Not assigned</option>
                    <?php foreach ($semesters as $semester): ?>
                        <option value="<?= (int)$semester['id'] ?>">
                            <?= e($semester['session_name'] . ' — ' . $semester['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <label>Description</label>
        <textarea name="description" rows="3"></textarea>
        <br>
        <button class="btn" type="submit">Create Course</button>
    </form>
    <?php endif; ?>
</section>

<section class="card">
    <h3>Assign a lecturer</h3>

    <?php if (!$session): ?>
        <p>Set a current academic session before assigning lecturers.</p>
    <?php elseif (!$courses || !$lecturers): ?>
        <p>Create at least one course and one lecturer first.</p>
    <?php else: ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="assign_lecturer" value="1">

        <div class="grid2">
            <div>
                <label>Course</label>
                <select name="course_id" required>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= (int)$course['id'] ?>"><?= e($course['code'] . ' — ' . $course['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Lecturer</label>
                <select name="lecturer_id" required>
                    <?php foreach ($lecturers as $lecturerItem): ?>
                        <option value="<?= (int)$lecturerItem['id'] ?>">
                            <?= e($lecturerItem['full_name'] . ' (' . $lecturerItem['staff_id'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <br>
        <button class="btn teal" type="submit">Assign Lecturer</button>
    </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="table-wrap">
    <table>
        <thead>
            <tr><th>Code</th><th>Course title</th><th>Credits</th><th>Department</th><th>Level</th></tr>
        </thead>
        <tbody>
        <?php if (!$courses): ?>
            <tr><td colspan="5">No courses found.</td></tr>
        <?php else: ?>
            <?php foreach ($courses as $course): ?>
                <tr>
                    <td><?= e($course['code']) ?></td>
                    <td><?= e($course['title']) ?></td>
                    <td><?= (int)$course['credit_units'] ?></td>
                    <td><?= e($course['department_name'] ?? '-') ?></td>
                    <td><?= e($course['level']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>