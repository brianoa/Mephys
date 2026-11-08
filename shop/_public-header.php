<?php
// Expects public-shop helpers and customer authentication from _public-bootstrap.php.
$shopPageTitle = isset($shopPageTitle) ? (string) $shopPageTitle : 'Products';
$shopPageDescription = isset($shopPageDescription) ? (string) $shopPageDescription : 'Browse products available from Mephys Physiotherapy Clinic.';
$shopSignedInCustomer = function_exists('mephysShopCustomer') ? mephysShopCustomer() : null;
$shopCartCount = function_exists('mephysShopPublicCartCount') ? mephysShopPublicCartCount() : 0;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index,follow">
    <title><?= mephysShopPublicEscape($shopPageTitle) ?> | Mephys Clinic</title>
    <meta name="description" content="<?= mephysShopPublicEscape($shopPageDescription) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root{--mephys-blue:#006bb3;--mephys-teal:#0099cc;--mephys-ink:#20364b;--mephys-muted:#758395;--mephys-line:#e7edf3;--mephys-bg:#f6f9fc}
        *{box-sizing:border-box}
        body{margin:0;background:#fff;color:#34445a;font-family:Inter,"Segoe UI",Arial,sans-serif}
        .shop-site-nav{background:rgba(255,255,255,.97);border-bottom:1px solid var(--mephys-line);box-shadow:0 3px 16px rgba(22,50,80,.05)}
        .shop-site-nav .navbar-brand{font-weight:800;color:var(--mephys-blue);font-size:1.45rem;letter-spacing:.2px}
        .shop-site-nav .nav-link{font-weight:600;color:#34445a;padding:.65rem .8rem!important}
        .shop-site-nav .nav-link:hover,.shop-site-nav .nav-link.active{color:var(--mephys-blue)}
        .shop-public-main{min-height:65vh}
        .shop-breadcrumb{font-size:.85rem;color:var(--mephys-muted)}
        .shop-breadcrumb a{color:var(--mephys-blue);text-decoration:none}
        .shop-public-footer{background:#17364e;color:#eaf1f6;margin-top:70px;padding:32px 0}
        .shop-public-footer a{color:#eaf1f6;text-decoration:none}
        .shop-public-footer a:hover{color:#71d2ed}
        .shop-eyebrow{color:var(--mephys-teal);font-size:.75rem;font-weight:800;letter-spacing:1.8px;text-transform:uppercase}
        .shop-heading{color:var(--mephys-ink);font-size:clamp(1.8rem,3vw,2.5rem);font-weight:800;letter-spacing:-.7px}
        .shop-muted{color:var(--mephys-muted)}
        .shop-account-nav{display:flex;align-items:center;gap:.45rem}
        .shop-account-nav form{display:inline-flex;margin:0}
        .shop-account-nav .btn-link{font-weight:600;text-decoration:none;padding:.45rem .65rem}
        @media(max-width:575px){.shop-site-nav .navbar-brand{font-size:1.2rem}.shop-account-nav{align-items:stretch;flex-direction:column}.shop-account-nav form{display:block}.shop-account-nav form button{width:100%;text-align:left}}
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg sticky-top shop-site-nav">
    <div class="container">
        <a class="navbar-brand" href="../index.php#home">Mephys</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mephysShopNav" aria-controls="mephysShopNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mephysShopNav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item"><a class="nav-link" href="../index.php#home">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="../services.php">Services</a></li>
                <li class="nav-item"><a class="nav-link" href="index.php">Products</a></li>
                <li class="nav-item"><a class="nav-link" href="../about.php#about">About Us</a></li>
                <li class="nav-item"><a class="nav-link" href="../location.php#map">Location</a></li>
                <li class="nav-item"><a class="nav-link" href="cart.php"><i class="fa-solid fa-cart-shopping me-1"></i>Cart <span class="badge rounded-pill text-bg-primary"><?= (int) $shopCartCount ?></span></a></li>
                <?php if ($shopSignedInCustomer): ?>
                    <li class="nav-item"><a class="nav-link" href="account.php"><i class="fa-regular fa-circle-user me-1"></i>My account</a></li>
                    <li class="nav-item shop-account-nav">
                        <span class="small text-muted px-lg-2">Hi, <?= mephysShopPublicEscape($shopSignedInCustomer['first_name'] ?? 'Customer') ?></span>
                        <form method="POST" action="logout.php">
                            <?= mephysShopPublicCsrfField() ?>
                            <button class="btn btn-link nav-link text-danger" type="submit"><i class="fa-solid fa-arrow-right-from-bracket me-1"></i>Sign out</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="login.php"><i class="fa-regular fa-user me-1"></i>Sign in</a></li>
                    <li class="nav-item"><a class="btn btn-primary btn-sm ms-lg-2" href="register.php">Create account</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<main class="shop-public-main">
