<?php
$page_title = 'Institutional Reports';
require_once __DIR__ . '/header.php';
require_role('admin');

$departmentStats = $pdo->query(
    "SELECT d.name,
            COUNT(DISTINCT st.id) AS student_count,
            COUNT(DISTINCT l.id) AS lecturer_count
     FROM departments d
     LEFT JOIN students st ON st.department_id = d.id
     LEFT JOIN lecturers l ON l.department_id = d.id
     GROUP BY d.id, d.name
     ORDER BY student_count DESC"
)->fetchAll();

$coursePerformance = $pdo->query(
    "SELECT c.code, c.title,
            COUNT(r.id) AS assessment_records,
            ROUND(AVG(r.total), 2) AS average_percentage
     FROM courses c
     INNER JOIN enrollments e ON e.course_id = c.id
     INNER JOIN results r ON r.enrollment_id = e.id
     WHERE r.is_published = 1
     GROUP BY c.id, c.code, c.title
     ORDER BY c.code"
)->fetchAll();

$feeBalances = $pdo->query(
    "SELECT u.full_name, st.matric_no,
            SUM(f.amount - f.amount_paid) AS outstanding_balance
     FROM fee_invoices f
     INNER JOIN students st ON st.id = f.student_id
     INNER JOIN users u ON u.id = st.user_id
     WHERE f.amount > f.amount_paid
     GROUP BY st.id, u.full_name, st.matric_no
     ORDER BY outstanding_balance DESC
     LIMIT 30"
)->fetchAll();

$paymentSummary = $pdo->query(
    "SELECT method, COUNT(*) AS payment_count,
            SUM(amount) AS total_amount
     FROM payments
     GROUP BY method
     ORDER BY total_amount DESC"
)->fetchAll();

$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalLecturers = (int)$pdo->query("SELECT COUNT(*) FROM lecturers")->fetchColumn();
$totalCourses = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$totalOutstanding = (float)$pdo->query(
    "SELECT COALESCE(SUM(amount - amount_paid), 0)
     FROM fee_invoices WHERE amount > amount_paid"
)->fetchColumn();
?>

<section class="cards">
    <div class="card">
        <h3>Total Students</h3>
        <div class="big"><?= $totalStudents ?></div>
    </div>

    <div class="card">
        <h3>Total Lecturers</h3>
        <div class="big"><?= $totalLecturers ?></div>
    </div>

    <div class="card">
        <h3>Total Courses</h3>
        <div class="big"><?= $totalCourses ?></div>
    </div>

    <div class="card">
        <h3>Outstanding Fees</h3>
        <div class="big">₦<?= number_format($totalOutstanding, 2) ?></div>
    </div>
</section>

<section class="card">
    <h3>Department overview</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Department</th><th>Students</th><th>Lecturers</th></tr>
            </thead>
            <tbody>
            <?php if (!$departmentStats): ?>
                <tr><td colspan="3">No department data available.</td></tr>
            <?php else: ?>
                <?php foreach ($departmentStats as $item): ?>
                    <tr>
                        <td><?= e($item['name']) ?></td>
                        <td><?= (int)$item['student_count'] ?></td>
                        <td><?= (int)$item['lecturer_count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h3>Published assessment performance</h3>
    <p class="muted">
        These are average percentages across published assessment records.
        They are not final course grades or student CGPA figures.
    </p>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Course</th><th>Assessment records</th><th>Average percentage</th></tr>
            </thead>
            <tbody>
            <?php if (!$coursePerformance): ?>
                <tr><td colspan="3">No published assessment results are available.</td></tr>
            <?php else: ?>
                <?php foreach ($coursePerformance as $item): ?>
                    <tr>
                        <td><?= e($item['code'] . ' — ' . $item['title']) ?></td>
                        <td><?= (int)$item['assessment_records'] ?></td>
                        <td><?= number_format((float)$item['average_percentage'], 2) ?>%</td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h3>Outstanding student fee balances</h3>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Student</th><th>Matric number</th><th>Outstanding balance</th></tr>
            </thead>
            <tbody>
            <?php if (!$feeBalances): ?>
                <tr><td colspan="3">No outstanding balances.</td></tr>
            <?php else: ?>
                <?php foreach ($feeBalances as $item): ?>
                    <tr>
                        <td><?= e($item['full_name']) ?></td>
                        <td><?= e($item['matric_no']) ?></td>
                        <td>₦<?= number_format((float)$item['outstanding_balance'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h3>Recorded payments by method</h3>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Payment method</th><th>Number of records</th><th>Total recorded</th></tr>
            </thead>
            <tbody>
            <?php if (!$paymentSummary): ?>
                <tr><td colspan="3">No payment records available.</td></tr>
            <?php else: ?>
                <?php foreach ($paymentSummary as $item): ?>
                    <tr>
                        <td><?= e(ucwords(str_replace('_', ' ', $item['method']))) ?></td>
                        <td><?= (int)$item['payment_count'] ?></td>
                        <td>₦<?= number_format((float)$item['total_amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>