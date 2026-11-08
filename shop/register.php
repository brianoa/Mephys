<?php
declare(strict_types=1);

require_once __DIR__ . '/_public-bootstrap.php';

if (mephysShopCustomer()) {
    header('Location: account.php');
    exit;
}

$error = '';
$values = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
];

$next = mephysShopSafeNext((string) ($_GET['next'] ?? $_POST['next'] ?? 'account.php'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mephysShopPublicVerifyCsrf();

    $values['first_name'] = trim((string) ($_POST['first_name'] ?? ''));
    $values['last_name'] = trim((string) ($_POST['last_name'] ?? ''));
    $values['email'] = strtolower(trim((string) ($_POST['email'] ?? '')));
    $values['phone'] = trim((string) ($_POST['phone'] ?? ''));

    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($values['first_name'] === '' || strlen($values['first_name']) > 80) {
        $error = 'Enter your first name (maximum 80 characters).';
    } elseif ($values['last_name'] === '' || strlen($values['last_name']) > 80) {
        $error = 'Enter your last name (maximum 80 characters).';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 255) {
        $error = 'Enter a valid email address.';
    } elseif ($values['phone'] !== '' && strlen($values['phone']) > 30) {
        $error = 'Your phone number must not exceed 30 characters.';
    } elseif (strlen($password) < 10) {
        $error = 'Your password must contain at least 10 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'The passwords do not match.';
    } else {
        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                "INSERT INTO shop_customers
                    (first_name, last_name, email, phone, password_hash, status, created_at, updated_at)
                 VALUES
                    (:first_name, :last_name, :email, :phone, :password_hash, 'active', NOW(), NOW())"
            );

            $stmt->execute([
                ':first_name' => $values['first_name'],
                ':last_name' => $values['last_name'],
                ':email' => $values['email'],
                ':phone' => $values['phone'] !== '' ? $values['phone'] : null,
                ':password_hash' => $passwordHash,
            ]);

            $customer = ['id' => (int) $pdo->lastInsertId()];
            mephysShopLoginCustomer($customer);

            header('Location: ' . $next);
            exit;
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                $error = 'An account with this email already exists. Please sign in instead.';
            } else {
                error_log('Mephys customer registration failed: ' . $e->getMessage());
                $error = 'We could not create your account. Please try again.';
            }
        } catch (Throwable $e) {
            error_log('Mephys customer registration/session setup failed: ' . $e->getMessage());
            $error = 'Your account may have been created, but we could not sign you in. Please try signing in.';
        }
    }
}

$shopPageTitle = 'Create customer account';
require __DIR__ . '/_public-header.php';
?>

<section class="py-5">
    <div class="container" style="max-width: 620px;">
        <div class="shop-eyebrow mb-2">Join Mephys</div>
        <h1 class="shop-heading mb-2">Create your account</h1>
        <p class="shop-muted mb-4">
            Create an account to save your cart, complete checkout and view your orders.
        </p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert">
                <?= mephysShopPublicEscape($error) ?>
            </div>
        <?php endif; ?>

        <div class="border rounded-4 p-4 p-md-5">
            <form method="POST" action="register.php" novalidate>
                <?= mephysShopPublicCsrfField() ?>
                <input type="hidden" name="next" value="<?= mephysShopPublicEscape($next) ?>">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="first_name">First name</label>
                        <input class="form-control" id="first_name" name="first_name" type="text" maxlength="80" autocomplete="given-name" required value="<?= mephysShopPublicEscape($values['first_name']) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="last_name">Last name</label>
                        <input class="form-control" id="last_name" name="last_name" type="text" maxlength="80" autocomplete="family-name" required value="<?= mephysShopPublicEscape($values['last_name']) ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <input class="form-control" id="email" name="email" type="email" maxlength="255" autocomplete="email" required value="<?= mephysShopPublicEscape($values['email']) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="phone">Phone number (optional)</label>
                    <input class="form-control" id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" value="<?= mephysShopPublicEscape($values['phone']) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" id="password" name="password" type="password" minlength="10" autocomplete="new-password" required>
                    <div class="form-text">Use at least 10 characters.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="confirm_password">Confirm password</label>
                    <input class="form-control" id="confirm_password" name="confirm_password" type="password" minlength="10" autocomplete="new-password" required>
                </div>

                <button class="btn btn-primary btn-lg w-100" type="submit">Create account</button>
            </form>

            <?php
            $googleClientId = trim((string) getenv('MEPHYS_GOOGLE_CLIENT_ID'));
            if ($googleClientId !== ''):
            ?>
                <div class="text-center text-muted my-3">or</div>
                <div id="g_id_onload" data-client_id="<?= mephysShopPublicEscape($googleClientId) ?>" data-callback="handleGoogleCredential" data-auto_prompt="false"></div>
                <div class="g_id_signin d-flex justify-content-center" data-type="standard" data-size="large" data-theme="outline" data-text="signup_with" data-shape="rectangular" data-logo_alignment="left"></div>
                <form id="google-form" method="POST" action="google-auth.php" class="d-none">
                    <?= mephysShopPublicCsrfField() ?>
                    <input type="hidden" name="credential" id="google-credential">
                    <input type="hidden" name="next" value="<?= mephysShopPublicEscape($next) ?>">
                </form>
                <script src="https://accounts.google.com/gsi/client" async defer></script>
                <script>
                    function handleGoogleCredential(response) {
                        document.getElementById('google-credential').value = response.credential;
                        document.getElementById('google-form').submit();
                    }
                </script>
            <?php else: ?>
                <p class="small text-muted mt-3 mb-0">Google sign-in will appear here after the Mephys Google OAuth Client ID is configured.</p>
            <?php endif; ?>
        </div>

        <p class="text-center mt-4 mb-0">
            Already have an account? <a href="login.php?next=<?= rawurlencode($next) ?>">Sign in</a>
        </p>
    </div>
</section>

<?php require __DIR__ . '/_public-footer.php'; ?>
