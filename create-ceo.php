
<?php

// This setup page must only be accessible from the local computer.
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

if (!in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

require_once __DIR__ . '/staff-auth.php';

global $pdo;

$error = '';
$success = false;

// Check whether the initial CEO account already exists.
$stmt = $pdo->query(
    "SELECT COUNT(*) FROM staff_users WHERE role = 'ceo'"
);

$ceoExists = (int) $stmt->fetchColumn() > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$ceoExists) {

    if (!mephysVerifyCsrfToken($_POST['csrf_token'] ?? '')) {

        http_response_code(403);
        $error = 'Invalid request. Refresh the page and try again.';

    } else {

        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (
            !preg_match('/\A[a-zA-Z0-9_.-]{4,60}\z/', $username)
        ) {
            $error = 'Username must contain 4–60 characters using letters, numbers, dots, hyphens or underscores.';

        } elseif (
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 255
        ) {
            $error = 'Enter a valid email address.';

        } elseif (
            strlen($password) < 14 ||
            strlen($password) > 4096
        ) {
            $error = 'Your password must contain at least 14 characters.';

        } elseif (!hash_equals($password, $confirmPassword)) {

            $error = 'The passwords do not match.';

        } elseif (
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[0-9]/', $password) ||
            !preg_match('/[^a-zA-Z0-9]/', $password)
        ) {
            $error = 'Use uppercase and lowercase letters, a number and a special character.';

        } else {

            try {

                // Check again immediately before account creation.
                $stmt = $pdo->query(
                    "SELECT COUNT(*) FROM staff_users WHERE role = 'ceo'"
                );

                if ((int) $stmt->fetchColumn() > 0) {

                    $error = 'The initial CEO account has already been created.';

                } else {

                    $passwordHash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    if ($passwordHash === false) {
                        throw new RuntimeException('Password hashing failed.');
                    }

                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        "INSERT INTO staff_users
                            (username, email, password_hash, role,
                             is_active, must_change_password)
                         VALUES
                            (:username, :email, :password_hash,
                             'ceo', 1, 1)"
                    );

                    $stmt->execute([
                        ':username' => $username,
                        ':email' => $email,
                        ':password_hash' => $passwordHash
                    ]);

                    $ceoId = (int) $pdo->lastInsertId();

                    // Record the initial account creation.
                    $stmt = $pdo->prepare(
                        "INSERT INTO staff_audit_logs
                            (staff_user_id, action, entity_type, entity_id, details, ip_hash)
                         VALUES
                            (:staff_id, 'initial_ceo_created',
                             'staff_user', :entity_id,
                             'Initial CEO account created locally.',
                             :ip_hash)"
                    );

                    $stmt->execute([
                        ':staff_id' => $ceoId,
                        ':entity_id' => $ceoId,
                        ':ip_hash' => mephysHashIp()
                    ]);

                    $pdo->commit();

                    $success = true;
                    $ceoExists = true;

                    // Do not retain submitted passwords.
                    unset($password, $confirmPassword);

                }

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Mephys CEO setup database error: ' . $e->getMessage());

                $error = 'Unable to create the account. Check that the username and email are not already in use.';

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Mephys CEO setup error: ' . $e->getMessage());

                $error = 'An unexpected error occurred. Please try again.';
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

    <title>Initial CEO Setup | Mephys</title>

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

        .setup-card {
            background: #fff;
            width: 100%;
            max-width: 500px;
            padding: 32px;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(0,0,0,.08);
        }

        h2 {
            color: #263b50;
            font-weight: 700;
        }

        .form-control {
            min-height: 46px;
        }

        .setup-button {
            background: #238b75;
            color: #fff;
            font-weight: 600;
            min-height: 46px;
        }

        .setup-button:hover {
            background: #176d5b;
            color: #fff;
        }
    </style>
<link rel="stylesheet" href="mephys-theme.css?v=20261110c">
<link rel="stylesheet" href="mephys-readability.css?v=20261111a">
</head>

<body>

<div class="setup-card">

    <h2 class="text-center mb-2">Initial CEO Setup</h2>

    <p class="text-center text-secondary mb-4">
        Create the first Mephys administration account.
    </p>

    <?php if ($success): ?>

        <div class="alert alert-success">
            <strong>CEO account created successfully.</strong>
            <p class="mb-0 mt-2">
                You can now remove this setup file and sign in.
                You will be required to change your password.
            </p>
        </div>

    <?php elseif ($ceoExists): ?>

        <div class="alert alert-warning">
            The initial CEO account already exists.
            This setup page cannot create another initial CEO account.
        </div>

    <?php else: ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(mephysCsrfToken(), ENT_QUOTES, 'UTF-8') ?>"
            >

            <div class="mb-3">
                <label class="form-label" for="username">CEO Username</label>
                <input
                    type="text"
                    name="username"
                    id="username"
                    class="form-control"
                    minlength="4"
                    maxlength="60"
                    pattern="[a-zA-Z0-9_.-]+"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label" for="email">CEO Email</label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    class="form-control"
                    maxlength="255"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label" for="password">Initial Password</label>
                <input
                    type="password"
                    name="password"
                    id="password"
                    class="form-control"
                    minlength="14"
                    maxlength="4096"
                    autocomplete="new-password"
                    required
                >
                <small class="text-secondary">
                    At least 14 characters, including uppercase, lowercase,
                    a number and a special character.
                </small>
            </div>

            <div class="mb-4">
                <label class="form-label" for="confirm_password">
                    Confirm Password
                </label>
                <input
                    type="password"
                    name="confirm_password"
                    id="confirm_password"
                    class="form-control"
                    minlength="14"
                    maxlength="4096"
                    autocomplete="new-password"
                    required
                >
            </div>

            <button type="submit" class="btn setup-button w-100">
                Create CEO Account
            </button>

        </form>

    <?php endif; ?>

</div>

</body>
</html>
