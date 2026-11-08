<?php

declare(strict_types=1);

// Public shop pages use the same PDO connection as the existing Mephys website.
require_once dirname(__DIR__) . '/db.php';

global $pdo;

if (!$pdo instanceof PDO) {
    http_response_code(503);
    exit('The shop is temporarily unavailable. Please try again later.');
}

if (!function_exists('mephysShopPublicEscape')) {
    function mephysShopPublicEscape($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

if (!function_exists('mephysShopPublicImageUrl')) {
    function mephysShopPublicImageUrl(?string $path): string
    {
        $path = trim((string) $path);
        // Only serve image paths created by the shop's own upload process.
        if ($path === '' || !str_starts_with($path, 'shop/uploads/products/')) {
            return '../images/logo.jpeg';
        }
        return '../' . $path;
    }
}

if (!function_exists('mephysShopPublicMoney')) {
    function mephysShopPublicMoney($amount): string
    {
        return 'KSh ' . number_format((float) $amount, 2);
    }
}


// Customer cart and form security use a dedicated public-shop session namespace.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['mephys_shop_csrf'])) {
    $_SESSION['mephys_shop_csrf'] = bin2hex(random_bytes(32));
}
if (!isset($_SESSION['mephys_shop_cart']) || !is_array($_SESSION['mephys_shop_cart'])) {
    $_SESSION['mephys_shop_cart'] = [];
}
if (!function_exists('mephysShopPublicCsrfField')) {
    function mephysShopPublicCsrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . mephysShopPublicEscape($_SESSION['mephys_shop_csrf'] ?? '') . '">';
    }
}
if (!function_exists('mephysShopPublicVerifyCsrf')) {
    function mephysShopPublicVerifyCsrf(): void
    {
        $sent = (string) ($_POST['csrf_token'] ?? '');
        $known = (string) ($_SESSION['mephys_shop_csrf'] ?? '');
        if ($sent === '' || $known === '' || !hash_equals($known, $sent)) {
            http_response_code(403);
            exit('Your session security check failed. Refresh the page and try again.');
        }
    }
}
if (!function_exists('mephysShopPublicCartCount')) {
    function mephysShopPublicCartCount(): int
    {
        return array_sum(array_map('intval', $_SESSION['mephys_shop_cart'] ?? []));
    }
}

// Customer authentication and persistent customer-cart helpers.
require_once __DIR__ . '/_customer-auth.php';

// Restore the signed-in customer's saved cart for the current public shop request.
mephysShopRestoreCustomerCart();
