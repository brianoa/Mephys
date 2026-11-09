<?php
declare(strict_types=1);

require_once __DIR__ . '/_public-bootstrap.php';
global $pdo;

$customer = mephysShopRequireCustomer('account.php');
$orderId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$orderId) {
    http_response_code(404);
    exit('Order not found.');
}

$orderStmt = $pdo->prepare(
    'SELECT o.*,
            p.status AS latest_payment_status,
            p.provider_reference AS mpesa_receipt,
            p.response_description AS payment_message,
            p.phone AS payment_phone
     FROM shop_orders o
     LEFT JOIN shop_payments p ON p.id = (
         SELECT MAX(p2.id) FROM shop_payments p2 WHERE p2.order_id = o.id
     )
     WHERE o.id = :id AND o.customer_id = :customer_id
     LIMIT 1'
);
$orderStmt->execute([
    ':id' => $orderId,
    ':customer_id' => (int) $customer['id'],
]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

$itemStmt = $pdo->prepare(
    'SELECT product_name, unit_price, quantity, line_total
     FROM shop_order_items WHERE order_id = :id ORDER BY id ASC'
);
$itemStmt->execute([':id' => $orderId]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

$shopPageTitle = 'Order status';
require __DIR__ . '/_public-header.php';
?>
<section class="py-5">
    <div class="container" style="max-width:850px">
        <div class="border rounded-4 p-4 p-md-5">
            <div class="shop-eyebrow mb-2">Order <?= mephysShopPublicEscape($order['order_number']) ?></div>
            <h1 class="shop-heading mb-3">Order and payment status</h1>

            <?php if (isset($_GET['prompt'])): ?>
                <div class="alert alert-info" role="status">
                    <strong>M-Pesa request sent.</strong> Check <?= mephysShopPublicEscape($order['payment_phone'] ?: 'your phone') ?> for the STK prompt. Enter your M-Pesa PIN on your phone to approve the payment. Never share your PIN with anyone.
                </div>
            <?php elseif (isset($_GET['error'])): ?>
                <div class="alert alert-warning" role="alert">
                    We could not start the M-Pesa request. Your order has been saved, but payment has not been received. Please contact Mephys and quote order <?= mephysShopPublicEscape($order['order_number']) ?>.
                </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-sm-6"><div class="small shop-muted">Payment</div><strong class="text-uppercase"><?= mephysShopPublicEscape($order['payment_status']) ?></strong></div>
                <div class="col-sm-6"><div class="small shop-muted">Order fulfilment</div><strong class="text-uppercase"><?= mephysShopPublicEscape(str_replace('_', ' ', $order['status'])) ?></strong></div>
                <div class="col-sm-6"><div class="small shop-muted">M-Pesa receipt</div><strong><?= mephysShopPublicEscape($order['mpesa_receipt'] ?: 'Not yet available') ?></strong></div>
                <div class="col-sm-6"><div class="small shop-muted">Total</div><strong class="text-primary"><?= mephysShopPublicMoney($order['total_amount']) ?></strong></div>
            </div>

            <h2 class="h5 fw-bold">Items ordered</h2>
            <?php foreach ($items as $item): ?>
                <div class="d-flex justify-content-between border-bottom py-2 gap-3">
                    <div><?= mephysShopPublicEscape($item['product_name']) ?> <span class="small shop-muted">× <?= (int) $item['quantity'] ?></span></div>
                    <strong><?= mephysShopPublicMoney($item['line_total']) ?></strong>
                </div>
            <?php endforeach; ?>

            <?php if ($order['payment_status'] === 'paid'): ?>
                <div class="alert alert-success mt-4 mb-0">Payment confirmed. Thank you for shopping with Mephys.</div>
            <?php elseif ($order['payment_status'] === 'failed'): ?>
                <div class="alert alert-warning mt-4 mb-0">The payment was not completed. Please contact Mephys before placing another order to avoid duplicate orders.</div>
            <?php else: ?>
                <div class="alert alert-info mt-4 mb-0">Payment is awaiting confirmation. If the M-Pesa prompt is on your phone, approve it with your PIN. This page refreshes automatically.</div>
            <?php endif; ?>

            <div class="mt-4 d-flex flex-wrap gap-2">
                <a href="account.php" class="btn btn-outline-primary">My account and orders</a>
                <a href="index.php" class="btn btn-primary">Continue shopping</a>
            </div>
        </div>
    </div>
</section>
<?php if ($order['payment_status'] === 'pending'): ?>
<script>setTimeout(function () { window.location.reload(); }, 10000);</script>
<?php endif; ?>
<?php require __DIR__ . '/_public-footer.php'; ?>
