<?php
declare(strict_types=1);

require_once __DIR__ . '/_public-bootstrap.php';

if (mephysShopCustomer()) {
    header('Location: account.php');
    exit;
}

$error = '';
$email = '';
$next = mephysShopSafeNext((string) ($_GET['next'] ?? $_POST['next'] ?? 'account.php'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mephysShopPublicVerifyCsrf();

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif ($password === '') {
        $error = 'Enter your password.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'SELECT id, first_name, last_name, email, password_hash, status
                 FROM shop_customers
                 WHERE email = :email
                 LIMIT 1'
            );
            $stmt->execute([':email' => $email]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$customer ||
                $customer['status'] !== 'active' ||
                empty($customer['password_hash']) ||
                !password_verify($password, (string) $customer['password_hash'])
            ) {
                $error = 'The email or password is incorrect.';
            } else {
                $pdo->prepare(
                    'UPDATE shop_customers SET last_login_at = NOW() WHERE id = :id'
                )->execute([':id' => (int) $customer['id']]);

                // Regenerates the session ID and merges the guest cart with the saved cart.
                mephysShopLoginCustomer($customer);

                header('Location: ' . $next);
                exit;
            }
        } catch (Throwable $e) {
            error_log('Mephys customer login failed: ' . $e->getMessage());
            $error = 'The sign-in service is temporarily unavailable. Please try again.';
        }
    }
}

$shopPageTitle = 'Customer sign in';
require __DIR__ . '/_public-header.php';
?>

<section class="py-5">
    <div class="container" style="max-width: 560px;">
        <div class="shop-eyebrow mb-2">Welcome back</div>
        <h1 class="shop-heading mb-2">Sign in to Mephys</h1>
        <p class="shop-muted mb-4">Your saved cart will be restored when you sign in.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert">
                <?= mephysShopPublicEscape($error) ?>
            </div>
        <?php endif; ?>

        <div class="border rounded-4 p-4 p-md-5">
            <form method="POST" action="login.php" autocomplete="on">
                <?= mephysShopPublicCsrfField() ?>
                <input type="hidden" name="next" value="<?= mephysShopPublicEscape($next) ?>">

                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <input
                        class="form-control"
                        id="email"
                        name="email"
                        type="email"
                        maxlength="255"
                        required
                        autocomplete="email"
                        value="<?= mephysShopPublicEscape($email) ?>"
                    >
                </div>

                <div class="mb-4">
                    <label class="form-label" for="password">Password</label>
                    <input
                        class="form-control"
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                    >
                </div>

                <button class="btn btn-primary btn-lg w-100" type="submit">Sign in</button>
            </form>

            <?php $googleClientId = trim((string) getenv('MEPHYS_GOOGLE_CLIENT_ID')); ?>
            <?php if ($googleClientId !== ''): ?>
                <div class="text-center text-muted my-3">or continue with</div>

                <div
                    id="g_id_onload"
                    data-client_id="<?= mephysShopPublicEscape($googleClientId) ?>"
                    data-callback="handleGoogleCredential"
                    data-auto_prompt="false"
                ></div>
                <div
                    class="g_id_signin d-flex justify-content-center"
                    data-type="standard"
                    data-size="large"
                    data-theme="outline"
                    data-text="signin_with"
                    data-shape="rectangular"
                    data-logo_alignment="left"
                ></div>

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
                <p class="small text-muted mt-3 mb-0">
                    Google sign-in will appear here after the Mephys Google OAuth Client ID is configured.
                </p>
            <?php endif; ?>
        </div>

        <p class="text-center mt-4 mb-0">
            New to Mephys? <a href="register.php?next=<?= rawurlencode($next) ?>">Create an account</a>
        </p>
    </div>
</section>

<?php require __DIR__ . '/_public-footer.php'; ?>
