<?php
$page_title = 'Academic Sessions';
require_once __DIR__ . '/header.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_session'])) {
    csrf_check();

    $name = trim($_POST['name'] ?? '');

    if (!preg_match('/^\d{4}\/\d{4}$/', $name)) {
        $_SESSION['flash'] = 'Enter the session in the format 2026/2027.';
        redirect('academic_sessions.php');
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO academic_sessions (name, is_current, registration_open) VALUES (?, 0, 0)");
        $stmt->execute([$name]);
        audit('session_create', 'Created academic session ' . $name);
        $_SESSION['flash'] = 'Academic session created.';
    } catch (PDOException $e) {
        $_SESSION['flash'] = 'That academic session already exists or could not be created.';
    }

    redirect('academic_sessions.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_current'])) {
    csrf_check();

    $id = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT);

    if (!$id) {
        $_SESSION['flash'] = 'Invalid academic session.';
        redirect('academic_sessions.php');
    }

    try {
        $pdo->beginTransaction();

        $check = $pdo->prepare("SELECT id FROM academic_sessions WHERE id = ? FOR UPDATE");
        $check->execute([$id]);

        if (!$check->fetchColumn()) {
            throw new RuntimeException('Academic session not found.');
        }

        $pdo->exec("UPDATE academic_sessions SET is_current = 0");
        $stmt = $pdo->prepare("UPDATE academic_sessions SET is_current = 1 WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();

        audit('session_set_current', 'Set academic session ' . $id . ' as current');
        $_SESSION['flash'] = 'Current academic session updated.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['flash'] = $e instanceof RuntimeException ? $e->getMessage() : 'Could not update the current session.';
    }

    redirect('academic_sessions.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_registration'])) {
    csrf_check();

    $id = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT);
    $open = isset($_POST['registration_open']) && $_POST['registration_open'] === '1' ? 1 : 0;

    if (!$id) {
        $_SESSION['flash'] = 'Invalid session.';
        redirect('academic_sessions.php');
    }

    $stmt = $pdo->prepare("UPDATE academic_sessions SET registration_open = ? WHERE id = ?");
    $stmt->execute([$open, $id]);

    audit('registration_window_update', 'Session ' . $id . ', registration open: ' . $open);
    $_SESSION['flash'] = 'Registration setting updated.';
    redirect('academic_sessions.php');
}

$rows = $pdo->query("SELECT * FROM academic_sessions ORDER BY id DESC")->fetchAll();
?>

<section class="card">
    <h3>Create academic session</h3>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="add_session" value="1">
        <label>Academic session</label>
        <input name="name" required placeholder="2026/2027">
        <br><br>
        <button class="btn" type="submit">Create Session</button>
    </form>
</section>

<section class="table-wrap">
    <table>
        <thead>
            <tr><th>Session</th><th>Current</th><th>Registration</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="4">No academic sessions have been created.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $item): ?>
                <tr>
                    <td><?= e($item['name']) ?></td>
                    <td><?= $item['is_current'] ? 'Current session' : '—' ?></td>
                    <td><?= $item['registration_open'] ? 'Open' : 'Closed' ?></td>
                    <td>
                        <form method="post" style="display:inline-block">
                            <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                            <input type="hidden" name="set_current" value="1">
                            <input type="hidden" name="session_id" value="<?= (int)$item['id'] ?>">
                            <button class="btn sm" type="submit">Set Current</button>
                        </form>

                        <form method="post" style="display:inline-block">
                            <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                            <input type="hidden" name="toggle_registration" value="1">
                            <input type="hidden" name="session_id" value="<?= (int)$item['id'] ?>">
                            <input type="hidden" name="registration_open" value="<?= $item['registration_open'] ? '0' : '1' ?>">
                            <button class="btn sm ghost" type="submit">
                                <?= $item['registration_open'] ? 'Close Registration' : 'Open Registration' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>