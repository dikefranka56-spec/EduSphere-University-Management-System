<?php
$page_title = 'Academic Semesters';
require_once __DIR__ . '/header.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_semester'])) {
    csrf_check();

    $sessionId = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name'] ?? '');

    if (!$sessionId || $name === '' || strlen($name) > 50) {
        $_SESSION['flash'] = 'Enter a valid session and semester name.';
        redirect('semesters.php');
    }

    $check = $pdo->prepare("SELECT id FROM academic_sessions WHERE id = ?");
    $check->execute([$sessionId]);

    if (!$check->fetchColumn()) {
        $_SESSION['flash'] = 'Selected academic session does not exist.';
        redirect('semesters.php');
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO semesters (session_id, name, is_current) VALUES (?, ?, 0)");
        $stmt->execute([$sessionId, $name]);
        audit('semester_create', 'Created semester ' . $name . ' for session ' . $sessionId);
        $_SESSION['flash'] = 'Semester created successfully.';
    } catch (PDOException $e) {
        $_SESSION['flash'] = 'Could not create the semester. Check the session and try again.';
    }

    redirect('semesters.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_current'])) {
    csrf_check();

    $semesterId = filter_input(INPUT_POST, 'semester_id', FILTER_VALIDATE_INT);

    if (!$semesterId) {
        $_SESSION['flash'] = 'Invalid semester.';
        redirect('semesters.php');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id, session_id FROM semesters WHERE id = ? FOR UPDATE");
        $stmt->execute([$semesterId]);
        $selected = $stmt->fetch();

        if (!$selected) {
            throw new RuntimeException('Semester not found.');
        }

        /* Only one current semester is maintained globally. */
        $pdo->exec("UPDATE semesters SET is_current = 0");

        $stmt = $pdo->prepare("UPDATE semesters SET is_current = 1 WHERE id = ?");
        $stmt->execute([$semesterId]);

        $pdo->commit();

        audit('semester_set_current', 'Set semester ' . $semesterId . ' as current');
        $_SESSION['flash'] = 'Current semester updated.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['flash'] = $e instanceof RuntimeException ? $e->getMessage() : 'Could not update the current semester.';
    }

    redirect('semesters.php');
}

$sessions = $pdo->query("SELECT id, name FROM academic_sessions ORDER BY id DESC")->fetchAll();

$rows = $pdo->query(
    "SELECT s.*, a.name AS session_name
     FROM semesters s
     INNER JOIN academic_sessions a ON a.id = s.session_id
     ORDER BY a.id DESC, s.id DESC"
)->fetchAll();
?>

<section class="card">
    <h3>Create semester</h3>

    <?php if (!$sessions): ?>
        <p>Create an academic session before creating a semester.</p>
    <?php else: ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="add_semester" value="1">

        <div class="grid2">
            <div>
                <label>Academic session</label>
                <select name="session_id" required>
                    <?php foreach ($sessions as $sessionItem): ?>
                        <option value="<?= (int)$sessionItem['id'] ?>"><?= e($sessionItem['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Semester name</label>
                <select name="name" required>
                    <option value="First Semester">First Semester</option>
                    <option value="Second Semester">Second Semester</option>
                    <option value="Third Semester">Third Semester</option>
                </select>
            </div>
        </div>

        <br>
        <button class="btn" type="submit">Create Semester</button>
    </form>
    <?php endif; ?>
</section>

<section class="table-wrap">
    <table>
        <thead>
            <tr><th>Semester</th><th>Academic Session</th><th>Current</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="4">No semesters have been created.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $item): ?>
                <tr>
                    <td><?= e($item['name']) ?></td>
                    <td><?= e($item['session_name']) ?></td>
                    <td><?= $item['is_current'] ? 'Current semester' : '—' ?></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                            <input type="hidden" name="set_current" value="1">
                            <input type="hidden" name="semester_id" value="<?= (int)$item['id'] ?>">
                            <button class="btn sm ghost" type="submit">Set Current</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>