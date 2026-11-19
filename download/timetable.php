<?php
$page_title = 'Academic Timetable';
require_once __DIR__ . '/header.php';
require_role(['admin', 'lecturer', 'student']);

$role = $_SESSION['role'];
$session = current_session();
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_timetable'])) {
    csrf_check();

    $courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
    $lecturerId = filter_input(INPUT_POST, 'lecturer_id', FILTER_VALIDATE_INT);
    $day = $_POST['day_of_week'] ?? '';
    $start = $_POST['start_time'] ?? '';
    $end = $_POST['end_time'] ?? '';
    $room = trim($_POST['room'] ?? '');

    if (!$courseId || !$lecturerId || !$session || !in_array($day, $days, true) || $room === '') {
        $_SESSION['flash'] = 'Enter valid timetable details and configure a current academic session.';
        redirect('timetable.php');
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end) || $start >= $end) {
        $_SESSION['flash'] = 'The timetable end time must be later than the start time.';
        redirect('timetable.php');
    }

    try {
        $assigned = $pdo->prepare(
            "SELECT COUNT(*) FROM course_assignments
             WHERE course_id = ? AND lecturer_id = ? AND session_id = ?"
        );
        $assigned->execute([$courseId, $lecturerId, $session['id']]);

        if ((int)$assigned->fetchColumn() < 1) {
            throw new RuntimeException('Assign this lecturer to the selected course for the current session before scheduling it.');
        }

        /* A time conflict exists when an existing entry starts before
           the new entry ends and ends after the new entry starts. */
        $conflict = $pdo->prepare(
            "SELECT COUNT(*) FROM timetables
             WHERE session_id = ? AND day_of_week = ?
               AND start_time < ? AND end_time > ?
               AND (room = ? OR lecturer_id = ?)"
        );
        $conflict->execute([
            $session['id'],
            $day,
            $end,
            $start,
            $room,
            $lecturerId
        ]);

        if ((int)$conflict->fetchColumn() > 0) {
            throw new RuntimeException('Scheduling conflict: this room or lecturer already has a class during that time.');
        }

        $insert = $pdo->prepare(
            "INSERT INTO timetables
             (course_id, lecturer_id, day_of_week, start_time, end_time, room, session_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $insert->execute([
            $courseId,
            $lecturerId,
            $day,
            $start,
            $end,
            $room,
            $session['id']
        ]);

        audit('timetable_create', 'Created timetable entry for course ' . $courseId);
        $_SESSION['flash'] = 'Timetable entry created successfully.';
    } catch (Throwable $e) {
        $_SESSION['flash'] = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'The timetable entry could not be saved.';
    }

    redirect('timetable.php');
}

if ($role === 'admin') {
    $courses = $pdo->query("SELECT id, code, title FROM courses ORDER BY code")->fetchAll();
    $lecturers = $pdo->query(
        "SELECT l.id, l.staff_id, u.full_name
         FROM lecturers l INNER JOIN users u ON u.id = l.user_id
         WHERE u.status = 'active' ORDER BY u.full_name"
    )->fetchAll();

    $rows = $pdo->query(
        "SELECT t.*, c.code, c.title, u.full_name, a.name AS session_name
         FROM timetables t
         INNER JOIN courses c ON c.id = t.course_id
         LEFT JOIN lecturers l ON l.id = t.lecturer_id
         LEFT JOIN users u ON u.id = l.user_id
         LEFT JOIN academic_sessions a ON a.id = t.session_id
         ORDER BY t.session_id DESC,
            FIELD(t.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),
            t.start_time"
    )->fetchAll();
} elseif ($role === 'lecturer') {
    $lecturer = get_lecturer();

    if ($lecturer && $session) {
        $stmt = $pdo->prepare(
            "SELECT t.*, c.code, c.title, a.name AS session_name
             FROM timetables t
             INNER JOIN courses c ON c.id = t.course_id
             LEFT JOIN academic_sessions a ON a.id = t.session_id
             WHERE t.lecturer_id = ? AND t.session_id = ?
             ORDER BY FIELD(t.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),
                      t.start_time"
        );
        $stmt->execute([$lecturer['id'], $session['id']]);
        $rows = $stmt->fetchAll();
    } else {
        $rows = [];
    }
} else {
    if ($student && $session) {
        $stmt = $pdo->prepare(
            "SELECT DISTINCT t.*, c.code, c.title, a.name AS session_name
             FROM timetables t
             INNER JOIN courses c ON c.id = t.course_id
             INNER JOIN enrollments e ON e.course_id = t.course_id
                AND e.session_id = t.session_id
             LEFT JOIN academic_sessions a ON a.id = t.session_id
             WHERE e.student_id = ? AND e.status = 'approved'
               AND t.session_id = ?
             ORDER BY FIELD(t.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),
                      t.start_time"
        );
        $stmt->execute([$student['id'], $session['id']]);
        $rows = $stmt->fetchAll();
    } else {
        $rows = [];
    }
}
?>

<?php if ($role === 'admin'): ?>
<section class="card">
    <h3>Create timetable entry</h3>

    <?php if (!$session): ?>
        <p>Set a current academic session before creating timetable entries.</p>
    <?php elseif (!$courses || !$lecturers): ?>
        <p>Create courses, lecturers and course assignments first.</p>
    <?php else: ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="add_timetable" value="1">

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
                <label>Lecturer</label>
                <select name="lecturer_id" required>
                    <?php foreach ($lecturers as $lecturerItem): ?>
                        <option value="<?= (int)$lecturerItem['id'] ?>"><?= e($lecturerItem['full_name'] . ' (' . $lecturerItem['staff_id'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Day</label>
                <select name="day_of_week" required>
                    <?php foreach ($days as $day): ?>
                        <option value="<?= e($day) ?>"><?= e($day) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div><label>Start time</label><input name="start_time" type="time" required></div>
            <div><label>End time</label><input name="end_time" type="time" required></div>
            <div><label>Room / Venue</label><input name="room" maxlength="60" required placeholder="Lecture Theatre 1"></div>
        </div>

        <br>
        <button class="btn" type="submit">Save Timetable Entry</button>
    </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Course</th>
                <th>Day</th>
                <th>Time</th>
                <th>Room</th>
                <?php if ($role === 'admin'): ?><th>Lecturer</th><th>Session</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="<?= $role === 'admin' ? 6 : 4 ?>">No timetable entries found.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $item): ?>
                <tr>
                    <td><?= e($item['code'] . ' — ' . $item['title']) ?></td>
                    <td><?= e($item['day_of_week']) ?></td>
                    <td><?= e(substr($item['start_time'], 0, 5) . ' – ' . substr($item['end_time'], 0, 5)) ?></td>
                    <td><?= e($item['room']) ?></td>
                    <?php if ($role === 'admin'): ?>
                        <td><?= e($item['full_name'] ?? '-') ?></td>
                        <td><?= e($item['session_name'] ?? '-') ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>