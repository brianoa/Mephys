<?php
declare(strict_types=1);
require_once __DIR__ . '/_public-bootstrap.php';

$customer = mephysShopRequireCustomer('account.php');
global $pdo;

$ordersStmt = $pdo->prepare(
    'SELECT o.id, o.order_number, o.total_amount, o.status, o.payment_status, o.ordered_at,
            (SELECT COUNT(*) FROM shop_order_items oi WHERE oi.order_id = o.id) AS item_count
     FROM shop_orders o
     WHERE o.customer_id = :customer_id
     ORDER BY o.ordered_at DESC, o.id DESC
     LIMIT 20'
);
$ordersStmt->execute([':customer_id' => (int) $customer['id']]);
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

$shopPageTitle = 'My account';
require __DIR__ . '/_public-header.php';
?>
<section class="py-5">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
            <div>
                <div class="shop-eyebrow mb-2">Customer account</div>
                <h1 class="shop-heading mb-1">Welcome, <?= mephysShopPublicEscape($customer['first_name']) ?></h1>
                <p class="shop-muted mb-0">Manage your profile, delivery details and Mephys orders.</p>
            </div>
            <a class="btn btn-primary" href="cart.php"><i class="fa-solid fa-cart-shopping me-2"></i>View my cart <span class="badge text-bg-light ms-1"><?= mephysShopPublicCartCount() ?></span></a>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="border rounded-4 p-4 h-100">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width:54px;height:54px;font-size:1.35rem"><i class="fa-regular fa-user"></i></div>
                        <div><h2 class="h5 fw-bold mb-0"><?= mephysShopPublicEscape($customer['first_name'] . ' ' . $customer['last_name']) ?></h2><span class="small text-success"><i class="fa-solid fa-circle-check me-1"></i>Signed in</span></div>
                    </div>
                    <hr>
                    <div class="small text-muted mb-1">Email address</div>
                    <div class="mb-3 fw-semibold"><?= mephysShopPublicEscape($customer['email']) ?></div>
                    <div class="small text-muted mb-1">Phone number</div>
                    <div class="mb-3 fw-semibold"><?= mephysShopPublicEscape($customer['phone'] ?: 'Not provided') ?></div>
                    <div class="small text-muted mb-1">Account status</div>
                    <div class="fw-semibold text-capitalize"><?= mephysShopPublicEscape($customer['status']) ?></div>
                    <hr>
                    <div class="small text-muted mb-2">Default delivery address</div>
                    <?php if (!empty($customer['address_line1'])): ?>
                        <div class="fw-semibold"><?= mephysShopPublicEscape($customer['recipient_name'] ?: ($customer['first_name'] . ' ' . $customer['last_name'])) ?></div>
                        <div><?= mephysShopPublicEscape($customer['address_line1']) ?></div>
                        <?php if (!empty($customer['address_line2'])): ?><div><?= mephysShopPublicEscape($customer['address_line2']) ?></div><?php endif; ?>
                        <div><?= mephysShopPublicEscape($customer['town_city']) ?><?= !empty($customer['county']) ? ', ' . mephysShopPublicEscape($customer['county']) : '' ?></div>
                        <?php if (!empty($customer['postal_code'])): ?><div><?= mephysShopPublicEscape($customer['postal_code']) ?></div><?php endif; ?>
                    <?php else: ?>
                        <p class="shop-muted mb-0">You have not saved a delivery address yet. You can provide one during checkout.</p>
                    <?php endif; ?>
                    <div class="mt-4 d-grid gap-2">
                        <a class="btn btn-outline-primary" href="cart.php">Open shopping cart</a>
                        <a class="btn btn-outline-secondary" href="index.php">Continue shopping</a>
                        <form method="POST" action="logout.php">
                            <?= mephysShopPublicCsrfField() ?>
                            <button class="btn btn-outline-danger w-100" type="submit"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Sign out</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="border rounded-4 p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div><div class="shop-eyebrow mb-1">Purchase history</div><h2 class="h4 fw-bold mb-0">My orders</h2></div>
                        <span class="badge text-bg-light"><?= count($orders) ?> recent order<?= count($orders) === 1 ? '' : 's' ?></span>
                    </div>
                    <?php if (!$orders): ?>
                        <div class="text-center py-5">
                            <i class="fa-solid fa-box-open fa-2x text-primary mb-3"></i>
                            <h3 class="h5 fw-bold">No orders yet</h3>
                            <p class="shop-muted">When you place an order, it will appear here.</p>
                            <a class="btn btn-primary" href="index.php">Browse products</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead class="table-light"><tr><th>Order</th><th>Date</th><th>Items</th><th>Total</th><th>Order status</th><th>Payment</th></tr></thead>
                                <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td class="fw-semibold">#<?= mephysShopPublicEscape($order['order_number']) ?></td>
                                        <td class="text-nowrap small"><?= mephysShopPublicEscape(date('d M Y', strtotime((string) $order['ordered_at']))) ?></td>
                                        <td><?= (int) $order['item_count'] ?></td>
                                        <td class="text-nowrap fw-semibold"><?= mephysShopPublicMoney($order['total_amount']) ?></td>
                                        <td><span class="badge text-bg-secondary text-capitalize"><?= mephysShopPublicEscape(str_replace('_', ' ', $order['status'])) ?></span></td>
                                        <td><span class="badge <?= $order['payment_status'] === 'paid' ? 'text-bg-success' : 'text-bg-warning' ?> text-capitalize"><?= mephysShopPublicEscape(str_replace('_', ' ', $order['payment_status'])) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="small shop-muted mb-0">Showing your 20 most recent orders.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/_public-footer.php'; ?>
