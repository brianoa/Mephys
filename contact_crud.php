
<?php

require_once __DIR__ . '/staff-auth.php';

$staff = mephysRequireStaffLogin();

global $pdo;

try {
    $stmt = $pdo->query("
        SELECT id, name, email, message, created_at
        FROM messages
        ORDER BY id DESC
    ");

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Contact messages error: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to load contact messages. Please try again later.");
}

function contactEscape($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
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

    <title>Contact Messages - Mephys</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-5">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">

        <div>
            <h2 class="mb-1">Contact Messages</h2>
            <p class="text-muted mb-0">
                Messages submitted through the public website.
            </p>
        </div>

        <div class="text-end">
            <span class="text-muted small">
                Logged in as: <?= contactEscape($staff['username']) ?>
            </span>

            <div class="mt-2">
                <a href="crud.php" class="btn btn-outline-primary btn-sm">
                    Back to Appointments
                </a>
            </div>
        </div>

    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">
            Message deleted successfully.
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white py-3">
            <strong>Received Messages (<?= count($messages) ?>)</strong>
        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-striped table-hover align-middle mb-0">

                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Message</th>
                            <th>Date Received</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (!empty($messages)): ?>

                        <?php $i = 1; ?>

                        <?php foreach ($messages as $msg): ?>

                            <tr>
                                <td><?= $i++ ?></td>

                                <td>
                                    <?= contactEscape($msg['name']) ?>
                                </td>

                                <td>
                                    <a href="mailto:<?= contactEscape($msg['email']) ?>">
                                        <?= contactEscape($msg['email']) ?>
                                    </a>
                                </td>

                                <td style="min-width:250px; max-width:400px; white-space:normal;">
                                    <?= nl2br(contactEscape($msg['message'])) ?>
                                </td>

                                <td>
                                    <?= contactEscape($msg['created_at'] ?? '') ?>
                                </td>

                                <td>
                                    <form
                                        method="POST"
                                        action="delete_message.php"
                                        class="delete-message-form"
                                        data-name="<?= contactEscape($msg['name']) ?>"
                                    >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$msg['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= contactEscape(mephysCsrfToken()) ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-danger btn-sm"
                                        >
                                            Delete
                                        </button>

                                    </form>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No messages have been received yet.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.querySelectorAll('.delete-message-form').forEach(form => {

    form.addEventListener('submit', function(event) {

        event.preventDefault();

        const name = this.dataset.name;

        Swal.fire({
            title: 'Delete this message?',
            text: `This message from ${name} will be permanently deleted.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel'
        }).then(result => {

            if (result.isConfirmed) {
                this.submit();
            }

        });

    });

});
</script>

</body>
</html>
