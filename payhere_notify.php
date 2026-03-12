<?php
require_once 'config/db.php';
$db = getDB();

// PayHere Merchant Secret (Must be replaced with actual secret)
$merchant_secret = 'MjcxNzkxMjY2MTgyNzMwNTg5MTE5MTQwMjQ2MTU1OTQyODc4Mzc=';


$merchant_id = $_POST['merchant_id'];
$order_id = $_POST['order_id'];
$payment_id = $_POST['payment_id'];
$payhere_amount = $_POST['payhere_amount'];
$payhere_currency = $_POST['payhere_currency'];
$status_code = $_POST['status_code'];
$md5sig = $_POST['md5sig'];

$local_md5sig = strtoupper(
    md5(
    $merchant_id .
    $order_id .
    $payhere_amount .
    $payhere_currency .
    $status_code .
    strtoupper(md5($merchant_secret))
)
);

if (($local_md5sig === $md5sig) and ($status_code == 2)) {
    // Payment Successful
    try {
        $stmt = $db->prepare("UPDATE online_orders SET payment_status = 'paid', payment_id = ?, transaction_status = 'SUCCESS', payment_method = 'card' WHERE order_number = ? AND payment_status != 'paid'");
        $stmt->execute([$payment_id, $order_id]);

        // Log the notification for debugging
        file_put_contents('payhere_log.txt', date('[Y-m-d H:i:s] ') . "Success: Order $order_id Payment $payment_id\n", FILE_APPEND);
    }
    catch (Exception $e) {
        file_put_contents('payhere_log.txt', date('[Y-m-d H:i:s] ') . "Database Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}
else {
    // Payment Failed or Invalid Signature
    file_put_contents('payhere_log.txt', date('[Y-m-d H:i:s] ') . "Failed/Invalid: Order $order_id Status $status_code\n", FILE_APPEND);
}
?>
