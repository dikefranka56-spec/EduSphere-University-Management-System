<?php
$page_title = 'Faculty Management';
require_once __DIR__ . '/header.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_faculty'])) {
    csrf_check();

    $name = trim($_POST['name'] ?? '');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $description = trim($_POST['description'] ?? '');

    if ($name === '' || strlen($name) > 150 || $code === '' || strlen($code) > 20) {
        $_SESSION['flash'] = 'Enter a valid faculty name and code.';
        redirect('faculties.php');
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO faculties (name, code, description)
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$name, $code, $description]);

        audit('faculty_create', 'Created faculty ' . $name);
        $_SESSION['flash'] = 'Faculty created successfully.';
    } catch (PDOException $e) {
        $_SESSION['flash'] = 'Faculty could not be created. The name or code may already exist.';
    }

    redirect('faculties.php');
}

$faculties = $pdo->query(
    "SELECT f.*, COUNT(d.id) AS department_count
     FROM faculties f
     LEFT JOIN departments d ON d.faculty_id = f.id
     GROUP BY f.id
     ORDER BY f.name"
)->fetchAll();
?>

<section class="card">
    <h3>Create faculty</h3>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="create_faculty" value="1">

        <div class="grid3">
            <div>
                <label>Faculty name</label>
                <input name="name" maxlength="150" required placeholder="Faculty of Science">
            </div>

            <div>
                <label>Faculty code</label>
                <input name="code" maxlength="20" required placeholder="SCI">
            </div>

            <div>
                <label>Description</label>
                <input name="description" placeholder="Faculty overview">
            </div>
        </div>

        <br>
        <button class="btn" type="submit">Create Faculty</button>
    </form>
</section>

<section class="table-wrap">
    <table>
        <thead>
            <tr><th>Faculty</th><th>Code</th><th>Description</th><th>Departments</th></tr>
        </thead>
        <tbody>
        <?php if (!$faculties): ?>
            <tr><td colspan="4">No faculties have been created.</td></tr>
        <?php else: ?>
            <?php foreach ($faculties as $faculty): ?>
                <tr>
                    <td><?= e($faculty['name']) ?></td>
                    <td><?= e($faculty['code'] ?? '—') ?></td>
                    <td><?= e($faculty['description'] ?? '—') ?></td>
                    <td><?= (int)$faculty['department_count'] ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>