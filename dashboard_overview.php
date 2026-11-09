<?php

// This file is included by crud.php.
// The authenticated staff record is loaded in crud.php.

$overviewStaffName = 'Staff';

if (isset($staff) && is_array($staff) && isset($staff['username'])) {
    $overviewStaffName = (string) $staff['username'];
}

// Dashboard appointment statistics.
$overviewCounts = [
    'total' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'cancelled' => 0,
];

$recentAppointments = [];

try {

    // Get appointment statistics.
    $overviewStmt = $pdo->query("
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(status = 'pending'), 0) AS pending,
            COALESCE(SUM(status = 'confirmed'), 0) AS confirmed,
            COALESCE(SUM(status = 'completed'), 0) AS completed,
            COALESCE(SUM(status = 'cancelled'), 0) AS cancelled
        FROM appointments
    ");

    $overviewResult = $overviewStmt->fetch(PDO::FETCH_ASSOC);

    if ($overviewResult) {
        foreach ($overviewCounts as $key => $value) {
            $overviewCounts[$key] = (int) ($overviewResult[$key] ?? 0);
        }
    }

    // Get the five most recent appointments.
    $recentStmt = $pdo->query("
        SELECT
            id,
            full_name,
            service,
            appointment_date,
            appointment_time,
            status
        FROM appointments
        ORDER BY
            appointment_date DESC,
            appointment_time DESC,
            id DESC
        LIMIT 5
    ");

    $recentAppointments = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log('Mephys overview error: ' . $e->getMessage());

    echo '<div class="alert alert-danger">
        Unable to load the dashboard overview right now.
    </div>';
}


// Online shop summary. These figures are kept separate from appointment statistics.
$shopOverview = [
    'orders' => 0,
    'paid_orders' => 0,
    'awaiting_payment' => 0,
    'customers' => 0,
    'published_products' => 0,
    'paid_revenue' => 0,
];
$recentShopOrders = [];
$shopOverviewError = false;

try {
    $shopStatsStmt = $pdo->query(
        "SELECT
            COUNT(*) AS orders,
            COALESCE(SUM(payment_status = 'paid'), 0) AS paid_orders,
            COALESCE(SUM(payment_status IN ('unpaid','pending')), 0) AS awaiting_payment,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) AS paid_revenue
         FROM shop_orders"
    );
    $shopStats = $shopStatsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    foreach (['orders', 'paid_orders', 'awaiting_payment', 'paid_revenue'] as $key) {
        $shopOverview[$key] = $shopStats[$key] ?? $shopOverview[$key];
    }

    $shopOverview['customers'] = (int) $pdo->query("SELECT COUNT(*) FROM shop_customers")->fetchColumn();
    $shopOverview['published_products'] = (int) $pdo->query("SELECT COUNT(*) FROM shop_products WHERE status = 'published'")->fetchColumn();

    $recentShopStmt = $pdo->query(
        "SELECT o.id, o.order_number, o.customer_first_name, o.customer_last_name,
                o.total_amount, o.currency, o.payment_status, o.status, o.ordered_at,
                p.provider_reference AS receipt
         FROM shop_orders o
         LEFT JOIN shop_payments p ON p.id = (
             SELECT MAX(p2.id) FROM shop_payments p2 WHERE p2.order_id = o.id
         )
         ORDER BY o.ordered_at DESC, o.id DESC
         LIMIT 6"
    );
    $recentShopOrders = $recentShopStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Mephys shop dashboard summary error: ' . $e->getMessage());
    $shopOverviewError = true;
}

$statusLabels = [
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];

?>

<!-- Dashboard heading -->
<div class="welcome-row">

    <div>
        <h1 class="page-heading">Dashboard Overview</h1>

        <div class="page-subtitle">
            Welcome back,
            <?= mephysEscape($overviewStaffName) ?>.
            Here is the clinic's appointment activity.
        </div>
    </div>

    <a href="crud.php?page=appointments" class="btn btn-primary">
        <i class="bi bi-calendar-check me-1"></i>
        View Appointments
    </a>

</div>


<!-- Appointment statistics -->
<section class="stats-grid" aria-label="Appointment overview">

    <div class="stat-card">
        <div class="stat-label">Total Appointments</div>
        <div class="stat-number">
            <?= $overviewCounts['total'] ?>
        </div>
        <div class="stat-indicator indicator-total"></div>
    </div>

    <div class="stat-card">
        <div class="stat-label">Pending</div>
        <div class="stat-number">
            <?= $overviewCounts['pending'] ?>
        </div>
        <div class="stat-indicator indicator-pending"></div>
    </div>

    <div class="stat-card">
        <div class="stat-label">Confirmed</div>
        <div class="stat-number">
            <?= $overviewCounts['confirmed'] ?>
        </div>
        <div class="stat-indicator indicator-confirmed"></div>
    </div>

    <div class="stat-card">
        <div class="stat-label">Completed</div>
        <div class="stat-number">
            <?= $overviewCounts['completed'] ?>
        </div>
        <div class="stat-indicator indicator-completed"></div>
    </div>

    <div class="stat-card">
        <div class="stat-label">Cancelled</div>
        <div class="stat-number">
            <?= $overviewCounts['cancelled'] ?>
        </div>
        <div class="stat-indicator indicator-cancelled"></div>
    </div>

</section>


<!-- Recent appointments -->
<section class="panel">

    <div class="panel-header">

        <div>
            <h2 class="section-heading">
                Recent Appointments
            </h2>

            <div class="results-count">
                The five most recently scheduled appointment records.
            </div>
        </div>

        <a
            href="crud.php?page=appointments"
            class="export-btn"
        >
            View all appointments
            <i class="bi bi-arrow-right"></i>
        </a>

    </div>


    <div class="table-wrap">

        <table class="appointments-table">

            <thead>
                <tr>
                    <th>Client</th>
                    <th>Service</th>
                    <th>Appointment Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>


            <tbody>

                <?php if (empty($recentAppointments)): ?>

                    <tr>
                        <td colspan="5" class="empty-state">
                            No appointment records are available yet.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($recentAppointments as $appointment): ?>

                        <?php

                        $appointmentStatus = (string) (
                            $appointment['status'] ?? 'pending'
                        );

                        $statusClass = in_array(
                            $appointmentStatus,
                            [
                                'pending',
                                'confirmed',
                                'completed',
                                'cancelled'
                            ],
                            true
                        )
                            ? 'status-' . $appointmentStatus
                            : 'status-pending';

                        $statusLabel = $statusLabels[
                            $appointmentStatus
                        ] ?? 'Pending';

                        ?>

                        <tr>

                            <td>
                                <div class="patient-name">
                                    <?= mephysEscape(
                                        $appointment['full_name']
                                    ) ?>
                                </div>
                            </td>

                            <td>
                                <?= mephysEscape(
                                    $appointment['service']
                                ) ?>
                            </td>

                            <td>

                                <div class="appointment-date">
                                    <?= mephysEscape(
                                        $appointment['appointment_date']
                                    ) ?>
                                </div>

                                <div class="appointment-time">
                                    <?= mephysEscape(
                                        $appointment['appointment_time']
                                    ) ?>
                                </div>

                            </td>

                            <td>

                                <span class="status-badge <?= mephysEscape($statusClass) ?>">
                                    <?= mephysEscape($statusLabel) ?>
                                </span>

                            </td>

                            <td>

                                <a
                                    class="icon-action edit"
                                    href="update_appointment.php?id=<?= (int) $appointment['id'] ?>"
                                    title="Edit appointment"
                                >
                                    <i class="bi bi-pencil-square"></i>
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>

<!-- Online shop management overview -->
<div class="welcome-row mt-4">
    <div>
        <h2 class="page-heading" style="font-size:18px">Online Shop Overview</h2>
        <div class="page-subtitle">Orders, confirmed customer payments, registered shoppers and product catalogue activity.</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="crud.php?page=orders" class="btn btn-primary"><i class="bi bi-bag-check me-1"></i>Manage orders</a>
        <a href="shop/admin/products.php" class="btn btn-outline-primary"><i class="bi bi-box-seam me-1"></i>Manage products</a>
    </div>
</div>

<?php if ($shopOverviewError): ?>
    <div class="alert alert-warning">The shop summary is temporarily unavailable. The appointment dashboard is still available.</div>
<?php else: ?>
<section class="stats-grid commerce-stats" aria-label="Online shop overview">
    <div class="stat-card"><div class="stat-label">Total shop orders</div><div class="stat-number"><?= (int) $shopOverview['orders'] ?></div><div class="stat-indicator indicator-total"></div></div>
    <div class="stat-card"><div class="stat-label">Paid orders</div><div class="stat-number"><?= (int) $shopOverview['paid_orders'] ?></div><div class="stat-indicator indicator-completed"></div></div>
    <div class="stat-card"><div class="stat-label">Awaiting payment</div><div class="stat-number"><?= (int) $shopOverview['awaiting_payment'] ?></div><div class="stat-indicator indicator-pending"></div></div>
    <div class="stat-card"><div class="stat-label">Registered customers</div><div class="stat-number"><?= (int) $shopOverview['customers'] ?></div><div class="stat-indicator indicator-confirmed"></div></div>
    <div class="stat-card"><div class="stat-label">Published products</div><div class="stat-number"><?= (int) $shopOverview['published_products'] ?></div><div class="stat-indicator indicator-total"></div></div>
    <div class="stat-card"><div class="stat-label">Confirmed revenue</div><div class="stat-number" style="font-size:19px">KSh <?= number_format((float) $shopOverview['paid_revenue'], 2) ?></div><div class="stat-indicator indicator-completed"></div></div>
</section>

<section class="panel mb-4">
    <div class="panel-header">
        <div><h2 class="section-heading">Recent shop orders</h2><div class="results-count">Latest six customer orders and their recorded payment states.</div></div>
        <div class="d-flex flex-wrap gap-2"><a href="crud.php?page=payments" class="export-btn">Payment ledger <i class="bi bi-arrow-right"></i></a><a href="crud.php?page=customers" class="export-btn">Customer directory <i class="bi bi-arrow-right"></i></a></div>
    </div>
    <div class="table-wrap">
        <table class="appointments-table" style="min-width:850px">
            <thead><tr><th>Order</th><th>Customer</th><th>Placed</th><th>Order value</th><th>Payment</th><th>Receipt</th><th>Action</th></tr></thead>
            <tbody>
            <?php if (!$recentShopOrders): ?>
                <tr><td colspan="7" class="empty-state">No online shop orders have been placed yet.</td></tr>
            <?php else: foreach ($recentShopOrders as $shopOrder): ?>
                <?php
                $shopPaymentStatus = (string) ($shopOrder['payment_status'] ?? 'unpaid');
                $shopPaymentClass = $shopPaymentStatus === 'paid' ? 'status-completed' : (in_array($shopPaymentStatus, ['pending'], true) ? 'status-pending' : (in_array($shopPaymentStatus, ['failed','refunded'], true) ? 'status-cancelled' : 'status-confirmed'));
                ?>
                <tr>
                    <td><div class="patient-name"><?= mephysEscape($shopOrder['order_number']) ?></div></td>
                    <td><?= mephysEscape(trim($shopOrder['customer_first_name'] . ' ' . $shopOrder['customer_last_name'])) ?></td>
                    <td><?= mephysEscape(date('d M Y H:i', strtotime((string) $shopOrder['ordered_at']))) ?></td>
                    <td><strong><?= mephysEscape($shopOrder['currency']) ?> <?= number_format((float) $shopOrder['total_amount'], 2) ?></strong></td>
                    <td><span class="status-badge <?= mephysEscape($shopPaymentClass) ?>"><?= mephysEscape(str_replace('_', ' ', $shopPaymentStatus)) ?></span></td>
                    <td><?= mephysEscape($shopOrder['receipt'] ?? '—') ?></td>
                    <td><a class="icon-action edit" href="crud.php?page=orders" title="Open order management"><i class="bi bi-arrow-up-right-square"></i></a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

