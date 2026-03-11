<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config/db.php';
$db = getDB();

$isLoggedIn = isset($_SESSION['customer_id']);
if ($isLoggedIn) {
    header('Location: index.php');
    exit;
}

$error = '';
$redirect = $_GET['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $redirect = $_POST['redirect'] ?? ''; // Get redirect from POST data

    if ($login && $password) {
        $stmt = $db->prepare("SELECT * FROM customers WHERE (username = ? OR phone = ? OR email = ?) AND status = 'active'");
        $stmt->execute([$login, $login, $login]);
        $customer = $stmt->fetch();

        if ($customer && password_verify($password, $customer['password'])) {
            $_SESSION['customer_id'] = $customer['id'];
            $_SESSION['customer_name'] = $customer['name'];
            header('Location: ' . ($redirect ?: 'index.php'));
            exit;
        } else {
            $error = 'Invalid credentials. Please check your username/phone/email and password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}

$pageTitle = "Login";
require_once 'includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-icon"><i class="bi bi-person-check"></i></div>
            <h2>Welcome Back</h2>
            <p>Login with your phone, email, or username</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
            <div class="form-group">
                <label class="form-label">Phone / Email / Username</label>
                <input type="text" name="login" class="form-control" placeholder="Enter your phone, email or username" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label" style="display:flex;justify-content:space-between;align-items:center;">
                    Password 
                    <a href="forgot_password.php" style="font-size:12px;font-weight:500;">Forgot Password?</a>
                </label>
                <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">
                <i class="bi bi-box-arrow-in-right"></i> Login
            </button>
        </form>

        <div class="auth-links">
            Don't have an account? <a href="register.php">Create one</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
