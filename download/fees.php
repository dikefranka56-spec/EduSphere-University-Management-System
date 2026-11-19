<?php
$page_title = 'Fees & Invoices';
require_once __DIR__ . '/header.php';
require_role(['admin', 'student']);

$role = $_SESSION['role'];
$student = $role === 'student' ? get_student() : null;

if ($role === 'student' && !$student) {
    $_SESSION['flash'] = 'Your student profile could not be found.';
    redirect('student_dashboard.php');
}

if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_category'])) {
    csrf_check();

    $name = trim($_POST['name'] ?? '');
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $session = current_session();

    if ($name === '' || strlen($name) > 120 || $amount === false || $amount === null || $amount <= 0 || !$session) {
        $_SESSION['flash'] = 'Enter a valid fee name and amount, and configure a current session.';
        redirect('fees.php');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO fee_categories (name, amount, session_id) VALUES (?, ?, ?)"
    );
    $stmt->execute([$name, $amount, $session['id']]);

    audit('fee_category_create', 'Created fee category ' . $name);
    $_SESSION['flash'] = 'Fee category created.';
    redirect('fees.php');
}

if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invoice'])) {
    csrf_check();

    $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $dueDate = trim($_POST['due_date'] ?? '');
    $dueDate = $dueDate !== '' ? $dueDate : null;

    if (!$studentId || !$categoryId || $amount === false || $amount === null || $amount <= 0) {
        $_SESSION['flash'] = 'Enter a valid student, fee category and invoice amount.';
        redirect('fees.php');
    }

    if ($dueDate !== null) {
        $date = DateTime::createFromFormat('Y-m-d', $dueDate);
        if (!$date || $date->format('Y-m-d') !== $dueDate) {
            $_SESSION['flash'] = 'Enter a valid invoice due date.';
            redirect('fees.php');
        }
    }

    $studentCheck = $pdo->prepare("SELECT id FROM students WHERE id = ?");
    $studentCheck->execute([$studentId]);

    $categoryCheck = $pdo->prepare("SELECT id FROM fee_categories WHERE id = ?");
    $categoryCheck->execute([$categoryId]);

    if (!$studentCheck->fetchColumn() || !$categoryCheck->fetchColumn()) {
        $_SESSION['flash'] = 'The selected student or fee category does not exist.';
        redirect('fees.php');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO fee_invoices (student_id, category_id, amount, due_date)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$studentId, $categoryId, $amount, $dueDate]);

    audit('invoice_create', 'Created invoice for student ' . $studentId . ', amount ' . $amount);
    $_SESSION['flash'] = 'Invoice issued successfully.';
    redirect('fees.php');
}

if ($role === 'admin') {
    $categories = $pdo->query("SELECT * FROM fee_categories ORDER BY id DESC")->fetchAll();

    $students = $pdo->query(
        "SELECT st.id, st.matric_no, u.full_name
         FROM students st
         INNER JOIN users u ON u.id = st.user_id
         WHERE u.status = 'active'
         ORDER BY u.full_name"
    )->fetchAll();

    $invoices = $pdo->query(
        "SELECT f.*, fc.name AS category_name, u.full_name, st.matric_no
         FROM fee_invoices f
         INNER JOIN students st ON st.id = f.student_id
         INNER JOIN users u ON u.id = st.user_id
         LEFT JOIN fee_categories fc ON fc.id = f.category_id
         ORDER BY f.id DESC LIMIT 500"
    )->fetchAll();
} else {
    $categories = [];
    $students = [];

    $stmt = $pdo->prepare(
        "SELECT f.*, fc.name AS category_name
         FROM fee_invoices f
         LEFT JOIN fee_categories fc ON fc.id = f.category_id
         WHERE f.student_id = ?
         ORDER BY f.id DESC"
    );
    $stmt->execute([$student['id']]);
    $invoices = $stmt->fetchAll();
}
?>

<?php if ($role === 'admin'): ?>
<section class="card">
    <h3>Create fee category</h3>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="create_category" value="1">

        <div class="grid2">
            <div>
                <label>Fee name</label>
                <input name="name" maxlength="120" required placeholder="Tuition Fee — First Semester">
            </div>
            <div>
                <label>Default amount (₦)</label>
                <input name="amount" type="number" min="0.01" step="0.01" required>
            </div>
        </div>

        <br>
        <button class="btn" type="submit">Create Fee Category</button>
    </form>
</section>

<section class="card">
    <h3>Issue invoice to a student</h3>

    <?php if (!$students || !$categories): ?>
        <p>You need at least one student and one fee category before issuing an invoice.</p>
    <?php else: ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="create_invoice" value="1">

        <div class="grid2">
            <div>
                <label>Student</label>
                <select name="student_id" required>
                    <?php foreach ($students as $studentItem): ?>
                        <option value="<?= (int)$studentItem['id'] ?>">
                            <?= e($studentItem['full_name'] . ' — ' . $studentItem['matric_no']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Fee category</label>
                <select name="category_id" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int)$category['id'] ?>">
                            <?= e($category['name'] . ' — ₦' . number_format((float)$category['amount'], 2)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Invoice amount (₦)</label>
                <input name="amount" type="number" min="0.01" step="0.01" required>
            </div>

            <div>
                <label>Due date</label>
                <input name="due_date" type="date">
            </div>
        </div>

        <br>
        <button class="btn teal" type="submit">Issue Invoice</button>
    </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Invoice</th>
                <?php if ($role === 'admin'): ?><th>Student</th><?php endif; ?>
                <th>Category</th>
                <th>Amount</th>
                <th>Paid</th>
                <th>Balance</th>
                <th>Due Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$invoices): ?>
            <tr><td colspan="<?= $role === 'admin' ? 8 : 7 ?>">No invoices found.</td></tr>
        <?php else: ?>
            <?php foreach ($invoices as $invoice): ?>
                <tr>
                    <td>#<?= (int)$invoice['id'] ?></td>

                    <?php if ($role === 'admin'): ?>
                        <td><?= e($invoice['full_name'] . ' (' . $invoice['matric_no'] . ')') ?></td>
                    <?php endif; ?>

                    <td><?= e($invoice['category_name'] ?? 'General fee') ?></td>
                    <td>₦<?= number_format((float)$invoice['amount'], 2) ?></td>
                    <td>₦<?= number_format((float)$invoice['amount_paid'], 2) ?></td>
                    <td>₦<?= number_format(max(0, (float)$invoice['amount'] - (float)$invoice['amount_paid']), 2) ?></td>
                    <td><?= e($invoice['due_date'] ?? '—') ?></td>
                    <td><span class="badge"><?= e(ucfirst($invoice['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>