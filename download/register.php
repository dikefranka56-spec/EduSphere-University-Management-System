<?php
require_once __DIR__ . '/config.php';

$message = '';

$registrationSettings = $pdo->query(
    "SELECT allow_registration FROM settings ORDER BY id ASC LIMIT 1"
)->fetch();

$registrationAllowed = $registrationSettings &&
    (int)$registrationSettings['allow_registration'] === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (!$registrationAllowed) {
        $message = 'Student registration is currently disabled.';
    } else {
        $name = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $departmentId = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
        $level = filter_input(INPUT_POST, 'level', FILTER_VALIDATE_INT);

        if ($name === '' || strlen($name) > 150) {
            $message = 'Enter a valid full name.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 180) {
            $message = 'Enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $message = 'Password must contain at least 8 characters.';
        } elseif (!$departmentId || !in_array($level, [100,200,300,400], true)) {
            $message = 'Select a valid department and academic level.';
        } else {
            $check = $pdo->prepare("SELECT id FROM departments WHERE id=?");
            $check->execute([$departmentId]);

            if (!$check->fetchColumn()) {
                $message = 'Selected department is unavailable.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $insert = $pdo->prepare(
                        "INSERT INTO users (email,password_hash,role,full_name)
                         VALUES (?,?,'student',?)"
                    );
                    $insert->execute([
                        $email,
                        password_hash($password, PASSWORD_DEFAULT),
                        $name
                    ]);

                    $userId = (int)$pdo->lastInsertId();
                    $matric = 'EDU/' . date('Y') . '/' .
                        str_pad((string)$userId, 5, '0', STR_PAD_LEFT);

                    $student = $pdo->prepare(
                        "INSERT INTO students
                         (user_id,matric_no,department_id,level,admission_year)
                         VALUES (?,?,?,?,?)"
                    );
                    $student->execute([
                        $userId,$matric,$departmentId,$level,(int)date('Y')
                    ]);

                    $pdo->commit();
                    $message = 'Account created successfully! Your matriculation number is ' .
                        $matric . '. You can now sign in.';
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $message = 'Registration failed. This email may already be registered.';
                }
            }
        }
    }
}

$departments = $pdo->query(
    "SELECT id,name FROM departments ORDER BY name"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Student Registration | EduSphere</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,Arial,sans-serif;background:#f1f5fa;color:#172b46}
header{height:72px;background:white;display:flex;align-items:center;justify-content:space-between;padding:0 7%;box-shadow:0 3px 15px #152b4610}
.brand{font-size:21px;font-weight:800;color:#15365d;text-decoration:none}
.brand span{color:#d9a33e}
header a:last-child{color:#1769aa;font-weight:700;text-decoration:none;font-size:14px}
main{padding:45px 18px}
.panel{max-width:620px;margin:auto;background:white;border-radius:17px;padding:38px;box-shadow:0 15px 50px #102b5012}
.icon{font-size:35px}
h1{font-size:29px;color:#15365d;margin:10px 0}
.intro{color:#738198;font-size:14px;margin-bottom:23px}
.message{background:#eaf5ff;color:#17558a;padding:13px;border-radius:8px;margin-bottom:18px;font-size:13px}
.formgrid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}
label{display:block;font-size:13px;font-weight:700;margin:15px 0 7px}
input,select{width:100%;padding:13px;border:1px solid #dce4ee;border-radius:8px;background:white;font-size:14px;outline:none}
input:focus,select:focus{border-color:#1769aa;box-shadow:0 0 0 3px #1769aa18}
button{width:100%;background:#1769aa;color:white;padding:15px;border:0;border-radius:8px;font-size:15px;font-weight:800;margin-top:23px;cursor:pointer}
button:hover{background:#102b50}
.bottom{text-align:center;color:#738198;font-size:13px;margin-top:20px}
.bottom a{color:#1769aa;font-weight:700;text-decoration:none}
@media(max-width:560px){.panel{padding:26px 20px}.formgrid{grid-template-columns:1fr}header{padding:0 5%}}
</style>
</head>
<body>
<header>
<a class="brand" href="index.php">🎓 Edu<span>Sphere</span></a>
<a href="login.php?portal=admin">Admin Portal →</a>
</header>
<main>
<div class="panel">
<div class="icon">🎓</div>
<h1>Create Your Student Account</h1>
<p class="intro">Register to access your academic information and student services.</p>

<?php if ($message): ?>
<div class="message"><?= e($message) ?></div>
<?php endif; ?>

<?php if (!$registrationAllowed): ?>
<p>Student registration is currently closed. Please contact the institution.</p>
<?php elseif (!$departments): ?>
<p>No departments are available yet. Please contact the administrator.</p>
<?php else: ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
<div class="formgrid">
<div>
<label>Full Name</label>
<input name="full_name" maxlength="150" autocomplete="name" required>
</div>
<div>
<label>Email Address</label>
<input name="email" type="email" maxlength="180" autocomplete="email" required>
</div>
<div>
<label>Password</label>
<input name="password" type="password" minlength="8" autocomplete="new-password" required>
</div>
<div>
<label>Department</label>
<select name="department_id" required>
<option value="">Choose department</option>
<?php foreach ($departments as $d): ?>
<option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<label>Academic Level</label>
<select name="level" required>
<option value="">Choose level</option>
<option value="100">100 Level</option>
<option value="200">200 Level</option>
<option value="300">300 Level</option>
<option value="400">400 Level</option>
</select>
</div>
</div>
<button type="submit">Create Student Account</button>
</form>
<?php endif; ?>

<p class="bottom">Already registered? <a href="login.php">Sign in here</a></p>
<p class="bottom"><a href="index.php">← Back to University Homepage</a></p>
</div>
</main>
</body>
</html>
