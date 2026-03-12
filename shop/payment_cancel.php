<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config/db.php';
$pageTitle = "Payment Cancelled";
require_once 'includes/header.php';
?>

<div class="shop-wrapper" style="padding: 100px 20px; text-align: center;">
    <div style="max-width: 500px; margin: 0 auto; background: var(--shop-surface); padding: 40px; border-radius: var(--shop-radius-xl); border: 1px solid var(--shop-border);">
        <div style="width: 80px; height: 80px; background: rgba(239, 68, 68, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
            <i class="bi bi-x-lg" style="font-size: 30px; color: #ef4444;"></i>
        </div>
        <h1 style="font-size: 28px; font-weight: 800; color: #fff; margin-bottom: 10px;">Payment Cancelled</h1>
        <p style="color: var(--shop-text-secondary); margin-bottom: 30px;">Your payment was cancelled. No charges were made. You can try placing the order again from your cart.</p>
        
        <div style="display: flex; gap: 12px; justify-content: center;">
            <a href="cart.php" class="btn btn-primary"><i class="bi bi-cart"></i> Go to Cart</a>
            <a href="checkout.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Checkout</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
