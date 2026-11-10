<?php

declare(strict_types=1);
require_once __DIR__ . '/_public-bootstrap.php';

global $pdo;

$search = trim((string) ($_GET['q'] ?? ''));
if (function_exists('mb_substr')) {
    $search = mb_substr($search, 0, 100, 'UTF-8');
} else {
    $search = substr($search, 0, 100);
}
$categorySlug = trim((string) ($_GET['category'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'featured');
$allowedSorts = ['featured', 'newest', 'price_low', 'price_high', 'name'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'featured';
}

$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$perPage = 12;

$orderBy = match ($sort) {
    'newest' => 'p.created_at DESC, p.id DESC',
    'price_low' => 'p.price ASC, p.name ASC',
    'price_high' => 'p.price DESC, p.name ASC',
    'name' => 'p.name ASC',
    default => 'p.is_featured DESC, p.sort_order ASC, p.name ASC',
};

$categories = [];
$products = [];
$totalProducts = 0;
$selectedCategory = null;
$loadError = false;

try {
    $categories = $pdo->query("SELECT id,name,slug FROM shop_categories WHERE is_active=1 ORDER BY sort_order ASC,name ASC")->fetchAll(PDO::FETCH_ASSOC);

    $where = ["p.status = 'published'", '(c.id IS NULL OR c.is_active = 1)'];
    $params = [];

    if ($search !== '') {
        $where[] = '(p.name LIKE :search_name OR p.short_description LIKE :search_short OR p.description LIKE :search_description OR p.sku LIKE :search_sku)';
        $term = '%' . $search . '%';
        $params[':search_name'] = $term;
        $params[':search_short'] = $term;
        $params[':search_description'] = $term;
        $params[':search_sku'] = $term;
    }

    if ($categorySlug !== '') {
        $where[] = 'c.slug = :category_slug';
        $params[':category_slug'] = $categorySlug;
    }

    $whereSql = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM shop_products p LEFT JOIN shop_categories c ON c.id=p.category_id WHERE {$whereSql}");
    $countStmt->execute($params);
    $totalProducts = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalProducts / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $productSql = "
        SELECT p.id,p.name,p.slug,p.short_description,p.price,p.compare_at_price,
               p.stock_quantity,p.track_inventory,p.allow_backorder,p.is_featured,
               (SELECT COUNT(*) FROM shop_product_variants pv WHERE pv.product_id=p.id AND pv.is_active=1) AS active_variant_count,
               c.name AS category_name,
               COALESCE(
                   primary_image.image_path,
                   (SELECT gallery_image.image_path
                    FROM shop_product_images gallery_image
                    WHERE gallery_image.product_id=p.id
                    ORDER BY gallery_image.is_primary DESC,gallery_image.sort_order ASC,gallery_image.id ASC
                    LIMIT 1)
               ) AS image_path
        FROM shop_products p
        LEFT JOIN shop_categories c ON c.id=p.category_id
        LEFT JOIN shop_product_images primary_image
               ON primary_image.product_id=p.id AND primary_image.is_primary=1
        WHERE {$whereSql}
        ORDER BY {$orderBy}
        LIMIT :limit_rows OFFSET :offset_rows
    ";
    $productStmt = $pdo->prepare($productSql);
    foreach ($params as $key => $value) {
        $productStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $productStmt->bindValue(':limit_rows', $perPage, PDO::PARAM_INT);
    $productStmt->bindValue(':offset_rows', $offset, PDO::PARAM_INT);
    $productStmt->execute();
    $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($categorySlug !== '') {
        foreach ($categories as $category) {
            if ($category['slug'] === $categorySlug) {
                $selectedCategory = $category;
                break;
            }
        }
    }
} catch (Throwable $e) {
    error_log('Mephys public shop catalogue error: ' . $e->getMessage());
    $loadError = true;
}

$totalPages = max(1, (int) ceil($totalProducts / $perPage));
$shopPageTitle = $selectedCategory ? (string) $selectedCategory['name'] : 'Products';
$shopPageDescription = 'Browse products currently offered by Mephys Physiotherapy Clinic.';
require __DIR__ . '/_public-header.php';

function mephysShopPageUrl(int $targetPage, string $search, string $category, string $sort): string
{
    $params = ['page' => $targetPage];
    if ($search !== '') $params['q'] = $search;
    if ($category !== '') $params['category'] = $category;
    if ($sort !== 'featured') $params['sort'] = $sort;
    return 'index.php?' . http_build_query($params);
}
?>

<section class="py-5" style="background:linear-gradient(120deg,#f1f8fc,#f8fbfd 58%,#edf8fb)">
    <div class="container py-3">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div class="shop-eyebrow mb-2">Mephys online shop</div>
                <h1 class="shop-heading mb-2">Products to support your recovery and wellbeing</h1>
                <p class="shop-muted mb-0">Explore the products currently offered by Mephys Physiotherapy Clinic.</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <span class="badge rounded-pill text-bg-light border px-3 py-2"><i class="fa-solid fa-box-open me-2 text-primary"></i><?= number_format($totalProducts) ?> products available to browse</span>
            </div>
        </div>
    </div>
</section>

<section class="py-4 py-lg-5">
    <div class="container">
        <div class="shop-breadcrumb mb-4"><a href="../index.php">Home</a> <span class="mx-2">/</span> Products<?= $selectedCategory ? ' <span class="mx-2">/</span> ' . mephysShopPublicEscape($selectedCategory['name']) : '' ?></div>

        <form method="GET" class="row g-2 align-items-end mb-4 p-3 p-lg-4 rounded-4 border" style="background:#fbfdff">
            <div class="col-lg-5">
                <label class="form-label small fw-semibold" for="q">Search products</label>
                <div class="input-group"><span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-secondary"></i></span><input class="form-control" id="q" name="q" value="<?= mephysShopPublicEscape($search) ?>" placeholder="Search by product name or description"></div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label small fw-semibold" for="category">Category</label>
                <select class="form-select" id="category" name="category">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= mephysShopPublicEscape($category['slug']) ?>" <?= $categorySlug === $category['slug'] ? 'selected' : '' ?>><?= mephysShopPublicEscape($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small fw-semibold" for="sort">Sort by</label>
                <select class="form-select" id="sort" name="sort">
                    <option value="featured" <?= $sort === 'featured' ? 'selected' : '' ?>>Featured</option>
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                    <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: low to high</option>
                    <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: high to low</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name</option>
                </select>
            </div>
            <div class="col-lg-2 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1" type="submit">Apply</button>
                <a class="btn btn-outline-secondary" href="index.php" aria-label="Clear filters"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>

        <?php if ($loadError): ?>
            <div class="alert alert-warning">The product catalogue is temporarily unavailable. Please try again later.</div>
        <?php elseif (!$products): ?>
            <div class="text-center py-5 px-3 rounded-4 border" style="background:#fbfdff">
                <div class="mb-3 text-primary" style="font-size:2.5rem"><i class="fa-solid fa-box-open"></i></div>
                <h2 class="h4 fw-bold">No products found</h2>
                <p class="shop-muted mb-3"><?= $search !== '' || $categorySlug !== '' ? 'Try changing your search or category filters.' : 'Products will appear here when the Mephys team publishes them.' ?></p>
                <?php if ($search !== '' || $categorySlug !== ''): ?><a href="index.php" class="btn btn-outline-primary">View all products</a><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center mb-3"><div class="small shop-muted">Showing <?= number_format(min($totalProducts, $offset + 1)) ?>–<?= number_format(min($totalProducts, $offset + count($products))) ?> of <?= number_format($totalProducts) ?> products</div></div>
            <div class="row g-3 g-md-4">
                <?php foreach ($products as $product):
                    $isOutOfStock = (int) $product['track_inventory'] === 1 && (int) $product['stock_quantity'] < 1 && (int) $product['allow_backorder'] !== 1;
                    $hasDiscount = $product['compare_at_price'] !== null && (float) $product['compare_at_price'] > (float) $product['price'];
                ?>
                    <div class="col-6 col-md-4 col-xl-3">
                        <article class="h-100 d-flex flex-column border rounded-4 overflow-hidden bg-white" style="box-shadow:0 5px 18px rgba(26,55,85,.045)">
                            <a href="product.php?slug=<?= rawurlencode((string) $product['slug']) ?>" class="d-block position-relative text-decoration-none" style="background:#f5f8fa">
                                <img src="<?= mephysShopPublicEscape(mephysShopPublicImageUrl($product['image_path'])) ?>" alt="<?= mephysShopPublicEscape($product['name']) ?>" loading="lazy" class="w-100" style="height:220px;object-fit:contain;padding:12px">
                                <?php if ((int) $product['is_featured'] === 1): ?><span class="badge text-bg-primary position-absolute top-0 start-0 m-2">Featured</span><?php endif; ?>
                                <?php if ($isOutOfStock): ?><span class="badge text-bg-secondary position-absolute top-0 end-0 m-2">Out of stock</span><?php elseif ($hasDiscount): ?><span class="badge text-bg-danger position-absolute top-0 end-0 m-2">Special price</span><?php endif; ?>
                            </a>
                            <div class="p-3 d-flex flex-column flex-grow-1">
                                <?php if (!empty($product['category_name'])): ?><div class="small text-uppercase fw-bold mb-1" style="color:#7d91a4;font-size:.68rem;letter-spacing:.7px"><?= mephysShopPublicEscape($product['category_name']) ?></div><?php endif; ?>
                                <h2 class="h6 fw-bold mb-2" style="min-height:2.5em;line-height:1.25">
                                    <a class="text-decoration-none" style="color:#263f55" href="product.php?slug=<?= rawurlencode((string) $product['slug']) ?>">
                                        <?= mephysShopPublicEscape($product['name']) ?>
                                    </a>
                                </h2>
                                <p class="small shop-muted mb-3 product-card-description">
                                    <?= mephysShopPublicEscape($product['short_description'] ?: 'View product details for more information.') ?>
                                </p>
                                <div class="d-flex align-items-baseline flex-wrap gap-2 mb-3"><span class="fw-bold" style="color:#006bb3;font-size:1.05rem"><?= mephysShopPublicMoney($product['price']) ?></span><?php if ($hasDiscount): ?><span class="small text-decoration-line-through text-secondary"><?= mephysShopPublicMoney($product['compare_at_price']) ?></span><?php endif; ?></div>
                                <?php if (!$isOutOfStock && (int) ($product['active_variant_count'] ?? 0) === 0): ?>
                                    <form method="POST" action="cart.php" class="mb-2">
                                        <?= mephysShopPublicCsrfField() ?>
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <button class="btn btn-primary btn-sm w-100" type="submit">
                                            <i class="fa-solid fa-cart-plus me-1"></i>Add to cart
                                        </button>
                                    </form>
                                <?php elseif (!$isOutOfStock): ?>
                                    <a href="product.php?slug=<?= rawurlencode((string) $product['slug']) ?>" class="btn btn-outline-primary btn-sm w-100 mb-2">
                                        <i class="fa-solid fa-eye me-1"></i>View product options
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm w-100 mb-2" type="button" disabled>Out of stock</button>
                                <?php endif; ?>
                                <a href="product.php?slug=<?= rawurlencode((string) $product['slug']) ?>" class="btn btn-outline-primary btn-sm w-100">View details <i class="fa-solid fa-arrow-right ms-1"></i></a>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="mt-4" aria-label="Product pages"><ul class="pagination justify-content-center flex-wrap">
                    <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="<?= mephysShopPublicEscape(mephysShopPageUrl($page - 1, $search, $categorySlug, $sort)) ?>">Previous</a></li><?php endif; ?>
                    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?><li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= mephysShopPublicEscape(mephysShopPageUrl($p, $search, $categorySlug, $sort)) ?>"><?= $p ?></a></li><?php endfor; ?>
                    <?php if ($page < $totalPages): ?><li class="page-item"><a class="page-link" href="<?= mephysShopPublicEscape(mephysShopPageUrl($page + 1, $search, $categorySlug, $sort)) ?>">Next</a></li><?php endif; ?>
                </ul></nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php if (isset($_GET['signup']) && $_GET['signup'] === '1' && !mephysShopCustomer()): ?>
<div class="modal fade" id="mephysSignupModal" tabindex="-1" aria-labelledby="mephysSignupModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center px-4 px-md-5 pb-4">
                <div class="mb-3 d-inline-flex align-items-center justify-content-center rounded-circle" style="width:64px;height:64px;background:#eaf5fc;color:#0876bd;font-size:1.6rem"><i class="fa-solid fa-user-plus"></i></div>
                <h2 class="h4 fw-bold mb-2" id="mephysSignupModalLabel">Create an account to add this item</h2>
                <p class="text-secondary mb-4">Join Mephys to save your cart, place orders and track your purchases. The product you selected will be added automatically after you sign in.</p>
                <a class="btn btn-primary btn-lg w-100 mb-3" href="register.php?next=cart.php">Create an account</a>
                <a class="btn btn-outline-primary btn-lg w-100 mb-3" href="login.php?next=cart.php">I already have an account — sign in</a>
                <?php $googleClientId = trim((string) getenv('MEPHYS_GOOGLE_CLIENT_ID')); ?>
                <?php if ($googleClientId !== '' && is_file(__DIR__ . '/google-auth.php')): ?>
                    <div class="small text-secondary mb-2">Or continue with Google</div>
                    <div id="g_id_onload" data-client_id="<?= mephysShopPublicEscape($googleClientId) ?>" data-callback="handleMephysGoogleCredential" data-auto_prompt="false"></div>
                    <div class="g_id_signin d-flex justify-content-center" data-type="standard" data-size="large" data-theme="outline" data-text="signup_with" data-shape="rectangular" data-logo_alignment="left"></div>
                    <form id="mephys-google-form" method="POST" action="google-auth.php" class="d-none">
                        <?= mephysShopPublicCsrfField() ?>
                        <input type="hidden" name="credential" id="mephys-google-credential">
                        <input type="hidden" name="next" value="cart.php">
                    </form>
                    <script src="https://accounts.google.com/gsi/client" async defer></script>
                    <script>function handleMephysGoogleCredential(response){document.getElementById('mephys-google-credential').value=response.credential;document.getElementById('mephys-google-form').submit();}</script>
                <?php else: ?>
                    <p class="small text-muted mb-0">Google sign-up can be enabled once Mephys Google sign-in is configured. You can create your account manually above.</p>
                <?php endif; ?>
                <p class="small text-muted mt-3 mb-0">You can close this window and keep browsing. Your selected item will be added after you sign in.</p>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalElement = document.getElementById('mephysSignupModal');
    if (modalElement && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
});
</script>
<?php endif; ?>
<style>
/* Keep product descriptions tidy and product cards consistent */
.product-card-description {
    line-height: 1.45;
    height: 4.35em;
    min-height: 4.35em;
    overflow: hidden;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
    line-clamp: 3;
}

/* Keep product prices and buttons aligned */
.product-card-description + div {
    margin-top: auto;
}
</style>
<?php require __DIR__ . '/_public-footer.php'; ?>
