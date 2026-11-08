<?php

declare(strict_types=1);

/**
 * Shared security, database and layout helpers for Mephys shop administration.
 * Place this file at shop/admin/_bootstrap.php.
 */

$mephysProjectRoot = dirname(__DIR__, 2);
require_once $mephysProjectRoot . '/staff-auth.php';

$shopAdminStaff = mephysRequireStaffLogin();
global $pdo;

if (!$pdo instanceof PDO) {
    http_response_code(500);
    exit('The database connection is unavailable.');
}

// The current staff_users table permits these two staff roles.
if (!is_array($shopAdminStaff) || !in_array(
    (string) ($shopAdminStaff['role'] ?? ''),
    ['ceo', 'technical_staff'],
    true
)) {
    http_response_code(403);
    exit('You are not authorised to manage the online shop.');
}

if (!function_exists('mephysShopAdminEscape')) {
    function mephysShopAdminEscape($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

if (!function_exists('mephysShopAdminLength')) {
    function mephysShopAdminLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}

if (!function_exists('mephysShopAdminCsrfField')) {
    function mephysShopAdminCsrfField(): string
    {
        if (!function_exists('mephysCsrfToken')) {
            throw new RuntimeException('The staff CSRF helper is unavailable.');
        }

        return '<input type="hidden" name="csrf_token" value="' .
            mephysShopAdminEscape(mephysCsrfToken()) . '">';
    }
}

if (!function_exists('mephysShopAdminVerifyCsrf')) {
    function mephysShopAdminVerifyCsrf(): void
    {
        if (
            !function_exists('mephysVerifyCsrfToken') ||
            !mephysVerifyCsrfToken($_POST['csrf_token'] ?? '')
        ) {
            http_response_code(403);
            exit('Invalid security token. Refresh the page and try again.');
        }
    }
}

if (!function_exists('mephysShopAdminSlug')) {
    function mephysShopAdminSlug(PDO $pdo, string $table, string $name, ?int $excludeId = null): string
    {
        $allowedTables = ['shop_products', 'shop_categories', 'shop_product_attributes'];
        if (!in_array($table, $allowedTables, true)) {
            throw new InvalidArgumentException('Invalid slug table.');
        }

        $base = strtolower(trim($name));
        $base = preg_replace('/[^a-z0-9]+/i', '-', $base) ?? '';
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'item';
        }

        $maxLength = $table === 'shop_categories' ? 160 : ($table === 'shop_product_attributes' ? 140 : 200);
        $candidate = substr($base, 0, $maxLength);
        $suffix = 2;

        while (true) {
            $sql = "SELECT id FROM {$table} WHERE slug = :slug";
            $params = [':slug' => $candidate];
            if ($excludeId !== null) {
                $sql .= ' AND id <> :exclude_id';
                $params[':exclude_id'] = $excludeId;
            }
            $sql .= ' LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                return $candidate;
            }

            $tail = '-' . $suffix++;
            $candidate = substr($base, 0, $maxLength - strlen($tail)) . $tail;
        }
    }
}

if (!function_exists('mephysShopAdminFlash')) {
    function mephysShopAdminFlash(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['mephys_shop_admin_flash'] = [
            'type' => in_array($type, ['success', 'danger', 'warning', 'info'], true) ? $type : 'info',
            'message' => $message,
        ];
    }
}

if (!function_exists('mephysShopAdminShowFlash')) {
    function mephysShopAdminShowFlash(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $flash = $_SESSION['mephys_shop_admin_flash'] ?? null;
        unset($_SESSION['mephys_shop_admin_flash']);
        if (!is_array($flash) || empty($flash['message'])) {
            return;
        }
        echo '<div class="alert alert-' . mephysShopAdminEscape($flash['type'] ?? 'info') . ' alert-dismissible fade show" role="alert">';
        echo mephysShopAdminEscape($flash['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
}

if (!function_exists('mephysShopAdminLayoutStart')) {
    function mephysShopAdminLayoutStart(string $title, array $staff): void
    {
        $safeTitle = mephysShopAdminEscape($title);
        $staffName = mephysShopAdminEscape($staff['username'] ?? 'Staff');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<meta name="robots" content="noindex,nofollow">';
        echo '<title>' . $safeTitle . ' | Mephys Shop Admin</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
        echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">';
        echo '<style>
            :root{--shop-ink:#20364b;--shop-muted:#778596;--shop-line:#e7edf3;--shop-blue:#2868d7;--shop-bg:#f5f7fb}
            body{margin:0;background:var(--shop-bg);color:#34445a;font-family:Inter,"Segoe UI",Arial,sans-serif;font-size:14px}
            .shop-admin-top{background:#fff;border-bottom:1px solid var(--shop-line);padding:15px 24px;display:flex;align-items:center;justify-content:space-between;gap:15px}
            .shop-admin-brand{font-weight:800;letter-spacing:.4px;color:var(--shop-ink);text-decoration:none}
            .shop-admin-brand small{display:block;font-size:9px;letter-spacing:1.2px;color:#94a0ad;font-weight:700}
            .shop-admin-wrap{max-width:1500px;margin:0 auto;padding:25px 24px 45px}
            .shop-admin-nav{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:22px}
            .shop-admin-nav a{padding:8px 12px;border:1px solid var(--shop-line);border-radius:8px;text-decoration:none;color:#58687a;background:#fff;font-size:12px;font-weight:650}
            .shop-admin-nav a:hover{border-color:#bfd2f2;color:var(--shop-blue);background:#f8fbff}
            .shop-card{background:#fff;border:1px solid var(--shop-line);border-radius:12px;box-shadow:0 3px 12px rgba(33,54,80,.035)}
            .shop-card-head{padding:16px 19px;border-bottom:1px solid #edf1f5;display:flex;justify-content:space-between;align-items:center;gap:15px}
            .shop-card-body{padding:19px}
            .shop-title{font-size:22px;font-weight:800;color:var(--shop-ink);letter-spacing:-.4px;margin:0}
            .shop-subtitle{font-size:12px;color:var(--shop-muted);margin-top:5px}
            .form-label{font-size:12px;font-weight:700;color:#536275}
            .form-control,.form-select{font-size:13px;border-color:#dfe6ee;border-radius:7px;min-height:39px}
            .form-control:focus,.form-select:focus{border-color:#8eb4ec;box-shadow:0 0 0 .2rem rgba(40,104,215,.1)}
            .btn{font-size:12px;font-weight:700;border-radius:7px}
            .btn-primary{background:var(--shop-blue);border-color:var(--shop-blue)}
            .table{font-size:12px}
            .table th{font-size:10px;text-transform:uppercase;letter-spacing:.4px;color:#7e8b9a;background:#fafbfd;white-space:nowrap}
            .table td,.table th{padding:12px;vertical-align:middle}
            .shop-muted{color:var(--shop-muted)}
            .shop-thumb{width:54px;height:54px;object-fit:cover;border-radius:8px;border:1px solid var(--shop-line);background:#f8fafc}
            .shop-stat{padding:15px 17px}.shop-stat strong{display:block;font-size:24px;color:var(--shop-ink)}.shop-stat span{font-size:11px;color:var(--shop-muted)}
            .required-mark{color:#c2414b}
            @media(max-width:640px){.shop-admin-top{padding:13px 15px}.shop-admin-wrap{padding:18px 12px 30px}.shop-card-body{padding:14px}.shop-title{font-size:19px}}
        </style></head><body>';
        echo '<header class="shop-admin-top"><a class="shop-admin-brand" href="products.php">MEPHYS<small>ONLINE SHOP MANAGEMENT</small></a>';
        echo '<div class="d-flex align-items-center gap-3"><span class="small text-secondary">' . $staffName . '</span><a class="btn btn-outline-secondary btn-sm" href="../../crud.php?page=dashboard"><i class="bi bi-arrow-left me-1"></i>Staff dashboard</a></div></header>';
        echo '<main class="shop-admin-wrap">';
        echo '<nav class="shop-admin-nav" aria-label="Shop administration">';
        echo '<a href="products.php"><i class="bi bi-box-seam me-1"></i>Products</a>';
        echo '<a href="categories.php"><i class="bi bi-tags me-1"></i>Categories</a>';
        echo '<a href="attributes.php"><i class="bi bi-sliders me-1"></i>Specifications</a>';
        echo '</nav>';
        mephysShopAdminShowFlash();
    }
}

if (!function_exists('mephysShopAdminLayoutEnd')) {
    function mephysShopAdminLayoutEnd(): void
    {
        echo '</main><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>';
    }
}

if (!function_exists('mephysShopAdminAudit')) {
    function mephysShopAdminAudit(string $action, string $entityType, int $entityId): void
    {
        if (function_exists('mephysAuditLog')) {
            mephysAuditLog($action, $entityType, $entityId);
        }
    }
}
