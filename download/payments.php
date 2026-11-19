<?php
$page_title = 'Payments & Receipts';
require_once __DIR__ . '/header.php';
require_role(['admin', 'student']);

$role = $_SESSION['role'];
$student = $role === 'student' ? get_student() : null;
$invoiceList = [];
$rows = [];

if ($role === 'student' && !$student) {
    $_SESSION['flash'] = 'Your student profile could not be found. Contact the administrator.';
    redirect('student_dashboard.php');
}

/* Only an administrator can record a manual payment. */
if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    csrf_check();

    $invoiceId = filter_input(INPUT_POST, 'invoice_id', FILTER_VALIDATE_INT);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $method = $_POST['method'] ?? '';
    $reference = trim($_POST['reference'] ?? '');

    if (
        !$invoiceId ||
        $amount === false ||
        $amount === null ||
        $amount <= 0 ||
        !in_array($method, ['manual', 'bank_transfer'], true) ||
        $reference === ''
    ) {
        $_SESSION['flash'] = 'Enter a valid invoice, payment amount, payment method and reference.';
        redirect('payments.php');
    }

    if (strlen($reference) > 100) {
        $_SESSION['flash'] = 'The payment reference is too long.';
        redirect('payments.php');
    }

    try {
        $pdo->beginTransaction();

        /* Lock the invoice while checking and recording the payment. */
        $stmt = $pdo->prepare(
            "SELECT * FROM fee_invoices WHERE id = ? FOR UPDATE"
        );
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch();

        if (!$invoice) {
            throw new RuntimeException('The selected invoice does not exist.');
        }

        $balance = (float)$invoice['amount'] - (float)$invoice['amount_paid'];

        if ($balance <= 0) {
            throw new RuntimeException('This invoice has already been fully paid.');
        }

        if ((float)$amount > $balance) {
            throw new RuntimeException(
                'Payment exceeds the outstanding balance of ₦' . number_format($balance, 2) . '.'
            );
        }

        $duplicate = $pdo->prepare(
            "SELECT COUNT(*) FROM payments WHERE reference = ?"
        );
        $duplicate->execute([$reference]);

        if ((int)$duplicate->fetchColumn() > 0) {
            throw new RuntimeException('This payment reference has already been used.');
        }

        $newPaid = (float)$invoice['amount_paid'] + (float)$amount;
        $newStatus = $newPaid >= (float)$invoice['amount']
            ? 'paid'
            : 'partial';

        $insert = $pdo->prepare(
            "INSERT INTO payments
             (invoice_id, student_id, amount, method, reference, gateway_status, recorded_by)
             VALUES (?, ?, ?, ?, ?, 'none', ?)"
        );
        $insert->execute([
            $invoiceId,
            $invoice['student_id'],
            $amount,
            $method,
            $reference,
            $_SESSION['user_id']
        ]);

        $update = $pdo->prepare(
            "UPDATE fee_invoices SET amount_paid = ?, status = ? WHERE id = ?"
        );
        $update->execute([$newPaid, $newStatus, $invoiceId]);

        audit(
            'payment_record',
            'Recorded ' . $method . ' payment for invoice ' . $invoiceId .
            ', amount ' . number_format((float)$amount, 2)
        );

        $pdo->commit();

        $_SESSION['flash'] = 'Payment record saved. Reference: ' . $reference .
            '. This is an administrative record, not an independently verified online gateway transaction.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['flash'] = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'The payment could not be recorded. Check the database and try again.';
    }

    redirect('payments.php');
}

/* Load only invoices belonging to the logged-in student,
   or all invoices for an administrator. */
if ($role === 'student') {
    $stmt = $pdo->prepare(
        "SELECT f.*, fc.name AS category_name
         FROM fee_invoices f
         LEFT JOIN fee_categories fc ON fc.id = f.category_id
         WHERE f.student_id = ?
         ORDER BY f.id DESC"
    );
    $stmt->execute([$student['id']]);
    $invoiceList = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT p.*, f.id AS invoice_number
         FROM payments p
         INNER JOIN fee_invoices f ON f.id = p.invoice_id
         WHERE p.student_id = ?
         ORDER BY p.id DESC"
    );
    $stmt->execute([$student['id']]);
    $rows = $stmt->fetchAll();
} else {
    $invoiceList = $pdo->query(
        "SELECT f.*, u.full_name, st.matric_no, fc.name AS category_name
         FROM fee_invoices f
         INNER JOIN students st ON st.id = f.student_id
         INNER JOIN users u ON u.id = st.user_id
         LEFT JOIN fee_categories fc ON fc.id = f.category_id
         WHERE f.amount > f.amount_paid
         ORDER BY f.id DESC LIMIT 300"
    )->fetchAll();

    $rows = $pdo->query(
        "SELECT p.*, u.full_name, st.matric_no
         FROM payments p
         INNER JOIN students st ON st.id = p.student_id
         INNER JOIN users u ON u.id = st.user_id
         ORDER BY p.id DESC LIMIT 300"
    )->fetchAll();
}
?>

<?php if ($role === 'admin'): ?>
<section class="card">
    <h3>Record a manual payment</h3>

    <p class="muted">
        This records a payment received and checked by an administrator.
        It does not connect to Paystack or another payment gateway.
        Online gateway payments must only be marked verified after a genuine provider confirmation.
    </p>

    <?php if (!$invoiceList): ?>
        <p>There are no outstanding invoices to receive payments for.</p>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
            <input type="hidden" name="record_payment" value="1">

            <div class="grid3">
                <div>
                    <label>Outstanding invoice</label>
                    <select name="invoice_id" required>
                        <?php foreach ($invoiceList as $invoice): ?>
                            <?php $balance = (float)$invoice['amount'] - (float)$invoice['amount_paid']; ?>
                            <option value="<?= (int)$invoice['id'] ?>">
                                <?= e(
                                    '#' . $invoice['id'] . ' — ' .
                                    $invoice['full_name'] . ' (' . $invoice['matric_no'] . ')' .
                                    ' — Balance ₦' . number_format($balance, 2)
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Amount received (₦)</label>
                    <input name="amount" type="number" min="0.01" step="0.01" required>
                </div>

                <div>
                    <label>Payment method</label>
                    <select name="method" required>
                        <option value="manual">Cash / Manual</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
                </div>

                <div>
                    <label>Payment reference</label>
                    <input name="reference" maxlength="100" required placeholder="Enter receipt or transfer reference">
                </div>
            </div>

            <br>
            <button class="btn" type="submit">Record Payment</button>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="card">
    <h3><?= $role === 'student' ? 'My invoices' : 'Outstanding invoices' ?></h3>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <?php if ($role === 'admin'): ?><th>Student</th><?php endif; ?>
                    <th>Fee Category</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$invoiceList): ?>
                <tr><td colspan="<?= $role === 'admin' ? 7 : 6 ?>">No invoices found.</td></tr>
            <?php else: ?>
                <?php foreach ($invoiceList as $invoice): ?>
                    <tr>
                        <td>#<?= (int)$invoice['id'] ?></td>
                        <?php if ($role === 'admin'): ?>
                            <td><?= e($invoice['full_name'] . ' (' . $invoice['matric_no'] . ')') ?></td>
                        <?php endif; ?>
                        <td><?= e($invoice['category_name'] ?? 'General fee') ?></td>
                        <td>₦<?= number_format((float)$invoice['amount'], 2) ?></td>
                        <td>₦<?= number_format((float)$invoice['amount_paid'], 2) ?></td>
                        <td>₦<?= number_format(max(0, (float)$invoice['amount'] - (float)$invoice['amount_paid']), 2) ?></td>
                        <td><span class="badge"><?= e(ucfirst($invoice['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h3>Payment history</h3>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Invoice</th>
                    <?php if ($role === 'admin'): ?><th>Student</th><?php endif; ?>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Gateway Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="<?= $role === 'admin' ? 7 : 6 ?>">No payment records found.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $payment): ?>
                    <tr>
                        <td><?= e($payment['reference']) ?></td>
                        <td>#<?= (int)$payment['invoice_id'] ?></td>
                        <?php if ($role === 'admin'): ?>
                            <td><?= e($payment['full_name'] . ' (' . $payment['matric_no'] . ')') ?></td>
                        <?php endif; ?>
                        <td>₦<?= number_format((float)$payment['amount'], 2) ?></td>
                        <td><?= e(ucwords(str_replace('_', ' ', $payment['method']))) ?></td>
                        <td><?= e(ucfirst($payment['gateway_status'])) ?></td>
                        <td><?= e($payment['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>