<?php

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

global $pdo;

// Archive products instead of deleting them so previous orders can retain their references.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'archive') {
    mephysShopAdminVerifyCsrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) {
        mephysShopAdminFlash('danger', 'Invalid product selected.');
        header('Location: products.php');
        exit;
    }

    $stmt = $pdo->prepare("UPDATE shop_products SET status = 'archived', updated_by_staff_id = :staff_id WHERE id = :id");
    $stmt->execute([':staff_id' => (int) $shopAdminStaff['id'], ':id' => $id]);
    mephysShopAdminAudit('shop_product_archived', 'shop_product', (int) $id);
    mephysShopAdminFlash('success', 'Product archived. It will no longer appear in the public shop.');
    header('Location: products.php');
    exit;
}

$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$allowedStatuses = ['', 'draft', 'published', 'archived'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(p.name LIKE :search_name OR p.sku LIKE :search_sku OR p.slug LIKE :search_slug)';
    $params[':search_name'] = '%' . $search . '%';
    $params[':search_sku'] = '%' . $search . '%';
    $params[':search_slug'] = '%' . $search . '%';
}
if ($statusFilter !== '') {
    $where[] = 'p.status = :status';
    $params[':status'] = $statusFilter;
}

$sql = "
    SELECT
        p.id, p.name, p.sku, p.price, p.compare_at_price,
        p.stock_quantity, p.track_inventory, p.status, p.is_featured,
        p.updated_at, c.name AS category_name,
        (
            SELECT pi.image_path
            FROM shop_product_images pi
            WHERE pi.product_id = p.id
            ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
            LIMIT 1
        ) AS primary_image
    FROM shop_products p
    LEFT JOIN shop_categories c ON c.id = p.category_id
";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY p.updated_at DESC, p.id DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statsStmt = $pdo->query("SELECT COUNT(*) AS total, SUM(status='published') AS published, SUM(status='draft') AS drafts, SUM(status='archived') AS archived FROM shop_products");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'published' => 0, 'drafts' => 0, 'archived' => 0];

mephysShopAdminLayoutStart('Products', $shopAdminStaff);
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
        <h1 class="shop-title">Products</h1>
        <div class="shop-subtitle">Manage the products customers can browse and purchase on the Mephys website.</div>
    </div>
    <a href="/shop/admin/products-form.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add product</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="shop-card shop-stat"><strong><?= (int) $stats['total'] ?></strong><span>All products</span></div></div>
    <div class="col-6 col-lg-3"><div class="shop-card shop-stat"><strong><?= (int) $stats['published'] ?></strong><span>Published</span></div></div>
    <div class="col-6 col-lg-3"><div class="shop-card shop-stat"><strong><?= (int) $stats['drafts'] ?></strong><span>Drafts</span></div></div>
    <div class="col-6 col-lg-3"><div class="shop-card shop-stat"><strong><?= (int) $stats['archived'] ?></strong><span>Archived</span></div></div>
</div>

<section class="shop-card">
    <div class="shop-card-head">
        <div><strong>Product catalogue</strong><div class="shop-subtitle">Showing up to 200 most recently updated products.</div></div>
    </div>
    <div class="shop-card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-7"><label class="form-label" for="search">Search products</label><input class="form-control" id="search" name="search" value="<?= mephysShopAdminEscape($search) ?>" placeholder="Product name, SKU or slug"></div>
            <div class="col-md-3"><label class="form-label" for="status">Publication status</label><select class="form-select" id="status" name="status"><option value="">All statuses</option><?php foreach (['draft','published','archived'] as $status): ?><option value="<?= $status ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1" type="submit">Filter</button><a class="btn btn-outline-secondary" href="products.php">Reset</a></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Updated</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (!$products): ?>
                <tr><td colspan="7" class="text-center py-5 text-secondary">No products found. Add your first product to begin building the catalogue.</td></tr>
            <?php else: foreach ($products as $product): ?>
                <tr>
                    <td><div class="d-flex align-items-center gap-3">
                        <?php if (!empty($product['primary_image'])): ?><img class="shop-thumb" src="../../<?= mephysShopAdminEscape($product['primary_image']) ?>" alt="">
                        <?php else: ?><div class="shop-thumb d-grid place-items-center text-secondary" style="display:grid;place-items:center"><i class="bi bi-image"></i></div><?php endif; ?>
                        <div><div class="fw-bold"><?= mephysShopAdminEscape($product['name']) ?> <?= (int) $product['is_featured'] === 1 ? '<span class="badge text-bg-warning ms-1">Featured</span>' : '' ?></div><div class="small shop-muted">SKU: <?= mephysShopAdminEscape($product['sku'] ?: 'Not set') ?></div></div>
                    </div></td>
                    <td><?= mephysShopAdminEscape($product['category_name'] ?? 'Uncategorised') ?></td>
                    <td class="fw-semibold">KSh <?= number_format((float) $product['price'], 2) ?><?php if ($product['compare_at_price'] !== null): ?><div class="small text-secondary text-decoration-line-through">KSh <?= number_format((float) $product['compare_at_price'], 2) ?></div><?php endif; ?></td>
                    <td><?= (int) $product['track_inventory'] === 1 ? (int) $product['stock_quantity'] : '<span class="text-secondary">Not tracked</span>' ?></td>
                    <td><span class="badge rounded-pill <?= $product['status'] === 'published' ? 'text-bg-success' : ($product['status'] === 'draft' ? 'text-bg-secondary' : 'text-bg-dark') ?>"><?= mephysShopAdminEscape(ucfirst($product['status'])) ?></span></td>
                    <td class="small text-secondary"><?= mephysShopAdminEscape(date('d M Y', strtotime((string) $product['updated_at']))) ?></td>
                    <td><div class="d-flex justify-content-end gap-2">
                        <a class="btn btn-sm btn-outline-primary" href="/shop/admin/products-form.php?id=<?= (int) $product['id'] ?>"><i class="bi bi-pencil-square me-1"></i>Edit</a>
                        <?php if ($product['status'] !== 'archived'): ?><form method="POST" onsubmit="return confirm('Archive this product? It will be hidden from the public shop.');"><?= mephysShopAdminCsrfField() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= (int) $product['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-archive"></i></button></form><?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php mephysShopAdminLayoutEnd(); ?>
