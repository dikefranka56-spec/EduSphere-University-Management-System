<?php
$page_title = 'Results & Grading';
require_once __DIR__ . '/header.php';
require_role(['admin', 'lecturer', 'student']);

$role = $_SESSION['role'];
$rows = [];
$enrolments = [];
$assessments = [];
$student = null;
$current = current_session();

if ($role === 'student') {
    $student = get_student();

    if (!$student) {
        $_SESSION['flash'] = 'Your student profile could not be found. Contact the administrator.';
        $rows = [];
    } else {
        $stmt = $pdo->prepare(
            "SELECT r.*, c.code, c.title, c.credit_units,
                    a.title AS assessment_title, a.max_score
             FROM results r
             INNER JOIN enrollments e ON e.id = r.enrollment_id
             INNER JOIN courses c ON c.id = e.course_id
             LEFT JOIN assessments a ON a.id = r.assessment_id
             WHERE r.student_id = ? AND r.is_published = 1
             ORDER BY r.id DESC"
        );
        $stmt->execute([$student['id']]);
        $rows = $stmt->fetchAll();
    }
}

/* Only administrators and lecturers can enter results. */
if (in_array($role, ['admin', 'lecturer'], true)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_result'])) {
        csrf_check();

        $enrollmentId = filter_input(INPUT_POST, 'enrollment_id', FILTER_VALIDATE_INT);
        $assessmentId = filter_input(INPUT_POST, 'assessment_id', FILTER_VALIDATE_INT);
        $scoreInput = filter_input(INPUT_POST, 'score', FILTER_VALIDATE_FLOAT);

        if (!$enrollmentId || !$assessmentId || $scoreInput === false || $scoreInput === null || $scoreInput < 0) {
            $_SESSION['flash'] = 'Enter a valid enrolment, assessment and score.';
            redirect('results.php');
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT e.*, c.code, c.title, a.id AS assessment_id,
                        a.max_score, a.title AS assessment_title
                 FROM enrollments e
                 INNER JOIN courses c ON c.id = e.course_id
                 INNER JOIN assessments a ON a.course_id = e.course_id
                 WHERE e.id = ? AND a.id = ? AND e.status = 'approved'
                 LIMIT 1"
            );
            $stmt->execute([$enrollmentId, $assessmentId]);
            $record = $stmt->fetch();

            if (!$record) {
                throw new RuntimeException('The selected assessment does not belong to this approved enrolment.');
            }

            if ($role === 'lecturer') {
                $lecturer = get_lecturer();

                if (!$lecturer) {
                    throw new RuntimeException('Your lecturer profile could not be found.');
                }

                $check = $pdo->prepare(
                    "SELECT COUNT(*) FROM course_assignments
                     WHERE course_id = ? AND lecturer_id = ? AND session_id = ?"
                );
                $check->execute([
                    $record['course_id'],
                    $lecturer['id'],
                    $record['session_id']
                ]);

                if ((int)$check->fetchColumn() < 1) {
                    throw new RuntimeException('You are not assigned to this course for the selected academic session.');
                }
            }

            $maxScore = (float)$record['max_score'];

            if ($maxScore <= 0 || (float)$scoreInput > $maxScore) {
                throw new RuntimeException('The score cannot be greater than the assessment maximum of ' . $maxScore . '.');
            }

            $percentage = ((float)$scoreInput / $maxScore) * 100;
            $grade = grade_for($percentage);

            if (!$grade) {
                throw new RuntimeException('No grading rule is configured. Ask the administrator to configure grading rules.');
            }

            /* Lecturers can enter results, but only administrators publish them. */
            $published = ($role === 'admin' && isset($_POST['is_published'])) ? 1 : 0;

            $existing = $pdo->prepare(
                "SELECT id FROM results WHERE enrollment_id = ? AND assessment_id = ? LIMIT 1"
            );
            $existing->execute([$enrollmentId, $assessmentId]);
            $existingId = $existing->fetchColumn();

            if ($existingId) {
                if ($role === 'lecturer') {
                    $update = $pdo->prepare(
                        "UPDATE results
                         SET score = ?, total = ?, grade = ?, grade_point = ?
                         WHERE id = ?"
                    );
                    $update->execute([
                        $scoreInput,
                        $percentage,
                        $grade['grade'],
                        $grade['grade_point'],
                        $existingId
                    ]);
                } else {
                    $update = $pdo->prepare(
                        "UPDATE results
                         SET score = ?, total = ?, grade = ?, grade_point = ?, is_published = ?
                         WHERE id = ?"
                    );
                    $update->execute([
                        $scoreInput,
                        $percentage,
                        $grade['grade'],
                        $grade['grade_point'],
                        $published,
                        $existingId
                    ]);
                }

                audit('result_update', 'Updated result for enrolment ' . $enrollmentId);
            } else {
                $insert = $pdo->prepare(
                    "INSERT INTO results
                     (student_id, enrollment_id, assessment_id, score, total, grade, grade_point, is_published)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $insert->execute([
                    $record['student_id'],
                    $enrollmentId,
                    $assessmentId,
                    $scoreInput,
                    $percentage,
                    $grade['grade'],
                    $grade['grade_point'],
                    $published
                ]);

                audit('result_create', 'Created result for enrolment ' . $enrollmentId);
            }

            $pdo->commit();
            $_SESSION['flash'] = 'Result saved successfully. Grade: ' . $grade['grade'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $_SESSION['flash'] = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'The result could not be saved. Check the database configuration and try again.';
        }

        redirect('results.php');
    }

    if ($role === 'admin') {
        $enrolments = $pdo->query(
            "SELECT e.id, e.course_id, e.session_id, st.matric_no, u.full_name,
                    c.code, c.title
             FROM enrollments e
             INNER JOIN students st ON st.id = e.student_id
             INNER JOIN users u ON u.id = st.user_id
             INNER JOIN courses c ON c.id = e.course_id
             WHERE e.status = 'approved'
             ORDER BY u.full_name, c.code"
        )->fetchAll();

        $assessments = $pdo->query(
            "SELECT a.id, a.course_id, a.title, a.max_score, c.code
             FROM assessments a
             INNER JOIN courses c ON c.id = a.course_id
             ORDER BY c.code, a.title"
        )->fetchAll();

        $rows = $pdo->query(
            "SELECT r.*, c.code, c.title, u.full_name, st.matric_no,
                    a.title AS assessment_title, a.max_score
             FROM results r
             INNER JOIN enrollments e ON e.id = r.enrollment_id
             INNER JOIN courses c ON c.id = e.course_id
             INNER JOIN students st ON st.id = r.student_id
             INNER JOIN users u ON u.id = st.user_id
             LEFT JOIN assessments a ON a.id = r.assessment_id
             ORDER BY r.id DESC LIMIT 300"
        )->fetchAll();
    } else {
        $lecturer = get_lecturer();

        if ($lecturer) {
            $stmt = $pdo->prepare(
                "SELECT e.id, e.course_id, e.session_id, st.matric_no, u.full_name,
                        c.code, c.title
                 FROM enrollments e
                 INNER JOIN students st ON st.id = e.student_id
                 INNER JOIN users u ON u.id = st.user_id
                 INNER JOIN courses c ON c.id = e.course_id
                 INNER JOIN course_assignments ca
                    ON ca.course_id = e.course_id AND ca.session_id = e.session_id
                 WHERE e.status = 'approved' AND ca.lecturer_id = ?
                 ORDER BY u.full_name, c.code"
            );
            $stmt->execute([$lecturer['id']]);
            $enrolments = $stmt->fetchAll();

            $stmt = $pdo->prepare(
                "SELECT a.id, a.course_id, a.title, a.max_score, c.code
                 FROM assessments a
                 INNER JOIN courses c ON c.id = a.course_id
                 INNER JOIN course_assignments ca ON ca.course_id = a.course_id
                 WHERE ca.lecturer_id = ?
                 ORDER BY c.code, a.title"
            );
            $stmt->execute([$lecturer['id']]);
            $assessments = $stmt->fetchAll();

            $stmt = $pdo->prepare(
                "SELECT r.*, c.code, c.title, u.full_name, st.matric_no,
                        a.title AS assessment_title, a.max_score
                 FROM results r
                 INNER JOIN enrollments e ON e.id = r.enrollment_id
                 INNER JOIN courses c ON c.id = e.course_id
                 INNER JOIN students st ON st.id = r.student_id
                 INNER JOIN users u ON u.id = st.user_id
                 LEFT JOIN assessments a ON a.id = r.assessment_id
                 INNER JOIN course_assignments ca
                    ON ca.course_id = e.course_id AND ca.session_id = e.session_id
                 WHERE ca.lecturer_id = ?
                 ORDER BY r.id DESC LIMIT 300"
            );
            $stmt->execute([$lecturer['id']]);
            $rows = $stmt->fetchAll();
        }
    }
}
?>

<?php if (in_array($role, ['admin', 'lecturer'], true)): ?>
<section class="card">
    <h3>Enter or update an assessment result</h3>
    <p class="muted">
        Select an approved course enrolment and an assessment belonging to that course.
        Lecturers can enter results for their assigned courses. Administrators control publication.
    </p>

    <?php if (!$enrolments || !$assessments): ?>
        <p>No eligible enrolments or assessments are available yet. Create assessments and approve course registrations first.</p>
    <?php else: ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="save_result" value="1">

        <div class="grid3">
            <div>
                <label>Student enrolment</label>
                <select name="enrollment_id" required>
                    <?php foreach ($enrolments as $item): ?>
                        <option value="<?= (int)$item['id'] ?>">
                            <?= e($item['full_name'] . ' — ' . $item['matric_no'] . ' — ' . $item['code']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Assessment</label>
                <select name="assessment_id" required>
                    <?php foreach ($assessments as $item): ?>
                        <option value="<?= (int)$item['id'] ?>">
                            <?= e($item['code'] . ' — ' . $item['title'] . ' (Max ' . $item['max_score'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Score obtained</label>
                <input name="score" type="number" min="0" step="0.01" required placeholder="Enter score">
            </div>
        </div>

        <?php if ($role === 'admin'): ?>
            <label>
                <input type="checkbox" name="is_published" value="1" style="width:auto">
                Publish this result to the student
            </label>
        <?php else: ?>
            <p class="muted">Your submitted result will remain unpublished until an administrator publishes it.</p>
        <?php endif; ?>

        <br>
        <button class="btn" type="submit">Save Result</button>
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
                <th>Assessment</th>
                <th>Score</th>
                <th>Percentage</th>
                <th>Grade</th>
                <th>Grade Point</th>
                <?php if ($role !== 'student'): ?><th>Publication</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="<?= $role === 'student' ? 6 : 8 ?>">No results are available to display.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $item): ?>
                <tr>
                    <td><?= e($item['code'] . ' — ' . $item['title']) ?></td>
                    <?php if ($role !== 'student'): ?>
                        <td><?= e(($item['full_name'] ?? '') . ' (' . ($item['matric_no'] ?? '') . ')') ?></td>
                    <?php endif; ?>
                    <td><?= e($item['assessment_title'] ?? 'Assessment unavailable') ?></td>
                    <td><?= e($item['score']) ?> / <?= e($item['max_score'] ?? '-') ?></td>
                    <td><?= number_format((float)$item['total'], 2) ?>%</td>
                    <td><?= e($item['grade']) ?></td>
                    <td><?= e($item['grade_point']) ?></td>
                    <?php if ($role !== 'student'): ?>
                        <td><?= !empty($item['is_published']) ? 'Published' : 'Unpublished' ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>