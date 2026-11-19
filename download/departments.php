<?php
$page_title = 'Department Management';
require_once __DIR__ . '/header.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_department'])) {
    csrf_check();

    $facultyId = filter_input(INPUT_POST, 'faculty_id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name'] ?? '');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $description = trim($_POST['description'] ?? '');

    if (!$facultyId || $name === '' || strlen($name) > 150 || $code === '' || strlen($code) > 20) {
        $_SESSION['flash'] = 'Enter valid department details.';
        redirect('departments.php');
    }

    $facultyCheck = $pdo->prepare("SELECT id FROM faculties WHERE id = ?");
    $facultyCheck->execute([$facultyId]);

    if (!$facultyCheck->fetchColumn()) {
        $_SESSION['flash'] = 'The selected faculty does not exist.';
        redirect('departments.php');
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO departments (faculty_id, name, code, description)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$facultyId, $name, $code, $description]);

        audit('department_create', 'Created department ' . $name);
        $_SESSION['flash'] = 'Department created successfully.';
    } catch (PDOException $e) {
        $_SESSION['flash'] = 'Department could not be created. Its code may already exist.';
    }

    redirect('departments.php');
}

$faculties = $pdo->query("SELECT id, name FROM faculties ORDER BY name")->fetchAll();

$departments = $pdo->query(
    "SELECT d.*, f.name AS faculty_name,
            (SELECT COUNT(*) FROM students st WHERE st.department_id = d.id) AS student_count,
            (SELECT COUNT(*) FROM lecturers l WHERE l.department_id = d.id) AS lecturer_count
     FROM departments d
     LEFT JOIN faculties f ON f.id = d.faculty_id
     ORDER BY d.name"
)->fetchAll();
?>

<section class="card">
    <h3>Create department</h3>

    <?php if (!$faculties): ?>
        <p>Create a faculty before creating a department.</p>
    <?php else: ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="create_department" value="1">

        <div class="grid2">
            <div>
                <label>Faculty</label>
                <select name="faculty_id" required>
                    <?php foreach ($faculties as $faculty): ?>
                        <option value="<?= (int)$faculty['id'] ?>"><?= e($faculty['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Department name</label>
                <input name="name" maxlength="150" required placeholder="Computer Science">
            </div>

            <div>
                <label>Department code</label>
                <input name="code" maxlength="20" required placeholder="CSC">
            </div>

            <div>
                <label>Description</label>
                <input name="description" placeholder="Department overview">
            </div>
        </div>

        <br>
        <button class="btn" type="submit">Create Department</button>
    </form>
    <?php endif; ?>
</section>

<section class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Department</th><th>Code</th><th>Faculty</th>
                <th>Students</th><th>Lecturers</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$departments): ?>
            <tr><td colspan="5">No departments have been created.</td></tr>
        <?php else: ?>
            <?php foreach ($departments as $department): ?>
                <tr>
                    <td><?= e($department['name']) ?></td>
                    <td><?= e($department['code'] ?? '—') ?></td>
                    <td><?= e($department['faculty_name'] ?? '—') ?></td>
                    <td><?= (int)$department['student_count'] ?></td>
                    <td><?= (int)$department['lecturer_count'] ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>