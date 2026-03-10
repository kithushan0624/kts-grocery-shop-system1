<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config/db.php';
$db = getDB();

$isLoggedIn = isset($_SESSION['customer_id']);
if ($isLoggedIn) { header('Location: index.php'); exit; }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$name || !$username || !$phone || !$password) {
        $error = 'Name, username, phone and password are required.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check uniqueness
        $stmt = $db->prepare("SELECT id FROM customers WHERE username = ? OR phone = ? OR (email != '' AND email = ?)");
        $stmt->execute([$username, $phone, $email]);
        if ($stmt->fetch()) {
            $error = 'Username, phone number, or email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO customers (name, username, phone, email, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $username, $phone, $email, $hash]);
            $success = 'Account created successfully! You can now login.';
        }
    }
}

$pageTitle = "Create Account";
require_once 'includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card" style="max-width:500px;">
        <div class="auth-header">
            <div class="auth-icon"><i class="bi bi-person-plus"></i></div>
            <h2>Create Account</h2>
            <p>Join us and start shopping online</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= $success ?> <a href="login.php" style="font-weight:700;">Login now →</a></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" class="form-control" placeholder="Your full name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="form-row cols-2">
                <div class="form-group">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" placeholder="Choose a username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number *</label>
                    <input type="tel" name="phone" class="form-control" placeholder="07X XXX XXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="your@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-row cols-2">
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" placeholder="Min 6 characters" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password *</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">
                <i class="bi bi-person-plus"></i> Create Account
            </button>
        </form>

        <div class="auth-links">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
