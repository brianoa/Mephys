<?php
declare(strict_types=1);

/** Return the signed-in shop customer. */
function mephysShopCustomer(): ?array
{
    global $pdo;

    $id = filter_var(
        $_SESSION['mephys_customer_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (!$id) {
        return null;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT c.id, c.first_name, c.last_name,
                    CONCAT(c.first_name, ' ', c.last_name) AS full_name,
                    c.email, c.phone, c.status,
                    a.recipient_name, a.address_line1, a.address_line2,
                    a.town_city, a.county, a.postal_code, a.delivery_instructions
             FROM shop_customers c
             LEFT JOIN shop_customer_addresses a
               ON a.id = (
                    SELECT a2.id
                    FROM shop_customer_addresses a2
                    WHERE a2.customer_id = c.id
                    ORDER BY a2.is_default DESC, a2.id DESC
                    LIMIT 1
               )
             WHERE c.id = :id
             LIMIT 1"
        );

        $stmt->execute([':id' => $id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer || $customer['status'] !== 'active') {
            unset($_SESSION['mephys_customer_id']);
            return null;
        }

        return $customer;

    } catch (Throwable $e) {
        error_log('Mephys customer lookup failed: ' . $e->getMessage());
        return null;
    }
}

/** Allow only safe internal shop destinations. */
function mephysShopSafeNext(string $next): string
{
    $allowed = ['checkout.php', 'cart.php', 'account.php'];

    $path = parse_url($next, PHP_URL_PATH) ?: '';

    if (
        !in_array(basename($path), $allowed, true) ||
        str_contains($next, '..') ||
        str_starts_with($next, '//')
    ) {
        return 'account.php';
    }

    return basename($path);
}

/** Get or create the customer's active cart. */
function mephysShopActiveCartId(int $customerId, bool $create = true): ?int
{
    global $pdo;

    $stmt = $pdo->prepare(
        "SELECT id FROM shop_carts
         WHERE customer_id = :customer_id
         AND status = 'active'
         ORDER BY id DESC LIMIT 1"
    );

    $stmt->execute([':customer_id' => $customerId]);

    $cartId = $stmt->fetchColumn();

    if ($cartId !== false) {
        return (int) $cartId;
    }

    if (!$create) {
        return null;
    }

    $insert = $pdo->prepare(
        "INSERT INTO shop_carts
         (customer_id, status, created_at, updated_at)
         VALUES (:customer_id, 'active', NOW(), NOW())"
    );

    $insert->execute([':customer_id' => $customerId]);

    return (int) $pdo->lastInsertId();
}

/** Save the customer's session cart into the database. */
function mephysShopSaveCustomerCart(): void
{
    global $pdo;

    $customer = mephysShopCustomer();

    if (!$customer) {
        return;
    }

    try {
        $pdo->beginTransaction();

        $cartId = mephysShopActiveCartId((int) $customer['id'], true);

        if (!$cartId) {
            throw new RuntimeException('Could not create customer cart.');
        }

        $pdo->prepare(
            'DELETE FROM shop_cart_items WHERE cart_id = :cart_id'
        )->execute([':cart_id' => $cartId]);

        $insert = $pdo->prepare(
            "INSERT INTO shop_cart_items
             (cart_id, product_id, variant_id, quantity, created_at, updated_at)
             SELECT :cart_id, p.id, NULL, :quantity, NOW(), NOW()
             FROM shop_products p
             WHERE p.id = :product_id
             AND p.status = 'published'"
        );

        foreach ($_SESSION['mephys_shop_cart'] ?? [] as $productId => $quantity) {
            $productId = (int) $productId;
            $quantity = max(1, min(99, (int) $quantity));

            if ($productId > 0) {
                $insert->execute([
                    ':cart_id' => $cartId,
                    ':quantity' => $quantity,
                    ':product_id' => $productId
                ]);
            }
        }

        $pdo->prepare(
            'UPDATE shop_carts SET updated_at = NOW() WHERE id = :id'
        )->execute([':id' => $cartId]);

        $pdo->commit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Mephys customer cart save failed: ' . $e->getMessage());
    }
}

/** Sign in and merge the visitor's cart with the saved customer cart. */
function mephysShopLoginCustomer(array $customer): void
{
    global $pdo;

    $guestCart = is_array($_SESSION['mephys_shop_cart'] ?? null)
        ? $_SESSION['mephys_shop_cart']
        : [];

    session_regenerate_id(true);

    $_SESSION['mephys_customer_id'] = (int) $customer['id'];

    $savedCart = [];

    try {
        $cartId = mephysShopActiveCartId((int) $customer['id'], false);

        if ($cartId) {
            $stmt = $pdo->prepare(
                'SELECT product_id, quantity
                 FROM shop_cart_items
                 WHERE cart_id = :cart_id
                 AND variant_id IS NULL'
            );

            $stmt->execute([':cart_id' => $cartId]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $savedCart[(int) $row['product_id']] = (int) $row['quantity'];
            }
        }

    } catch (Throwable $e) {
        error_log('Mephys saved cart load failed: ' . $e->getMessage());
    }

    // Merge the visitor's existing cart.
    foreach ($guestCart as $productId => $quantity) {
        $productId = (int) $productId;
        $quantity = (int) $quantity;

        if ($productId > 0 && $quantity > 0) {
            $savedCart[$productId] = min(
                99,
                (int) ($savedCart[$productId] ?? 0) + $quantity
            );
        }
    }

    // Add the product that triggered registration or sign-in.
    $pendingAdd = $_SESSION['mephys_shop_pending_add'] ?? null;

    if (is_array($pendingAdd)) {
        $pendingProductId = filter_var(
            $pendingAdd['product_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $pendingQuantity = filter_var(
            $pendingAdd['quantity'] ?? 1,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 99]]
        );

        if ($pendingProductId && $pendingQuantity) {
            $savedCart[$pendingProductId] = min(
                99,
                (int) ($savedCart[$pendingProductId] ?? 0) + $pendingQuantity
            );
        }

        unset($_SESSION['mephys_shop_pending_add']);
    }

    $_SESSION['mephys_shop_cart'] = $savedCart;

    mephysShopSaveCustomerCart();

    $_SESSION['mephys_shop_csrf'] = bin2hex(random_bytes(32));
}

/** Require a signed-in customer. */
function mephysShopRequireCustomer(string $next = 'checkout.php'): array
{
    $customer = mephysShopCustomer();

    if (!$customer) {
        $safeNext = mephysShopSafeNext($next);

        $_SESSION['mephys_shop_after_login'] = $safeNext;

        header('Location: login.php?next=' . rawurlencode($safeNext));
        exit;
    }

    return $customer;
}

/** Sign out the customer. */
function mephysShopLogoutCustomer(): void
{
    unset(
        $_SESSION['mephys_customer_id'],
        $_SESSION['mephys_shop_after_login']
    );

    session_regenerate_id(true);

    $_SESSION['mephys_shop_csrf'] = bin2hex(random_bytes(32));
}

/** Restore the saved customer cart on each shop request. */
function mephysShopRestoreCustomerCart(): void
{
    global $pdo;

    $customer = mephysShopCustomer();

    if (!$customer) {
        return;
    }

    try {
        $cartId = mephysShopActiveCartId((int) $customer['id'], false);

        if (!$cartId) {
            $_SESSION['mephys_shop_cart'] = [];
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT product_id, quantity
             FROM shop_cart_items
             WHERE cart_id = :cart_id
             AND variant_id IS NULL'
        );

        $stmt->execute([':cart_id' => $cartId]);

        $cart = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cart[(int) $row['product_id']] = (int) $row['quantity'];
        }

        $_SESSION['mephys_shop_cart'] = $cart;

    } catch (Throwable $e) {
        error_log('Mephys customer cart restore failed: ' . $e->getMessage());
    }
}
