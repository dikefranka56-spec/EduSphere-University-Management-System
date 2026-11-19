<?php
$page_title = 'Notifications';
require_once __DIR__ . '/header.php';
require_role(['admin', 'student', 'lecturer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    csrf_check();

    $stmt = $pdo->prepare(
        "UPDATE notifications SET is_read = 1 WHERE user_id = ?"
    );
    $stmt->execute([$_SESSION['user_id']]);

    $_SESSION['flash'] = 'Your notifications have been marked as read.';
    redirect('messages.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    csrf_check();

    $notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);

    if (!$notificationId) {
        $_SESSION['flash'] = 'Invalid notification.';
        redirect('messages.php');
    }

    $stmt = $pdo->prepare(
        "UPDATE notifications SET is_read = 1
         WHERE id = ? AND user_id = ?"
    );
    $stmt->execute([$notificationId, $_SESSION['user_id']]);

    $_SESSION['flash'] = 'Notification updated.';
    redirect('messages.php');
}

$stmt = $pdo->prepare(
    "SELECT * FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC LIMIT 100"
);
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();

$unreadCount = 0;
foreach ($notifications as $notification) {
    if (!(int)$notification['is_read']) {
        $unreadCount++;
    }
}
?>

<section class="card">
    <h3>Your notifications</h3>
    <p class="muted">
        <?= (int)$unreadCount ?> unread notification(s)
    </p>

    <?php if ($unreadCount > 0): ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
            <input type="hidden" name="mark_all_read" value="1">
            <button class="btn ghost" type="submit">Mark All as Read</button>
        </form>
    <?php endif; ?>
</section>

<?php if (!$notifications): ?>
    <p class="muted">You do not have any notifications yet.</p>
<?php else: ?>
    <?php foreach ($notifications as $notification): ?>
        <article class="card">
            <p><?= nl2br(e($notification['message'])) ?></p>

            <p class="muted">
                <?= e($notification['created_at']) ?>
                · <?= $notification['is_read'] ? 'Read' : 'Unread' ?>
            </p>

            <?php if (!(int)$notification['is_read']): ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                    <input type="hidden" name="mark_read" value="1">
                    <input type="hidden" name="notification_id" value="<?= (int)$notification['id'] ?>">
                    <button class="btn sm ghost" type="submit">Mark as Read</button>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>