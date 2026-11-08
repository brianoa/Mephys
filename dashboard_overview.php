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