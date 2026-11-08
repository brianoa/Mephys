
<?php

require_once __DIR__ . '/staff-auth.php';

use PHPMailer\PHPMailer\PHPMailer;

// Require an authenticated staff account.
$staff = mephysRequireStaffLogin();

// Only POST requests are permitted.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

// Verify CSRF token.
if (!mephysVerifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid security token. Please refresh and try again.');
}

// Validate appointment ID.
$id = filter_var(
    $_POST['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

// Allowed appointment statuses.
$allowedStatuses = [
    'pending',
    'confirmed',
    'completed',
    'cancelled'
];

$newStatus = strtolower(trim($_POST['status'] ?? ''));

if (!$id) {
    http_response_code(400);
    exit('Invalid appointment ID.');
}

if (!in_array($newStatus, $allowedStatuses, true)) {
    http_response_code(400);
    exit('Invalid appointment status.');
}

global $pdo;

try {

    // Retrieve the appointment details and its current status.
    $check = $pdo->prepare(
        "SELECT
            id,
            status,
            full_name,
            email,
            service,
            appointment_date,
            appointment_time,
            location
         FROM appointments
         WHERE id = :id
         LIMIT 1"
    );

    $check->execute([':id' => $id]);

    $appointment = $check->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        http_response_code(404);
        exit('Appointment not found.');
    }

    $oldStatus = $appointment['status'];

    // Only process an actual status change.
    if ($oldStatus !== $newStatus) {

        // Update only if the status has not changed since it was retrieved.
        // This also helps prevent duplicate notifications from simultaneous requests.
        $update = $pdo->prepare(
            "UPDATE appointments
             SET status = :status,
                 status_updated_at = NOW(),
                 status_updated_by = :staff_id
             WHERE id = :id
               AND status = :old_status"
        );

        $update->execute([
            ':status' => $newStatus,
            ':staff_id' => $staff['id'],
            ':id' => $id,
            ':old_status' => $oldStatus
        ]);

        // Continue only if this request actually changed the status.
        if ($update->rowCount() === 1) {

            // Record the status change in the staff audit log.
            mephysAuditLog(
                'appointment_status_changed',
                'appointment',
                $id
            );

            // Send an email only for confirmation or cancellation.
            if (in_array($newStatus, ['confirmed', 'cancelled'], true)) {

                try {

                    // Load SMTP configuration.
                    $mailConfig = require __DIR__ . '/smtp-config.php';

                    // Load PHPMailer.
                    require_once __DIR__ . '/src/Exception.php';
                    require_once __DIR__ . '/src/PHPMailer.php';
                    require_once __DIR__ . '/src/SMTP.php';

                    // Validate SMTP configuration.
                    foreach (
                        ['host', 'username', 'password', 'port', 'admin_email']
                        as $configKey
                    ) {
                        if (
                            !isset($mailConfig[$configKey]) ||
                            $mailConfig[$configKey] === ''
                        ) {
                            throw new RuntimeException(
                                'Missing SMTP configuration: ' . $configKey
                            );
                        }
                    }

                    // Validate the client's email address.
                    if (!filter_var($appointment['email'], FILTER_VALIDATE_EMAIL)) {
                        throw new RuntimeException(
                            'Invalid client email address for appointment #' . $id
                        );
                    }

                    // Safely escape appointment information for HTML.
                    $safeName = htmlspecialchars(
                        $appointment['full_name'],
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    );

                    $safeService = htmlspecialchars(
                        $appointment['service'],
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    );

                    $safeDate = htmlspecialchars(
                        $appointment['appointment_date'],
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    );

                    $safeTime = htmlspecialchars(
                        substr((string) $appointment['appointment_time'], 0, 5),
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    );

                    $safeLocation = htmlspecialchars(
                        $appointment['location'] ?? '',
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    );

                    // Create the email.
                    $mail = new PHPMailer(true);

                    $mail->isSMTP();
                    $mail->Host = $mailConfig['host'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $mailConfig['username'];
                    $mail->Password = $mailConfig['password'];
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = (int) $mailConfig['port'];

                    $mail->CharSet = PHPMailer::CHARSET_UTF8;
                    $mail->isHTML(true);

                    $mail->setFrom(
                        $mailConfig['username'],
                        'Mephys Physiotherapy Clinic'
                    );

                    $mail->addAddress(
                        $appointment['email'],
                        $appointment['full_name']
                    );

                    $mail->addReplyTo(
                        $mailConfig['admin_email'],
                        'Mephys Physiotherapy Clinic'
                    );

                    // ====================================
                    // APPOINTMENT CONFIRMATION EMAIL
                    // ====================================

                    if ($newStatus === 'confirmed') {

                        $mail->Subject =
                            'Your Appointment Has Been Confirmed - Mephys Clinic';

                        $mail->Body = "
                            <h3>Dear {$safeName},</h3>

                            <p>
                                We are pleased to inform you that your
                                appointment request with
                                <strong>Mephys Physiotherapy Clinic</strong>
                                has been approved.
                            </p>

                            <p>Here are your confirmed appointment details:</p>

                            <p>
                                <strong>Reference:</strong> #{$id}<br>
                                <strong>Service:</strong> {$safeService}<br>
                                <strong>Date:</strong> {$safeDate}<br>
                                <strong>Time:</strong> {$safeTime}<br>
                                <strong>Location:</strong> {$safeLocation}
                            </p>

                            <p>
                                Your appointment is now confirmed.
                                We look forward to serving you.
                            </p>

                            <p>
                                Warm regards,<br>
                                <strong>Mephys Clinic Team</strong>
                            </p>
                        ";

                        $mail->AltBody =
                            "Dear {$appointment['full_name']},\n\n" .
                            "Your appointment request with Mephys Physiotherapy Clinic has been approved.\n\n" .
                            "Reference: #{$id}\n" .
                            "Service: {$appointment['service']}\n" .
                            "Date: {$appointment['appointment_date']}\n" .
                            "Time: " . substr((string) $appointment['appointment_time'], 0, 5) . "\n" .
                            "Location: {$appointment['location']}\n\n" .
                            "Your appointment is now confirmed.\n\n" .
                            "Mephys Clinic Team";

                    }

                    // ====================================
                    // APPOINTMENT CANCELLATION EMAIL
                    // ====================================

                    elseif ($newStatus === 'cancelled') {

                        $mail->Subject =
                            'Appointment Cancellation Notice - Mephys Clinic';

                        $mail->Body = "
                            <h3>Dear {$safeName},</h3>

                            <p>
                                We regret to inform you that your appointment
                                request with
                                <strong>Mephys Physiotherapy Clinic</strong>
                                has been cancelled.
                            </p>

                            <p>Here are the details of the affected appointment:</p>

                            <p>
                                <strong>Reference:</strong> #{$id}<br>
                                <strong>Service:</strong> {$safeService}<br>
                                <strong>Date:</strong> {$safeDate}<br>
                                <strong>Time:</strong> {$safeTime}<br>
                                <strong>Location:</strong> {$safeLocation}
                            </p>

                            <p>
                                If you would like to enquire about this
                                cancellation or arrange another appointment,
                                please reply to this email and our clinic
                                team will assist you.
                            </p>

                            <p>
                                We apologise for any inconvenience.
                            </p>

                            <p>
                                Warm regards,<br>
                                <strong>Mephys Clinic Team</strong>
                            </p>
                        ";

                        $mail->AltBody =
                            "Dear {$appointment['full_name']},\n\n" .
                            "We regret to inform you that your appointment request with Mephys Physiotherapy Clinic has been cancelled.\n\n" .
                            "Reference: #{$id}\n" .
                            "Service: {$appointment['service']}\n" .
                            "Date: {$appointment['appointment_date']}\n" .
                            "Time: " . substr((string) $appointment['appointment_time'], 0, 5) . "\n" .
                            "Location: {$appointment['location']}\n\n" .
                            "If you would like to enquire about this cancellation or arrange another appointment, please reply to this email.\n\n" .
                            "We apologise for any inconvenience.\n\n" .
                            "Mephys Clinic Team";
                    }

                    // Send the notification.
                    $mail->send();

                } catch (Throwable $emailError) {

                    // Email failure must not reverse the status update.
                    error_log(
                        'Mephys appointment status email failed | ' .
                        'Appointment #' . $id .
                        ' | New status: ' . $newStatus .
                        ' | Error: ' . $emailError->getMessage()
                    );
                }
            }
        }
    }

    // Return staff to the dashboard.
    header('Location: crud.php?status_updated=1');
    exit;

} catch (PDOException $e) {

    error_log(
        'Mephys appointment status update error: ' .
        $e->getMessage()
    );

    http_response_code(500);
    exit('Unable to update appointment status. Please try again later.');
}
