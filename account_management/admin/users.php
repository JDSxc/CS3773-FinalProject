<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

if (($_SESSION['user']['user_role'] ?? '') !== 'admin') {
    redirect('../account.php');
}

requireAdmin();
$pageTitle = 'Admin - Manage Users';
$errors = [];
$success = '';
$currentAdmin = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = ($_POST['user_role'] ?? 'customer') === 'admin' ? 'admin' : 'customer';

        if ($firstName === '' || $lastName === '' || $email === '' || $username === '' || $password === '') {
            $errors[] = 'All new user fields are required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'New user email is invalid.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'New user password must be at least 8 characters.';
        }

        $check = $pdo->prepare('SELECT user_id FROM users WHERE email = ? OR username = ?');
        $check->execute([$email, $username]);
        if ($check->fetch()) {
            $errors[] = 'That new user email or username already exists.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email, username, user_pass, user_role) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$firstName, $lastName, $email, $username, password_hash($password, PASSWORD_DEFAULT), $role]);
            $success = 'User created successfully.';
        }
    }

    if ($action === 'update') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $role = ($_POST['user_role'] ?? 'customer') === 'admin' ? 'admin' : 'customer';
        $newPassword = $_POST['new_password'] ?? '';

        if ($userId <= 0 || $firstName === '' || $lastName === '' || $email === '' || $username === '') {
            $errors[] = 'Update requires a valid user and all core fields.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Updated email is invalid.';
        }
        if ($newPassword !== '' && strlen($newPassword) < 8) {
            $errors[] = 'Updated password must be at least 8 characters.';
        }

        $check = $pdo->prepare('SELECT user_id FROM users WHERE (email = ? OR username = ?) AND user_id <> ?');
        $check->execute([$email, $username, $userId]);
        if ($check->fetch()) {
            $errors[] = 'Another user already has that email or username.';
        }

        if (!$errors) {
            if ($newPassword !== '') {
                $stmt = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, username = ?, user_role = ?, user_pass = ? WHERE user_id = ?');
                $stmt->execute([$firstName, $lastName, $email, $username, $role, password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, username = ?, user_role = ? WHERE user_id = ?');
                $stmt->execute([$firstName, $lastName, $email, $username, $role, $userId]);
            }

            if ($userId === (int) $currentAdmin['user_id']) {
                refreshSessionUser($pdo, $userId);
            }
            $success = 'User updated successfully.';
        }
    }

    if ($action === 'delete') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId === (int) $currentAdmin['user_id']) {
            $errors[] = 'Admins cannot delete their own currently signed-in account.';
        } elseif ($userId > 0) {
            $stmt = $pdo->prepare('DELETE FROM users WHERE user_id = ?');
            $stmt->execute([$userId]);
            $success = 'User deleted successfully.';
        }
    }
}

$users = $pdo->query('SELECT user_id, first_name, last_name, email, username, user_role, created FROM users ORDER BY created DESC')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<h1>Admin User Management</h1>
<p class="muted">Create, edit, and delete users from the users table.</p>

<?php foreach ($errors as $error): ?>
    <div class="error"><?= e($error); ?></div>
<?php endforeach; ?>
<?php if ($success): ?>
    <div class="success"><?= e($success); ?></div>
<?php endif; ?>

<h2>Create New User</h2>
<form method="POST">
    <input type="hidden" name="action" value="create">
    <div class="row">
        <div><label>First Name</label><input type="text" name="first_name" required></div>
        <div><label>Last Name</label><input type="text" name="last_name" required></div>
    </div>
    <div class="row">
        <div><label>Email</label><input type="email" name="email" required></div>
        <div><label>Username</label><input type="text" name="username" required></div>
    </div>
    <div class="row">
        <div><label>Password</label><input type="password" name="password" required></div>
        <div>
            <label>Role</label>
            <select name="user_role">
                <option value="customer">customer</option>
                <option value="admin">admin</option>
            </select>
        </div>
    </div>
    <button type="submit">Create User</button>
</form>

<h2>Existing Users</h2>
<?php foreach ($users as $row): ?>
    <form method="POST" style="border:1px solid #e5e7eb;padding:16px;border-radius:12px;margin-bottom:18px;">
        <input type="hidden" name="user_id" value="<?= (int) $row['user_id']; ?>">
        <div class="row">
            <div><label>First Name</label><input type="text" name="first_name" value="<?= e($row['first_name']); ?>" required></div>
            <div><label>Last Name</label><input type="text" name="last_name" value="<?= e($row['last_name']); ?>" required></div>
        </div>
        <div class="row">
            <div><label>Email</label><input type="email" name="email" value="<?= e($row['email']); ?>" required></div>
            <div><label>Username</label><input type="text" name="username" value="<?= e($row['username']); ?>" required></div>
        </div>
        <div class="row">
            <div>
                <label>Role</label>
                <select name="user_role">
                    <option value="customer" <?= $row['user_role'] === 'customer' ? 'selected' : ''; ?>>customer</option>
                    <option value="admin" <?= $row['user_role'] === 'admin' ? 'selected' : ''; ?>>admin</option>
                </select>
            </div>
            <div>
                <label>Reset Password (optional)</label>
                <input type="password" name="new_password">
            </div>
        </div>
        <p class="muted">Created: <?= e($row['created']); ?> | User ID: <?= (int) $row['user_id']; ?></p>
        <button type="submit" name="action" value="update">Save User</button>
        <button type="submit" name="action" value="delete" class="danger" onclick="return confirm('Delete this user?');">Delete User</button>
    </form>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
