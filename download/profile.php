<?php
$page_title = 'My Profile';
require_once __DIR__ . '/header.php';
require_role(['admin', 'student', 'lecturer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    csrf_check();

    $name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '' || strlen($name) > 150 || strlen($phone) > 40) {
        $_SESSION['flash'] = 'Enter a valid name and phone number.';
        redirect('profile.php');
    }

    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
    $stmt->execute([$name, $phone, $_SESSION['user_id']]);

    $_SESSION['full_name'] = $name;

    audit('profile_update', 'Updated personal profile');
    $_SESSION['flash'] = 'Your profile has been updated.';
    redirect('profile.php');
}

$stmt = $pdo->prepare(
    "SELECT id, full_name, email, phone, role, status, created_at
     FROM users WHERE id = ? LIMIT 1"
);
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['flash'] = 'Account not found.';
    redirect('logout.php');
}
?>

<section class="card">
    <h3>Personal information</h3>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="save_profile" value="1">

        <div class="grid2">
            <div>
                <label>Full name</label>
                <input name="full_name" maxlength="150" value="<?= e($user['full_name']) ?>" required>
            </div>

            <div>
                <label>Email address</label>
                <input value="<?= e($user['email']) ?>" disabled>
            </div>

            <div>
                <label>Phone number</label>
                <input name="phone" maxlength="40" value="<?= e($user['phone'] ?? '') ?>">
            </div>

            <div>
                <label>Account role</label>
                <input value="<?= e(ucfirst($user['role'])) ?>" disabled>
            </div>
        </div>

        <p class="muted">Account created: <?= e($user['created_at']) ?></p>
        <br>
        <button class="btn" type="submit">Save Profile</button>
    </form>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>