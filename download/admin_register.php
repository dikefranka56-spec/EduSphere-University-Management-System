<?php
require_once __DIR__ . '/config.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $full_name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($full_name === '' || strlen($full_name) > 150) {
        $error = 'Enter a valid full name.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Your password must contain at least 8 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Your passwords do not match.';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->execute([$email]);

        if ($check->fetch()) {
            $error = 'An account with this email address already exists.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                "INSERT INTO users (email, password_hash, role, full_name, status)
                 VALUES (?, ?, 'admin', ?, 'active')"
            );

            $stmt->execute([$email, $password_hash, $full_name]);

            $new_admin_id = (int)$pdo->lastInsertId();

            audit('admin_registration', 'New administrator account registered. User ID: ' . $new_admin_id);

            header('Location: login.php?portal=admin&registered=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create Admin Account | EduSphere</title>

<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,Arial,sans-serif;background:#f1f5fa;color:#172b46;min-height:100vh}
header{height:76px;background:white;display:flex;align-items:center;padding:0 7%;box-shadow:0 3px 15px #152b4610}
.brand{font-size:21px;font-weight:800;color:#15365d;text-decoration:none}
.brand span{color:#d9a33e}
.wrapper{min-height:calc(100vh - 76px);display:grid;grid-template-columns:1fr 1fr}
.visual{background:linear-gradient(145deg,#102b50ed,#1769aaad),url('https://images.unsplash.com/photo-1562774053-701939374585?auto=format&fit=crop&w=1200&q=85') center/cover;display:flex;align-items:center;padding:10%}
.visual h1{font-size:clamp(34px,4vw,52px);color:white;line-height:1.15;margin:18px 0}
.visual p{color:#e1eaf5;font-size:16px;max-width:460px}
.visual small{color:#f3c66e;font-weight:800;letter-spacing:2px}
.form-area{display:grid;place-items:center;padding:35px 20px}
.panel{background:white;width:100%;max-width:460px;padding:38px;border-radius:17px;box-shadow:0 15px 50px #102b5012}
.icon{font-size:34px;margin-bottom:12px}
.panel h2{font-size:27px;color:#15365d;margin-bottom:8px}
.sub{color:#738198;font-size:14px;margin-bottom:20px;line-height:1.6}
label{display:block;font-size:13px;font-weight:700;margin:15px 0 7px}
input{width:100%;padding:13px;border:1px solid #dce4ee;border-radius:8px;font-size:15px;outline:none}
input:focus{border-color:#1769aa;box-shadow:0 0 0 3px #1769aa18}
.submit{width:100%;border:0;background:#1769aa;color:white;padding:15px;border-radius:8px;font-size:15px;font-weight:800;margin-top:22px;cursor:pointer}
.submit:hover{background:#102b50}
.error{background:#fff0f0;color:#a52d35;padding:12px;border-radius:7px;font-size:13px;margin-bottom:15px}
.bottom{text-align:center;color:#738198;font-size:13px;margin-top:22px;line-height:1.8}
.bottom a{color:#1769aa;font-weight:700;text-decoration:none}
.home{display:block;text-align:center;margin-top:15px;color:#738198;font-size:13px;text-decoration:none}
@media(max-width:800px){.wrapper{grid-template-columns:1fr}.visual{display:none}header{padding:0 5%}.panel{padding:30px 24px}}
</style>
</head>

<body>
<header>
<a class="brand" href="index.php">🎓 Edu<span>Sphere</span></a>
</header>

<div class="wrapper">
<div class="visual">
<div>
<small>EDUSPHERE UNIVERSITY</small>
<h1>Create Your Administrator Account</h1>
<p>Register to access university administration tools and manage academic operations through EduSphere.</p>
</div>
</div>

<div class="form-area">
<div class="panel">

<div class="icon">🛡️</div>
<h2>Create Admin Account</h2>
<p class="sub">Enter your details below to register your administrator account.</p>

<?php if ($error): ?>
<div class="error"><?= e($error) ?></div>
<?php endif; ?>

<form method="post">
<input type="hidden" name="csrf" value="<?= e(csrf()) ?>">

<label>Full Name</label>
<input type="text" name="full_name" maxlength="150"
value="<?= e($_POST['full_name'] ?? '') ?>" autocomplete="name" required>

<label>Email Address</label>
<input type="email" name="email"
value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email" required>

<label>Password</label>
<input type="password" name="password" minlength="8"
autocomplete="new-password" required>

<label>Confirm Password</label>
<input type="password" name="confirm_password" minlength="8"
autocomplete="new-password" required>

<button class="submit" type="submit">Create Admin Account</button>
</form>

<p class="bottom">
Already have an admin account?
<a href="login.php?portal=admin">Sign In</a>
</p>

<a class="home" href="index.php">← Return to University Homepage</a>

</div>
</div>
</div>
</body>
</html>
