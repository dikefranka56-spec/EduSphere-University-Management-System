<?php
$page_title = 'Announcements';
require_once __DIR__ . '/header.php';
require_role(['admin', 'lecturer', 'student']);

$role = $_SESSION['role'];

if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish'])) {
    csrf_check();

    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $audience = $_POST['audience'] ?? '';
    $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

    if ($title === '' || $body === '' || !in_array($audience, ['all', 'students', 'lecturers', 'department'], true)) {
        $_SESSION['flash'] = 'Enter a title, announcement message and valid audience.';
        redirect('announcements.php');
    }

    if ($audience === 'department' && !$departmentId) {
        $_SESSION['flash'] = 'Select a department for a department-specific announcement.';
        redirect('announcements.php');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO announcements (title, body, audience, department_id, created_by)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$title, $body, $audience, $departmentId, $_SESSION['user_id']]);

    audit('announcement_create', 'Published announcement: ' . $title);
    $_SESSION['flash'] = 'Announcement published successfully.';
    redirect('announcements.php');
}

$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();

if ($role === 'admin') {
    $rows = $pdo->query(
        "SELECT a.*, d.name AS department_name
         FROM announcements a
         LEFT JOIN departments d ON d.id = a.department_id
         ORDER BY a.created_at DESC"
    )->fetchAll();
} else {
    $profile = $role === 'student' ? get_student() : get_lecturer();
    $departmentId = $profile['department_id'] ?? 0;
    $audience = $role === 'student' ? 'students' : 'lecturers';

    $stmt = $pdo->prepare(
        "SELECT a.*, d.name AS department_name
         FROM announcements a
         LEFT JOIN departments d ON d.id = a.department_id
         WHERE a.audience = 'all'
            OR a.audience = ?
            OR (a.audience = 'department' AND a.department_id = ?)
         ORDER BY a.created_at DESC"
    );
    $stmt->execute([$audience, $departmentId]);
    $rows = $stmt->fetchAll();
}
?>

<?php if ($role === 'admin'): ?>
<section class="card">
    <h3>Publish an announcement</h3>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="publish" value="1">

        <label>Announcement title</label>
        <input name="title" maxlength="200" required>

        <label>Message</label>
        <textarea name="body" rows="5" required></textarea>

        <div class="grid2">
            <div>
                <label>Audience</label>
                <select name="audience" required>
                    <option value="all">Everyone</option>
                    <option value="students">Students</option>
                    <option value="lecturers">Lecturers</option>
                    <option value="department">Specific department</option>
                </select>
            </div>

            <div>
                <label>Department</label>
                <select name="department_id">
                    <option value="">Select department</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= (int)$department['id'] ?>"><?= e($department['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <br>
        <button class="btn" type="submit">Publish Announcement</button>
    </form>
</section>
<?php endif; ?>

<?php if (!$rows): ?>
    <p class="muted">There are no announcements available for your account.</p>
<?php else: ?>
    <?php foreach ($rows as $announcement): ?>
        <article class="card">
            <h3><?= e($announcement['title']) ?></h3>
            <p><?= nl2br(e($announcement['body'])) ?></p>
            <p class="muted">
                Audience: <?= e(ucfirst($announcement['audience'])) ?>
                <?php if (!empty($announcement['department_name'])): ?>
                    · Department: <?= e($announcement['department_name']) ?>
                <?php endif; ?>
                · <?= e($announcement['created_at']) ?>
            </p>
        </article>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>