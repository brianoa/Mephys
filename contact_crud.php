
<?php

// Reuse the authenticated staff session when included inside crud.php.
// Authenticate separately when contact_crud.php is opened directly.
if (!isset($staff) || !is_array($staff)) {
    require_once __DIR__ . '/staff-auth.php';
    $staff = mephysRequireStaffLogin();
}

global $pdo;

// Load contact messages.
try {
    $stmt = $pdo->query("
        SELECT id, name, email, message, created_at
        FROM messages
        ORDER BY id DESC
    ");

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Contact messages error: " . $e->getMessage());

    echo '<div class="alert alert-danger">
        Unable to load contact messages. Please try again later.
    </div>';

    return;
}

// Safely display database values.
if (!function_exists('contactEscape')) {
    function contactEscape($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

?>

<style>
    /* All contact-page styling is restricted to this section. */

    #contact-messages-content {
        width: 100%;
        min-width: 0;
    }

    #contact-messages-content .contact-message-panel {
        overflow: hidden;
        border: 1px solid #e8edf3;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 2px 9px rgba(34, 55, 85, .025);
    }

    #contact-messages-content .contact-message-header {
        padding: 17px 20px;
        border-bottom: 1px solid #edf0f4;
        color: #39485b;
        font-size: 13px;
        font-weight: 750;
    }

    #contact-messages-content .contact-table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    #contact-messages-content .contact-message-table {
        width: 100%;
        min-width: 850px;
        margin-bottom: 0;
        font-size: 11px;
    }

    #contact-messages-content .contact-message-table th {
        padding: 12px 13px;
        white-space: nowrap;
        font-size: 10px;
    }

    #contact-messages-content .contact-message-table td {
        padding: 13px;
        vertical-align: middle;
    }

    #contact-messages-content .contact-message-text {
        min-width: 250px;
        max-width: 400px;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    #contact-messages-content .contact-empty-state {
        padding: 40px 15px;
        text-align: center;
        color: #929cab;
    }

    #contact-messages-content .contact-user-info {
        color: #8994a3;
        font-size: 11px;
    }
</style>

<div id="contact-messages-content">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">

        <div>
            <h2 class="page-heading">Contact Messages</h2>

            <div class="page-subtitle">
                Messages submitted through the public website.
            </div>
        </div>

        <div class="contact-user-info">
            Logged in as:
            <strong><?= contactEscape($staff['username'] ?? 'Staff') ?></strong>
        </div>

    </div>

    <?php if (isset($_GET['deleted'])): ?>

        <div class="alert alert-success">
            Message deleted successfully.
        </div>

    <?php endif; ?>

    <section class="contact-message-panel">

        <div class="contact-message-header">
            Received Messages (<?= count($messages) ?>)
        </div>

        <div class="contact-table-wrap">

            <table class="table table-striped table-hover align-middle contact-message-table">

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

                            <td class="contact-message-text">
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
                                        value="<?= (int) $msg['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= contactEscape(mephysCsrfToken()) ?>"
                                    >

                                    <button type="submit" class="btn btn-danger btn-sm">
                                        Delete
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="contact-empty-state">
                            No messages have been received yet.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
(function () {

    const contactContainer = document.getElementById('contact-messages-content');

    if (!contactContainer) {
        return;
    }

    contactContainer.querySelectorAll('.delete-message-form').forEach(form => {

        form.addEventListener('submit', function (event) {

            event.preventDefault();

            const name = this.dataset.name || 'this contact';

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

})();
</script>
