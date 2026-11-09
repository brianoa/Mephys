<?php
declare(strict_types=1);

require_once __DIR__ . '/_public-bootstrap.php';
require_once __DIR__ . '/_mpesa.php';

global $pdo;
$customer = mephysShopRequireCustomer('checkout.php');

$cart = is_array($_SESSION['mephys_shop_cart'] ?? null) ? $_SESSION['mephys_shop_cart'] : [];
$cart = array_filter($cart, static fn($quantity): bool => (int) $quantity > 0);
if (!$cart) {
    header('Location: cart.php');
    exit;
}

$ids = array_values(array_filter(array_map('intval', array_keys($cart)), static fn(int $id): bool => $id > 0));
if (!$ids) {
    header('Location: cart.php');
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$productStmt = $pdo->prepare(
    "SELECT id, name, price, stock_quantity, track_inventory, allow_backorder, status
     FROM shop_products
     WHERE id IN ($placeholders)"
);
$productStmt->execute($ids);
$products = $productStmt->fetchAll(PDO::FETCH_ASSOC);
$productMap = [];
foreach ($products as $product) {
    $productMap[(int) $product['id']] = $product;
}

$subtotal = 0.0;
foreach ($cart as $productId => $quantity) {
    if (isset($productMap[(int) $productId])) {
        $subtotal += (float) $productMap[(int) $productId]['price'] * (int) $quantity;
    }
}

$addressStmt = $pdo->prepare(
    'SELECT id, recipient_name, phone, address_line1, address_line2,
            town_city, county, postal_code, latitude, longitude, delivery_instructions, is_default
     FROM shop_customer_addresses
     WHERE customer_id = :customer_id
     ORDER BY is_default DESC, id DESC'
);
$addressStmt->execute([':customer_id' => (int) $customer['id']]);
$savedAddresses = $addressStmt->fetchAll(PDO::FETCH_ASSOC);

$defaultAddress = null;
foreach ($savedAddresses as $savedAddress) {
    if ((int) $savedAddress['is_default'] === 1) {
        $defaultAddress = $savedAddress;
        break;
    }
}
if ($defaultAddress === null && $savedAddresses) {
    $defaultAddress = $savedAddresses[0];
}

$values = [
    'recipient_name' => (string) ($defaultAddress['recipient_name'] ?? trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))),
    'delivery_phone' => (string) ($defaultAddress['phone'] ?? $customer['phone'] ?? ''),
    'mpesa_phone' => (string) ($customer['phone'] ?? ''),
    'county' => (string) ($defaultAddress['county'] ?? 'Nairobi'),
    'town_city' => (string) ($defaultAddress['town_city'] ?? ''),
    'address_line1' => (string) ($defaultAddress['address_line1'] ?? ''),
    'address_line2' => (string) ($defaultAddress['address_line2'] ?? ''),
    'postal_code' => (string) ($defaultAddress['postal_code'] ?? ''),
    'landmark' => '',
    'delivery_instructions' => (string) ($defaultAddress['delivery_instructions'] ?? ''),
    'latitude' => (string) ($defaultAddress['latitude'] ?? ''),
    'longitude' => (string) ($defaultAddress['longitude'] ?? ''),
    'customer_note' => '',
];

$selectedAddressId = (int) ($defaultAddress['id'] ?? 0);
$saveAddress = true;
$makeDefault = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mephysShopPublicVerifyCsrf();

    foreach ($values as $key => $unused) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $selectedAddressId = (int) ($_POST['saved_address_id'] ?? 0);
    $saveAddress = isset($_POST['save_address']);
    $makeDefault = isset($_POST['make_default']);

    $recipientName = $values['recipient_name'];
    $deliveryPhone = '';
    $mpesaPhone = '';

    if ($recipientName === '' || strlen($recipientName) > 160) {
        $error = 'Enter the name of the person receiving the delivery.';
    } elseif ($values['county'] === '' || strlen($values['county']) > 120) {
        $error = 'Select or enter the delivery county.';
    } elseif ($values['town_city'] === '' || strlen($values['town_city']) > 120) {
        $error = 'Enter the town or city for delivery.';
    } elseif ($values['address_line1'] === '' || strlen($values['address_line1']) > 255) {
        $error = 'Enter the estate, neighbourhood, road or street.';
    } elseif (strlen($values['address_line2']) > 255) {
        $error = 'Building or house details are too long.';
    } elseif (strlen($values['postal_code']) > 30) {
        $error = 'Postal code is too long.';
    } elseif (strlen($values['landmark']) > 500) {
        $error = 'Landmark details are too long.';
    } elseif (strlen($values['delivery_instructions']) > 3000) {
        $error = 'Delivery instructions are too long.';
    } elseif (strlen($values['customer_note']) > 1000) {
        $error = 'Order note is too long.';
    }

    if ($error === '') {
        try {
            $deliveryPhone = mephysMpesaPhone($values['delivery_phone']);
        } catch (Throwable $e) {
            $error = 'Enter a valid Kenyan delivery contact number, for example 0712345678.';
        }
    }
    if ($error === '') {
        try {
            $mpesaPhone = mephysMpesaPhone($values['mpesa_phone']);
        } catch (Throwable $e) {
            $error = 'Enter a valid Kenyan M-Pesa number, for example 0712345678.';
        }
    }

    $latitude = null;
    $longitude = null;
    if ($error === '') {
        $latRaw = $values['latitude'];
        $lngRaw = $values['longitude'];
        if (($latRaw === '') !== ($lngRaw === '')) {
            $error = 'Please select a complete location pin on the map, or clear both coordinates and provide detailed directions.';
        } elseif ($latRaw !== '' && $lngRaw !== '') {
            if (!is_numeric($latRaw) || !is_numeric($lngRaw)) {
                $error = 'The selected map location is invalid. Please choose the location again.';
            } else {
                $latitude = (float) $latRaw;
                $longitude = (float) $lngRaw;
                // Broad Kenya bounds; this is a sanity check, not proof of address validity.
                if ($latitude < -5.0 || $latitude > 5.5 || $longitude < 33.5 || $longitude > 42.5) {
                    $error = 'The selected map pin appears to be outside Kenya. Please select the correct delivery location.';
                }
            }
        } elseif (strlen(trim($values['landmark'] . ' ' . $values['delivery_instructions'])) < 15) {
            $error = 'Please pin the delivery location on the map, or provide a clear landmark and detailed directions if you cannot use the map.';
        }
    }

    if ($error === '' && $selectedAddressId > 0) {
        $ownedAddressStmt = $pdo->prepare(
            'SELECT id FROM shop_customer_addresses WHERE id = :id AND customer_id = :customer_id LIMIT 1'
        );
        $ownedAddressStmt->execute([
            ':id' => $selectedAddressId,
            ':customer_id' => (int) $customer['id'],
        ]);
        if (!$ownedAddressStmt->fetchColumn()) {
            $error = 'The selected saved address could not be found. Please enter the delivery address again.';
        }
    }

    if ($error === '') {
        try {
            $pdo->beginTransaction();

            $lineRows = [];
            $grandTotal = 0.0;
            $lockProduct = $pdo->prepare(
                'SELECT id, name, price, stock_quantity, track_inventory, allow_backorder, status
                 FROM shop_products WHERE id = :id FOR UPDATE'
            );

            foreach ($cart as $productId => $quantity) {
                $productId = (int) $productId;
                $quantity = (int) $quantity;
                $lockProduct->execute([':id' => $productId]);
                $product = $lockProduct->fetch(PDO::FETCH_ASSOC);

                if (!$product || $product['status'] !== 'published') {
                    throw new RuntimeException('A product in your cart is no longer available. Please review your cart.');
                }
                if ($quantity < 1 || $quantity > 99) {
                    throw new RuntimeException('A cart quantity is invalid. Please update your cart.');
                }
                if ((int) $product['track_inventory'] === 1 && (int) $product['allow_backorder'] !== 1 && (int) $product['stock_quantity'] < $quantity) {
                    throw new RuntimeException('There is not enough stock for ' . $product['name'] . '. Please update your cart.');
                }

                $unitPrice = (float) $product['price'];
                $lineTotal = $unitPrice * $quantity;
                $grandTotal += $lineTotal;
                $lineRows[] = [
                    'product_id' => $productId,
                    'name' => (string) $product['name'],
                    'sku' => null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            if ($grandTotal <= 0 || $grandTotal > 150000) {
                throw new RuntimeException('The order amount is outside the supported M-Pesa payment range. Please contact Mephys for assistance.');
            }
            if (abs($grandTotal - round($grandTotal)) > 0.001) {
                throw new RuntimeException('M-Pesa STK Push accepts whole-shilling amounts. Please contact Mephys to adjust this order total.');
            }

            $addressLine1 = trim($values['address_line1']);
            $addressLine2 = trim($values['address_line2']) !== '' ? trim($values['address_line2']) : null;
            $instructions = [];
            if (trim($values['landmark']) !== '') {
                $instructions[] = 'Landmark: ' . trim($values['landmark']);
            }
            if (trim($values['delivery_instructions']) !== '') {
                $instructions[] = 'Delivery directions: ' . trim($values['delivery_instructions']);
            }
            $instructions[] = 'Arrival contact: Call the recipient on ' . $deliveryPhone . ' on arrival.';
            $deliveryInstructions = implode("\n", $instructions);

            // Save or update this customer's address only when requested.
            if ($saveAddress) {
                if ($makeDefault || count($savedAddresses) === 0) {
                    $pdo->prepare('UPDATE shop_customer_addresses SET is_default = 0 WHERE customer_id = :customer_id')
                        ->execute([':customer_id' => (int) $customer['id']]);
                }

                if ($selectedAddressId > 0) {
                    $saveStmt = $pdo->prepare(
                        'UPDATE shop_customer_addresses
                         SET recipient_name = :recipient_name, phone = :phone,
                             address_line1 = :line1, address_line2 = :line2,
                             town_city = :town_city, county = :county, postal_code = :postal_code,
                             latitude = :latitude, longitude = :longitude,
                             delivery_instructions = :instructions,
                             is_default = :is_default, updated_at = NOW()
                         WHERE id = :id AND customer_id = :customer_id'
                    );
                    $saveStmt->execute([
                        ':recipient_name' => $recipientName,
                        ':phone' => $deliveryPhone,
                        ':line1' => $addressLine1,
                        ':line2' => $addressLine2,
                        ':town_city' => trim($values['town_city']),
                        ':county' => trim($values['county']),
                        ':postal_code' => trim($values['postal_code']) ?: null,
                        ':latitude' => $latitude,
                        ':longitude' => $longitude,
                        ':instructions' => $deliveryInstructions,
                        ':is_default' => ($makeDefault || count($savedAddresses) === 0) ? 1 : 0,
                        ':id' => $selectedAddressId,
                        ':customer_id' => (int) $customer['id'],
                    ]);
                } else {
                    $isDefault = ($makeDefault || count($savedAddresses) === 0) ? 1 : 0;
                    $saveStmt = $pdo->prepare(
                        'INSERT INTO shop_customer_addresses
                         (customer_id, recipient_name, phone, address_line1, address_line2,
                          town_city, county, postal_code, latitude, longitude,
                          delivery_instructions, is_default, created_at, updated_at)
                         VALUES
                         (:customer_id, :recipient_name, :phone, :line1, :line2,
                          :town_city, :county, :postal_code, :latitude, :longitude,
                          :instructions, :is_default, NOW(), NOW())'
                    );
                    $saveStmt->execute([
                        ':customer_id' => (int) $customer['id'],
                        ':recipient_name' => $recipientName,
                        ':phone' => $deliveryPhone,
                        ':line1' => $addressLine1,
                        ':line2' => $addressLine2,
                        ':town_city' => trim($values['town_city']),
                        ':county' => trim($values['county']),
                        ':postal_code' => trim($values['postal_code']) ?: null,
                        ':latitude' => $latitude,
                        ':longitude' => $longitude,
                        ':instructions' => $deliveryInstructions,
                        ':is_default' => $isDefault,
                    ]);
                }
            }

            $orderNumber = 'MPH' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
            $insertOrder = $pdo->prepare(
                'INSERT INTO shop_orders
                 (order_number, customer_id, customer_first_name, customer_last_name,
                  customer_email, customer_phone, delivery_recipient_name, delivery_phone,
                  delivery_address_line1, delivery_address_line2, delivery_town_city,
                  delivery_county, delivery_postal_code, delivery_latitude, delivery_longitude,
                  delivery_instructions, currency, subtotal, shipping_fee, discount_amount,
                  tax_amount, total_amount, status, payment_status, payment_method,
                  customer_note, ordered_at, updated_at)
                 VALUES
                 (:order_number, :customer_id, :first_name, :last_name,
                  :email, :customer_phone, :recipient, :delivery_phone,
                  :line1, :line2, :town_city, :county, :postal_code, :latitude, :longitude,
                  :instructions, \'KES\', :subtotal, 0, 0, 0, :total,
                  \'pending\', \'pending\', \'mpesa\', :customer_note, NOW(), NOW())'
            );
            $insertOrder->execute([
                ':order_number' => $orderNumber,
                ':customer_id' => (int) $customer['id'],
                ':first_name' => (string) $customer['first_name'],
                ':last_name' => (string) $customer['last_name'],
                ':email' => (string) $customer['email'],
                ':customer_phone' => $mpesaPhone,
                ':recipient' => $recipientName,
                ':delivery_phone' => $deliveryPhone,
                ':line1' => $addressLine1,
                ':line2' => $addressLine2,
                ':town_city' => trim($values['town_city']),
                ':county' => trim($values['county']) ?: null,
                ':postal_code' => trim($values['postal_code']) ?: null,
                ':latitude' => $latitude,
                ':longitude' => $longitude,
                ':instructions' => $deliveryInstructions,
                ':subtotal' => $grandTotal,
                ':total' => $grandTotal,
                ':customer_note' => trim($values['customer_note']) ?: null,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $insertItem = $pdo->prepare(
                'INSERT INTO shop_order_items
                 (order_id, product_id, variant_id, product_name, sku_snapshot,
                  variant_snapshot, quantity, unit_price, line_total)
                 VALUES (:order_id, :product_id, NULL, :product_name, :sku, NULL,
                         :quantity, :unit_price, :line_total)'
            );
            foreach ($lineRows as $line) {
                $insertItem->execute([
                    ':order_id' => $orderId,
                    ':product_id' => $line['product_id'],
                    ':product_name' => $line['name'],
                    ':sku' => $line['sku'],
                    ':quantity' => $line['quantity'],
                    ':unit_price' => $line['unit_price'],
                    ':line_total' => $line['line_total'],
                ]);
            }

            $insertPayment = $pdo->prepare(
                'INSERT INTO shop_payments
                 (order_id, payment_provider, payment_method, amount, currency, status, phone, created_at, updated_at)
                 VALUES (:order_id, \'safaricom_daraja\', \'mpesa\', :amount, \'KES\', \'initiated\', :phone, NOW(), NOW())'
            );
            $insertPayment->execute([
                ':order_id' => $orderId,
                ':amount' => $grandTotal,
                ':phone' => $mpesaPhone,
            ]);
            $paymentId = (int) $pdo->lastInsertId();
            $pdo->commit();

            try {
                $response = mephysMpesaStkPush(mephysMpesaConfig(), $mpesaPhone, (int) round($grandTotal), $orderNumber);
                $checkoutRequestId = (string) ($response['CheckoutRequestID'] ?? '');
                $merchantRequestId = (string) ($response['MerchantRequestID'] ?? '');
                $responseCode = (string) ($response['ResponseCode'] ?? '');
                if ($responseCode !== '0' || $checkoutRequestId === '') {
                    throw new RuntimeException((string) ($response['ResponseDescription'] ?? 'Safaricom did not accept the payment request.'));
                }

                $updatePayment = $pdo->prepare(
                    "UPDATE shop_payments
                     SET checkout_request_id = :checkout_id,
                         merchant_request_id = :merchant_id,
                         response_code = :response_code,
                         response_description = :response_description,
                         status = 'pending', updated_at = NOW()
                     WHERE id = :id"
                );
                $updatePayment->execute([
                    ':checkout_id' => $checkoutRequestId,
                    ':merchant_id' => $merchantRequestId,
                    ':response_code' => $responseCode,
                    ':response_description' => substr((string) ($response['CustomerMessage'] ?? 'M-Pesa payment prompt sent.'), 0, 500),
                    ':id' => $paymentId,
                ]);

                $_SESSION['mephys_shop_cart'] = [];
                mephysShopSaveCustomerCart();
                header('Location: order-status.php?id=' . $orderId . '&prompt=1');
                exit;
            } catch (Throwable $paymentError) {
                error_log('Mephys M-Pesa STK request failed for order ' . $orderNumber . ': ' . $paymentError->getMessage());
                $pdo->prepare(
                    "UPDATE shop_payments SET status = 'failed', response_description = :description, updated_at = NOW() WHERE id = :id"
                )->execute([
                    ':description' => substr($paymentError->getMessage(), 0, 500),
                    ':id' => $paymentId,
                ]);
                $pdo->prepare(
                    "UPDATE shop_orders SET payment_status = 'failed', updated_at = NOW() WHERE id = :id"
                )->execute([':id' => $orderId]);
                header('Location: order-status.php?id=' . $orderId . '&error=1');
                exit;
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'We could not create your order. Please check your details and try again.';
            error_log('Mephys checkout failed: ' . $e->getMessage());
        }
    }
}

$countyOptions = [
    'Baringo','Bomet','Bungoma','Busia','Elgeyo-Marakwet','Embu','Garissa','Homa Bay','Isiolo',
    'Kajiado','Kakamega','Kericho','Kiambu','Kilifi','Kirinyaga','Kisii','Kisumu','Kitui','Kwale',
    'Laikipia','Lamu','Machakos','Makueni','Mandera','Marsabit','Meru','Migori','Mombasa','Murang’a',
    'Nairobi','Nakuru','Nandi','Narok','Nyamira','Nyandarua','Nyeri','Samburu','Siaya','Taita-Taveta',
    'Tana River','Tharaka-Nithi','Trans Nzoia','Turkana','Uasin Gishu','Vihiga','Wajir','West Pokot'
];

$shopPageTitle = 'Delivery and checkout';
require __DIR__ . '/_public-header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<style>
    #mephys-delivery-map{height:360px;border-radius:14px;border:1px solid #dce5ed;z-index:1}
    .delivery-help{font-size:.88rem;color:#718096}
    .required-mark{color:#c62828}
</style>
<section class="py-5">
    <div class="container">
        <div class="shop-eyebrow mb-2">Secure checkout</div>
        <h1 class="shop-heading mb-2">Delivery details and payment</h1>
        <p class="shop-muted mb-4">Tell us exactly where to deliver. A house number is optional; a map pin or clear directions will help our delivery team find you.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= mephysShopPublicEscape($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="row g-4" id="mephys-checkout-form">
            <?= mephysShopPublicCsrfField() ?>
            <div class="col-lg-7">
                <div class="border rounded-4 p-4 mb-4">
                    <h2 class="h5 fw-bold mb-3">Who should receive the order?</h2>
                    <div class="mb-3">
                        <label class="form-label" for="recipient_name">Recipient's full name <span class="required-mark">*</span></label>
                        <input class="form-control" id="recipient_name" name="recipient_name" required maxlength="160" autocomplete="name" value="<?= mephysShopPublicEscape($values['recipient_name']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="delivery_phone">Delivery contact phone <span class="required-mark">*</span></label>
                        <input class="form-control" id="delivery_phone" name="delivery_phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="0712 345 678" required value="<?= mephysShopPublicEscape($values['delivery_phone']) ?>">
                        <div class="form-text">Our delivery person will call this number if they need help finding you or when they arrive.</div>
                    </div>
                    <div>
                        <label class="form-label" for="mpesa_phone">M-Pesa phone number <span class="required-mark">*</span></label>
                        <input class="form-control" id="mpesa_phone" name="mpesa_phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="0712 345 678" required value="<?= mephysShopPublicEscape($values['mpesa_phone']) ?>">
                        <div class="form-text">The Safaricom payment prompt will be sent to this number. It can be different from the delivery contact.</div>
                    </div>
                </div>

                <div class="border rounded-4 p-4 mb-4">
                    <h2 class="h5 fw-bold mb-3">Where should we deliver?</h2>
                    <?php if ($savedAddresses): ?>
                        <div class="mb-3">
                            <label class="form-label" for="saved_address_id">Use a saved address</label>
                            <select class="form-select" id="saved_address_id" name="saved_address_id">
                                <option value="0">Enter a new delivery address</option>
                                <?php foreach ($savedAddresses as $saved): ?>
                                    <option value="<?= (int) $saved['id'] ?>" <?= $selectedAddressId === (int) $saved['id'] ? 'selected' : '' ?>>
                                        <?= mephysShopPublicEscape($saved['address_line1'] . ', ' . $saved['town_city'] . ', ' . $saved['county']) ?><?= (int) $saved['is_default'] === 1 ? ' (Default)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Choose one to fill in its saved details, or select “Enter a new delivery address”.</div>
                        </div>
                    <?php else: ?>
                        <input type="hidden" id="saved_address_id" name="saved_address_id" value="0">
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="county">County <span class="required-mark">*</span></label>
                            <select class="form-select" id="county" name="county" required>
                                <option value="">Select county</option>
                                <?php foreach ($countyOptions as $county): ?>
                                    <option value="<?= mephysShopPublicEscape($county) ?>" <?= $values['county'] === $county ? 'selected' : '' ?>><?= mephysShopPublicEscape($county) ?></option>
                                <?php endforeach; ?>
                                <?php if ($values['county'] !== '' && !in_array($values['county'], $countyOptions, true)): ?>
                                    <option value="<?= mephysShopPublicEscape($values['county']) ?>" selected><?= mephysShopPublicEscape($values['county']) ?></option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="town_city_select">Town / City <span class="required-mark">*</span></label>
                            <select class="form-select" id="town_city_select" required>
                                <option value="">Select county first</option>
                            </select>
                            <input type="hidden" id="town_city" name="town_city" value="<?= mephysShopPublicEscape($values['town_city']) ?>">
                            <input class="form-control mt-2" id="town_city_other" type="text" maxlength="120" placeholder="Enter your town or city" style="display:none">
                            <div class="form-text">Choose from the list, or select “Other / not listed” to type your town.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="address_line1">Estate / neighbourhood, road or street <span class="required-mark">*</span></label>
                            <input class="form-control" id="address_line1" name="address_line1" required maxlength="255" placeholder="e.g. South B, Golden Gate Road" value="<?= mephysShopPublicEscape($values['address_line1']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="address_line2">Building, apartment, floor or house number (optional)</label>
                            <input class="form-control" id="address_line2" name="address_line2" maxlength="255" placeholder="e.g. House 548, Block C, 2nd floor" value="<?= mephysShopPublicEscape($values['address_line2']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="postal_code">Postal code (optional)</label>
                            <input class="form-control" id="postal_code" name="postal_code" maxlength="30" value="<?= mephysShopPublicEscape($values['postal_code']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="landmark">Nearby landmark (recommended)</label>
                            <input class="form-control" id="landmark" name="landmark" maxlength="500" placeholder="e.g. Near South B Shopping Centre" value="<?= mephysShopPublicEscape($values['landmark']) ?>">
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <div>
                                <label class="form-label fw-semibold mb-0">Pin your delivery location</label>
                                <div class="delivery-help">Tap or click the map to place the pin. You can drag it to adjust.</div>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" type="button" id="use-my-location"><i class="fa-solid fa-location-crosshairs me-1"></i>Use my current location</button>
                        </div>
                        <div id="mephys-delivery-map" aria-label="Map for choosing the delivery location"></div>
                        <div class="d-flex flex-wrap justify-content-between gap-2 mt-2">
                            <span class="delivery-help" id="map-location-status" aria-live="polite">No map pin selected yet.</span>
                            <button class="btn btn-sm btn-link text-danger p-0" type="button" id="clear-map-pin">Clear map pin</button>
                        </div>
                        <input type="hidden" id="latitude" name="latitude" value="<?= mephysShopPublicEscape($values['latitude']) ?>">
                        <input type="hidden" id="longitude" name="longitude" value="<?= mephysShopPublicEscape($values['longitude']) ?>">
                        <div class="form-text">If you cannot use the map, provide a clear landmark and detailed directions below. A map pin helps the rider navigate but does not independently verify the address.</div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label" for="delivery_instructions">How can our delivery person find you?</label>
                        <textarea class="form-control" id="delivery_instructions" name="delivery_instructions" rows="3" maxlength="3000" placeholder="e.g. Enter through the blue gate, second block on the left. Call me when you arrive; I will meet you at the gate."><?= mephysShopPublicEscape($values['delivery_instructions']) ?></textarea>
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="save_address" name="save_address" value="1" <?= $saveAddress ? 'checked' : '' ?>>
                        <label class="form-check-label" for="save_address">Save this delivery address to my Mephys account</label>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="make_default" name="make_default" value="1" <?= $makeDefault ? 'checked' : '' ?>>
                        <label class="form-check-label" for="make_default">Make this my default delivery address</label>
                    </div>
                </div>

                <div class="border rounded-4 p-4">
                    <h2 class="h5 fw-bold mb-3">Additional order note</h2>
                    <textarea class="form-control" id="customer_note" name="customer_note" rows="2" maxlength="1000" placeholder="Anything else Mephys should know about this order?"><?= mephysShopPublicEscape($values['customer_note']) ?></textarea>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="border rounded-4 p-4 position-sticky" style="top:100px">
                    <h2 class="h5 fw-bold">Order summary</h2>
                    <?php foreach ($cart as $productId => $quantity): ?>
                        <?php if (!isset($productMap[(int) $productId])) { continue; } $product = $productMap[(int) $productId]; ?>
                        <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                            <div><?= mephysShopPublicEscape($product['name']) ?><div class="small shop-muted">Quantity: <?= (int) $quantity ?></div></div>
                            <strong><?= mephysShopPublicMoney((float) $product['price'] * (int) $quantity) ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <div class="d-flex justify-content-between py-3"><span>Subtotal</span><strong><?= mephysShopPublicMoney($subtotal) ?></strong></div>
                    <div class="small shop-muted mb-3">Delivery charges, if applicable, will be confirmed by Mephys before dispatch.</div>
                    <div class="d-flex justify-content-between border-top pt-3 mb-3"><strong>Total to pay now</strong><strong class="fs-5 text-primary"><?= mephysShopPublicMoney($subtotal) ?></strong></div>
                    <button class="btn btn-primary btn-lg w-100" type="submit"><i class="fa-solid fa-mobile-screen-button me-2"></i>Continue to M-Pesa</button>
                    <p class="small shop-muted mt-3 mb-0">After submitting, check the M-Pesa phone for a payment prompt and enter your PIN there. Never share your PIN with anyone.</p>
                </div>
            </div>
        </form>
    </div>
</section>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function () {
    const savedAddresses = <?= json_encode($savedAddresses, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const addressSelect = document.getElementById('saved_address_id');
    const countySelect = document.getElementById('county');
    const townSelect = document.getElementById('town_city_select');
    const townValue = document.getElementById('town_city');
    const townOther = document.getElementById('town_city_other');
    const checkoutForm = document.querySelector('form');
    const fields = ['recipient_name', 'delivery_phone', 'county', 'address_line1', 'address_line2', 'postal_code', 'delivery_instructions', 'latitude', 'longitude'];
    const mapElement = document.getElementById('mephys-delivery-map');
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    const mapStatus = document.getElementById('map-location-status');
    const townsByCounty = {
        'Baringo':['Kabarnet','Eldama Ravine','Mogotio','Marigat','Chemolingot'],
        'Bomet':['Bomet','Sotik','Longisa','Mulot','Kaplong'],
        'Bungoma':['Bungoma','Webuye','Kimilili','Chwele','Malaba','Sirisia'],
        'Busia':['Busia','Malaba','Nambale','Port Victoria','Funyula','Bumala'],
        'Elgeyo-Marakwet':['Iten','Kapsowar','Tambach','Chepkorio'],
        'Embu':['Embu','Runyenjes','Siakago','Manyatta'],
        'Garissa':['Garissa','Dadaab','Modogashe'],
        'Homa Bay':['Homa Bay','Oyugis','Mbita','Kendu Bay','Rangwe'],
        'Isiolo':['Isiolo','Merti','Garbatulla'],
        'Kajiado':['Kajiado','Ngong','Kitengela','Ongata Rongai','Kiserian','Namanga','Isinya','Loitokitok'],
        'Kakamega':['Kakamega','Mumias','Malava','Butere','Lugari','Shinyalu'],
        'Kericho':['Kericho','Litein','Londiani','Kipkelion','Fort Ternan'],
        'Kiambu':['Kiambu','Thika','Ruiru','Juja','Limuru','Kikuyu','Kiambaa','Githunguri','Lari','Wangige'],
        'Kilifi':['Kilifi','Malindi','Mtwapa','Mariakani','Watamu','Kaloleni','Ganze'],
        'Kirinyaga':['Kerugoya','Kutus','Wanguru','Sagana','Kagio'],
        'Kisii':['Kisii','Ogembo','Suneka','Keroka','Nyamache'],
        'Kisumu':['Kisumu','Ahero','Maseno','Muhoroni','Kombewa'],
        'Kitui':['Kitui','Mwingi','Mutomo','Kibwezi','Kyuso'],
        'Kwale':['Ukunda','Diani','Kwale','Lunga Lunga','Msambweni'],
        'Laikipia':['Nanyuki','Nyahururu','Rumuruti','Kinamba'],
        'Lamu':['Lamu','Mpeketoni','Witu','Hindi'],
        'Machakos':['Machakos','Athi River','Mlolongo','Kangundo','Tala','Masii','Matuu'],
        'Makueni':['Wote','Emali','Sultan Hamud','Kibwezi','Makindu'],
        'Mandera':['Mandera','El Wak','Rhamu'],
        'Marsabit':['Marsabit','Moyale','Laisamis','North Horr'],
        'Meru':['Meru','Maua','Nkubu','Timau','Chuka','Mikinduri'],
        'Migori':['Migori','Awendo','Rongo','Kehancha','Isebania','Sori'],
        'Mombasa':['Mombasa','Likoni','Bamburi','Changamwe','Mtwapa'],
        'Murang’a':['Murang’a','Muranga','Kenol','Maragua','Kangema','Kiriaini'],
        'Nairobi':['Nairobi','Westlands','Kasarani','Embakasi','Karen','Kibera','Roysambu','Lang’ata','Ruaraka'],
        'Nakuru':['Nakuru','Naivasha','Gilgil','Molo','Njoro','Rongai','Subukia'],
        'Nandi':['Kapsabet','Nandi Hills','Mosoriot','Lessos'],
        'Narok':['Narok','Kilgoris','Ololulunga','Suswa'],
        'Nyamira':['Nyamira','Keroka','Nyansiongo','Ekerenyo'],
        'Nyandarua':['Ol Kalou','Engineer','Njabini','Kinangop','Ndaragwa'],
        'Nyeri':['Nyeri','Karatina','Othaya','Mukurwe-ini','Mweiga'],
        'Samburu':['Maralal','Baragoi','Archers Post'],
        'Siaya':['Siaya','Bondo','Ugunja','Yala','Usenge'],
        'Taita-Taveta':['Voi','Taveta','Wundanyi','Mwatate'],
        'Tana River':['Hola','Garsen','Bura','Madogo'],
        'Tharaka-Nithi':['Chuka','Chogoria','Marimanti','Kathwana'],
        'Trans Nzoia':['Kitale','Kiminini','Endebess','Maili Saba'],
        'Turkana':['Lodwar','Kakuma','Lokichogio','Kalokol'],
        'Uasin Gishu':['Eldoret','Burnt Forest','Turbo','Moiben','Matunda'],
        'Vihiga':['Mbale','Luanda','Chavakali','Majengo'],
        'Wajir':['Wajir','Habaswein','Griftu'],
        'West Pokot':['Kapenguria','Makutano','Ortum','Kacheliba']
    };
    const mapCountyNames = Object.keys(townsByCounty);
    let map = null;
    let marker = null;
    let lastGeocodeAt = 0;

    function findCounty(value) {
        const clean = String(value || '').replace(/ county$/i, '').trim().toLowerCase();
        return mapCountyNames.find(name => name.toLowerCase() === clean) || mapCountyNames.find(name => name.replace(/[’']/g, '').toLowerCase() === clean.replace(/[’']/g, '')) || '';
    }
    function setTownOptions(county, selected) {
        const towns = townsByCounty[county] || [];
        townSelect.innerHTML = '<option value="">Select town / city</option>';
        towns.forEach(town => {
            const option = document.createElement('option');
            option.value = town;
            option.textContent = town;
            townSelect.appendChild(option);
        });
        const other = document.createElement('option');
        other.value = '__other__';
        other.textContent = 'Other / not listed';
        townSelect.appendChild(other);
        const match = towns.find(t => t.toLowerCase() === String(selected || '').trim().toLowerCase());
        if (match) {
            townSelect.value = match;
            townOther.style.display = 'none';
            townOther.value = '';
            townValue.value = match;
        } else if (selected) {
            townSelect.value = '__other__';
            townOther.style.display = '';
            townOther.value = selected;
            townValue.value = selected;
        } else {
            townSelect.value = '';
            townOther.style.display = 'none';
            townOther.value = '';
            townValue.value = '';
        }
    }
    function syncTownValue() {
        if (townSelect.value === '__other__') {
            townOther.style.display = '';
            townOther.required = true;
            townValue.value = townOther.value.trim();
        } else {
            townOther.style.display = 'none';
            townOther.required = false;
            townOther.value = '';
            townValue.value = townSelect.value;
        }
    }
    countySelect.addEventListener('change', function () {
        setTownOptions(countySelect.value, '');
    });
    townSelect.addEventListener('change', syncTownValue);
    townOther.addEventListener('input', syncTownValue);
    if (checkoutForm) checkoutForm.addEventListener('submit', function () { syncTownValue(); });
    setTownOptions(findCounty(countySelect.value), townValue.value);

    async function reverseGeocode(lat, lng) {
        const wait = Math.max(0, 1100 - (Date.now() - lastGeocodeAt));
        if (wait) await new Promise(resolve => setTimeout(resolve, wait));
        lastGeocodeAt = Date.now();
        mapStatus.textContent = 'Pin selected. Looking up the address…';
        try {
            const url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&zoom=18&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng) + '&accept-language=en';
            const response = await fetch(url, {headers: {'Accept':'application/json'}});
            if (!response.ok) throw new Error('Address lookup unavailable');
            const result = await response.json();
            const a = result.address || {};
            const rawCounty = a.county || a.state_district || a.state || '';
            const county = findCounty(rawCounty);
            if (county) {
                countySelect.value = county;
                setTownOptions(county, townValue.value);
            }
            const town = a.city || a.town || a.municipality || a.city_district || a.village || a.suburb || '';
            if (town) {
                const countyName = findCounty(countySelect.value);
                setTownOptions(countyName, town);
            }
            const estate = a.neighbourhood || a.suburb || a.quarter || a.residential || '';
            const road = a.road || a.pedestrian || a.footway || '';
            const addressParts = [];
            if (estate && estate.toLowerCase() !== String(town).toLowerCase()) addressParts.push(estate);
            if (road && road.toLowerCase() !== String(estate).toLowerCase()) addressParts.push(road);
            if (addressParts.length) document.getElementById('address_line1').value = addressParts.join(', ');
            if (a.postcode) document.getElementById('postal_code').value = a.postcode;
            mapStatus.textContent = 'Location found. Please check the suggested county, town and street, then correct anything missing.';
        } catch (error) {
            mapStatus.textContent = 'Pin selected, but address details could not be filled automatically. Please enter or check them manually.';
        }
    }

    function setPin(lat, lng, zoom, lookupAddress) {
        latInput.value = Number(lat).toFixed(7);
        lngInput.value = Number(lng).toFixed(7);
        const point = [Number(lat), Number(lng)];
        if (!marker) {
            marker = L.marker(point, {draggable: true}).addTo(map);
            marker.on('dragend', function () {
                const position = marker.getLatLng();
                setPin(position.lat, position.lng, false, true);
            });
        } else marker.setLatLng(point);
        if (zoom !== false) map.setView(point, zoom || 16);
        mapStatus.textContent = 'Location pin selected: ' + Number(lat).toFixed(5) + ', ' + Number(lng).toFixed(5);
        if (lookupAddress) reverseGeocode(lat, lng);
    }

    if (window.L && mapElement) {
        map = L.map(mapElement).setView([0.0236, 37.9062], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'
        }).addTo(map);
        map.on('click', function (event) { setPin(event.latlng.lat, event.latlng.lng, false, true); });
        if (latInput.value !== '' && lngInput.value !== '') setPin(parseFloat(latInput.value), parseFloat(lngInput.value), 15, false);
        setTimeout(function () { map.invalidateSize(); }, 250);
    } else mapStatus.textContent = 'The map could not load. You can still enter detailed directions below.';

    if (addressSelect) {
        addressSelect.addEventListener('change', function () {
            const selectedId = Number(addressSelect.value);
            const address = savedAddresses.find(item => Number(item.id) === selectedId);
            if (!address) {
                fields.forEach(name => { const field = document.getElementById(name); if (field) field.value = ''; });
                countySelect.value = '';
                setTownOptions('', '');
                document.getElementById('landmark').value = '';
                if (marker && map) { map.removeLayer(marker); marker = null; }
                mapStatus.textContent = 'No map pin selected yet.';
                return;
            }
            fields.forEach(name => { const field = document.getElementById(name); if (field && Object.prototype.hasOwnProperty.call(address, name)) field.value = address[name] ?? ''; });
            const county = findCounty(address.county);
            countySelect.value = county || '';
            setTownOptions(county, address.town_city || '');
            if (address.latitude !== null && address.longitude !== null && map) setPin(address.latitude, address.longitude, 15, false);
            else if (marker && map) { map.removeLayer(marker); marker = null; mapStatus.textContent = 'This saved address has no map pin yet.'; }
            document.getElementById('landmark').value = '';
        });
    }

    document.getElementById('use-my-location').addEventListener('click', function () {
        if (!navigator.geolocation) {
            mapStatus.textContent = 'Your browser does not support location sharing. You can place the pin manually.';
            return;
        }
        mapStatus.textContent = 'Requesting permission and finding your location…';
        navigator.geolocation.getCurrentPosition(function (position) {
            if (map) setPin(position.coords.latitude, position.coords.longitude, 17, true);
        }, function (error) {
            mapStatus.textContent = error.code === 1
                ? 'Location permission was denied. Allow location access in your browser, or place the pin manually.'
                : 'We could not get your location. Check your connection and browser location settings, or place the pin manually.';
        }, {enableHighAccuracy: true, timeout: 20000, maximumAge: 60000});
    });

    document.getElementById('clear-map-pin').addEventListener('click', function () {
        latInput.value = '';
        lngInput.value = '';
        if (marker && map) { map.removeLayer(marker); marker = null; }
        mapStatus.textContent = 'Map pin cleared. Please provide a clear landmark and detailed directions below.';
    });
})();
</script>
<?php require __DIR__ . '/_public-footer.php'; ?>
