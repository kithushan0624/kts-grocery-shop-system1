<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config/db.php';
$pageTitle = "Payment Successful";
require_once 'includes/header.php';
?>

<div class="shop-wrapper" style="padding: 100px 20px; text-align: center;">
    <div style="max-width: 500px; margin: 0 auto; background: var(--shop-surface); padding: 40px; border-radius: var(--shop-radius-xl); border: 1px solid var(--shop-border);">
        <div style="width: 80px; height: 80px; background: var(--shop-green-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
            <i class="bi bi-check-lg" style="font-size: 40px; color: var(--shop-green);"></i>
        </div>
        <h1 style="font-size: 28px; font-weight: 800; color: #fff; margin-bottom: 10px;">Payment Successful!</h1>
        <p style="color: var(--shop-text-secondary); margin-bottom: 30px;">Thank you for your payment. Your order is being processed and will be delivered soon.</p>
        
        <div style="display: flex; gap: 12px; justify-content: center;">
            <a href="orders.php" class="btn btn-primary"><i class="bi bi-receipt"></i> View My Orders</a>
            <a href="index.php" class="btn btn-secondary"><i class="bi bi-house"></i> Return Home</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
