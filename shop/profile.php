<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config/db.php';
$db = getDB();

$isLoggedIn = isset($_SESSION['customer_id']);
if (!$isLoggedIn) { header('Location: login.php'); exit; }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['change_pw'])) {
        $currentP = $_POST['current_password'] ?? '';
        $newP = $_POST['new_password'] ?? '';
        $confirmP = $_POST['confirm_password'] ?? '';

        $stmt = $db->prepare("SELECT password FROM customers WHERE id = ?");
        $stmt->execute([$_SESSION['customer_id']]);
        $currHash = $stmt->fetchColumn();

        if (!password_verify($currentP, $currHash)) {
            $error = "Current password is incorrect.";
        } elseif ($newP !== $confirmP) {
            $error = "New passwords do not match.";
        } elseif (strlen($newP) < 6) {
            $error = "New password must be at least 6 characters.";
        } else {
            $hash = password_hash($newP, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE customers SET password = ? WHERE id = ?");
            if ($stmt->execute([$hash, $_SESSION['customer_id']])) {
                $success = "Password updated successfully!";
            } else {
                $error = "Failed to update password.";
            }
        }
    } else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (!$name || !$phone) {
            $error = "Name and Phone are required.";
        } else {
            $stmt = $db->prepare("SELECT id FROM customers WHERE (phone = ? OR (email != '' AND email = ?)) AND id != ?");
            $stmt->execute([$phone, $email, $_SESSION['customer_id']]);
            if ($stmt->fetch()) {
                $error = "Phone number or email is already in use by another account.";
            } else {
                $stmt = $db->prepare("UPDATE customers SET name = ?, phone = ?, email = ?, address = ? WHERE id = ?");
                if ($stmt->execute([$name, $phone, $email, $address, $_SESSION['customer_id']])) {
                    $_SESSION['customer_name'] = $name;
                    $success = "Profile updated successfully!";
                } else {
                    $error = "Failed to update profile.";
                }
            }
        }
    }
}

$stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$_SESSION['customer_id']]);
$customer = $stmt->fetch();
if (!$customer) { die("Customer not found."); }

$pageTitle = "My Profile";
require_once 'includes/header.php';
?>

<div class="shop-wrapper">
    <div class="section-header" style="padding-top:30px;">
        <h2><i class="bi bi-person-circle"></i> My Profile</h2>
    </div>

    <div class="profile-layout">
        <!-- Sidebar -->
        <div>
            <div class="shop-card profile-sidebar">
                <div class="profile-avatar"><?= strtoupper(substr($customer['name'], 0, 1)) ?></div>
                <h3 style="font-size:20px;font-weight:800;color:#fff;margin-bottom:4px;"><?= htmlspecialchars($customer['name']) ?></h3>
                <p style="color:var(--shop-text-muted);font-size:14px;margin-bottom:0;">@<?= htmlspecialchars($customer['username']) ?></p>

                <div class="loyalty-card">
                    <div style="display:flex;align-items:center;gap:8px;justify-content:center;margin-bottom:6px;">
                        <i class="bi bi-star-fill" style="color:#fbbf24;font-size:18px;"></i>
                        <span class="loyalty-points"><?= number_format($customer['loyalty_points']) ?></span>
                    </div>
                    <div class="loyalty-label">Loyalty Points</div>
                </div>
            </div>
        </div>

        <!-- Change Password / Edit Form -->
        <div>
            <div class="shop-card" style="margin-bottom:20px;">
                <h3 style="font-size:18px;font-weight:800;color:#fff;margin-bottom:24px;padding-bottom:16px;border-bottom:1px solid var(--shop-border);">
                    <i class="bi bi-pencil-square" style="color:var(--shop-accent);"></i> Edit Details
                </h3>

                <?php if($error && !isset($_POST['change_pw'])): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>
                <?php if($success && !isset($_POST['change_pw'])): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= $success ?></div><?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($customer['name']) ?>" required>
                    </div>
                    <div class="form-row cols-2">
                        <div class="form-group">
                            <label class="form-label">Phone *</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Delivery Address</label>
                        <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding:12px 28px;">
                        <i class="bi bi-check-lg"></i> Save Changes
                    </button>
                </form>
            </div>

            <div class="shop-card">
                <h3 style="font-size:18px;font-weight:800;color:#fff;margin-bottom:24px;padding-bottom:16px;border-bottom:1px solid var(--shop-border);">
                    <i class="bi bi-shield-lock" style="color:var(--shop-accent);"></i> Change Password
                </h3>

                <?php if($error && isset($_POST['change_pw'])): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>
                <?php if($success && isset($_POST['change_pw'])): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= $success ?></div><?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="change_pw" value="1">
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" placeholder="Enter current password" required>
                    </div>
                    <div class="form-row cols-2">
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" placeholder="At least 6 characters" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-secondary" style="padding:12px 28px;">
                        <i class="bi bi-key"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
