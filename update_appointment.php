
<?php

require_once __DIR__ . '/staff-auth.php';

// Only authenticated staff can edit appointments.
$staff = mephysRequireStaffLogin();

$error = '';
$success = false;

// Safely escape values before displaying them in HTML.
function editAppointmentEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Validate the appointment ID.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid appointment ID.');
}

try {
    // Fetch the appointment.
    $stmt = $pdo->prepare(
        "SELECT * FROM appointments WHERE id = :id LIMIT 1"
    );
    $stmt->execute([':id' => $id]);

    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        http_response_code(404);
        exit('Appointment not found.');
    }

    // Process the submitted form.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Verify CSRF token.
        if (!mephysVerifyCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            $error = 'Your security token has expired or is invalid. Please refresh the page and try again.';
        } else {

            // Collect and clean submitted values.
            $service = trim($_POST['service'] ?? '');
            $name = trim($_POST['fullName'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $date = trim($_POST['date'] ?? '');
            $time = trim($_POST['time'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $location = trim($_POST['location'] ?? '');

            // Validate required fields.
            if (
                $service === '' ||
                $name === '' ||
                $email === '' ||
                $phone === '' ||
                $date === '' ||
                $time === '' ||
                $location === ''
            ) {
                $error = 'Please complete all required fields.';
            } elseif (strlen($service) > 150) {
                $error = 'The service name is too long.';
            } elseif (strlen($name) > 150) {
                $error = 'The full name is too long.';
            } elseif (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } elseif (strlen($phone) > 30) {
                $error = 'The phone number is too long.';
            } elseif (strlen($location) > 255) {
                $error = 'The location cannot exceed 255 characters.';
            } elseif (strlen($notes) > 10000) {
                $error = 'The notes are too long.';
            } else {

                // Validate the calendar date.
                $dateObject = DateTime::createFromFormat('!Y-m-d', $date);
                $dateErrors = DateTime::getLastErrors();

                $validDate = $dateObject !== false
                    && $dateObject->format('Y-m-d') === $date
                    && ($dateErrors === false ||
                        ($dateErrors['warning_count'] === 0 &&
                         $dateErrors['error_count'] === 0));

                // Validate the time in 24-hour format.
                $validTime = preg_match(
                    '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                    $time
                );

                if (!$validDate) {
                    $error = 'Please enter a valid appointment date.';
                } elseif (!$validTime) {
                    $error = 'Please enter a valid appointment time.';
                } else {

                    // Update the appointment.
                    $update = $pdo->prepare(
                        "UPDATE appointments
                         SET service = :service,
                             full_name = :name,
                             email = :email,
                             phone = :phone,
                             appointment_date = :date,
                             appointment_time = :time,
                             location = :location,
                             notes = :notes
                         WHERE id = :id"
                    );

                    $update->execute([
                        ':service' => $service,
                        ':name' => $name,
                        ':email' => $email,
                        ':phone' => $phone,
                        ':date' => $date,
                        ':time' => $time,
                        ':location' => $location,
                        ':notes' => $notes !== '' ? $notes : null,
                        ':id' => $id
                    ]);

                    // Record the successful edit in the audit log.
                    mephysAuditLog(
                        'appointment_updated',
                        'appointment',
                        $id
                    );

                    // Prevent the form from being submitted again on refresh.
                    header('Location: crud.php?updated=1');
                    exit;
                }
            }

            // Keep submitted values visible if validation fails.
            if ($error !== '') {
                $appointment['service'] = $service;
                $appointment['full_name'] = $name;
                $appointment['email'] = $email;
                $appointment['phone'] = $phone;
                $appointment['appointment_date'] = $date;
                $appointment['appointment_time'] = $time;
                $appointment['location'] = $location;
                $appointment['notes'] = $notes;
            }
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        header('Allow: GET, POST');
        exit('Method not allowed.');
    }

} catch (PDOException $e) {
    error_log('Appointment update error: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to process the appointment. Please try again later.');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Appointment | Mephys</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background-color: #f4f7f9;
        }

        .form-container {
            margin-top: 50px;
            margin-bottom: 50px;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        h2 {
            font-weight: 600;
        }
    </style>
<link rel="stylesheet" href="mephys-theme.css?v=20261111readable">
<link rel="stylesheet" href="mephys-readability.css?v=20261111a">
</head>

<body>

<div class="container">
    <div class="form-container">

        <h2 class="mb-4 text-center">Edit Appointment</h2>

        <p class="text-muted text-center">
            Logged in as:
            <?= editAppointmentEscape($staff['username']) ?>
        </p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert">
                <?= editAppointmentEscape($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="update_appointment.php?id=<?= (int) $id ?>">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= editAppointmentEscape(mephysCsrfToken()) ?>"
            >

            <div class="mb-3">
                <label class="form-label">Service</label>
                <input
                    type="text"
                    class="form-control"
                    name="service"
                    maxlength="150"
                    value="<?= editAppointmentEscape($appointment['service']) ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input
                    type="text"
                    class="form-control"
                    name="fullName"
                    maxlength="150"
                    value="<?= editAppointmentEscape($appointment['full_name']) ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input
                    type="email"
                    class="form-control"
                    name="email"
                    maxlength="255"
                    value="<?= editAppointmentEscape($appointment['email']) ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label">Phone</label>
                <input
                    type="text"
                    class="form-control"
                    name="phone"
                    maxlength="30"
                    value="<?= editAppointmentEscape($appointment['phone']) ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label">Date</label>
                <input
                    type="date"
                    class="form-control"
                    name="date"
                    value="<?= editAppointmentEscape($appointment['appointment_date']) ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label">Time</label>
                <input
                    type="time"
                    class="form-control"
                    name="time"
                    value="<?= editAppointmentEscape(substr((string) $appointment['appointment_time'], 0, 5)) ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label" for="appointmentEditLocation">Visit location / full address</label>
                <textarea id="appointmentEditLocation" class="form-control" name="location" maxlength="255" rows="2" required><?= editAppointmentEscape($appointment['location']) ?></textarea>
                <div class="form-text">Update this only if the visit location changes. Contact and directions information is also in the appointment notes.</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Notes, visit contact and directions</label>
                <textarea
                    class="form-control"
                    name="notes"
                    rows="3"
                    maxlength="10000"
                ><?= editAppointmentEscape($appointment['notes']) ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                Update Appointment
            </button>

            <a href="crud.php" class="btn btn-secondary w-100 mt-2">
                Cancel
            </a>

        </form>
    </div>
</div>

</body>
</html>
