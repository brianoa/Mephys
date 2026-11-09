<?php
$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
if (!in_array($statusFilter, ['', 'active', 'blocked'], true)) { $statusFilter = ''; }
$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(c.first_name LIKE :q_first OR c.last_name LIKE :q_last OR c.email LIKE :q_email OR c.phone LIKE :q_phone)';
    $term = '%' . $search . '%';
    $params = [':q_first' => $term, ':q_last' => $term, ':q_email' => $term, ':q_phone' => $term];
}
if ($statusFilter !== '') { $where[] = 'c.status = :status'; $params[':status'] = $statusFilter; }

$customerStats = ['total' => 0, 'active' => 0, 'blocked' => 0, 'with_paid_orders' => 0];
$customers = [];
$pageError = '';
try {
    $stats = $pdo->query("SELECT COUNT(*) AS total,
        COALESCE(SUM(status='active'),0) AS active,
        COALESCE(SUM(status='blocked'),0) AS blocked
        FROM shop_customers")->fetch(PDO::FETCH_ASSOC);
    if ($stats) { $customerStats = array_merge($customerStats, $stats); }
    $customerStats['with_paid_orders'] = (int) $pdo->query("SELECT COUNT(DISTINCT customer_id) FROM shop_orders WHERE payment_status = 'paid'")->fetchColumn();

    $sql = "SELECT c.id, c.first_name, c.last_name, c.email, c.phone, c.status, c.email_verified_at, c.last_login_at, c.created_at,
                COUNT(o.id) AS order_count,
                COALESCE(SUM(CASE WHEN o.payment_status='paid' THEN o.total_amount ELSE 0 END),0) AS lifetime_paid,
                MAX(o.ordered_at) AS last_order_at,
                MAX(o.payment_status='paid') AS has_paid_order
            FROM shop_customers c
            LEFT JOIN shop_orders o ON o.customer_id = c.id";
    if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
    $sql .= ' GROUP BY c.id ORDER BY c.created_at DESC, c.id DESC LIMIT 200';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Mephys customers page error: ' . $e->getMessage());
    $pageError = 'Customer records could not be loaded. Check the PHP server error log.';
}
$formatKes = static fn($amount): string => 'KSh ' . number_format((float) $amount, 2);
?>

<div class="welcome-row">
    <div><h1 class="page-heading">Shop Customers</h1><div class="page-subtitle">View registered customer accounts, their order history totals and confirmed payments.</div></div>
    <a href="crud.php?page=orders" class="btn btn-outline-primary"><i class="bi bi-bag-check me-1"></i>View orders</a>
</div>

<?php if ($pageError !== ''): ?><div class="alert alert-danger"><?= mephysEscape($pageError) ?></div><?php else: ?>
<div class="stats-grid commerce-stats">
    <div class="stat-card"><div class="stat-label">Registered customers</div><div class="stat-number"><?= (int) $customerStats['total'] ?></div><div class="stat-indicator indicator-total"></div></div>
    <div class="stat-card"><div class="stat-label">Active accounts</div><div class="stat-number"><?= (int) $customerStats['active'] ?></div><div class="stat-indicator indicator-completed"></div></div>
    <div class="stat-card"><div class="stat-label">Blocked accounts</div><div class="stat-number"><?= (int) $customerStats['blocked'] ?></div><div class="stat-indicator indicator-cancelled"></div></div>
    <div class="stat-card"><div class="stat-label">Customers with paid orders</div><div class="stat-number"><?= (int) $customerStats['with_paid_orders'] ?></div><div class="stat-indicator indicator-confirmed"></div></div>
</div>

<section class="panel">
    <div class="panel-header"><div><h2 class="section-heading">Customer directory</h2><div class="results-count">Latest 200 registered customer accounts.</div></div></div>
    <form method="GET" class="filters" style="grid-template-columns:minmax(190px,1.5fr) minmax(150px,1fr) auto">
        <input type="hidden" name="page" value="customers">
        <div class="search-filter"><label class="filter-label" for="customer-q">Search customers</label><input class="form-control" id="customer-q" name="q" value="<?= mephysEscape($search) ?>" placeholder="Name, email or phone number"></div>
        <div><label class="filter-label" for="customer-status">Account status</label><select class="form-select" id="customer-status" name="status"><option value="">All accounts</option><option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option><option value="blocked" <?= $statusFilter === 'blocked' ? 'selected' : '' ?>>Blocked</option></select></div>
        <div class="filter-buttons"><button class="btn btn-primary" type="submit">Filter</button><a class="btn btn-outline-secondary" href="crud.php?page=customers">Reset</a></div>
    </form>
    <div class="table-wrap"><table class="appointments-table" style="min-width:1050px">
        <thead><tr><th>Customer</th><th>Phone</th><th>Account</th><th>Orders</th><th>Paid total</th><th>Last order</th><th>Joined</th><th>Records</th></tr></thead>
        <tbody>
        <?php if (!$customers): ?><tr><td colspan="8" class="empty-state">No customer accounts match this search.</td></tr>
        <?php else: foreach ($customers as $customer): ?>
            <tr>
                <td><div class="patient-name"><?= mephysEscape(trim($customer['first_name'] . ' ' . $customer['last_name'])) ?></div><div class="patient-email"><?= mephysEscape($customer['email']) ?></div><div class="patient-email"><?= !empty($customer['email_verified_at']) ? 'Email verified' : 'Email not verified' ?></div></td>
                <td><?= mephysEscape($customer['phone'] ?? '—') ?></td>
                <td><span class="badge rounded-pill <?= $customer['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= mephysEscape(ucfirst($customer['status'])) ?></span></td>
                <td><strong><?= (int) $customer['order_count'] ?></strong></td>
                <td><strong><?= mephysEscape($formatKes($customer['lifetime_paid'])) ?></strong></td>
                <td><?= !empty($customer['last_order_at']) ? mephysEscape(date('d M Y', strtotime((string) $customer['last_order_at']))) : '—' ?></td>
                <td><?= mephysEscape(date('d M Y', strtotime((string) $customer['created_at']))) ?></td>
                <td><div class="d-flex flex-wrap gap-1"><a class="btn btn-sm btn-outline-primary" href="crud.php?page=orders&amp;q=<?= rawurlencode((string) $customer['email']) ?>">Orders</a><a class="btn btn-sm btn-outline-secondary" href="crud.php?page=payments&amp;q=<?= rawurlencode((string) $customer['email']) ?>">Payments</a></div></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</section>
<?php endif; ?>
