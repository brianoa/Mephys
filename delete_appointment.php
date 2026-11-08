
<?php

require_once __DIR__ . '/staff-auth.php';

// Require an authenticated staff account.
$staff = mephysRequireStaffLogin();

// Deletion must use POST, never GET.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

// Verify CSRF token.
if (!mephysVerifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid security token. Please refresh the page and try again.');
}

// Validate appointment ID.
$id = filter_var(
    $_POST['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$id) {
    http_response_code(400);
    exit('Invalid appointment ID.');
}

try {
    // Check that the appointment exists.
    $check = $pdo->prepare(
        "SELECT id FROM appointments WHERE id = :id LIMIT 1"
    );
    $check->execute([':id' => $id]);

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        exit('Appointment not found.');
    }

    // Delete the appointment.
    $stmt = $pdo->prepare(
        "DELETE FROM appointments WHERE id = :id"
    );

    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 1) {
        // Record the deletion without storing customer details.
        mephysAuditLog(
            'appointment_deleted',
            'appointment',
            $id
        );

        header('Location: crud.php?deleted=1');
        exit;
    }

    http_response_code(409);
    exit('The appointment could not be deleted. Please try again.');

} catch (PDOException $e) {
    error_log('Appointment deletion error: ' . $e->getMessage());

    http_response_code(500);
    exit('Unable to delete the appointment. Please try again later.');
}
