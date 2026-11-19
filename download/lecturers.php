<?php
$page_title = 'Lecturer Management';
require_once __DIR__ . '/header.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_lecturer'])) {
    csrf_check();

    $name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $departmentId = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
    $title = trim($_POST['title'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');

    if ($name === '' || strlen($name) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash'] = 'Enter a valid lecturer name and email address.';
        redirect('lecturers.php');
    }

    if (strlen($password) < 10) {
        $_SESSION['flash'] = 'Temporary password must contain at least 10 characters.';
        redirect('lecturers.php');
    }

    if (!$departmentId || strlen($title) > 40 || strlen($qualification) > 150) {
        $_SESSION['flash'] = 'Enter valid lecturer department and qualification details.';
        redirect('lecturers.php');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "INSERT INTO users (email, password_hash, role, full_name)
             VALUES (?, ?, 'lecturer', ?)"
        );
        $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $name]);

        $userId = (int)$pdo->lastInsertId();
        $staffId = 'STF/' . date('Y') . '/' . str_pad((string)$userId, 5, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare(
            "INSERT INTO lecturers (user_id, staff_id, department_id, title, qualification)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $staffId, $departmentId, $title, $qualification]);

        $pdo->commit();

        audit('lecturer_create', 'Created lecturer ' . $staffId);
        $_SESSION['flash'] = 'Lecturer account created. Staff ID: ' . $staffId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['flash'] = 'Lecturer creation failed. The email may already exist or the selected department may be invalid.';
    }

    redirect('lecturers.php');
}

$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();

$lecturers = $pdo->query(
    "SELECT l.*, u.full_name, u.email, u.phone, u.status,
            d.name AS department_name
     FROM lecturers l
     INNER JOIN users u ON u.id = l.user_id
     LEFT JOIN departments d ON d.id = l.department_id
     ORDER BY l.id DESC"
)->fetchAll();
?>

<section class="card">
    <h3>Add a lecturer</h3>

    <?php if (!$departments): ?>
        <p>Create a department before adding lecturers.</p>
    <?php else: ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="create_lecturer" value="1">

        <div class="grid3">
            <div><label>Full name</label><input name="full_name" maxlength="150" required></div>
            <div><label>Email</label><input name="email" type="email" maxlength="180" required></div>
            <div><label>Temporary password</label><input name="password" type="password" minlength="10" required></div>

            <div>
                <label>Department</label>
                <select name="department_id" required>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= (int)$department['id'] ?>"><?= e($department['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div><label>Title</label><input name="title" maxlength="40" placeholder="Dr, Mr, Professor"></div>
            <div><label>Qualification</label><input name="qualification" maxlength="150" placeholder="PhD Computer Science"></div>
        </div>

        <br>
        <button class="btn" type="submit">Create Lecturer Account</button>
    </form>
    <?php endif; ?>
</section>

<section class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Staff ID</th><th>Name</th><th>Email</th>
                <th>Department</th><th>Title</th><th>Qualification</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$lecturers): ?>
            <tr><td colspan="7">No lecturers found.</td></tr>
        <?php else: ?>
            <?php foreach ($lecturers as $lecturer): ?>
                <tr>
                    <td><?= e($lecturer['staff_id']) ?></td>
                    <td><?= e($lecturer['full_name']) ?></td>
                    <td><?= e($lecturer['email']) ?></td>
                    <td><?= e($lecturer['department_name'] ?? '—') ?></td>
                    <td><?= e($lecturer['title'] ?? '—') ?></td>
                    <td><?= e($lecturer['qualification'] ?? '—') ?></td>
                    <td><?= e(ucfirst($lecturer['status'])) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>