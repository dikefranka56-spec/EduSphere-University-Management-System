<?php
$page_title = 'Student Management';
require_once __DIR__ . '/header.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_student'])) {
    csrf_check();

    $name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $departmentId = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
    $level = filter_input(INPUT_POST, 'level', FILTER_VALIDATE_INT);
    $gender = $_POST['gender'] ?? '';

    if ($name === '' || strlen($name) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash'] = 'Enter a valid student name and email address.';
        redirect('students.php');
    }

    if (strlen($password) < 10) {
        $_SESSION['flash'] = 'Temporary password must contain at least 10 characters.';
        redirect('students.php');
    }

    if (!$departmentId || !in_array($level, [100, 200, 300, 400], true)) {
        $_SESSION['flash'] = 'Select a valid department and academic level.';
        redirect('students.php');
    }

    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $_SESSION['flash'] = 'Select a valid gender option.';
        redirect('students.php');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "INSERT INTO users (email, password_hash, role, full_name)
             VALUES (?, ?, 'student', ?)"
        );
        $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $name]);

        $userId = (int)$pdo->lastInsertId();
        $matric = 'EDU/' . date('Y') . '/' . str_pad((string)$userId, 5, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare(
            "INSERT INTO students (user_id, matric_no, department_id, level, admission_year, gender)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $matric, $departmentId, $level, (int)date('Y'), $gender]);

        $pdo->commit();

        audit('student_create', 'Created student ' . $matric);
        $_SESSION['flash'] = 'Student account created. Matriculation number: ' . $matric;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['flash'] = 'Student creation failed. The email may already exist or the selected department may be invalid.';
    }

    redirect('students.php');
}

$search = trim($_GET['q'] ?? '');
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();

$term = '%' . $search . '%';
$stmt = $pdo->prepare(
    "SELECT st.*, u.full_name, u.email, u.phone, u.status,
            d.name AS department_name
     FROM students st
     INNER JOIN users u ON u.id = st.user_id
     LEFT JOIN departments d ON d.id = st.department_id
     WHERE u.full_name LIKE ? OR st.matric_no LIKE ? OR u.email LIKE ?
     ORDER BY st.id DESC LIMIT 300"
);
$stmt->execute([$term, $term, $term]);
$students = $stmt->fetchAll();
?>

<section class="card">
    <h3>Add a student</h3>

    <?php if (!$departments): ?>
        <p>Create a department before adding students.</p>
    <?php else: ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="create_student" value="1">

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

            <div>
                <label>Level</label>
                <select name="level">
                    <option value="100">100 Level</option>
                    <option value="200">200 Level</option>
                    <option value="300">300 Level</option>
                    <option value="400">400 Level</option>
                </select>
            </div>

            <div>
                <label>Gender</label>
                <select name="gender">
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>

        <br>
        <button class="btn" type="submit">Create Student Account</button>
    </form>
    <?php endif; ?>
</section>

<section class="card">
    <h3>Find students</h3>
    <form method="get" class="searchbar">
        <input name="q" value="<?= e($search) ?>" placeholder="Search by name, matric number or email">
        <button class="btn" type="submit">Search</button>
    </form>
</section>

<section class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Matric number</th><th>Name</th><th>Email</th>
                <th>Department</th><th>Level</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$students): ?>
            <tr><td colspan="6">No students found.</td></tr>
        <?php else: ?>
            <?php foreach ($students as $student): ?>
                <tr>
                    <td><?= e($student['matric_no']) ?></td>
                    <td><?= e($student['full_name']) ?></td>
                    <td><?= e($student['email']) ?></td>
                    <td><?= e($student['department_name'] ?? '—') ?></td>
                    <td><?= e($student['level']) ?></td>
                    <td><?= e(ucfirst($student['status'])) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>