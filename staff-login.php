
<?php

require_once __DIR__ . '/staff-auth.php';

global $pdo;

$error = '';
$successMessage = '';

if (mephysIsStaffLoggedIn()) {
    header('Location: crud.php');
    exit;
}

// Display a generic session-expiry message.
if (isset($_GET['expired'])) {
    $error = 'Your session has expired. Please sign in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate CSRF token.
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!mephysVerifyCsrfToken($csrfToken)) {
        http_response_code(403);
        $error = 'Your request could not be verified. Refresh the page and try again.';

    } else {

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (
            $username === '' ||
            $password === '' ||
            strlen($username) > 60 ||
            strlen($password) > 4096
        ) {
            $error = 'Invalid username or password.';

        } else {

            $usernameHash = hash_hmac(
                'sha256',
                strtolower($username),
                MEPHYS_SECURITY_KEY
            );

            $ipHash = mephysHashIp();

            try {

                // Limit attempts for this username and IP.
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM staff_login_attempts
                     WHERE username_hash = :username_hash
                       AND ip_hash = :ip_hash
                       AND was_successful = 0
                       AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
                );

                $stmt->execute([
                    ':username_hash' => $usernameHash,
                    ':ip_hash' => $ipHash
                ]);

                $usernameAttempts = (int) $stmt->fetchColumn();

                // Also limit attempts originating from the same IP.
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM staff_login_attempts
                     WHERE ip_hash = :ip_hash
                       AND was_successful = 0
                       AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
                );

                $stmt->execute([
                    ':ip_hash' => $ipHash
                ]);

                $ipAttempts = (int) $stmt->fetchColumn();

                if ($usernameAttempts >= 5 || $ipAttempts >= 20) {

                    // Do not reveal which limit was reached.
                    $error = 'Unable to sign in. Please wait 15 minutes before trying again.';

                } else {

                    // Find the account.
                    $stmt = $pdo->prepare(
                        "SELECT id, username, email, password_hash,
                                role, is_active, must_change_password
                         FROM staff_users
                         WHERE username = :username
                         LIMIT 1"
                    );

                    $stmt->execute([
                        ':username' => $username
                    ]);

                    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

                    $authenticated = (
                        $staff &&
                        (int) $staff['is_active'] === 1 &&
                        password_verify($password, $staff['password_hash'])
                    );

                    // Record every attempt without storing passwords.
                    $stmt = $pdo->prepare(
                        "INSERT INTO staff_login_attempts
                            (username_hash, ip_hash, was_successful)
                         VALUES
                            (:username_hash, :ip_hash, :successful)"
                    );

                    $stmt->execute([
                        ':username_hash' => $usernameHash,
                        ':ip_hash' => $ipHash,
                        ':successful' => $authenticated ? 1 : 0
                    ]);

                    if ($authenticated) {

                        // Upgrade older password hashes when necessary.
                        if (password_needs_rehash(
                            $staff['password_hash'],
                            PASSWORD_DEFAULT
                        )) {
                            $newHash = password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            );

                            $update = $pdo->prepare(
                                "UPDATE staff_users
                                 SET password_hash = :password_hash
                                 WHERE id = :id"
                            );

                            $update->execute([
                                ':password_hash' => $newHash,
                                ':id' => $staff['id']
                            ]);
                        }

                        // Prevent session fixation.
                        session_regenerate_id(true);

                        // Store only necessary account information.
                        $_SESSION['staff_user_id'] = (int) $staff['id'];
                        $_SESSION['staff_username'] = $staff['username'];
                        $_SESSION['staff_role'] = $staff['role'];
                        $_SESSION['mephys_authenticated_at'] = time();
                        $_SESSION['mephys_last_activity'] = time();

                        // Rotate the CSRF token after authentication.
                        $_SESSION['mephys_csrf_token'] = bin2hex(random_bytes(32));

                        // Update last login time.
                        $update = $pdo->prepare(
                            "UPDATE staff_users
                             SET last_login_at = NOW()
                             WHERE id = :id"
                        );

                        $update->execute([
                            ':id' => $staff['id']
                        ]);

                        mephysAuditLog(
                            'staff_login',
                            'staff_user',
                            (int) $staff['id']
                        );

                        // Redirect users who must change their password.
                        if ((int) $staff['must_change_password'] === 1) {
                            header('Location: staff-change-password.php');
                            exit;
                        }

                        header('Location: crud.php');
                        exit;

                    } else {
                        // Same message for unknown, disabled and incorrect accounts.
                        $error = 'Invalid username or password.';
                    }
                }

            } catch (Throwable $e) {

                error_log('Mephys login error: ' . $e->getMessage());

                http_response_code(503);

                $error = 'The login service is temporarily unavailable. Please try again later.';
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

    <title>Staff Login | Mephys</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: #f4f7f9;
            font-family: 'Segoe UI', sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 430px;
            background: #fff;
            padding: 35px;
            border-radius: 16px;
            box-shadow: 0 8px 35px rgba(0, 0, 0, 0.08);
        }

        .login-heading {
            color: #263b50;
            font-weight: 700;
        }

        .login-description {
            color: #718096;
            font-size: 14px;
        }

        .form-label {
            font-weight: 600;
            color: #34495e;
        }

        .form-control {
            min-height: 48px;
            border-radius: 8px;
        }

        .form-control:focus {
            border-color: #238b75;
            box-shadow: 0 0 0 0.2rem rgba(35, 139, 117, 0.15);
        }

        .login-button {
            min-height: 48px;
            background: #238b75;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
        }

        .login-button:hover {
            background: #176d5b;
            color: #fff;
        }

        .security-note {
            font-size: 12px;
            color: #718096;
        }
    </style>
</head>

<body>

<div class="login-card">

    <div class="text-center mb-4">

        <h3 class="login-heading">Mephys Administration</h3>

        <p class="login-description">
            Authorized staff access only
        </p>

    </div>

    <?php if ($error !== ''): ?>

        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php endif; ?>

    <form method="POST" action="staff-login.php">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(mephysCsrfToken(), ENT_QUOTES, 'UTF-8') ?>"
        >

        <div class="mb-3">

            <label for="username" class="form-label">
                Username
            </label>

            <input
                type="text"
                name="username"
                id="username"
                class="form-control"
                maxlength="60"
                autocomplete="username"
                required
                autofocus
            >

        </div>

        <div class="mb-4">

            <label for="password" class="form-label">
                Password
            </label>

            <input
                type="password"
                name="password"
                id="password"
                class="form-control"
                autocomplete="current-password"
                required
            >

        </div>

        <button
            type="submit"
            class="btn login-button w-100"
        >
            Sign In
        </button>

    </form>

    <div class="text-center mt-4">

        <p class="security-note mb-0">
            This portal is restricted to authorized Mephys personnel.
        </p>

    </div>

</div>

</body>
</html>
