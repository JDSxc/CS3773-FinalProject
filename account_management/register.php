<?php

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Register';
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($firstName === '' || $lastName === '' || $email === '' || $username === '' || $password === '') {
        $errors[] = 'All fields are required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $checkStmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? OR username = ?');
        $checkStmt->execute([$email, $username]);

        if ($checkStmt->fetch()) {
            $errors[] = 'That email or username is already in use.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare(
                'INSERT INTO users (first_name, last_name, email, username, user_pass, user_role) VALUES (?, ?, ?, ?, ?, ?)' 
            );
            $insertStmt->execute([$firstName, $lastName, $email, $username, $hashedPassword, 'customer']);
            $success = 'Registration successful. You can log in now.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<h1>Create Account</h1>
<p class="muted">Registers a new customer account in the users table.</p>

<?php foreach ($errors as $error): ?>
    <div class="error"><?= e($error); ?></div>
<?php endforeach; ?>
<?php if ($success): ?>
    <div class="success"><?= e($success); ?></div>
<?php endif; ?>

<form method="POST">
    <div class="row">
        <div>
            <label>First Name</label>
            <input type="text" name="first_name" value="<?= e($_POST['first_name'] ?? ''); ?>" required>
        </div>
        <div>
            <label>Last Name</label>
            <input type="text" name="last_name" value="<?= e($_POST['last_name'] ?? ''); ?>" required>
        </div>
    </div>

    <div class="row">
        <div>
            <label>Email</label>
            <input type="email" name="email" value="<?= e($_POST['email'] ?? ''); ?>" required>
        </div>
        <div>
            <label>Username</label>
            <input type="text" name="username" value="<?= e($_POST['username'] ?? ''); ?>" required>
        </div>
    </div>

    <div class="row">
        <div>
            <label>Password</label>
            <input type="password" name="password" required>
        </div>
        <div>
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required>
        </div>
    </div>

    <button type="submit">Register</button>
</form>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
