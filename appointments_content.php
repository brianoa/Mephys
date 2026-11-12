<?php

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
<div class="welcome-row">
                <div>
                    <h1 class="page-heading">Appointments</h1>
                    <div class="page-subtitle">View and manage client appointment requests.</div>
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
            <section class="stats-grid" aria-label="Appointment statistics">
                <div class="stat-card">
                    <div class="stat-label">Total Appointments</div>
                    <div class="stat-number"><?= $statusCounts['total'] ?></div>
                    <div class="stat-indicator indicator-total"></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending</div>
                    <div class="stat-number"><?= $statusCounts['pending'] ?></div>
                    <div class="stat-indicator indicator-pending"></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Confirmed</div>
                    <div class="stat-number"><?= $statusCounts['confirmed'] ?></div>
                    <div class="stat-indicator indicator-confirmed"></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Completed</div>
                    <div class="stat-number"><?= $statusCounts['completed'] ?></div>
                    <div class="stat-indicator indicator-completed"></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Cancelled</div>
                    <div class="stat-number"><?= $statusCounts['cancelled'] ?></div>
                    <div class="stat-indicator indicator-cancelled"></div>
                </div>
            </section>

            <!-- Appointment records -->
            <section class="panel" id="appointments">

                <div class="panel-header">
                    <div>
                        <h2 class="section-heading">Appointment Records</h2>
                        <div class="results-count"><?= count($appointments) ?> matching appointment(s)</div>
                    </div>

                    <div class="export-actions">
                        <a href="appointments_export.php?type=pdf" class="export-btn">
                            <i class="bi bi-file-earmark-pdf"></i> Export PDF
                        </a>
                        <a href="appointments_export.php?type=excel" class="export-btn">
                            <i class="bi bi-file-earmark-excel"></i> Export Excel
                        </a>
                        <a href="appointments_export.php?type=csv" class="export-btn">
                            <i class="bi bi-filetype-csv"></i> Export CSV
                        </a>
                    </div>
                </div>

                <!-- Search and filters -->
                <form method="GET" action="crud.php" class="filters">
                    <input type="hidden" name="page" value="appointments">

                    <div class="search-filter">
                        <label class="filter-label">Search client</label>
                        <input
                            type="search"
                            name="search"
                            class="form-control"
                            placeholder="Name, email or phone..."
                            value="<?= mephysEscape($search) ?>"
                        >
                    </div>

                    <div>
                        <label class="filter-label">Appointment Status</label>
                        <select name="status" class="form-select">
                            <option value="">All statuses</option>
                            <?php foreach ($allowedStatuses as $status): ?>
                                <option value="<?= mephysEscape($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>>
                                    <?= mephysEscape(mephysStatusLabel($status)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="filter-label">Service</label>
                        <select name="service" class="form-select">
                            <option value="">All services</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= mephysEscape($service) ?>" <?= $serviceFilter === $service ? 'selected' : '' ?>>
                                    <?= mephysEscape($service) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="filter-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?= mephysEscape($dateFrom) ?>">
                    </div>

                    <div>
                        <label class="filter-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?= mephysEscape($dateTo) ?>">
                    </div>

                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="crud.php?page=appointments" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>

                <!-- Appointment table -->
                <div class="table-wrap">
                    <table class="appointments-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Service</th>
                                <th>Phone</th>
                                <th>Appointment</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (empty($appointments)): ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
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
                                            <div class="patient-name"><?= mephysEscape($row['full_name']) ?></div>
                                            <div class="patient-email"><?= mephysEscape($row['email']) ?></div>

                                            <?php if (!empty($row['referral']) || !empty($row['notes'])): ?>
                                                <details class="details-box">
                                                    <summary class="icon-action" title="View additional details" aria-label="View additional details">
                                                        <i class="bi bi-eye"></i>
                                                    </summary>
                                                    <div class="details-content">
                                                        <?php if (!empty($row['referral'])): ?>
                                                            <strong>Referral:</strong> <?= mephysEscape($row['referral']) ?><br>
                                                        <?php endif; ?>
                                                        <?php if (!empty($row['notes'])): ?>
                                                            <strong>Notes:</strong> <?= nl2br(mephysEscape($row['notes'])) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </details>
                                            <?php endif; ?>
                                        </td>

                                        <td><?= mephysEscape($row['service']) ?></td>
                                        <td><?= mephysEscape($row['phone']) ?></td>

                                        <td>
                                            <div class="appointment-date"><?= mephysEscape($row['appointment_date']) ?></div>
                                            <div class="appointment-time"><?= mephysEscape(date('g:i A', strtotime((string) $row['appointment_time']) ?: time())) ?> EAT</div>
                                        </td>

                                        <td>
                                            <div class="fw-semibold" style="max-width:280px;overflow-wrap:anywhere"><?= mephysEscape($row['location']) ?></div>
                                            <?php if (preg_match('/^Visit type:\s*(.+)$/m', (string)($row['notes'] ?? ''), $visitMatch)): ?>
                                                <span class="small text-muted d-block mt-1"><?= mephysEscape(trim($visitMatch[1])) ?></span>
                                            <?php endif; ?>
                                            <?php if (preg_match('/^Visit contact phone:\s*(.+)$/m', (string)($row['notes'] ?? ''), $phoneMatch)): ?>
                                                <span class="small d-block mt-1"><i class="bi bi-telephone" aria-hidden="true"></i> <?= mephysEscape(trim($phoneMatch[1])) ?></span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="status-badge <?= mephysStatusClass($currentStatus) ?>">
                                                <?= mephysEscape(mephysStatusLabel($currentStatus)) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="row-actions">
                                                <a
                                                    href="update_appointment.php?id=<?= (int) $row['id'] ?>"
                                                    class="icon-action edit"
                                                    title="Edit appointment"
                                                    aria-label="Edit appointment"
                                                >
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>

                                                <form
                                                    method="POST"
                                                    action="update_appointment_status.php"
                                                    class="status-form"
                                                    onsubmit="return confirm('Are you sure you want to update this appointment status?');"
                                                >
                                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= mephysEscape(mephysCsrfToken()) ?>">

                                                    <select name="status" class="form-select" required aria-label="Change appointment status">
                                                        <?php foreach ($allowedStatuses as $status): ?>
                                                            <option value="<?= mephysEscape($status) ?>" <?= $currentStatus === $status ? 'selected' : '' ?>>
                                                                <?= mephysEscape(mephysStatusLabel($status)) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>

                                                    <button type="submit" class="btn btn-primary" title="Save status">
                                                        <i class="bi bi-check2"></i>
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

                <div class="table-footer">
                    Appointment records are retained. Use the status controls to cancel an appointment instead of permanently deleting it.
                </div>

            </section>
