<?php
// Payment ledger. Payment records are read-only here to preserve callback verification.
$paymentStatuses = [
    'initiated' => 'Initiated',
    'pending' => 'Pending',
    'successful' => 'Successful',
    'failed' => 'Failed',
    'cancelled' => 'Cancelled',
    'refunded' => 'Refunded',
];

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$dateFrom = trim((string) ($_GET['from'] ?? ''));
$dateTo = trim((string) ($_GET['to'] ?? ''));
if (!array_key_exists($statusFilter, ['' => ''] + $paymentStatuses)) { $statusFilter = ''; }
if ($dateFrom !== '' && !mephysValidDate($dateFrom)) { $dateFrom = ''; }
if ($dateTo !== '' && !mephysValidDate($dateTo)) { $dateTo = ''; }

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(o.order_number LIKE :q_order OR o.customer_first_name LIKE :q_first OR o.customer_last_name LIKE :q_last OR o.customer_email LIKE :q_email OR p.provider_reference LIKE :q_reference OR p.checkout_request_id LIKE :q_checkout OR p.phone LIKE :q_phone)';
    $term = '%' . $search . '%';
    $params = [':q_order' => $term, ':q_first' => $term, ':q_last' => $term, ':q_email' => $term, ':q_reference' => $term, ':q_checkout' => $term, ':q_phone' => $term];
}
if ($statusFilter !== '') { $where[] = 'p.status = :status_filter'; $params[':status_filter'] = $statusFilter; }
if ($dateFrom !== '') { $where[] = 'p.created_at >= :date_from'; $params[':date_from'] = $dateFrom . ' 00:00:00'; }
if ($dateTo !== '') { $where[] = 'p.created_at <= :date_to'; $params[':date_to'] = $dateTo . ' 23:59:59'; }

$paymentStats = ['total' => 0, 'successful' => 0, 'waiting' => 0, 'unsuccessful' => 0, 'collected' => 0];
$payments = [];
$pageError = '';
try {
    $stats = $pdo->query("SELECT COUNT(*) AS total,
        COALESCE(SUM(status='successful'),0) AS successful,
        COALESCE(SUM(status IN ('initiated','pending')),0) AS waiting,
        COALESCE(SUM(status IN ('failed','cancelled')),0) AS unsuccessful,
        COALESCE(SUM(CASE WHEN status='successful' THEN amount ELSE 0 END),0) AS collected
        FROM shop_payments")->fetch(PDO::FETCH_ASSOC);
    if ($stats) { $paymentStats = $stats; }

    $sql = "SELECT p.*, o.order_number, o.customer_first_name, o.customer_last_name, o.customer_email, o.payment_status AS order_payment_status
            FROM shop_payments p
            LEFT JOIN shop_orders o ON o.id = p.order_id";
    if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
    $sql .= ' ORDER BY p.created_at DESC, p.id DESC LIMIT 200';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Mephys payments page error: ' . $e->getMessage());
    $pageError = 'Payment records could not be loaded. Check the PHP server error log.';
}
$formatKes = static fn($amount): string => 'KSh ' . number_format((float) $amount, 2);
$badgeClasses = ['initiated' => 'text-bg-secondary', 'pending' => 'text-bg-warning', 'successful' => 'text-bg-success', 'failed' => 'text-bg-danger', 'cancelled' => 'text-bg-dark', 'refunded' => 'text-bg-info'];
?>

<div class="welcome-row">
    <div><h1 class="page-heading">Payment Tracking</h1><div class="page-subtitle">A payment ledger for M-Pesa STK Push attempts, Safaricom receipts and failed requests.</div></div>
    <a href="crud.php?page=orders" class="btn btn-outline-primary"><i class="bi bi-bag-check me-1"></i>Back to orders</a>
</div>

<?php if ($pageError !== ''): ?><div class="alert alert-danger"><?= mephysEscape($pageError) ?></div><?php else: ?>
<div class="stats-grid commerce-stats">
    <div class="stat-card"><div class="stat-label">Payment attempts</div><div class="stat-number"><?= (int) $paymentStats['total'] ?></div><div class="stat-indicator indicator-total"></div></div>
    <div class="stat-card"><div class="stat-label">Successful payments</div><div class="stat-number"><?= (int) $paymentStats['successful'] ?></div><div class="stat-indicator indicator-completed"></div></div>
    <div class="stat-card"><div class="stat-label">Awaiting result</div><div class="stat-number"><?= (int) $paymentStats['waiting'] ?></div><div class="stat-indicator indicator-pending"></div></div>
    <div class="stat-card"><div class="stat-label">Failed / cancelled</div><div class="stat-number"><?= (int) $paymentStats['unsuccessful'] ?></div><div class="stat-indicator indicator-cancelled"></div></div>
    <div class="stat-card"><div class="stat-label">Successful amount</div><div class="stat-number" style="font-size:19px"><?= mephysEscape($formatKes($paymentStats['collected'])) ?></div><div class="stat-indicator indicator-confirmed"></div></div>
</div>

<section class="panel">
    <div class="panel-header"><div><h2 class="section-heading">Payment ledger</h2><div class="results-count">Showing up to 200 latest matching payment attempts.</div></div></div>
    <form method="GET" class="filters" style="grid-template-columns:minmax(190px,1.5fr) repeat(3,minmax(130px,1fr)) auto">
        <input type="hidden" name="page" value="payments">
        <div class="search-filter"><label class="filter-label" for="payment-q">Search payments</label><input class="form-control" id="payment-q" name="q" value="<?= mephysEscape($search) ?>" placeholder="Order, customer, receipt, phone"></div>
        <div><label class="filter-label" for="payment-status-filter">Payment status</label><select class="form-select" id="payment-status-filter" name="status"><option value="">All statuses</option><?php foreach ($paymentStatuses as $value => $label): ?><option value="<?= mephysEscape($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= mephysEscape($label) ?></option><?php endforeach; ?></select></div>
        <div><label class="filter-label" for="payment-from">From date</label><input class="form-control" type="date" id="payment-from" name="from" value="<?= mephysEscape($dateFrom) ?>"></div>
        <div><label class="filter-label" for="payment-to">To date</label><input class="form-control" type="date" id="payment-to" name="to" value="<?= mephysEscape($dateTo) ?>"></div>
        <div class="filter-buttons"><button class="btn btn-primary" type="submit">Filter</button><a class="btn btn-outline-secondary" href="crud.php?page=payments">Reset</a></div>
    </form>
    <div class="table-wrap"><table class="appointments-table" style="min-width:1150px">
        <thead><tr><th>Payment / order</th><th>Customer</th><th>Method / phone</th><th>Amount</th><th>Payment result</th><th>Receipt</th><th>Created / paid</th><th>Details</th></tr></thead>
        <tbody>
        <?php if (!$payments): ?><tr><td colspan="8" class="empty-state">No payment attempts match these filters.</td></tr>
        <?php else: foreach ($payments as $payment): ?>
            <?php $paymentStatus = (string) $payment['status']; ?>
            <tr>
                <td><div class="patient-name"><?= mephysEscape($payment['order_number'] ?? ('Order ID #' . $payment['order_id'])) ?></div><div class="patient-email">Payment #<?= (int) $payment['id'] ?></div></td>
                <td><div class="patient-name"><?= mephysEscape(trim(($payment['customer_first_name'] ?? '') . ' ' . ($payment['customer_last_name'] ?? ''))) ?></div><div class="patient-email"><?= mephysEscape($payment['customer_email'] ?? '') ?></div></td>
                <td><?= mephysEscape(strtoupper($payment['payment_method'])) ?><div class="patient-email"><?= mephysEscape($payment['phone'] ?? 'Phone not recorded') ?></div></td>
                <td><strong><?= mephysEscape($formatKes($payment['amount'])) ?></strong><div class="patient-email"><?= mephysEscape($payment['currency']) ?></div></td>
                <td><span class="badge rounded-pill <?= mephysEscape($badgeClasses[$paymentStatus] ?? 'text-bg-secondary') ?>"><?= mephysEscape($paymentStatuses[$paymentStatus] ?? ucfirst($paymentStatus)) ?></span><div class="patient-email">Order: <?= mephysEscape($payment['order_payment_status'] ?? 'unknown') ?></div></td>
                <td><strong><?= mephysEscape($payment['provider_reference'] ?? '—') ?></strong></td>
                <td><div class="appointment-date"><?= mephysEscape(date('d M Y', strtotime((string) $payment['created_at']))) ?></div><div class="appointment-time"><?= !empty($payment['paid_at']) ? 'Paid ' . mephysEscape(date('d M H:i', strtotime((string) $payment['paid_at']))) : mephysEscape(date('H:i', strtotime((string) $payment['created_at']))) ?></div></td>
                <td><details class="details-box"><summary class="icon-action" title="Payment details"><i class="bi bi-three-dots"></i></summary><div class="details-content"><strong>Provider:</strong> <?= mephysEscape($payment['payment_provider'] ?? 'Not recorded') ?><br><strong>Response code:</strong> <?= mephysEscape($payment['response_code'] ?? '—') ?><br><strong>Response:</strong> <?= mephysEscape($payment['response_description'] ?? 'No response description') ?><br><strong>Checkout ID:</strong> <?= mephysEscape($payment['checkout_request_id'] ?? '—') ?><br><strong>Merchant ID:</strong> <?= mephysEscape($payment['merchant_request_id'] ?? '—') ?></div></details></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
    <div class="table-footer">This page is read-only. Successful payment status is set by verified Safaricom callback processing, not by a staff-side button.</div>
</section>
<?php endif; ?>
