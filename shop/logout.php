<?php
declare(strict_types=1);
require_once __DIR__ . '/_public-bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . (mephysShopCustomer() ? 'account.php' : 'index.php'));
    exit;
}

mephysShopPublicVerifyCsrf();
mephysShopLogoutCustomer();

header('Location: index.php?signed_out=1');
exit;
