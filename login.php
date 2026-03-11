<?php
session_start();
require_once 'config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: pages/dashboard.php');
    exit;
}

$error = '';
$msg = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $msg = 'You have been logged out successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['user_name']   = $user['name'];
            $_SESSION['username']    = $user['username'];
            $_SESSION['role']        = $user['role'];
            $_SESSION['supplier_id'] = $user['supplier_id'];

            // Update last login
            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

            // Log attendance if employee record exists
            try {
                $emp = $db->prepare("SELECT id FROM employees WHERE user_id = ? LIMIT 1");
                $emp->execute([$user['id']]);
                $empRec = $emp->fetch();
                if ($empRec) {
                    $today = date('Y-m-d');
                    $exists = $db->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = ?");
                    $exists->execute([$empRec['id'], $today]);
                    if (!$exists->fetch()) {
                        $db->prepare("INSERT INTO attendance (employee_id, date, check_in) VALUES (?, ?, ?)")
                           ->execute([$empRec['id'], $today, date('H:i:s')]);
                    }
                }
            } catch(Exception $e) {}

            logAudit('LOGIN', 'users', $user['id'], 'User logged in');

            // Redirect by role
            if ($user['role'] === 'cashier') {
                header('Location: pages/pos.php');
            } elseif ($user['role'] === 'supplier') {
                header('Location: pages/supplier_dashboard.php');
            } elseif ($user['role'] === 'customer') {
                header('Location: pages/customers.php');
            } else {
                header('Location: pages/dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — K.T.S Grocery Shop Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Inter', sans-serif;
    background: #0a0e1a;
    color: #f1f5f9;
    min-height: 100vh;
    display: flex;
    overflow: hidden;
}

/* Left panel */
.login-left {
    flex: 1;
    background: linear-gradient(135deg, #0a1628 0%, #0f2040 50%, #0a1628 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px;
    position: relative;
    overflow: hidden;
}
.login-left::before {
    content: '';
    position: absolute;
    width: 500px; height: 500px;
    background: radial-gradient(circle, rgba(34,197,94,0.12) 0%, transparent 70%);
    top: -100px; left: -100px;
    border-radius: 50%;
}
.login-left::after {
    content: '';
    position: absolute;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(34,197,94,0.08) 0%, transparent 70%);
    bottom: -80px; right: -80px;
    border-radius: 50%;
}

.brand-logo {
    display: flex; align-items: center; gap: 16px;
    margin-bottom: 60px;
    position: relative; z-index: 1;
}
.brand-logo .icon {
    width: 56px; height: 56px;
    background: linear-gradient(135deg, #22c55e, #16a34a);
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 28px;
    box-shadow: 0 0 30px rgba(34,197,94,0.4);
}
.brand-logo .name { font-size: 22px; font-weight: 800; }
.brand-logo .sub { font-size: 12px; color: #64748b; margin-top: 2px; }

.promo-content { position: relative; z-index: 1; text-align: center; }
.promo-content h1 {
    font-size: 40px; font-weight: 800;
    background: linear-gradient(135deg, #f1f5f9, #22c55e);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    line-height: 1.2; margin-bottom: 20px;
}
.promo-content p { color: #64748b; font-size: 15px; line-height: 1.7; max-width: 380px; }

.features-list { margin-top: 40px; display: flex; flex-direction: column; gap: 14px; }
.feature-item {
    display: flex; align-items: center; gap: 12px;
    color: #94a3b8; font-size: 14px;
}
.feature-item .feat-icon {
    width: 34px; height: 34px;
    background: rgba(34,197,94,0.1);
    border: 1px solid rgba(34,197,94,0.2);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
}

/* Right panel - login form */
.login-right {
    width: 480px;
    background: #0f1623;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
    border-left: 1px solid #1e293b;
}
.login-card { width: 100%; max-width: 380px; }

.login-card h2 { font-size: 26px; font-weight: 800; margin-bottom: 6px; }
.login-card .subtitle { color: #64748b; font-size: 13px; margin-bottom: 36px; }

.form-group { margin-bottom: 18px; }
.form-label {
    display: block; margin-bottom: 7px;
    font-size: 12px; font-weight: 600;
    color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;
}
.input-wrap { position: relative; }
.input-wrap .input-icon {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    font-size: 17px; color: #475569; pointer-events: none;
}
.form-control {
    width: 100%; padding: 12px 14px 12px 44px;
    background: #111827;
    border: 1px solid #1e293b;
    border-radius: 10px;
    color: #f1f5f9;
    font-size: 14px;
    font-family: inherit;
    transition: all 0.2s;
}
.form-control:focus {
    outline: none;
    border-color: #22c55e;
    box-shadow: 0 0 0 3px rgba(34,197,94,0.12);
    background: #131c2e;
}
.form-control::placeholder { color: #334155; }

.toggle-pass {
    position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
    background: none; border: none;
    color: #475569; cursor: pointer; font-size: 17px;
    transition: color 0.2s; padding: 0;
}
.toggle-pass:hover { color: #22c55e; }

.forgot-link {
    display: block; text-align: right;
    font-size: 12px; color: #22c55e;
    margin-top: -10px; margin-bottom: 18px;
}
.forgot-link:hover { color: #16a34a; }

.btn-login {
    width: 100%; padding: 14px;
    background: linear-gradient(135deg, #22c55e, #16a34a);
    border: none; border-radius: 10px;
    color: white; font-size: 15px; font-weight: 700;
    cursor: pointer; font-family: inherit;
    transition: all 0.2s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    margin-bottom: 20px;
}
.btn-login:hover { box-shadow: 0 6px 20px rgba(34,197,94,0.35); transform: translateY(-1px); }
.btn-login:active { transform: translateY(0); }

.alert {
    padding: 12px 16px; border-radius: 8px;
    font-size: 13px; margin-bottom: 18px;
    display: flex; align-items: center; gap: 8px;
}
.alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #fca5a5; }
.alert-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: #86efac; }

.setup-link {
    text-align: center; font-size: 12px; color: #475569;
    padding-top: 20px; border-top: 1px solid #1e293b;
}
.setup-link a { color: #3b82f6; }
.setup-link a:hover { color: #60a5fa; }

.copyright { text-align: center; margin-top: 24px; font-size: 11px; color: #334155; }

@media (max-width: 900px) {
    .login-left { display: none; }
    .login-right { width: 100%; border-left: none; }
}
</style>
</head>
<body>

<div class="login-left">
    <div class="brand-logo">
        <div class="icon"><i class="bi bi-cart3"></i></div>
        <div>
            <div class="name">K.T.S Grocery</div>
            <div class="sub">Management System</div>
        </div>
    </div>

    <div class="promo-content">
        <h1>Digitalize Your Grocery Shop</h1>
        <p>Complete management solution with real-time inventory tracking, smart POS billing, and live analytics.</p>

        <div class="features-list">
            <div class="feature-item"><div class="feat-icon"><i class="bi bi-box-seam"></i></div>Real-time Inventory Tracking</div>
            <div class="feature-item"><div class="feat-icon"><i class="bi bi-upc-scan"></i></div>Camera Barcode Scanning</div>
            <div class="feature-item"><div class="feat-icon"><i class="bi bi-receipt"></i></div>Smart POS & Billing</div>
            <div class="feature-item"><div class="feat-icon"><i class="bi bi-bar-chart-fill"></i></div>Sales Analytics & Reports</div>
            <div class="feature-item"><div class="feat-icon"><i class="bi bi-bell-fill"></i></div>Low Stock & Expiry Alerts</div>
        </div>
    </div>
</div>

<div class="login-right">
    <div class="login-card">
        <h2>Welcome Back <i class="bi bi-hand-wave text-accent" style="color:#3b82f6;"></i></h2>
        <p class="subtitle">Sign in to your K.T.S Grocery account</p>

        <?php if ($error): ?>
        <div class="alert alert-error"><i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($msg): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Username</label>
                <div class="input-wrap">
                    <span class="input-icon"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" class="form-control"
                           placeholder="Enter your username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           id="username" autocomplete="username" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-wrap">
                    <span class="input-icon"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control"
                           placeholder="Enter your password"
                           id="password" autocomplete="current-password" required>
                    <button type="button" class="toggle-pass" onclick="togglePassword()" id="toggleBtn"><i class="bi bi-eye"></i></button>
                </div>
            </div>

            <a href="#" class="forgot-link" onclick="alert('Please contact your administrator to reset your password.')">Forgot password?</a>

            <button type="submit" class="btn-login" id="loginBtn">
                <span><i class="bi bi-box-arrow-in-right"></i></span> Sign In
            </button>
        </form>

        <div class="setup-link">
            First time? <a href="setup/install.php">Run Database Setup →</a>
        </div>

        <div class="copyright">
            © <?= date('Y') ?> K.T.S Grocery Shop Management System
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const pass = document.getElementById('password');
    const btn = document.getElementById('toggleBtn');
    if (pass.type === 'password') {
        pass.type = 'text';
        btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
    } else {
        pass.type = 'password';
        btn.innerHTML = '<i class="bi bi-eye"></i>';
    }
}

document.querySelector('form').addEventListener('submit', function() {
    document.getElementById('loginBtn').innerHTML = '<span><i class="bi bi-hourglass-split"></i></span> Signing in...';
    document.getElementById('loginBtn').disabled = true;
});
</script>
</body>
</html>
