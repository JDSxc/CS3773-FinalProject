<?php

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Login';
$errors = [];

if (isLoggedIn()) {
    redirect('account.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        $errors[] = 'Enter your username/email and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1');
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['user_pass'])) {
            $_SESSION['user'] = [
                'user_id' => $user['user_id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'username' => $user['username'],
                'user_role' => $user['user_role'],
                'created' => $user['created'],
            ];
            redirect('account.php');
        }

        $errors[] = 'Invalid login credentials.';
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<h1>Login</h1>
<p class="muted">Customers and admins can sign in here.</p>

<?php foreach ($errors as $error): ?>
    <div class="error"><?= e($error); ?></div>
<?php endforeach; ?>

<form method="POST">
    <label>Email or Username</label>
    <input type="text" name="login" value="<?= e($_POST['login'] ?? ''); ?>" required>

    <label>Password</label>
    <input type="password" name="password" required>

    <button type="submit">Login</button>
</form>
<p>Don't have an account? <a href="register.php">Register here</a></p>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
