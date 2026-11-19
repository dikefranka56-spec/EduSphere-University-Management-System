<?php
$page_title = 'System Settings';
require_once __DIR__ . '/header.php';
require_role('admin');

$settings = $pdo->query("SELECT * FROM settings ORDER BY id ASC LIMIT 1")->fetch();

if (!$settings) {
    $pdo->prepare(
        "INSERT INTO settings (site_name, max_credit_units, min_credit_units, allow_registration)
         VALUES ('EduSphere', 24, 12, 1)"
    )->execute();

    $settings = $pdo->query("SELECT * FROM settings ORDER BY id ASC LIMIT 1")->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    csrf_check();

    $siteName = trim($_POST['site_name'] ?? '');
    $maxUnits = filter_input(INPUT_POST, 'max_credit_units', FILTER_VALIDATE_INT);
    $minUnits = filter_input(INPUT_POST, 'min_credit_units', FILTER_VALIDATE_INT);
    $allowRegistration = isset($_POST['allow_registration']) ? 1 : 0;

    if (
        $siteName === '' ||
        strlen($siteName) > 120 ||
        !$maxUnits ||
        !$minUnits ||
        $minUnits < 0 ||
        $maxUnits < 1 ||
        $minUnits > $maxUnits ||
        $maxUnits > 60
    ) {
        $_SESSION['flash'] = 'Enter a valid site name and credit-unit limits. Minimum units cannot exceed maximum units.';
        redirect('settings.php');
    }

    $stmt = $pdo->prepare(
        "UPDATE settings
         SET site_name = ?, max_credit_units = ?, min_credit_units = ?, allow_registration = ?
         WHERE id = ?"
    );
    $stmt->execute([
        $siteName,
        $maxUnits,
        $minUnits,
        $allowRegistration,
        $settings['id']
    ]);

    audit('settings_update', 'Updated system settings');
    $_SESSION['flash'] = 'System settings saved successfully.';
    redirect('settings.php');
}
?>

<section class="card">
    <h3>General configuration</h3>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="save_settings" value="1">

        <label>Institution / system name</label>
        <input name="site_name" maxlength="120" value="<?= e($settings['site_name']) ?>" required>

        <div class="grid2">
            <div>
                <label>Maximum credit units</label>
                <input name="max_credit_units" type="number" min="1" max="60"
                       value="<?= (int)$settings['max_credit_units'] ?>" required>
            </div>

            <div>
                <label>Minimum credit units</label>
                <input name="min_credit_units" type="number" min="0" max="60"
                       value="<?= (int)$settings['min_credit_units'] ?>" required>
            </div>
        </div>

        <label>
            <input type="checkbox" name="allow_registration" value="1" style="width:auto"
                <?= !empty($settings['allow_registration']) ? 'checked' : '' ?>>
            Allow student self-registration
        </label>

        <br><br>
        <button class="btn" type="submit">Save System Settings</button>
    </form>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>