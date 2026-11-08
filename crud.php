
<?php

require_once __DIR__ . '/staff-auth.php';

global $pdo;

// Require an authenticated and active staff account.
$staff = mephysRequireStaffLogin();

// Safely display database values.
function mephysEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Validate dates received through the filter form.
function mephysValidDate(string $date): bool
{
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);

    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

// Allowed appointment statuses.
$allowedStatuses = [
    'pending',
    'confirmed',
    'completed',
    'cancelled'
];

// Read search and filter values.
$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$serviceFilter = trim((string) ($_GET['service'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));

$filterErrors = [];

// Validate status filter.
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
    $filterErrors[] = 'Invalid status filter.';
}

// Validate date filters.
if ($dateFrom !== '' && !mephysValidDate($dateFrom)) {
    $dateFrom = '';
    $filterErrors[] = 'The start date is invalid.';
}

if ($dateTo !== '' && !mephysValidDate($dateTo)) {
    $dateTo = '';
    $filterErrors[] = 'The end date is invalid.';
}

if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    $dateFrom = '';
    $dateTo = '';
    $filterErrors[] = 'The start date cannot be after the end date.';
}

$appointments = [];
$services = [];

$statusCounts = [
    'total' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'cancelled' => 0
];

try {

    // Retrieve overall appointment statistics.
    // These figures are not affected by the table filters.
    $statsStmt = $pdo->query("
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(status = 'pending'), 0) AS pending,
            COALESCE(SUM(status = 'confirmed'), 0) AS confirmed,
            COALESCE(SUM(status = 'completed'), 0) AS completed,
            COALESCE(SUM(status = 'cancelled'), 0) AS cancelled
        FROM appointments
    ");

    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    if ($stats) {
        foreach ($statusCounts as $key => $value) {
            $statusCounts[$key] = (int) ($stats[$key] ?? 0);
        }
    }

    // Retrieve available services for the filter dropdown.
    $serviceStmt = $pdo->query("
        SELECT DISTINCT service
        FROM appointments
        WHERE service IS NOT NULL
          AND service <> ''
        ORDER BY service ASC
    ");

    $services = $serviceStmt->fetchAll(PDO::FETCH_COLUMN);

    // Build the appointment filtering query.
    $conditions = [];
    $parameters = [];

    if ($search !== '') {
        $conditions[] = "
            (
                full_name LIKE :search_name
                OR email LIKE :search_email
                OR phone LIKE :search_phone
            )
        ";

        $searchValue = '%' . $search . '%';

        $parameters[':search_name'] = $searchValue;
        $parameters[':search_email'] = $searchValue;
        $parameters[':search_phone'] = $searchValue;
    }

    if ($statusFilter !== '') {
        $conditions[] = 'status = :status';
        $parameters[':status'] = $statusFilter;
    }

    if ($serviceFilter !== '') {
        $conditions[] = 'service = :service';
        $parameters[':service'] = $serviceFilter;
    }

    if ($dateFrom !== '') {
        $conditions[] = 'appointment_date >= :date_from';
        $parameters[':date_from'] = $dateFrom;
    }

    if ($dateTo !== '') {
        $conditions[] = 'appointment_date <= :date_to';
        $parameters[':date_to'] = $dateTo;
    }

    $sql = "
        SELECT
            id,
            service,
            full_name,
            email,
            phone,
            appointment_date,
            appointment_time,
            location,
            referral,
            notes,
            status
        FROM appointments
    ";

    if (!empty($conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= "
        ORDER BY appointment_date DESC, appointment_time DESC, id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parameters);

    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log('Mephys appointments dashboard error: ' . $e->getMessage());

    http_response_code(500);
    exit('Unable to load the appointment dashboard. Please try again later.');
}

// Status badge styling.
function mephysStatusClass(string $status): string
{
    switch ($status) {
        case 'pending':
            return 'status-pending';

        case 'confirmed':
            return 'status-confirmed';

        case 'completed':
            return 'status-completed';

        case 'cancelled':
            return 'status-cancelled';

        default:
            return 'status-pending';
    }
}

// Friendly status labels.
function mephysStatusLabel(string $status): string
{
    return ucfirst(str_replace('_', ' ', $status));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="robots" content="noindex, nofollow">

    <title>Appointment Dashboard | Mephys</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        :root {
            --navy: #18344d;
            --navy-light: #254b68;
            --teal: #159b91;
            --background: #f3f6fa;
            --text: #334155;
            --muted: #718096;
            --border: #e5eaf0;
        }

        body {
            background: var(--background);
            font-family: 'Segoe UI', Arial, sans-serif;
            color: var(--text);
        }

        .dashboard-container {
            max-width: 1500px;
            margin: 35px auto 60px;
            padding: 0 20px;
        }

        .page-heading {
            color: var(--navy);
            font-weight: 750;
            letter-spacing: -0.5px;
        }

        .page-subtitle {
            color: var(--muted);
            font-size: 14px;
        }

        .staff-info {
            background: #fff;
            padding: 15px 18px;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(24, 52, 77, 0.04);
        }

        .staff-role {
            display: inline-block;
            padding: 5px 10px;
            background: #e8f4f1;
            color: #176d5b;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: capitalize;
        }

        .dashboard-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            height: 100%;
            box-shadow: 0 3px 12px rgba(24, 52, 77, 0.035);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(24, 52, 77, 0.08);
        }

        .stat-label {
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .stat-number {
            color: var(--navy);
            font-size: 29px;
            font-weight: 750;
            line-height: 1;
        }

        .stat-indicator {
            width: 38px;
            height: 4px;
            border-radius: 10px;
            margin-top: 15px;
        }

        .indicator-total {
            background: var(--navy);
        }

        .indicator-pending {
            background: #e5a000;
        }

        .indicator-confirmed {
            background: #2385d1;
        }

        .indicator-completed {
            background: #159b91;
        }

        .indicator-cancelled {
            background: #dc5360;
        }

        .content-panel {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 3px 12px rgba(24, 52, 77, 0.035);
        }

        .section-heading {
            color: var(--navy);
            font-weight: 700;
        }

        .filter-label {
            font-size: 12px;
            font-weight: 700;
            color: #526477;
            margin-bottom: 6px;
        }

        .form-control,
        .form-select {
            border-color: #dce4ec;
            border-radius: 8px;
            min-height: 40px;
            font-size: 13px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--teal);
            box-shadow: 0 0 0 3px rgba(21, 155, 145, 0.10);
        }

        .btn {
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-primary {
            background: var(--navy);
            border-color: var(--navy);
        }

        .btn-primary:hover {
            background: var(--navy-light);
            border-color: var(--navy-light);
        }

        .table {
            font-size: 13px;
            margin-bottom: 0;
        }

        .table thead th {
            background: #f0f4f8;
            color: #43566a;
            border-bottom: 1px solid var(--border);
            padding: 14px 12px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 13px 12px;
            vertical-align: middle;
            border-color: #edf0f4;
        }

        .patient-name {
            color: var(--navy);
            font-weight: 700;
            min-width: 140px;
        }

        .patient-email {
            color: var(--muted);
            font-size: 11px;
            margin-top: 4px;
            overflow-wrap: anywhere;
        }

        .appointment-date {
            color: var(--navy);
            font-weight: 650;
            white-space: nowrap;
        }

        .appointment-time {
            color: var(--muted);
            font-size: 11px;
            margin-top: 4px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 750;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .status-pending {
            background: #fff4d6;
            color: #946300;
        }

        .status-confirmed {
            background: #e1f0ff;
            color: #1765a5;
        }

        .status-completed {
            background: #dff5ed;
            color: #167454;
        }

        .status-cancelled {
            background: #fde8e9;
            color: #b83242;
        }

        .status-form {
            min-width: 155px;
        }

        .status-form .form-select {
            font-size: 11px;
            min-height: 34px;
        }

        .status-form .btn {
            width: 100%;
            margin-top: 5px;
            padding: 5px;
            font-size: 11px;
        }

        .details-box {
            margin-top: 6px;
            font-size: 11px;
            color: var(--muted);
            max-width: 190px;
            overflow-wrap: anywhere;
        }

        .details-box summary {
            cursor: pointer;
            color: #52718b;
            font-weight: 600;
        }

        .details-content {
            padding-top: 7px;
            line-height: 1.6;
        }

        .results-count {
            color: var(--muted);
            font-size: 13px;
        }

        .empty-state {
            padding: 45px 15px !important;
            text-align: center;
            color: var(--muted);
        }

        .logout-form {
            display: inline;
        }

        @media (max-width: 768px) {

            .dashboard-container {
                margin-top: 20px;
                padding: 0 12px;
            }

            .content-panel {
                padding: 16px;
            }

            .stat-number {
                font-size: 25px;
            }

        }

    </style>

</head>

<body>

<div class="dashboard-container">

    <!-- Page heading and staff account -->

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>
            <h2 class="page-heading mb-1">
                Appointment Dashboard
            </h2>

            <p class="page-subtitle mb-0">
                Mephys Physiotherapy Clinic Administration
            </p>
        </div>

        <div class="staff-info">

            <div class="mb-2">
                <strong><?= mephysEscape($staff['username']) ?></strong>
            </div>

            <span class="staff-role">
                <?= mephysEscape(str_replace('_', ' ', $staff['role'])) ?>
            </span>

            <div class="mt-3 d-flex flex-wrap gap-2">

                <a
                    href="staff-change-password.php"
                    class="btn btn-sm btn-outline-secondary"
                >
                    Change Password
                </a>

                <form
                    method="POST"
                    action="staff-logout.php"
                    class="logout-form"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= mephysEscape(mephysCsrfToken()) ?>"
                    >

                    <button
                        type="submit"
                        class="btn btn-sm btn-outline-danger"
                        onclick="return confirm('Are you sure you want to log out?');"
                    >
                        Logout
                    </button>

                </form>

            </div>

        </div>

    </div>

    <!-- Notifications -->

    <?php if (isset($_GET['updated']) && $_GET['updated'] === '1'): ?>

        <div class="alert alert-success alert-dismissible fade show">
            Appointment details updated successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

    <?php endif; ?>

    <?php if (isset($_GET['status_updated']) && $_GET['status_updated'] === '1'): ?>

        <div class="alert alert-success alert-dismissible fade show">
            Appointment status updated successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

    <?php endif; ?>

    <?php foreach ($filterErrors as $filterError): ?>

        <div class="alert alert-warning">
            <?= mephysEscape($filterError) ?>
        </div>

    <?php endforeach; ?>

    <!-- Appointment statistics -->

    <div class="row g-3 mb-4">

        <div class="col-6 col-md">
            <div class="dashboard-card">

                <div class="stat-label">Total Appointments</div>

                <div class="stat-number">
                    <?= $statusCounts['total'] ?>
                </div>

                <div class="stat-indicator indicator-total"></div>

            </div>
        </div>

        <div class="col-6 col-md">
            <div class="dashboard-card">

                <div class="stat-label">Pending</div>

                <div class="stat-number">
                    <?= $statusCounts['pending'] ?>
                </div>

                <div class="stat-indicator indicator-pending"></div>

            </div>
        </div>

        <div class="col-6 col-md">
            <div class="dashboard-card">

                <div class="stat-label">Confirmed</div>

                <div class="stat-number">
                    <?= $statusCounts['confirmed'] ?>
                </div>

                <div class="stat-indicator indicator-confirmed"></div>

            </div>
        </div>

        <div class="col-6 col-md">
            <div class="dashboard-card">

                <div class="stat-label">Completed</div>

                <div class="stat-number">
                    <?= $statusCounts['completed'] ?>
                </div>

                <div class="stat-indicator indicator-completed"></div>

            </div>
        </div>

        <div class="col-6 col-md">
            <div class="dashboard-card">

                <div class="stat-label">Cancelled</div>

                <div class="stat-number">
                    <?= $statusCounts['cancelled'] ?>
                </div>

                <div class="stat-indicator indicator-cancelled"></div>

            </div>
        </div>

    </div>

    <!-- Appointment records -->

    <div class="content-panel">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">

            <div>
                <h5 class="section-heading mb-1">
                    Appointment Records
                </h5>

                <div class="results-count">
                    <?= count($appointments) ?> matching appointment(s)
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">

                <a
                    href="appointments_export.php?type=pdf"
                    class="btn btn-sm btn-outline-danger"
                >
                    Export PDF
                </a>

                <a
                    href="appointments_export.php?type=excel"
                    class="btn btn-sm btn-outline-success"
                >
                    Export Excel
                </a>

                <a
                    href="appointments_export.php?type=csv"
                    class="btn btn-sm btn-outline-primary"
                >
                    Export CSV
                </a>

            </div>

        </div>

        <!-- Search and filters -->

        <form method="GET" action="crud.php" class="mb-4">

            <div class="row g-3">

                <div class="col-12 col-md-6 col-lg-3">

                    <label class="filter-label">
                        Search patient
                    </label>

                    <input
                        type="search"
                        name="search"
                        class="form-control"
                        placeholder="Name, email or phone..."
                        value="<?= mephysEscape($search) ?>"
                    >

                </div>

                <div class="col-12 col-md-6 col-lg-2">

                    <label class="filter-label">
                        Appointment Status
                    </label>

                    <select name="status" class="form-select">

                        <option value="">All statuses</option>

                        <?php foreach ($allowedStatuses as $status): ?>

                            <option
                                value="<?= mephysEscape($status) ?>"
                                <?= $statusFilter === $status ? 'selected' : '' ?>
                            >
                                <?= mephysEscape(mephysStatusLabel($status)) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-12 col-md-6 col-lg-2">

                    <label class="filter-label">
                        Service
                    </label>

                    <select name="service" class="form-select">

                        <option value="">All services</option>

                        <?php foreach ($services as $service): ?>

                            <option
                                value="<?= mephysEscape($service) ?>"
                                <?= $serviceFilter === $service ? 'selected' : '' ?>
                            >
                                <?= mephysEscape($service) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-6 col-md-3 col-lg-2">

                    <label class="filter-label">
                        From Date
                    </label>

                    <input
                        type="date"
                        name="date_from"
                        class="form-control"
                        value="<?= mephysEscape($dateFrom) ?>"
                    >

                </div>

                <div class="col-6 col-md-3 col-lg-2">

                    <label class="filter-label">
                        To Date
                    </label>

                    <input
                        type="date"
                        name="date_to"
                        class="form-control"
                        value="<?= mephysEscape($dateTo) ?>"
                    >

                </div>

                <div class="col-12 col-lg-1 d-flex align-items-end">

                    <button
                        type="submit"
                        class="btn btn-primary w-100"
                    >
                        Filter
                    </button>

                </div>

            </div>

            <div class="mt-3">

                <a
                    href="crud.php"
                    class="btn btn-sm btn-outline-secondary"
                >
                    Clear Filters
                </a>

            </div>

        </form>

        <hr class="mb-4">

        <!-- Appointment table -->

        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Service</th>
                        <th>Phone</th>
                        <th>Appointment</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Manage</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (empty($appointments)): ?>

                        <tr>
                            <td colspan="8" class="empty-state">
                                No appointments match your search or filters.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($appointments as $row): ?>

                            <?php
                                $currentStatus = in_array(
                                    $row['status'],
                                    $allowedStatuses,
                                    true
                                ) ? $row['status'] : 'pending';
                            ?>

                            <tr>

                                <td>
                                    #<?= (int) $row['id'] ?>
                                </td>

                                <td>

                                    <div class="patient-name">
                                        <?= mephysEscape($row['full_name']) ?>
                                    </div>

                                    <div class="patient-email">
                                        <?= mephysEscape($row['email']) ?>
                                    </div>

                                    <?php if (!empty($row['referral']) || !empty($row['notes'])): ?>

                                        <details class="details-box">

                                            <summary>
                                                Additional details
                                            </summary>

                                            <div class="details-content">

                                                <?php if (!empty($row['referral'])): ?>
                                                    <strong>Referral:</strong>
                                                    <?= mephysEscape($row['referral']) ?>
                                                    <br>
                                                <?php endif; ?>

                                                <?php if (!empty($row['notes'])): ?>
                                                    <strong>Notes:</strong>
                                                    <?= mephysEscape($row['notes']) ?>
                                                <?php endif; ?>

                                            </div>

                                        </details>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?= mephysEscape($row['service']) ?>
                                </td>

                                <td>
                                    <?= mephysEscape($row['phone']) ?>
                                </td>

                                <td>

                                    <div class="appointment-date">
                                        <?= mephysEscape($row['appointment_date']) ?>
                                    </div>

                                    <div class="appointment-time">
                                        <?= mephysEscape($row['appointment_time']) ?>
                                    </div>

                                </td>

                                <td>
                                    <?= mephysEscape($row['location']) ?>
                                </td>

                                <td>

                                    <span class="status-badge <?= mephysStatusClass($currentStatus) ?>">
                                        <?= mephysEscape(mephysStatusLabel($currentStatus)) ?>
                                    </span>

                                </td>

                                <td>

                                    <div class="d-flex flex-column gap-2">

                                        <a
                                            href="update_appointment.php?id=<?= (int) $row['id'] ?>"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            Edit Details
                                        </a>

                                        <form
                                            method="POST"
                                            action="update_appointment_status.php"
                                            class="status-form"
                                            onsubmit="return confirm('Are you sure you want to update this appointment status?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $row['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= mephysEscape(mephysCsrfToken()) ?>"
                                            >

                                            <select
                                                name="status"
                                                class="form-select"
                                                required
                                            >

                                                <?php foreach ($allowedStatuses as $status): ?>

                                                    <option
                                                        value="<?= mephysEscape($status) ?>"
                                                        <?= $currentStatus === $status ? 'selected' : '' ?>
                                                    >
                                                        <?= mephysEscape(mephysStatusLabel($status)) ?>
                                                    </option>

                                                <?php endforeach; ?>

                                            </select>

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-primary"
                                            >
                                                Update Status
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <div class="mt-3 text-secondary small">
            Appointment records are retained. Use the status controls to cancel an appointment instead of permanently deleting it.
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
