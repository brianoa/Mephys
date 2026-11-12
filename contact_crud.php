
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
    /* Contact messages — full-sized text on desktop, readable stacked cards on phones. */
    #contact-messages-content { width: 100%; min-width: 0; }
    #contact-messages-content .contact-message-panel {
        background: #f9fbfc;
        border: 1px solid #d6e2e6;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(29, 54, 65, .05);
    }
    #contact-messages-content .contact-message-header {
        padding: 19px 22px;
        background: #edf4f6;
        border-bottom: 1px solid #d6e2e6;
        color: #253d49;
        font-size: 1.125rem;
        font-weight: 750;
    }
    #contact-messages-content .contact-table-wrap { overflow-x: auto; width: 100%; }
    #contact-messages-content .contact-message-table {
        width: 100%;
        min-width: 880px;
        margin: 0;
        font-size: 1rem;
        line-height: 1.6;
        --bs-table-bg: #f9fbfc;
        --bs-table-striped-bg: #eef4f6;
        --bs-table-hover-bg: #e5eff2;
        --bs-table-color: #273f4b;
        --bs-table-border-color: #dce6e9;
    }
    #contact-messages-content .contact-message-table th {
        padding: 15px 13px;
        font-size: .9375rem;
        font-weight: 700;
        background: #344e5c;
        color: #fff;
        white-space: nowrap;
    }
    #contact-messages-content .contact-message-table td {
        padding: 16px 13px;
        font-size: 1rem;
        vertical-align: top;
        overflow-wrap: anywhere;
    }
    #contact-messages-content .contact-message-text {
        min-width: 220px;
        max-width: 520px;
        white-space: normal;
        overflow-wrap: anywhere;
        line-height: 1.7;
    }
    #contact-messages-content .contact-message-table a { color: #075d6a; }
    #contact-messages-content .contact-message-table td:last-child { min-width: 95px; white-space: nowrap; }
    #contact-messages-content .contact-message-table .btn {
        font-size: .9375rem;
        min-height: 40px;
        min-width: 75px;
        white-space: nowrap;
        padding: 7px 10px;
    }
    #contact-messages-content .contact-user-info { color: #506875; font-size: .9375rem; }
    #contact-messages-content .contact-empty-state { padding: 42px 16px; text-align: center; color: #526775; }

    html.dark-mode #contact-messages-content .contact-message-panel { background: #192a36; border-color: #304653; }
    html.dark-mode #contact-messages-content .contact-message-header { background: #203643; color: #e5f0f5; border-color: #304653; }
    html.dark-mode #contact-messages-content .contact-message-table {
        --bs-table-bg: #192a36; --bs-table-striped-bg: #1f3440;
        --bs-table-hover-bg: #263d49; --bs-table-color: #e0ebf0;
        --bs-table-border-color: #304653;
    }
    html.dark-mode #contact-messages-content .contact-message-table th { background: #203440 !important; color: #e6f1f5 !important; }
    html.dark-mode #contact-messages-content .contact-message-table a { color: #80d5e7; }
    html.dark-mode #contact-messages-content .contact-user-info { color: #adc2ce; }

    @media (max-width: 767.98px) {
        #contact-messages-content .contact-table-wrap { overflow: visible; }
        #contact-messages-content .contact-message-table,
        #contact-messages-content .contact-message-table tbody,
        #contact-messages-content .contact-message-table tr,
        #contact-messages-content .contact-message-table td { display: block; width: 100%; min-width: 0; }
        #contact-messages-content .contact-message-table thead { display: none; }
        #contact-messages-content .contact-message-table tbody tr {
            padding: 14px 18px;
            border-bottom: 1px solid #dce5e9;
        }
        #contact-messages-content .contact-message-table tbody td {
            display: grid;
            grid-template-columns: minmax(90px, 34%) minmax(0, 1fr);
            align-items: start;
            column-gap: 12px;
            padding: 10px 0;
            font-size: 1rem;
            border: 0;
            line-height: 1.6;
        }
        #contact-messages-content .contact-message-table tbody td[data-label]::before {
            content: attr(data-label);
            font-size: .9375rem;
            font-weight: 700;
            color: #506875;
        }
        #contact-messages-content .contact-message-table tbody td.contact-message-text { max-width: none; }
        #contact-messages-content .contact-message-table tbody td.contact-empty-state { display: block; text-align: center; }
        html.dark-mode #contact-messages-content .contact-message-table tbody td[data-label]::before { color: #bfd1da; }
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

                            <td data-label="Number"><?= $i++ ?></td>

                            <td data-label="Name">
                                <?= contactEscape($msg['name']) ?>
                            </td>

                            <td data-label="Email">
                                <a href="mailto:<?= contactEscape($msg['email']) ?>">
                                    <?= contactEscape($msg['email']) ?>
                                </a>
                            </td>

                            <td class="contact-message-text" data-label="Message">
                                <?= nl2br(contactEscape($msg['message'])) ?>
                            </td>

                            <td data-label="Received">
                                <?= contactEscape($msg['created_at'] ?? '') ?>
                            </td>

                            <td data-label="Action">

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

            if (!window.Swal) {
                if (window.confirm('Permanently delete this message from ' + name + '?')) {
                    this.submit();
                }
                return;
            }

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
