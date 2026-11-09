<?php
/**
 * Handles staff-side fulfilment updates from crud.php.
 * Payment state is intentionally NOT editable here; it is set by verified Daraja callbacks.
 */

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' ||
    (string) ($_POST['management_action'] ?? '') !== 'update_order'
) {
    return;
}

$redirect = 'crud.php?page=orders';

if (!mephysVerifyCsrfToken($_POST['csrf_token'] ?? null)) {
    header('Location: ' . $redirect . '&error=csrf');
    exit;
}

$orderId = filter_var(
    $_POST['order_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$allowedOrderStatuses = [
    'pending',
    'confirmed',
    'processing',
    'ready_for_dispatch',
    'dispatched',
    'delivered',
    'cancelled',
];

$newStatus = trim((string) ($_POST['order_status'] ?? ''));
$shippingProvider = trim((string) ($_POST['shipping_provider'] ?? ''));
$trackingNumber = trim((string) ($_POST['tracking_number'] ?? ''));
$staffNote = trim((string) ($_POST['staff_note'] ?? ''));
$historyMessage = trim((string) ($_POST['history_message'] ?? ''));

if (!$orderId || !in_array($newStatus, $allowedOrderStatuses, true)) {
    header('Location: ' . $redirect . '&error=invalid');
    exit;
}

if (strlen($shippingProvider) > 150 || strlen($trackingNumber) > 120 || strlen($staffNote) > 10000 || strlen($historyMessage) > 1000) {
    header('Location: ' . $redirect . '&error=length');
    exit;
}

try {
    $pdo->beginTransaction();

    $findOrder = $pdo->prepare(
        'SELECT id, order_number, status
         FROM shop_orders
         WHERE id = :id
         LIMIT 1
         FOR UPDATE'
    );
    $findOrder->execute([':id' => $orderId]);
    $existingOrder = $findOrder->fetch(PDO::FETCH_ASSOC);

    if (!$existingOrder) {
        $pdo->rollBack();
        header('Location: ' . $redirect . '&error=notfound');
        exit;
    }

    $oldStatus = (string) $existingOrder['status'];

    $updateOrder = $pdo->prepare(
        'UPDATE shop_orders
         SET status = :status,
             shipping_provider = :shipping_provider,
             tracking_number = :tracking_number,
             staff_note = :staff_note,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );

    $updateOrder->execute([
        ':status' => $newStatus,
        ':shipping_provider' => $shippingProvider !== '' ? $shippingProvider : null,
        ':tracking_number' => $trackingNumber !== '' ? $trackingNumber : null,
        ':staff_note' => $staffNote !== '' ? $staffNote : null,
        ':id' => $orderId,
    ]);

    if ($oldStatus !== $newStatus) {
        if ($historyMessage === '') {
            $historyMessage = 'Order status updated to ' . str_replace('_', ' ', $newStatus) . '.';
        }

        $history = $pdo->prepare(
            'INSERT INTO shop_order_status_history
                (order_id, old_status, new_status, message, is_customer_visible, changed_by_staff_id)
             VALUES
                (:order_id, :old_status, :new_status, :message, 1, :staff_id)'
        );
        $history->execute([
            ':order_id' => $orderId,
            ':old_status' => $oldStatus,
            ':new_status' => $newStatus,
            ':message' => $historyMessage,
            ':staff_id' => (int) ($staff['id'] ?? 0),
        ]);
    }

    $pdo->commit();
    header('Location: ' . $redirect . '&updated=1');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Mephys staff order update failed for order ID ' . (int) $orderId . ': ' . $e->getMessage());
    header('Location: ' . $redirect . '&error=save');
    exit;
}
