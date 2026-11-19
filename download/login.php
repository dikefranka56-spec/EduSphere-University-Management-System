<?php
require_once __DIR__ . '/config.php';

$portal = (($_GET['portal'] ?? 'student') === 'admin') ? 'admin' : 'student';

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter your email address and password.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT id,email,password_hash,role,full_name,status
             FROM users WHERE email=? LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['status'] === 'active'
            && password_verify($password, $user['password_hash'])) {

            if ($user['role'] !== $portal) {
                $error = $portal === 'admin'
                    ? 'This account does not have administrator access.'
                    : 'Please use the correct portal for your account.';
            } else {
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];

                audit('login', 'Successful ' . $portal . ' login');

                header('Location: dashboard.php');
                exit;
            }
        } else {
            $error = 'Invalid email or password, or your account is inactive.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $portal === 'admin' ? 'Administrator Login' : 'Student Login' ?> | EduSphere</title>

<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,Arial,sans-serif;background:#f1f5fa;color:#172b46;min-height:100vh}
header{height:76px;background:white;display:flex;align-items:center;justify-content:space-between;padding:0 7%;box-shadow:0 3px 15px #152b4610}
.brand{font-size:21px;font-weight:800;color:#15365d;text-decoration:none}
.brand span{color:#d9a33e}
nav{display:flex;gap:20px;align-items:center}
nav a{font-size:14px;font-weight:700;color:#25405e;text-decoration:none}
nav .admin{background:#15365d;color:white;padding:11px 17px;border-radius:7px}
.wrapper{min-height:calc(100vh - 76px);display:grid;grid-template-columns:1fr 1fr}
.visual{background:linear-gradient(145deg,#102b50ed,#1769aaad),url('https://images.unsplash.com/photo-1562774053-701939374585?auto=format&fit=crop&w=1200&q=85') center/cover;display:flex;align-items:center;padding:10%}
.visual h1{font-size:clamp(34px,4vw,52px);color:white;line-height:1.15;margin:18px 0}
.visual p{color:#e1eaf5;font-size:16px;max-width:460px}
.visual small{color:#f3c66e;font-weight:800;letter-spacing:2px}
.form-area{display:grid;place-items:center;padding:40px 20px}
.panel{background:white;width:100%;max-width:440px;padding:42px;border-radius:17px;box-shadow:0 15px 50px #102b5012}
.icon{font-size:34px;margin-bottom:15px}
.panel h2{font-size:29px;color:#15365d;margin-bottom:8px}
.sub{color:#738198;font-size:14px;margin-bottom:27px}
label{display:block;font-size:13px;font-weight:700;margin:17px 0 7px}
input{width:100%;padding:14px;border:1px solid #dce4ee;border-radius:8px;font-size:15px;outline:none}
input:focus{border-color:#1769aa;box-shadow:0 0 0 3px #1769aa18}
.submit{width:100%;border:0;background:#1769aa;color:white;padding:15px;border-radius:8px;font-size:15px;font-weight:800;margin-top:23px;cursor:pointer}
.submit:hover{background:#102b50}
.error{background:#fff0f0;color:#a52d35;padding:12px;border-radius:7px;font-size:13px;margin-bottom:15px}
.success{background:#eafaf0;color:#176b3a;padding:12px;border-radius:7px;font-size:13px;margin-bottom:15px}
.bottom{text-align:center;color:#738198;font-size:13px;margin-top:23px;line-height:1.8}
.bottom a{color:#1769aa;font-weight:700;text-decoration:none}
.home{display:block;text-align:center;margin-top:15px;color:#738198;font-size:13px;text-decoration:none}
@media(max-width:800px){.wrapper{grid-template-columns:1fr}.visual{display:none}header{padding:0 5%}nav{gap:10px}nav a{font-size:12px}.panel{padding:30px 24px}}
</style>
</head>

<body>
<header>
<a class="brand" href="index.php">🎓 Edu<span>Sphere</span></a>
<nav>
<a href="login.php?portal=student">Student</a>
<a class="admin" href="login.php?portal=admin">Admin Portal</a>
</nav>
</header>

<div class="wrapper">
<div class="visual">
<div>
<small>EDUSPHERE UNIVERSITY</small>
<h1><?= $portal === 'admin' ? 'Administration Portal' : 'Your Academic Journey Starts Here' ?></h1>
<p><?= $portal === 'admin' ? 'Access university management tools and oversee academic operations.' : 'Connect to your academic world. Manage your courses, results, timetable and university activities.' ?></p>
</div>
</div>

<div class="form-area">
<div class="panel">

<div class="icon"><?= $portal === 'admin' ? '🛡️' : '🎓' ?></div>

<h2><?= $portal === 'admin' ? 'Admin Sign In' : 'Welcome Back' ?></h2>

<p class="sub">
<?= $portal === 'admin' ? 'Sign in to your administrator account.' : 'Sign in to access your student portal.' ?>
</p>

<?php if ($error): ?>
<div class="error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($portal === 'admin' && isset($_GET['registered'])): ?>
<div class="success">Admin account created successfully. You can now sign in.</div>
<?php endif; ?>

<form method="post">
<input type="hidden" name="csrf" value="<?= e(csrf()) ?>">

<label>Email Address</label>
<input type="email" name="email" autocomplete="username" required>

<label>Password</label>
<input type="password" name="password" autocomplete="current-password" required>

<button class="submit" type="submit">
Sign In to <?= $portal === 'admin' ? 'Admin Portal' : 'Student Portal' ?>
</button>
</form>

<?php if ($portal === 'student'): ?>
<p class="bottom">New student? <a href="register.php">Create an account</a></p>
<?php else: ?>
<p class="bottom">
New administrator? <a href="admin_register.php">Create an Admin Account</a>
</p>
<?php endif; ?>

<a class="home" href="index.php">← Return to University Homepage</a>

</div>
</div>
</div>
</body>
</html>
