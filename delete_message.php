
<?php

require_once __DIR__ . '/staff-auth.php';

$staff = mephysRequireStaffLogin();

// Only POST requests are allowed.
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

// Validate message ID.
$id = filter_var(
    $_POST['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$id) {
    http_response_code(400);
    exit('Invalid message ID.');
}

global $pdo;

try {

    // Confirm the message exists before deleting.
    $check = $pdo->prepare("
        SELECT id
        FROM messages
        WHERE id = :id
        LIMIT 1
    ");

    $check->execute([':id' => $id]);

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        header('Location: contact_crud.php?error=not_found');
        exit;
    }

    // Delete the message.
    $stmt = $pdo->prepare("
        DELETE FROM messages
        WHERE id = :id
    ");

    $stmt->execute([':id' => $id]);

    // Record the action in the staff audit log.
    mephysAuditLog(
        'contact_message_deleted',
        'message',
        $id
    );

    header('Location: contact_crud.php?deleted=1');
    exit;

} catch (PDOException $e) {

    error_log("Contact message deletion error: " . $e->getMessage());

    http_response_code(500);
    exit('Unable to delete the message. Please try again later.');

}
