
<?php

require_once __DIR__ . '/staff-auth.php';

global $pdo;

// Only logged-in staff may access this page.
$staff = mephysRequireStaffLogin();

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!mephysVerifyCsrfToken($_POST['csrf_token'] ?? '')) {

        http_response_code(403);
        $error = 'Your request could not be verified. Refresh the page and try again.';

    } else {

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (
            $currentPassword === '' ||
            $newPassword === '' ||
            $confirmPassword === ''
        ) {
            $error = 'Please complete all password fields.';

        } elseif (
            strlen($currentPassword) > 4096 ||
            strlen($newPassword) > 4096
        ) {
            $error = 'Invalid password input.';

        } elseif (strlen($newPassword) < 14) {

            $error = 'Your new password must contain at least 14 characters.';

        } elseif (
            !preg_match('/[a-z]/', $newPassword) ||
            !preg_match('/[A-Z]/', $newPassword) ||
            !preg_match('/[0-9]/', $newPassword) ||
            !preg_match('/[^a-zA-Z0-9]/', $newPassword)
        ) {
            $error = 'Use uppercase and lowercase letters, a number and a special character.';

        } elseif (!hash_equals($newPassword, $confirmPassword)) {

            $error = 'The new passwords do not match.';

        } else {

            try {

                // Retrieve the latest password hash.
                $stmt = $pdo->prepare(
                    "SELECT password_hash
                     FROM staff_users
                     WHERE id = :id
                       AND is_active = 1
                     LIMIT 1"
                );

                $stmt->execute([
                    ':id' => $staff['id']
                ]);

                $account = $stmt->fetch(PDO::FETCH_ASSOC);

                if (
                    !$account ||
                    !password_verify($currentPassword, $account['password_hash'])
                ) {

                    $error = 'Your current password is incorrect.';

                } elseif (
                    password_verify($newPassword, $account['password_hash'])
                ) {

                    $error = 'Your new password must be different from your current password.';

                } else {

                    $newHash = password_hash(
                        $newPassword,
                        PASSWORD_DEFAULT
                    );

                    if ($newHash === false) {
                        throw new RuntimeException('Password hashing failed.');
                    }

                    $pdo->beginTransaction();

                    $update = $pdo->prepare(
                        "UPDATE staff_users
                         SET password_hash = :password_hash,
                             must_change_password = 0
                         WHERE id = :id
                           AND is_active = 1"
                    );

                    $update->execute([
                        ':password_hash' => $newHash,
                        ':id' => $staff['id']
                    ]);

                    if ($update->rowCount() !== 1) {
                        throw new RuntimeException('Password update failed.');
                    }

                    $log = $pdo->prepare(
                        "INSERT INTO staff_audit_logs
                            (staff_user_id, action, entity_type, entity_id, details, ip_hash)
                         VALUES
                            (:staff_id, 'password_changed',
                             'staff_user', :entity_id,
                             'Staff password changed successfully.',
                             :ip_hash)"
                    );

                    $log->execute([
                        ':staff_id' => $staff['id'],
                        ':entity_id' => $staff['id'],
                        ':ip_hash' => mephysHashIp()
                    ]);

                    $pdo->commit();

                    // Rotate the session ID and CSRF token.
                    session_regenerate_id(true);

                    $_SESSION['mephys_csrf_token'] = bin2hex(random_bytes(32));
                    $_SESSION['mephys_last_activity'] = time();

                    unset(
                        $currentPassword,
                        $newPassword,
                        $confirmPassword
                    );

                    $success = true;
                }

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Mephys password change error: ' . $e->getMessage());

                $error = 'Unable to update your password. Please try again.';
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="robots" content="noindex, nofollow">

    <title>Change Password | Mephys</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f4f7f9;
            font-family: 'Segoe UI', sans-serif;
            padding: 20px;
        }

        .password-card {
            width: 100%;
            max-width: 480px;
            background: #fff;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 8px 30px rgba(0,0,0,.08);
        }

        h2 {
            color: #263b50;
            font-weight: 700;
        }

        .form-control {
            min-height: 46px;
        }

        .save-button {
            background: #238b75;
            color: #fff;
            min-height: 46px;
            font-weight: 600;
        }

        .save-button:hover {
            background: #176d5b;
            color: #fff;
        }
    </style>

<link rel="stylesheet" href="mephys-theme.css?v=20261111readable">
<link rel="stylesheet" href="mephys-readability.css?v=20261111a">
</head>

<body>

<div class="password-card">

    <h2 class="text-center mb-2">Change Your Password</h2>

    <p class="text-secondary text-center mb-4">
        Create a new password to secure your Mephys account.
    </p>

    <?php if ($success): ?>

        <div class="alert alert-success">
            Your password has been changed successfully.
            You can now access the administration portal.
        </div>

        <a href="crud.php" class="btn save-button w-100">
            Continue to Appointments
        </a>

    <?php else: ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="staff-change-password.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(mephysCsrfToken(), ENT_QUOTES, 'UTF-8') ?>"
            >

            <div class="mb-3">
                <label for="current_password" class="form-label">
                    Current Password
                </label>

                <input
                    type="password"
                    name="current_password"
                    id="current_password"
                    class="form-control"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="new_password" class="form-label">
                    New Password
                </label>

                <input
                    type="password"
                    name="new_password"
                    id="new_password"
                    class="form-control"
                    minlength="14"
                    autocomplete="new-password"
                    required
                >

                <small class="text-secondary">
                    At least 14 characters, including uppercase, lowercase,
                    a number and a special character.
                </small>
            </div>

            <div class="mb-4">
                <label for="confirm_password" class="form-label">
                    Confirm New Password
                </label>

                <input
                    type="password"
                    name="confirm_password"
                    id="confirm_password"
                    class="form-control"
                    minlength="14"
                    autocomplete="new-password"
                    required
                >
            </div>

            <button type="submit" class="btn save-button w-100">
                Update Password
            </button>

        </form>

    <?php endif; ?>

</div>

</body>
</html>
