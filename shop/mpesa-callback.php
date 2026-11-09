<?php
declare(strict_types=1);

require_once __DIR__ . '/_public-bootstrap.php';
require_once __DIR__ . '/_mpesa.php';

global $pdo;

header('Content-Type: application/json; charset=utf-8');

/**
 * Return a Daraja callback acknowledgement.
 */
function mephysMpesaCallbackReply(int $code, string $description, int $httpStatus = 200): never
{
    http_response_code($httpStatus);
    echo json_encode(
        ['ResultCode' => $code, 'ResultDesc' => $description],
        JSON_UNESCAPED_SLASHES
    );
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);

if (!is_array($payload)) {
    mephysMpesaCallbackReply(1, 'Invalid callback payload', 400);
}

$callback = $payload['Body']['stkCallback'] ?? null;

if (!is_array($callback)) {
    mephysMpesaCallbackReply(1, 'Invalid callback body', 400);
}

$checkoutRequestId = trim((string) ($callback['CheckoutRequestID'] ?? ''));
$merchantRequestId = trim((string) ($callback['MerchantRequestID'] ?? ''));
$resultCode = (int) ($callback['ResultCode'] ?? 1);
$resultDescription = substr(
    (string) ($callback['ResultDesc'] ?? 'M-Pesa payment result received.'),
    0,
    500
);

if ($checkoutRequestId === '' || $merchantRequestId === '') {
    mephysMpesaCallbackReply(1, 'Missing payment request identifiers', 400);
}

try {
    $findPayment = $pdo->prepare(
        'SELECT py.id, py.order_id, py.amount, py.status, py.phone,
                py.merchant_request_id, py.checkout_request_id
         FROM shop_payments py
         WHERE py.checkout_request_id = :checkout_id
         LIMIT 1'
    );
    $findPayment->execute([':checkout_id' => $checkoutRequestId]);
    $payment = $findPayment->fetch(PDO::FETCH_ASSOC);

    // Acknowledge unknown callbacks so Safaricom does not keep retrying them.
    if (!$payment) {
        error_log('Mephys received an unknown M-Pesa callback: ' . $checkoutRequestId);
        mephysMpesaCallbackReply(0, 'Callback acknowledged');
    }

    // Both request identifiers must match the payment request saved at checkout.
    if (
        (string) ($payment['merchant_request_id'] ?? '') === '' ||
        !hash_equals((string) $payment['merchant_request_id'], $merchantRequestId)
    ) {
        error_log('Mephys M-Pesa callback merchant request mismatch for ' . $checkoutRequestId);
        mephysMpesaCallbackReply(0, 'Callback acknowledged for review');
    }

    // Handle a failed or cancelled STK request without overwriting a successful payment.
    if ($resultCode !== 0) {
        $pdo->beginTransaction();

        $lockPayment = $pdo->prepare(
            'SELECT status FROM shop_payments WHERE id = :id FOR UPDATE'
        );
        $lockPayment->execute([':id' => $payment['id']]);
        $currentStatus = (string) $lockPayment->fetchColumn();

        if ($currentStatus !== 'successful') {
            $updatePayment = $pdo->prepare(
                "UPDATE shop_payments
                 SET status = 'failed',
                     response_code = :code,
                     response_description = :description,
                     updated_at = NOW()
                 WHERE id = :id"
            );
            $updatePayment->execute([
                ':code' => (string) $resultCode,
                ':description' => $resultDescription,
                ':id' => $payment['id'],
            ]);

            $updateOrder = $pdo->prepare(
                "UPDATE shop_orders
                 SET payment_status = 'failed', updated_at = NOW()
                 WHERE id = :id AND payment_status <> 'paid'"
            );
            $updateOrder->execute([':id' => $payment['order_id']]);
        }

        $pdo->commit();
        mephysMpesaCallbackReply(0, 'Payment result recorded');
    }

    // Extract the successful-payment metadata supplied by Daraja.
    $items = $callback['CallbackMetadata']['Item'] ?? [];
    $metadata = [];

    foreach (is_array($items) ? $items : [] as $item) {
        if (is_array($item) && isset($item['Name'])) {
            $metadata[(string) $item['Name']] = $item['Value'] ?? null;
        }
    }

    $paidAmount = isset($metadata['Amount']) ? (float) $metadata['Amount'] : 0.0;
    $receipt = trim((string) ($metadata['MpesaReceiptNumber'] ?? ''));
    $paidPhoneRaw = trim((string) ($metadata['PhoneNumber'] ?? ''));

    if ($receipt === '' || $paidAmount <= 0 || $paidPhoneRaw === '') {
        error_log('Mephys M-Pesa callback is missing payment metadata for ' . $checkoutRequestId);

        // Keep the payment pending and record why it needs reconciliation.
        $review = $pdo->prepare(
            "UPDATE shop_payments
             SET response_code = 'CALLBACK_METADATA_MISSING',
                 response_description = :description,
                 updated_at = NOW()
             WHERE id = :id AND status <> 'successful'"
        );
        $review->execute([
            ':description' => 'Successful callback did not contain all expected payment details. Manual verification required.',
            ':id' => $payment['id'],
        ]);

        mephysMpesaCallbackReply(0, 'Payment requires verification');
    }

    // Normalize both numbers before comparing them.
    try {
        $paidPhone = mephysMpesaPhone($paidPhoneRaw);
        $requestedPhone = mephysMpesaPhone((string) $payment['phone']);
    } catch (Throwable $phoneError) {
        error_log(
            'Mephys M-Pesa phone validation failed for payment ' .
            $payment['id'] . ': ' . $phoneError->getMessage()
        );
        mephysMpesaCallbackReply(0, 'Payment requires manual verification');
    }

    if (
        abs($paidAmount - (float) $payment['amount']) > 0.01 ||
        !hash_equals($requestedPhone, $paidPhone)
    ) {
        error_log('Mephys M-Pesa amount or phone mismatch for payment ' . $payment['id']);

        $mismatch = $pdo->prepare(
            "UPDATE shop_payments
             SET response_code = 'VERIFY_MISMATCH',
                 response_description = :description,
                 updated_at = NOW()
             WHERE id = :id AND status <> 'successful'"
        );
        $mismatch->execute([
            ':description' => 'Safaricom callback amount or phone did not match the payment request. Manual review required.',
            ':id' => $payment['id'],
        ]);

        mephysMpesaCallbackReply(0, 'Payment requires review');
    }

    // Verify the transaction with Safaricom before marking the order as paid.
    $query = mephysMpesaStkQuery(mephysMpesaConfig(), $checkoutRequestId);

    $queryResultCode = (string) ($query['ResultCode'] ?? '');
    $queryCheckoutId = trim((string) ($query['CheckoutRequestID'] ?? ''));
    $queryMerchantId = trim((string) ($query['MerchantRequestID'] ?? ''));

    if (
        $queryResultCode !== '0' ||
        ($queryCheckoutId !== '' && !hash_equals($checkoutRequestId, $queryCheckoutId)) ||
        ($queryMerchantId !== '' && !hash_equals($merchantRequestId, $queryMerchantId))
    ) {
        error_log(
            'Mephys STK query did not verify payment ' . $checkoutRequestId .
            '; response: ' . json_encode($query)
        );
        mephysMpesaCallbackReply(0, 'Payment is awaiting verification');
    }

    // Lock the payment row so simultaneous or repeated callbacks cannot deduct stock twice.
    $pdo->beginTransaction();

    $lockPayment = $pdo->prepare(
        'SELECT status FROM shop_payments WHERE id = :id FOR UPDATE'
    );
    $lockPayment->execute([':id' => $payment['id']]);
    $currentStatus = (string) $lockPayment->fetchColumn();

    if ($currentStatus === 'successful') {
        $pdo->commit();
        mephysMpesaCallbackReply(0, 'Payment already processed');
    }

    $lockOrder = $pdo->prepare(
        'SELECT status FROM shop_orders WHERE id = :id FOR UPDATE'
    );
    $lockOrder->execute([':id' => $payment['order_id']]);
    $oldOrderStatus = (string) $lockOrder->fetchColumn();

    if ($oldOrderStatus === '') {
        throw new RuntimeException('The order linked to this M-Pesa payment could not be found.');
    }

    $updatePayment = $pdo->prepare(
        "UPDATE shop_payments
         SET status = 'successful',
             provider_reference = :receipt,
             response_code = '0',
             response_description = :description,
             paid_at = NOW(),
             updated_at = NOW()
         WHERE id = :id AND status <> 'successful'"
    );
    $updatePayment->execute([
        ':receipt' => $receipt,
        ':description' => $resultDescription,
        ':id' => $payment['id'],
    ]);

    if ($updatePayment->rowCount() > 0) {
        // Deduct stock only once, after a confirmed successful payment.
        $orderItems = $pdo->prepare(
            'SELECT oi.product_id, oi.quantity, p.track_inventory
             FROM shop_order_items oi
             LEFT JOIN shop_products p ON p.id = oi.product_id
             WHERE oi.order_id = :order_id'
        );
        $orderItems->execute([':order_id' => $payment['order_id']]);

        // Use distinct placeholders for native PDO prepared-statement compatibility.
        $deductStock = $pdo->prepare(
            'UPDATE shop_products
             SET stock_quantity = stock_quantity - :deduct_quantity
             WHERE id = :product_id
               AND stock_quantity >= :required_quantity'
        );

        foreach ($orderItems->fetchAll(PDO::FETCH_ASSOC) as $line) {
            if (
                $line['product_id'] !== null &&
                (int) $line['track_inventory'] === 1
            ) {
                $quantity = (int) $line['quantity'];

                $deductStock->execute([
                    ':deduct_quantity' => $quantity,
                    ':product_id' => (int) $line['product_id'],
                    ':required_quantity' => $quantity,
                ]);

                if ($deductStock->rowCount() === 0) {
                    error_log(
                        'Mephys paid order ' . $payment['order_id'] .
                        ' needs stock review for product ' . $line['product_id']
                    );
                }
            }
        }

        $updateOrder = $pdo->prepare(
            "UPDATE shop_orders
             SET payment_status = 'paid',
                 status = 'processing',
                 updated_at = NOW()
             WHERE id = :id"
        );
        $updateOrder->execute([':id' => $payment['order_id']]);

        $history = $pdo->prepare(
            'INSERT INTO shop_order_status_history
             (order_id, old_status, new_status, message, is_customer_visible, created_at)
             VALUES (:order_id, :old_status, :new_status, :message, 1, NOW())'
        );
        $history->execute([
            ':order_id' => $payment['order_id'],
            ':old_status' => $oldOrderStatus,
            ':new_status' => 'processing',
            ':message' => 'M-Pesa payment confirmed. Order is being processed.',
        ]);
    }

    $pdo->commit();
    mephysMpesaCallbackReply(0, 'Payment confirmation processed');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Mephys M-Pesa callback error: ' . $e->getMessage());
    mephysMpesaCallbackReply(1, 'Callback processing failed', 500);
}
