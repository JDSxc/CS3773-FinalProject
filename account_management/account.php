<?php

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$pageTitle = 'My Account';
$errors = [];
$success = '';
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($firstName === '' || $lastName === '' || $email === '' || $username === '') {
        $errors[] = 'First name, last name, email, and username are required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    $dupStmt = $pdo->prepare('SELECT user_id FROM users WHERE (email = ? OR username = ?) AND user_id <> ?');
    $dupStmt->execute([$email, $username, $user['user_id']]);
    if ($dupStmt->fetch()) {
        $errors[] = 'That email or username is already being used by another account.';
    }

    if ($newPassword !== '' && strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (!$errors) {
        if ($newPassword !== '') {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, username = ?, user_pass = ? WHERE user_id = ?');
            $stmt->execute([$firstName, $lastName, $email, $username, $hashedPassword, $user['user_id']]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, username = ? WHERE user_id = ?');
            $stmt->execute([$firstName, $lastName, $email, $username, $user['user_id']]);
        }

        refreshSessionUser($pdo, (int) $user['user_id']);
        $user = currentUser();
        $success = 'Account updated successfully.';
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<h1>My Account</h1>
<p class="muted">Update personal information stored in the users table.</p>

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
            <input type="text" name="first_name" value="<?= e($user['first_name']); ?>" required>
        </div>
        <div>
            <label>Last Name</label>
            <input type="text" name="last_name" value="<?= e($user['last_name']); ?>" required>
        </div>
    </div>

    <div class="row">
        <div>
            <label>Email</label>
            <input type="email" name="email" value="<?= e($user['email']); ?>" required>
        </div>
        <div>
            <label>Username</label>
            <input type="text" name="username" value="<?= e($user['username']); ?>" required>
        </div>
    </div>

    <div class="row">
        <div>
            <label>New Password (optional)</label>
            <input type="password" name="new_password">
        </div>
        <div>
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password">
        </div>
    </div>

    <button type="submit">Save Changes</button>
</form>

<hr>
<p><strong>Role:</strong> <?= e($user['user_role']); ?></p>
<p><strong>Account Created:</strong> <?= e($user['created']); ?></p>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
