
<?php
// Staff order management page, included by crud.php after staff authentication.

$orderStatuses = [
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'processing' => 'Processing',
    'ready_for_dispatch' => 'Ready for dispatch',
    'dispatched' => 'Dispatched',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
];

$paymentStatuses = [
    'unpaid' => 'Unpaid',
    'pending' => 'Payment pending',
    'paid' => 'Paid',
    'failed' => 'Payment failed',
    'partially_refunded' => 'Partially refunded',
    'refunded' => 'Refunded',
];

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$paymentFilter = trim((string) ($_GET['payment'] ?? ''));
$dateFrom = trim((string) ($_GET['from'] ?? ''));
$dateTo = trim((string) ($_GET['to'] ?? ''));

// Order to expand when View / update is clicked.
$openOrderId = isset($_GET['open_order'])
    ? max(0, (int) $_GET['open_order'])
    : 0;

if (!array_key_exists($statusFilter, ['' => ''] + $orderStatuses)) {
    $statusFilter = '';
}
if (!array_key_exists($paymentFilter, ['' => ''] + $paymentStatuses)) {
    $paymentFilter = '';
}
if ($dateFrom !== '' && !mephysValidDate($dateFrom)) {
    $dateFrom = '';
}
if ($dateTo !== '' && !mephysValidDate($dateTo)) {
    $dateTo = '';
}

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(o.order_number LIKE :q_order OR o.customer_first_name LIKE :q_first OR o.customer_last_name LIKE :q_last OR o.customer_email LIKE :q_email OR o.customer_phone LIKE :q_phone OR o.delivery_phone LIKE :q_delivery_phone)';

    $term = '%' . $search . '%';

    $params += [
        ':q_order' => $term,
        ':q_first' => $term,
        ':q_last' => $term,
        ':q_email' => $term,
        ':q_phone' => $term,
        ':q_delivery_phone' => $term,
    ];
}

if ($statusFilter !== '') {
    $where[] = 'o.status = :order_status_filter';
    $params[':order_status_filter'] = $statusFilter;
}

if ($paymentFilter !== '') {
    $where[] = 'o.payment_status = :payment_status_filter';
    $params[':payment_status_filter'] = $paymentFilter;
}

if ($dateFrom !== '') {
    $where[] = 'o.ordered_at >= :date_from';
    $params[':date_from'] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
    $where[] = 'o.ordered_at <= :date_to';
    $params[':date_to'] = $dateTo . ' 23:59:59';
}

$orderStats = [
    'total_orders' => 0,
    'paid_orders' => 0,
    'awaiting_payment' => 0,
    'failed_payments' => 0,
    'paid_revenue' => 0,
];

$orders = [];
$itemsByOrder = [];
$pageError = '';

try {
    $statsStmt = $pdo->query(
        "SELECT
            COUNT(*) AS total_orders,
            COALESCE(SUM(payment_status = 'paid'), 0) AS paid_orders,
            COALESCE(SUM(payment_status IN ('unpaid','pending')), 0) AS awaiting_payment,
            COALESCE(SUM(payment_status = 'failed'), 0) AS failed_payments,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) AS paid_revenue
         FROM shop_orders"
    );

    $orderStats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: $orderStats;

    $sql = "
        SELECT
            o.*,
            p.id AS latest_payment_id,
            p.payment_provider AS latest_payment_provider,
            p.payment_method AS latest_payment_method,
            p.status AS latest_payment_status,
            p.provider_reference AS mpesa_receipt,
            p.phone AS payment_phone,
            p.response_code AS payment_response_code,
            p.response_description AS payment_message,
            p.paid_at AS payment_paid_at,
            p.checkout_request_id,
            p.merchant_request_id
        FROM shop_orders o
        LEFT JOIN (
            SELECT p1.*
            FROM shop_payments p1
            INNER JOIN (
                SELECT order_id, MAX(id) AS latest_id
                FROM shop_payments
                GROUP BY order_id
            ) latest ON latest.latest_id = p1.id
        ) p ON p.order_id = o.id
    ";

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY o.ordered_at DESC, o.id DESC LIMIT 100';

    $ordersStmt = $pdo->prepare($sql);
    $ordersStmt->execute($params);
    $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($orders) {
        $orderIds = array_map(
            static fn(array $order): int => (int) $order['id'],
            $orders
        );

        $placeholders = [];
        $itemParams = [];

        foreach ($orderIds as $index => $id) {
            $placeholder = ':order_' . $index;
            $placeholders[] = $placeholder;
            $itemParams[$placeholder] = $id;
        }

        $itemSql = 'SELECT order_id, product_name, variant_snapshot, quantity, unit_price, line_total
                    FROM shop_order_items
                    WHERE order_id IN (' . implode(',', $placeholders) . ')
                    ORDER BY order_id DESC, id ASC';

        $itemsStmt = $pdo->prepare($itemSql);
        $itemsStmt->execute($itemParams);

        foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $itemsByOrder[(int) $item['order_id']][] = $item;
        }
    }
} catch (Throwable $e) {
    error_log('Mephys orders page error: ' . $e->getMessage());
    $pageError = 'The order records could not be loaded. Check the PHP server error log.';
}

$formatKes = static fn($amount): string =>
    'KSh ' . number_format((float) $amount, 2);

$paymentBadgeClasses = [
    'unpaid' => 'text-bg-secondary',
    'pending' => 'text-bg-warning',
    'paid' => 'text-bg-success',
    'failed' => 'text-bg-danger',
    'partially_refunded' => 'text-bg-info',
    'refunded' => 'text-bg-dark',
];

$orderBadgeClasses = [
    'pending' => 'text-bg-warning',
    'confirmed' => 'text-bg-primary',
    'processing' => 'text-bg-info',
    'ready_for_dispatch' => 'text-bg-info',
    'dispatched' => 'text-bg-primary',
    'delivered' => 'text-bg-success',
    'cancelled' => 'text-bg-secondary',
];
?>

<div class="welcome-row">
    <div>
        <h1 class="page-heading">Online Shop Orders</h1>
        <div class="page-subtitle">
            Track every customer order, payment result, delivery address and dispatch update.
        </div>
    </div>

    <a href="crud.php?page=payments" class="btn btn-outline-primary">
        <i class="bi bi-credit-card me-1"></i>View payment records
    </a>
</div>

<?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success">
        Order fulfilment details were saved successfully.
    </div>
<?php elseif (isset($_GET['error'])): ?>
    <div class="alert alert-danger">
        <?php
        $errorMessages = [
            'csrf' => 'The security token expired. Refresh this page and try again.',
            'invalid' => 'The order or status submitted was invalid.',
            'length' => 'One of the tracking or notes fields is too long.',
            'notfound' => 'That order could not be found.',
            'save' => 'The order update could not be saved. Check the PHP server error log.',
        ];

        echo mephysEscape(
            $errorMessages[(string) $_GET['error']] ?? 'The update could not be completed.'
        );
        ?>
    </div>
<?php endif; ?>

<?php if ($pageError !== ''): ?>

    <div class="alert alert-danger">
        <?= mephysEscape($pageError) ?>
    </div>

<?php else: ?>

<div class="stats-grid commerce-stats">

    <div class="stat-card">
        <div class="stat-label">All orders</div>
        <div class="stat-number"><?= (int) $orderStats['total_orders'] ?></div>
        <div class="stat-indicator indicator-total"></div>
    </div>

    <div class="stat-card">
        <div class="stat-label">Paid orders</div>
        <div class="stat-number"><?= (int) $orderStats['paid_orders'] ?></div>
        <div class="stat-indicator indicator-completed"></div>
    </div>

    <div class="stat-card">
        <div class="stat-label">Awaiting payment</div>
        <div class="stat-number"><?= (int) $orderStats['awaiting_payment'] ?></div>
        <div class="stat-indicator indicator-pending"></div>
    </div>

    <div class="stat-card">
        <div class="stat-label">Failed payments</div>
        <div class="stat-number"><?= (int) $orderStats['failed_payments'] ?></div>
        <div class="stat-indicator indicator-cancelled"></div>
    </div>

    <div class="stat-card">
        <div class="stat-label">Collected revenue</div>
        <div class="stat-number" style="font-size:19px">
            <?= mephysEscape($formatKes($orderStats['paid_revenue'])) ?>
        </div>
        <div class="stat-indicator indicator-confirmed"></div>
    </div>

</div>

<section class="panel">

    <div class="panel-header">
        <div>
            <h2 class="section-heading">Order register</h2>
            <div class="results-count">
                Latest 100 matching orders. Payment is confirmed from the payment callback, not from the order status.
            </div>
        </div>
    </div>

    <form method="GET" class="filters"
          style="grid-template-columns:minmax(190px,1.5fr) repeat(4,minmax(120px,1fr)) auto">

        <input type="hidden" name="page" value="orders">

        <div class="search-filter">
            <label class="filter-label" for="order-q">Find customer or order</label>
            <input class="form-control" id="order-q" name="q"
                   value="<?= mephysEscape($search) ?>"
                   placeholder="Order no., name, email or phone">
        </div>

        <div>
            <label class="filter-label" for="order-status">Order status</label>
            <select class="form-select" id="order-status" name="status">
                <option value="">All order statuses</option>
                <?php foreach ($orderStatuses as $value => $label): ?>
                    <option value="<?= mephysEscape($value) ?>"
                        <?= $statusFilter === $value ? 'selected' : '' ?>>
                        <?= mephysEscape($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="filter-label" for="payment-status">Payment status</label>
            <select class="form-select" id="payment-status" name="payment">
                <option value="">All payment statuses</option>
                <?php foreach ($paymentStatuses as $value => $label): ?>
                    <option value="<?= mephysEscape($value) ?>"
                        <?= $paymentFilter === $value ? 'selected' : '' ?>>
                        <?= mephysEscape($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="filter-label" for="order-from">From date</label>
            <input class="form-control" type="date" id="order-from"
                   name="from" value="<?= mephysEscape($dateFrom) ?>">
        </div>

        <div>
            <label class="filter-label" for="order-to">To date</label>
            <input class="form-control" type="date" id="order-to"
                   name="to" value="<?= mephysEscape($dateTo) ?>">
        </div>

        <div class="filter-buttons">
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn btn-outline-secondary" href="crud.php?page=orders">Reset</a>
        </div>

    </form>

    <div class="table-wrap">

        <table class="appointments-table" style="min-width:1200px">

            <thead>
                <tr>
                    <th>Order / customer</th>
                    <th>Items</th>
                    <th>Placed</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Order progress</th>
                    <th>Manage</th>
                </tr>
            </thead>

            <tbody>

            <?php if (!$orders): ?>

                <tr>
                    <td colspan="7" class="empty-state">
                        No orders match these filters.
                    </td>
                </tr>

            <?php else: foreach ($orders as $order): ?>

                <?php
                $orderId = (int) $order['id'];
                $orderItems = $itemsByOrder[$orderId] ?? [];

                $paymentStatus = (string) ($order['payment_status'] ?? 'unpaid');
                $orderStatus = (string) ($order['status'] ?? 'pending');

                $itemUnits = 0;

                foreach ($orderItems as $orderItem) {
                    $itemUnits += (int) $orderItem['quantity'];
                }

                $coordsAvailable =
                    $order['delivery_latitude'] !== null
                    && $order['delivery_longitude'] !== null
                    && $order['delivery_latitude'] !== ''
                    && $order['delivery_longitude'] !== '';

                // Preserve active filters when opening a specific order.
                $openOrderUrl = 'crud.php?' . http_build_query(
                    array_merge($_GET, [
                        'page' => 'orders',
                        'open_order' => $orderId
                    ])
                ) . '#order-' . $orderId;
                ?>

                <tr>

                    <td>
                        <div class="patient-name">
                            <?= mephysEscape($order['order_number']) ?>
                        </div>

                        <div class="patient-name mt-1">
                            <?= mephysEscape(
                                trim($order['customer_first_name'] . ' ' . $order['customer_last_name'])
                            ) ?>
                        </div>

                        <div class="patient-email">
                            <?= mephysEscape($order['customer_email']) ?>
                        </div>

                        <div class="patient-email">
                            Customer: <?= mephysEscape($order['customer_phone']) ?>
                        </div>
                    </td>

                    <td>
                        <strong><?= count($orderItems) ?></strong> line(s)
                        <div class="patient-email"><?= $itemUnits ?> unit(s)</div>
                    </td>

                    <td>
                        <div class="appointment-date">
                            <?= mephysEscape(date('d M Y', strtotime((string) $order['ordered_at']))) ?>
                        </div>
                        <div class="appointment-time">
                            <?= mephysEscape(date('H:i', strtotime((string) $order['ordered_at']))) ?>
                        </div>
                    </td>

                    <td>
                        <strong><?= mephysEscape($formatKes($order['total_amount'])) ?></strong>
                        <div class="patient-email">
                            Subtotal <?= mephysEscape($formatKes($order['subtotal'])) ?>
                        </div>
                    </td>

                    <td>
                        <span class="badge rounded-pill <?= mephysEscape(
                            $paymentBadgeClasses[$paymentStatus] ?? 'text-bg-secondary'
                        ) ?>">
                            <?= mephysEscape(
                                $paymentStatuses[$paymentStatus] ?? ucfirst($paymentStatus)
                            ) ?>
                        </span>

                        <div class="patient-email mt-1">
                            <?= mephysEscape(strtoupper(
                                (string) ($order['latest_payment_method'] ?? $order['payment_method'] ?? '')
                            )) ?>
                        </div>

                        <?php if (!empty($order['mpesa_receipt'])): ?>

                            <div class="patient-email">
                                Receipt: <strong><?= mephysEscape($order['mpesa_receipt']) ?></strong>
                            </div>

                        <?php elseif (!empty($order['payment_message'])): ?>

                            <div class="patient-email"
                                 title="<?= mephysEscape($order['payment_message']) ?>">
                                <?= mephysEscape(
                                    function_exists('mb_strimwidth')
                                        ? mb_strimwidth((string) $order['payment_message'], 0, 65, '…')
                                        : substr((string) $order['payment_message'], 0, 65)
                                ) ?>
                            </div>

                        <?php endif; ?>
                    </td>

                    <td>
                        <span class="badge rounded-pill <?= mephysEscape(
                            $orderBadgeClasses[$orderStatus] ?? 'text-bg-secondary'
                        ) ?>">
                            <?= mephysEscape(
                                $orderStatuses[$orderStatus] ?? ucfirst($orderStatus)
                            ) ?>
                        </span>
                    </td>

                    <td>
                        <a class="btn btn-sm btn-outline-primary"
                           href="<?= mephysEscape($openOrderUrl) ?>">
                            <i class="bi bi-eye me-1"></i>View / update
                        </a>
                    </td>

                </tr>

                <tr id="order-<?= $orderId ?>">

                    <td colspan="7" style="padding:0 13px 15px;background:#fbfcfe">

                        <details
                            id="order-details-<?= $orderId ?>"
                            <?= $openOrderId === $orderId ? 'open' : '' ?>
                        >

                            <summary style="cursor:pointer;color:#2d68ce;font-weight:700;padding:8px 0">
                                Order details, delivery address, payment diagnostics and fulfilment
                            </summary>

                            <div class="row g-3 pt-2">

                                <div class="col-lg-4">

                                    <div class="fw-bold mb-1">Delivery destination</div>

                                    <div>
                                        <?= mephysEscape($order['delivery_recipient_name']) ?>
                                        ·
                                        <?= mephysEscape($order['delivery_phone']) ?>
                                    </div>

                                    <div><?= mephysEscape($order['delivery_address_line1']) ?></div>

                                    <?php if (!empty($order['delivery_address_line2'])): ?>
                                        <div><?= mephysEscape($order['delivery_address_line2']) ?></div>
                                    <?php endif; ?>

                                    <div>
                                        <?= mephysEscape($order['delivery_town_city']) ?><?= !empty($order['delivery_county']) ? ', ' . mephysEscape($order['delivery_county']) : '' ?><?= !empty($order['delivery_postal_code']) ? ' ' . mephysEscape($order['delivery_postal_code']) : '' ?>
                                    </div>

                                    <?php if (!empty($order['delivery_instructions'])): ?>
                                        <div class="small text-secondary mt-1">
                                            <strong>Directions:</strong>
                                            <?= nl2br(mephysEscape($order['delivery_instructions'])) ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($coordsAvailable): ?>
                                        <a class="small"
                                           target="_blank"
                                           rel="noopener"
                                           href="https://www.openstreetmap.org/?mlat=<?= rawurlencode((string) $order['delivery_latitude']) ?>&amp;mlon=<?= rawurlencode((string) $order['delivery_longitude']) ?>#map=17/<?= rawurlencode((string) $order['delivery_latitude']) ?>/<?= rawurlencode((string) $order['delivery_longitude']) ?>">
                                            Open delivery pin on map
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!empty($order['customer_note'])): ?>
                                        <div class="small mt-2">
                                            <strong>Customer note:</strong>
                                            <?= nl2br(mephysEscape($order['customer_note'])) ?>
                                        </div>
                                    <?php endif; ?>

                                </div>

                                <div class="col-lg-4">

                                    <div class="fw-bold mb-1">Payment record</div>

                                    <div>
                                        Payment attempt:
                                        <?= mephysEscape($order['latest_payment_status'] ?? 'Not recorded') ?>
                                    </div>

                                    <div>
                                        Method:
                                        <?= mephysEscape($order['latest_payment_method'] ?? $order['payment_method'] ?? 'Not recorded') ?>
                                    </div>

                                    <div>
                                        Phone:
                                        <?= mephysEscape($order['payment_phone'] ?? 'Not recorded') ?>
                                    </div>

                                    <div>
                                        Receipt:
                                        <?= mephysEscape($order['mpesa_receipt'] ?? 'Not yet received') ?>
                                    </div>

                                    <div>
                                        Paid at:
                                        <?= !empty($order['payment_paid_at'])
                                            ? mephysEscape(date('d M Y H:i', strtotime((string) $order['payment_paid_at'])))
                                            : 'Not confirmed' ?>
                                    </div>

                                    <?php if (!empty($order['payment_message'])): ?>
                                        <div class="small text-secondary mt-1">
                                            <strong>Safaricom response:</strong>
                                            <?= mephysEscape($order['payment_message']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($order['checkout_request_id'])): ?>
                                        <div class="small text-secondary mt-1">
                                            Checkout ID: <?= mephysEscape($order['checkout_request_id']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($order['merchant_request_id'])): ?>
                                        <div class="small text-secondary">
                                            Merchant ID: <?= mephysEscape($order['merchant_request_id']) ?>
                                        </div>
                                    <?php endif; ?>

                                </div>

                                <div class="col-lg-4">

                                    <div class="fw-bold mb-1">Items ordered</div>

                                    <?php if (!$orderItems): ?>

                                        <div class="text-secondary">
                                            No item snapshots were found for this order.
                                        </div>

                                    <?php else: ?>

                                        <?php foreach ($orderItems as $orderItem): ?>

                                            <div class="d-flex justify-content-between gap-2 border-bottom py-1">

                                                <span>
                                                    <?= mephysEscape($orderItem['product_name']) ?>
                                                    <?= !empty($orderItem['variant_snapshot'])
                                                        ? ' · ' . mephysEscape($orderItem['variant_snapshot'])
                                                        : '' ?>
                                                    × <?= (int) $orderItem['quantity'] ?>
                                                </span>

                                                <strong>
                                                    <?= mephysEscape($formatKes($orderItem['line_total'])) ?>
                                                </strong>

                                            </div>

                                        <?php endforeach; ?>

                                    <?php endif; ?>

                                </div>

                            </div>

                            <form method="POST" class="border-top mt-3 pt-3">

                                <input type="hidden" name="csrf_token"
                                       value="<?= mephysEscape(mephysCsrfToken()) ?>">

                                <input type="hidden" name="management_action"
                                       value="update_order">

                                <input type="hidden" name="order_id"
                                       value="<?= $orderId ?>">

                                <div class="fw-bold mb-2">
                                    Update delivery / fulfilment
                                </div>

                                <div class="row g-2 align-items-end">

                                    <div class="col-md-3">
                                        <label class="filter-label" for="status-<?= $orderId ?>">
                                            Order status
                                        </label>

                                        <select class="form-select"
                                                name="order_status"
                                                id="status-<?= $orderId ?>">

                                            <?php foreach ($orderStatuses as $value => $label): ?>
                                                <option value="<?= mephysEscape($value) ?>"
                                                    <?= $orderStatus === $value ? 'selected' : '' ?>>
                                                    <?= mephysEscape($label) ?>
                                                </option>
                                            <?php endforeach; ?>

                                        </select>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="filter-label" for="provider-<?= $orderId ?>">
                                            Delivery provider
                                        </label>

                                        <input class="form-control"
                                               name="shipping_provider"
                                               id="provider-<?= $orderId ?>"
                                               maxlength="150"
                                               value="<?= mephysEscape($order['shipping_provider'] ?? '') ?>"
                                               placeholder="Courier / rider / pickup">
                                    </div>

                                    <div class="col-md-3">
                                        <label class="filter-label" for="tracking-<?= $orderId ?>">
                                            Tracking number
                                        </label>

                                        <input class="form-control"
                                               name="tracking_number"
                                               id="tracking-<?= $orderId ?>"
                                               maxlength="120"
                                               value="<?= mephysEscape($order['tracking_number'] ?? '') ?>"
                                               placeholder="Tracking reference">
                                    </div>

                                    <div class="col-md-3">
                                        <label class="filter-label" for="history-<?= $orderId ?>">
                                            Customer-visible update (when status changes)
                                        </label>

                                        <input class="form-control"
                                               name="history_message"
                                               id="history-<?= $orderId ?>"
                                               maxlength="1000"
                                               placeholder="Optional update for the customer">
                                    </div>

                                    <div class="col-md-9">
                                        <label class="filter-label" for="staff-note-<?= $orderId ?>">
                                            Internal staff note
                                        </label>

                                        <input class="form-control"
                                               name="staff_note"
                                               id="staff-note-<?= $orderId ?>"
                                               maxlength="10000"
                                               value="<?= mephysEscape($order['staff_note'] ?? '') ?>"
                                               placeholder="Internal note (not shown to customer)">
                                    </div>

                                    <div class="col-md-3 d-grid">
                                        <button class="btn btn-primary" type="submit">
                                            Save order updates
                                        </button>
                                    </div>

                                </div>

                            </form>

                        </details>

                    </td>

                </tr>

            <?php endforeach; endif; ?>

            </tbody>

        </table>

    </div>

    <div class="table-footer">
        Payment status is system-controlled. An order only appears as paid after a successful verified payment callback.
    </div>

</section>

<?php endif; ?>
