
<?php

require_once __DIR__ . '/staff-auth.php';

global $pdo;

// Require an authenticated and active staff account.
$staff = mephysRequireStaffLogin();

try {
    $stmt = $pdo->query(
        "SELECT * FROM appointments ORDER BY appointment_date DESC, appointment_time DESC"
    );

    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log('Mephys appointments retrieval error: ' . $e->getMessage());

    http_response_code(500);
    exit('Unable to load appointments. Please try again later.');
}

// Helper for safely displaying database values.
function mephysEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="robots" content="noindex, nofollow">

    <title>Manage Appointments | Mephys</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background-color: #f4f7f9;
            font-family: 'Segoe UI', sans-serif;
            color: #34495e;
        }

        .container {
            margin-top: 40px;
            margin-bottom: 50px;
        }

        .table-container {
            background-color: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.08);
        }

        .page-heading {
            font-weight: 700;
            color: #263b50;
        }

        .staff-info {
            background: #fff;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        }

        .staff-role {
            display: inline-block;
            padding: 5px 10px;
            background: #e8f4f1;
            color: #176d5b;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .table {
            font-size: 14px;
        }

        .table th {
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        .btn-space {
            margin-right: 5px;
        }

        .logout-form {
            display: inline;
        }

        .notes-cell {
            min-width: 160px;
            max-width: 250px;
            white-space: normal;
            overflow-wrap: anywhere;
        }

    </style>

</head>

<body>

<div class="container">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>
            <h2 class="page-heading mb-1">Manage Appointments</h2>

            <p class="text-secondary mb-0">
                Mephys Physiotherapy Clinic Administration
            </p>
        </div>

        <div class="staff-info">

            <div class="mb-2">
                <strong>
                    <?= mephysEscape($staff['username']) ?>
                </strong>
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

    <div class="table-container">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">

            <h5 class="mb-0">
                Appointment Records
                <span class="badge bg-secondary">
                    <?= count($appointments) ?>
                </span>
            </h5>

            <a href="add_appointment.php" class="btn btn-success">
                + New Appointment
            </a>

        </div>

        <div class="mb-3 d-flex flex-wrap gap-2">

            <a
                href="appointments_export.php?type=pdf"
                class="btn btn-danger"
            >
                Export PDF
            </a>

            <a
                href="appointments_export.php?type=excel"
                class="btn btn-success"
            >
                Export Excel
            </a>

            <a
                href="appointments_export.php?type=csv"
                class="btn btn-primary"
            >
                Export CSV
            </a>

        </div>

        <div class="table-responsive">

            <table class="table table-hover table-bordered align-middle text-center">

                <thead class="table-dark">

                    <tr>
                        <th>ID</th>
                        <th>Service</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Location</th>
                        <th>Referral</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (empty($appointments)): ?>

                        <tr>
                            <td colspan="11" class="text-center text-secondary py-4">
                                No appointments have been recorded yet.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($appointments as $row): ?>

                            <tr>

                                <td>
                                    <?= mephysEscape($row['id']) ?>
                                </td>

                                <td>
                                    <?= mephysEscape($row['service']) ?>
                                </td>

                                <td>
                                    <?= mephysEscape($row['full_name']) ?>
                                </td>

                                <td>
                                    <?= mephysEscape($row['email']) ?>
                                </td>

                                <td>
                                    <?= mephysEscape($row['phone']) ?>
                                </td>

                                <td>
                                    <?= mephysEscape($row['appointment_date']) ?>
                                </td>

                                <td>
                                    <?= mephysEscape($row['appointment_time']) ?>
                                </td>

                                <td>
                                    <?= mephysEscape($row['location']) ?>
                                </td>

                                <td>
                                    <?= mephysEscape($row['referral']) ?>
                                </td>

                                <td class="notes-cell">
                                    <?= mephysEscape($row['notes']) ?>
                                </td>

                                <td>

                                    <a
                                        href="update_appointment.php?id=<?= urlencode((string) $row['id']) ?>"
                                        class="btn btn-sm btn-primary btn-space"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="delete_appointment.php?id=<?= urlencode((string) $row['id']) ?>"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Are you sure you want to delete this appointment?');"
                                    >
                                        Delete
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>
