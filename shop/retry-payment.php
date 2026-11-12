<?php
declare(strict_types=1);

require_once __DIR__ . '/_public-bootstrap.php';
require_once __DIR__ . '/_mpesa.php';

global $pdo;
$customer = mephysShopRequireCustomer('account.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
mephysShopPublicVerifyCsrf();
$orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$orderId) {
    http_response_code(400);
    exit('Invalid order.');
}
$returnUrl = 'order-status.php?id=' . $orderId;
$config = mephysMpesaConfig();
try {
    mephysMpesaAssertConfigured($config);
} catch (Throwable $e) {
    error_log('Mephys M-Pesa retry config: ' . $e->getMessage());
    header('Location: ' . $returnUrl . '&retry_error=config');
    exit;
}

// Guard against repeat taps and duplicate simultaneous STK requests.
$retryKey = 'mephys_mpesa_retry_' . $orderId;
if ((int) ($_SESSION[$retryKey] ?? 0) > time() - 45) {
    header('Location: ' . $returnUrl . '&retry_error=slow');
    exit;
}

try {
    $pdo->beginTransaction();
    $orderStmt = $pdo->prepare(
        'SELECT id, order_number, customer_phone, total_amount, payment_status
         FROM shop_orders WHERE id = :id AND customer_id = :customer_id LIMIT 1 FOR UPDATE'
    );
    $orderStmt->execute([':id' => $orderId, ':customer_id' => (int) $customer['id']]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        $pdo->rollBack();
        http_response_code(404);
        exit('Order not found.');
    }
    if ($order['payment_status'] === 'paid') {
        $pdo->rollBack();
        header('Location: ' . $returnUrl);
        exit;
    }
    $lastStmt = $pdo->prepare(
        'SELECT status FROM shop_payments WHERE order_id = :order_id ORDER BY id DESC LIMIT 1 FOR UPDATE'
    );
    $lastStmt->execute([':order_id' => $orderId]);
    $lastStatus = (string) ($lastStmt->fetchColumn() ?: '');
    if ($order['payment_status'] !== 'failed' || !in_array($lastStatus, ['failed', 'cancelled'], true)) {
        $pdo->rollBack();
        header('Location: ' . $returnUrl . '&retry_error=pending');
        exit;
    }
    $amount = (float) $order['total_amount'];
    if ($amount < 1 || $amount > 150000 || abs($amount - round($amount)) > .001) {
        throw new RuntimeException('Invalid retry amount.');
    }
    $phone = mephysMpesaPhone((string) $order['customer_phone']);
    $payment = $pdo->prepare(
        "INSERT INTO shop_payments
          (order_id, payment_provider, payment_method, amount, currency, status, phone, created_at, updated_at)
          VALUES (:id, 'safaricom_daraja', 'mpesa', :amount, 'KES', 'initiated', :phone, NOW(), NOW())"
    );
    $payment->execute([':id' => $orderId, ':amount' => $amount, ':phone' => $phone]);
    $paymentId = (int) $pdo->lastInsertId();
    $pdo->prepare("UPDATE shop_orders SET payment_status = 'pending', updated_at = NOW() WHERE id = :id")
        ->execute([':id' => $orderId]);
    $pdo->commit();
    $_SESSION[$retryKey] = time();

    try {
        $response = mephysMpesaStkPush($config, $phone, (int) round($amount), (string) $order['order_number']);
        $checkoutId = (string) ($response['CheckoutRequestID'] ?? '');
        $responseCode = (string) ($response['ResponseCode'] ?? '');
        if ($responseCode !== '0' || $checkoutId === '') {
            throw new RuntimeException((string) ($response['ResponseDescription'] ?? 'Safaricom rejected the payment request.'));
        }
        $pdo->prepare(
            "UPDATE shop_payments
             SET checkout_request_id = :checkout_id, merchant_request_id = :merchant_id,
                 response_code = :code, response_description = :message,
                 status = 'pending', updated_at = NOW() WHERE id = :id"
        )->execute([
            ':checkout_id' => $checkoutId,
            ':merchant_id' => (string) ($response['MerchantRequestID'] ?? ''),
            ':code' => $responseCode,
            ':message' => substr((string) ($response['CustomerMessage'] ?? 'M-Pesa prompt sent.'), 0, 500),
            ':id' => $paymentId,
        ]);
        header('Location: ' . $returnUrl . '&prompt=1');
        exit;
    } catch (Throwable $e) {
        error_log('Mephys retry STK push failed for order ' . $orderId . ': ' . $e->getMessage());
        $pdo->prepare("UPDATE shop_payments SET status = 'failed', response_description = :description, updated_at = NOW() WHERE id = :id")
            ->execute([':id' => $paymentId, ':description' => substr($e->getMessage(), 0, 500)]);
        $pdo->prepare("UPDATE shop_orders SET payment_status = 'failed', updated_at = NOW() WHERE id = :id AND payment_status <> 'paid'")
            ->execute([':id' => $orderId]);
        header('Location: ' . $returnUrl . '&retry_error=request');
        exit;
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Mephys retry validation failed for order ' . $orderId . ': ' . $e->getMessage());
    header('Location: ' . $returnUrl . '&retry_error=order');
    exit;
}
