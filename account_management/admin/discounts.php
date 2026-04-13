<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) { redirect('../login.php'); }
requireAdmin();

$pageTitle = 'Admin - Discount Codes';
$errors    = [];
$success   = '';

// handle post actions (create/update/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $code       = strtoupper(trim($_POST['code'] ?? ''));
        $percent    = trim($_POST['discount_percent'] ?? '');
        $startDate  = trim($_POST['start_date'] ?? '') ?: null;
        $expireDate = trim($_POST['expire_date'] ?? '') ?: null;

        if ($code === '') { $errors[] = 'Discount code is required.'; }
        if (!is_numeric($percent) || (float) $percent <= 0 || (float) $percent > 100) {
            $errors[] = 'Discount percent must be between 0.01 and 100.';
        }
        if ($startDate && $expireDate && $expireDate < $startDate) {
            $errors[] = 'Expiry date cannot be before start date.';
        }

        if (!$errors) {
            $check = $pdo->prepare('SELECT discount_id FROM discount_codes WHERE code = ?');
            $check->execute([$code]);
            if ($check->fetch()) {
                $errors[] = "Code '{$code}' already exists.";
            } else {
                $pdo->prepare('INSERT INTO discount_codes (code, discount_percent, start_date, expire_date) VALUES (?,?,?,?)')
                    ->execute([$code, (float) $percent, $startDate, $expireDate]);
                $success = "Discount code '{$code}' created successfully.";
            }
        }
    }

    if ($action === 'update') {
        $id         = (int) ($_POST['discount_id'] ?? 0);
        $code       = strtoupper(trim($_POST['code'] ?? ''));
        $percent    = trim($_POST['discount_percent'] ?? '');
        $startDate  = trim($_POST['start_date'] ?? '') ?: null;
        $expireDate = trim($_POST['expire_date'] ?? '') ?: null;

        if ($id <= 0 || $code === '') { $errors[] = 'Invalid discount code data.'; }
        if (!is_numeric($percent) || (float) $percent <= 0 || (float) $percent > 100) {
            $errors[] = 'Discount percent must be between 0.01 and 100.';
        }
        if ($startDate && $expireDate && $expireDate < $startDate) {
            $errors[] = 'Expiry date cannot be before start date.';
        }

        $check = $pdo->prepare('SELECT discount_id FROM discount_codes WHERE code = ? AND discount_id <> ?');
        $check->execute([$code, $id]);
        if ($check->fetch()) { $errors[] = "Code '{$code}' already belongs to another entry."; }

        if (!$errors) {
            $pdo->prepare('UPDATE discount_codes SET code=?, discount_percent=?, start_date=?, expire_date=? WHERE discount_id=?')
                ->execute([$code, (float) $percent, $startDate, $expireDate, $id]);
            $success = "Discount code updated.";
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['discount_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM discount_codes WHERE discount_id = ?')->execute([$id]);
            $success = 'Discount code deleted.';
        }
    }
}

$codes = $pdo->query('SELECT * FROM discount_codes ORDER BY discount_id DESC')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<h1>Discount Code Management</h1>
<p class="muted">Create and manage discount codes customers can apply at checkout.</p>

<?php foreach ($errors as $err): ?>
    <div class="error"><?= e($err); ?></div>
<?php endforeach; ?>
<?php if ($success): ?>
    <div class="success"><?= e($success); ?></div>
<?php endif; ?>

<!-- Create new code -->
<h2>Create New Discount Code</h2>
<form method="POST">
    <input type="hidden" name="action" value="create">
    <div class="row">
        <div>
            <label>Code (e.g. SAVE10)</label>
            <input type="text" name="code" placeholder="SUMMER26" style="text-transform:uppercase;" required>
        </div>
        <div>
            <label>Discount Percent (%)</label>
            <input type="number" name="discount_percent" step="0.01" min="0.01" max="100" placeholder="10.00" required>
        </div>
    </div>
    <div class="row">
        <div>
            <label>Start Date (optional)</label>
            <input type="date" name="start_date">
        </div>
        <div>
            <label>Expiry Date (optional)</label>
            <input type="date" name="expire_date">
        </div>
    </div>
    <button type="submit">Create Code</button>
</form>

<hr style="margin:32px 0;">

<!-- Existing codes -->
<h2>Existing Codes (<?= count($codes); ?>)</h2>
<?php if (!$codes): ?>
    <p class="muted">No discount codes yet.</p>
<?php endif; ?>

<?php foreach ($codes as $dc):
    $today = date('Y-m-d');
    $isActive = true;
    if ($dc['start_date'] && $dc['start_date'] > $today) $isActive = false;
    if ($dc['expire_date'] && $dc['expire_date'] < $today) $isActive = false;
?>
<form method="POST" style="border:1px solid #e5e7eb;padding:16px;border-radius:12px;margin-bottom:16px;background:<?= $isActive ? '#f0fdf4' : '#fafafa'; ?>">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="discount_id" value="<?= (int) $dc['discount_id']; ?>">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <span class="muted">ID #<?= (int) $dc['discount_id']; ?></span>
        <span style="font-size:0.8rem;padding:3px 10px;border-radius:20px;background:<?= $isActive ? '#dcfce7' : '#fee2e2'; ?>;color:<?= $isActive ? '#166534' : '#991b1b'; ?>;">
            <?= $isActive ? 'Active' : 'Inactive / Expired'; ?>
        </span>
    </div>
    <div class="row">
        <div>
            <label>Code</label>
            <input type="text" name="code" value="<?= e($dc['code']); ?>" style="text-transform:uppercase;" required>
        </div>
        <div>
            <label>Discount %</label>
            <input type="number" name="discount_percent" step="0.01" min="0.01" max="100"
                   value="<?= number_format((float) $dc['discount_percent'], 2); ?>" required>
        </div>
    </div>
    <div class="row">
        <div>
            <label>Start Date</label>
            <input type="date" name="start_date" value="<?= e((string) ($dc['start_date'] ?? '')); ?>">
        </div>
        <div>
            <label>Expiry Date</label>
            <input type="date" name="expire_date" value="<?= e((string) ($dc['expire_date'] ?? '')); ?>">
        </div>
    </div>
    <button type="submit" name="action" value="update">Save Changes</button>
    <button type="submit" name="action" value="delete" class="danger"
            onclick="return confirm('Delete discount code <?= e($dc['code']); ?>?');">Delete</button>
</form>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
