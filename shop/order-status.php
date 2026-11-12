<?php
declare(strict_types=1);

require_once __DIR__ . '/_public-bootstrap.php';
global $pdo;

$customer = mephysShopRequireCustomer('account.php');

$orderId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$orderId) {
    http_response_code(404);
    exit('Order not found.');
}

/*
 * Load the order and its latest payment attempt.
 */
$orderStmt = $pdo->prepare(
    'SELECT o.*,
            p.status AS latest_payment_status,
            p.provider_reference AS mpesa_receipt,
            p.response_description AS payment_message,
            p.phone AS payment_phone
     FROM shop_orders o
     LEFT JOIN shop_payments p ON p.id = (
         SELECT MAX(p2.id)
         FROM shop_payments p2
         WHERE p2.order_id = o.id
     )
     WHERE o.id = :id
       AND o.customer_id = :customer_id
     LIMIT 1'
);

$orderStmt->execute([
    ':id' => $orderId,
    ':customer_id' => (int) $customer['id'],
]);

$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

/*
 * This same page also acts as the small status-check endpoint
 * used by JavaScript. It returns the latest database status.
 */
if (isset($_GET['status_check'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    echo json_encode([
        'payment_status' => (string) $order['payment_status'],
        'latest_payment_status' => (string) ($order['latest_payment_status'] ?? ''),
        'receipt' => (string) ($order['mpesa_receipt'] ?? ''),
        'message' => (string) ($order['payment_message'] ?? ''),
    ]);

    exit;
}

/*
 * Load order items.
 */
$itemStmt = $pdo->prepare(
    'SELECT product_name, unit_price, quantity, line_total
     FROM shop_order_items
     WHERE order_id = :id
     ORDER BY id ASC'
);

$itemStmt->execute([':id' => $orderId]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

/*
 * Determine the initial payment display.
 */
$paymentStatus = (string) ($order['payment_status'] ?? 'unpaid');
$latestPaymentStatus = (string) ($order['latest_payment_status'] ?? '');

$isPaid = $paymentStatus === 'paid';
$isFailed = $paymentStatus === 'failed'
    || $latestPaymentStatus === 'failed'
    || $latestPaymentStatus === 'cancelled';

$shopPageTitle = 'Order status';
require __DIR__ . '/_public-header.php';
?>

<style>
    .payment-status-card {
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        padding: 28px 20px;
        margin: 25px 0;
        text-align: center;
        background: #fff;
    }

    .payment-status-icon {
        width: 76px;
        height: 76px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 18px;
        font-size: 38px;
        font-weight: 700;
    }

    .payment-waiting .payment-status-icon {
        background: #fff7df;
        color: #b7791f;
    }

    .payment-success .payment-status-icon {
        background: #dcfce7;
        color: #15803d;
    }

    .payment-failed .payment-status-icon {
        background: #fee2e2;
        color: #dc2626;
    }

    .payment-spinner {
        width: 42px;
        height: 42px;
        border: 4px solid #f3dca2;
        border-top-color: #c58a20;
        border-radius: 50%;
        animation: mephys-spin .8s linear infinite;
    }

    @keyframes mephys-spin {
        to {
            transform: rotate(360deg);
        }
    }

    .payment-status-title {
        font-size: 22px;
        font-weight: 750;
        margin-bottom: 8px;
    }

    .payment-status-description {
        color: #64748b;
        line-height: 1.6;
        margin: 0 auto;
        max-width: 520px;
    }

    .payment-waiting .payment-status-title {
        color: #a16207;
    }

    .payment-success .payment-status-title {
        color: #15803d;
    }

    .payment-failed .payment-status-title {
        color: #dc2626;
    }

    .payment-check-note {
        font-size: 13px;
        color: #64748b;
        margin-top: 15px;
    }

    .payment-status-card[hidden] {
        display: none !important;
    }
</style>

<section class="py-5">
    <div class="container" style="max-width:850px">

        <div class="border rounded-4 p-4 p-md-5">

            <div class="shop-eyebrow mb-2">
                Order <?= mephysShopPublicEscape($order['order_number']) ?>
            </div>

            <h1 class="shop-heading mb-3">
                Order and payment status
            </h1>

            <?php if (isset($_GET['prompt'])): ?>
                <div class="alert alert-info" role="status">
                    <strong>M-Pesa request sent.</strong>
                    Check
                    <?= mephysShopPublicEscape(
                        $order['payment_phone'] ?: 'your phone'
                    ) ?>
                    for the STK prompt. Enter your M-Pesa PIN on your phone
                    to approve the payment. Never share your PIN with anyone.
                </div>
            <?php elseif (isset($_GET['error'])): ?>
                <div class="alert alert-warning" role="alert">
                    We could not start the M-Pesa request. Your order has
                    been saved, but payment has not been received.
                    Please contact Mephys and quote order
                    <?= mephysShopPublicEscape($order['order_number']) ?>.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['retry_error'])): ?>
                <div class="alert alert-warning" role="alert">
                    <?php if ($_GET['retry_error'] === 'config'): ?>
                        M-Pesa payments are not yet configured. Please contact Mephys before retrying.
                    <?php elseif ($_GET['retry_error'] === 'slow'): ?>
                        A payment attempt was made recently. Please check your phone and try again after a moment.
                    <?php elseif ($_GET['retry_error'] === 'pending'): ?>
                        A payment request may still be processing. Please check its status before retrying.
                    <?php else: ?>
                        We could not start another M-Pesa request. Please contact Mephys and quote your order number.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- PAYMENT WAITING -->
            <div
                id="payment-waiting"
                class="payment-status-card payment-waiting"
                <?= ($isPaid || $isFailed) ? 'hidden' : '' ?>
                role="status"
                aria-live="polite"
            >
                <div class="payment-status-icon">
                    <div class="payment-spinner"></div>
                </div>

                <div class="payment-status-title">
                    Waiting for M-Pesa confirmation
                </div>

                <p class="payment-status-description">
                    We are checking your payment. If the M-Pesa prompt
                    is on your phone, enter your PIN to approve it.
                    Please keep this page open.
                </p>

                <div class="payment-check-note" id="payment-check-note">
                    Checking payment status automatically...
                </div>
            </div>

            <!-- PAYMENT SUCCESS -->
            <div
                id="payment-success"
                class="payment-status-card payment-success"
                <?= $isPaid ? '' : 'hidden' ?>
                role="status"
                aria-live="polite"
            >
                <div class="payment-status-icon">
                    &#10003;
                </div>

                <div class="payment-status-title">
                    Payment successful!
                </div>

                <p class="payment-status-description">
                    Your M-Pesa payment has been confirmed.
                    Thank you for shopping with Mephys.
                    Your order is now being processed.
                </p>

                <div class="mt-3" id="success-receipt">
                    <?php if (!empty($order['mpesa_receipt'])): ?>
                        <strong>
                            M-Pesa receipt:
                            <?= mephysShopPublicEscape($order['mpesa_receipt']) ?>
                        </strong>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PAYMENT FAILURE -->
            <div
                id="payment-failed"
                class="payment-status-card payment-failed"
                <?= $isFailed ? '' : 'hidden' ?>
                role="alert"
                aria-live="assertive"
            >
                <div class="payment-status-icon">
                    &#10005;
                </div>

                <div class="payment-status-title">
                    Payment not completed
                </div>

                <p class="payment-status-description">
                    We could not confirm your M-Pesa payment.
                    Please contact Mephys and quote your order number
                    before attempting to place another order.
                </p>

                <div class="small text-secondary mt-3" id="failure-message">
                    <?= mephysShopPublicEscape(
                        $order['payment_message'] ?? ''
                    ) ?>
                </div>
            </div>

            <!-- ORDER DETAILS -->
            <div class="row g-3 mb-4">

                <div class="col-sm-6">
                    <div class="small shop-muted">Payment</div>
                    <strong
                        id="payment-status-text"
                        class="text-uppercase"
                    >
                        <?= mephysShopPublicEscape($paymentStatus) ?>
                    </strong>
                </div>

                <div class="col-sm-6">
                    <div class="small shop-muted">Order fulfilment</div>
                    <strong class="text-uppercase">
                        <?= mephysShopPublicEscape(
                            str_replace('_', ' ', $order['status'])
                        ) ?>
                    </strong>
                </div>

                <div class="col-sm-6">
                    <div class="small shop-muted">M-Pesa receipt</div>
                    <strong id="payment-receipt">
                        <?= mephysShopPublicEscape(
                            $order['mpesa_receipt'] ?: 'Not yet available'
                        ) ?>
                    </strong>
                </div>

                <div class="col-sm-6">
                    <div class="small shop-muted">Total</div>
                    <strong class="text-primary">
                        <?= mephysShopPublicMoney($order['total_amount']) ?>
                    </strong>
                </div>

            </div>

            <h2 class="h5 fw-bold">Items ordered</h2>

            <?php foreach ($items as $item): ?>
                <div class="d-flex justify-content-between border-bottom py-2 gap-3">

                    <div>
                        <?= mephysShopPublicEscape($item['product_name']) ?>

                        <span class="small shop-muted">
                            × <?= (int) $item['quantity'] ?>
                        </span>
                    </div>

                    <strong>
                        <?= mephysShopPublicMoney($item['line_total']) ?>
                    </strong>

                </div>
            <?php endforeach; ?>

            <?php if ($isFailed && !$isPaid): ?>
                <form method="POST" action="retry-payment.php" class="mt-3 mb-3">
                    <?= mephysShopPublicCsrfField() ?>
                    <input type="hidden" name="order_id" value="<?= (int) $orderId ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-rotate-right me-1"></i> Retry M-Pesa payment for this order
                    </button>
                    <p class="small shop-muted mt-2 mb-0">Only retry if the previous attempt failed and no money was deducted. If you received an M-Pesa receipt, contact Mephys first.</p>
                </form>
            <?php endif; ?>

            <div class="mt-4 d-flex flex-wrap gap-2">

                <a href="account.php" class="btn btn-outline-primary">
                    My account and orders
                </a>

                <a href="index.php" class="btn btn-primary">
                    Continue shopping
                </a>

            </div>

        </div>
    </div>
</section>

<script>
(function () {
    const waitingBox = document.getElementById('payment-waiting');
    const successBox = document.getElementById('payment-success');
    const failedBox = document.getElementById('payment-failed');

    const paymentText = document.getElementById('payment-status-text');
    const receiptText = document.getElementById('payment-receipt');
    const successReceipt = document.getElementById('success-receipt');
    const failureMessage = document.getElementById('failure-message');
    const checkNote = document.getElementById('payment-check-note');

    const orderId = <?= (int) $orderId ?>;

    let stopped = false;
    let checking = false;

    function showWaiting() {
        waitingBox.hidden = false;
        successBox.hidden = true;
        failedBox.hidden = true;

        paymentText.textContent = 'pending';
    }

    function showSuccess(receipt) {
        waitingBox.hidden = true;
        successBox.hidden = false;
        failedBox.hidden = true;

        paymentText.textContent = 'paid';

        if (receipt) {
            receiptText.textContent = receipt;
            successReceipt.textContent = 'M-Pesa receipt: ' + receipt;
        }

        stopped = true;
    }

    function showFailure(message) {
        waitingBox.hidden = true;
        successBox.hidden = true;
        failedBox.hidden = false;

        paymentText.textContent = 'failed';

        if (message) {
            failureMessage.textContent = message;
        }

        stopped = true;
    }

    async function checkPayment() {
        if (stopped || checking) {
            return;
        }

        checking = true;

        try {
            const url = new URL(window.location.href);
            url.searchParams.set('id', String(orderId));
            url.searchParams.set('status_check', '1');

            const response = await fetch(url.toString(), {
                method: 'GET',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Could not check payment status.');
            }

            const data = await response.json();

            const orderPayment = String(data.payment_status || '').toLowerCase();
            const attemptPayment = String(data.latest_payment_status || '').toLowerCase();

            if (orderPayment === 'paid') {
                showSuccess(data.receipt || '');
            } else if (
                orderPayment === 'failed'
                || attemptPayment === 'failed'
                || attemptPayment === 'cancelled'
            ) {
                showFailure(data.message || '');
            } else {
                showWaiting();

                checkNote.textContent =
                    'Your payment has not yet been confirmed. Checking again shortly...';
            }

        } catch (error) {
            checkNote.textContent =
                'We are having trouble checking the payment. We will try again shortly.';
        } finally {
            checking = false;
        }
    }

    <?php if (!$isPaid && !$isFailed): ?>
        checkPayment();
        setInterval(checkPayment, 5000);
    <?php endif; ?>
})();
</script>

<?php require __DIR__ . '/_public-footer.php'; ?>
