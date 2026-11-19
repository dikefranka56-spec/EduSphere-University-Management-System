<?php
$page_title = 'Assessments';
require_once __DIR__ . '/header.php';
require_role(['admin', 'lecturer']);

$role = $_SESSION['role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assessment'])) {
    csrf_check();

    $courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
    $title = trim($_POST['title'] ?? '');
    $type = $_POST['type'] ?? '';
    $maxScore = filter_input(INPUT_POST, 'max_score', FILTER_VALIDATE_FLOAT);
    $weight = filter_input(INPUT_POST, 'weight', FILTER_VALIDATE_FLOAT);
    $dueDate = trim($_POST['due_date'] ?? '');
    $dueDate = $dueDate !== '' ? $dueDate : null;

    if (
        !$courseId || $title === '' || strlen($title) > 150 ||
        !in_array($type, ['CA', 'Exam', 'Assignment', 'Quiz'], true) ||
        $maxScore === false || $maxScore === null || $maxScore <= 0 ||
        $weight === false || $weight === null || $weight < 0 || $weight > 100
    ) {
        $_SESSION['flash'] = 'Enter valid assessment details. Weight must be between 0 and 100.';
        redirect('assessments.php');
    }

    if ($dueDate !== null) {
        $date = DateTime::createFromFormat('Y-m-d', $dueDate);
        if (!$date || $date->format('Y-m-d') !== $dueDate) {
            $_SESSION['flash'] = 'Enter a valid assessment due date.';
            redirect('assessments.php');
        }
    }

    $courseCheck = $pdo->prepare("SELECT id FROM courses WHERE id = ?");
    $courseCheck->execute([$courseId]);

    if (!$courseCheck->fetchColumn()) {
        $_SESSION['flash'] = 'Selected course does not exist.';
        redirect('assessments.php');
    }

    if ($role === 'lecturer') {
        $lecturer = get_lecturer();
        $session = current_session();

        if (!$lecturer || !$session) {
            $_SESSION['flash'] = 'Lecturer profile or current session is missing.';
            redirect('assessments.php');
        }

        $assigned = $pdo->prepare(
            "SELECT COUNT(*) FROM course_assignments
             WHERE course_id = ? AND lecturer_id = ? AND session_id = ?"
        );
        $assigned->execute([$courseId, $lecturer['id'], $session['id']]);

        if ((int)$assigned->fetchColumn() < 1) {
            $_SESSION['flash'] = 'You are not assigned to this course in the current academic session.';
            redirect('assessments.php');
        }
    }

    $stmt = $pdo->prepare(
        "INSERT INTO assessments (course_id, title, type, max_score, weight, due_date, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $courseId,
        $title,
        $type,
        $maxScore,
        $weight,
        $dueDate,
        $_SESSION['user_id']
    ]);

    audit('assessment_create', 'Created assessment ' . $title . ' for course ' . $courseId);
    $_SESSION['flash'] = 'Assessment created successfully.';
    redirect('assessments.php');
}

if ($role === 'admin') {
    $courses = $pdo->query("SELECT id, code, title FROM courses ORDER BY code")->fetchAll();

    $rows = $pdo->query(
        "SELECT a.*, c.code, c.title AS course_title
         FROM assessments a
         INNER JOIN courses c ON c.id = a.course_id
         ORDER BY a.id DESC"
    )->fetchAll();
} else {
    $lecturer = get_lecturer();
    $session = current_session();

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
            "SELECT DISTINCT a.*, c.code, c.title AS course_title
             FROM assessments a
             INNER JOIN courses c ON c.id = a.course_id
             INNER JOIN course_assignments ca ON ca.course_id = c.id
             WHERE ca.lecturer_id = ? AND ca.session_id = ?
             ORDER BY a.id DESC"
        );
        $stmt->execute([$lecturer['id'], $session['id']]);
        $rows = $stmt->fetchAll();
    } else {
        $courses = [];
        $rows = [];
    }
}
?>

<section class="card">
    <h3>Create assessment</h3>

    <?php if (!$courses): ?>
        <p>No eligible courses are available. Administrators must create courses and assign them to lecturers.</p>
    <?php else: ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="create_assessment" value="1">

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
                <label>Assessment title</label>
                <input name="title" maxlength="150" required placeholder="Continuous Assessment 1">
            </div>

            <div>
                <label>Assessment type</label>
                <select name="type">
                    <option value="CA">Continuous Assessment</option>
                    <option value="Exam">Examination</option>
                    <option value="Assignment">Assignment</option>
                    <option value="Quiz">Quiz</option>
                </select>
            </div>

            <div>
                <label>Maximum score</label>
                <input name="max_score" type="number" min="0.01" step="0.01" value="100" required>
            </div>

            <div>
                <label>Weight (%)</label>
                <input name="weight" type="number" min="0" max="100" step="0.01" value="30" required>
            </div>

            <div>
                <label>Due date</label>
                <input name="due_date" type="date">
            </div>
        </div>

        <br>
        <button class="btn" type="submit">Create Assessment</button>
    </form>
    <?php endif; ?>
</section>

<section class="table-wrap">
    <table>
        <thead>
            <tr><th>Course</th><th>Assessment</th><th>Type</th><th>Maximum</th><th>Weight</th><th>Due Date</th></tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="6">No assessments found.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $item): ?>
                <tr>
                    <td><?= e($item['code'] . ' — ' . $item['course_title']) ?></td>
                    <td><?= e($item['title']) ?></td>
                    <td><?= e($item['type']) ?></td>
                    <td><?= e($item['max_score']) ?></td>
                    <td><?= e($item['weight']) ?>%</td>
                    <td><?= e($item['due_date'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>