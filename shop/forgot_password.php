<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config/db.php';
$db = getDB();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$login || !$newPassword || !$confirmPassword) {
        $error = 'Please fill in all fields.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $stmt = $db->prepare("SELECT id FROM customers WHERE (username = ? OR phone = ? OR email = ?) AND status = 'active'");
        $stmt->execute([$login, $login, $login]);
        $customer = $stmt->fetch();

        if ($customer) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $db->prepare("UPDATE customers SET password = ? WHERE id = ?")->execute([$hash, $customer['id']]);
            $success = 'Password reset successfully! You can now login.';
        } else {
            $error = 'No active account found with those details.';
        }
    }
}

$pageTitle = "Reset Password";
require_once 'includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-icon"><i class="bi bi-shield-lock"></i></div>
            <h2>Reset Password</h2>
            <p>Enter your details to reset your password</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= $success ?> <a href="login.php" style="font-weight:700;">Login now →</a></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Phone / Email / Username</label>
                <input type="text" name="login" class="form-control" placeholder="Identify your account" required>
            </div>
            <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" placeholder="Enter new password" required>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">
                <i class="bi bi-arrow-repeat"></i> Reset Password
            </button>
        </form>

        <div class="auth-links">
            Remembered? <a href="login.php">Back to Login</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
