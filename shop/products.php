<?php

declare(strict_types=1);
require_once __DIR__ . '/_public-bootstrap.php';

global $pdo;

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '' || strlen($slug) > 200 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
    http_response_code(404);
    exit('Product not found.');
}

try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name
        FROM shop_products p
        LEFT JOIN shop_categories c ON c.id=p.category_id
        WHERE p.slug=:slug AND p.status='published' AND (c.id IS NULL OR c.is_active=1)
        LIMIT 1
    ");
    $stmt->execute([':slug' => $slug]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        http_response_code(404);
        exit('Product not found.');
    }

    $imageStmt = $pdo->prepare('SELECT image_path,alt_text,is_primary,sort_order FROM shop_product_images WHERE product_id=:id ORDER BY is_primary DESC,sort_order ASC,id ASC');
    $imageStmt->execute([':id' => $product['id']]);
    $images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

    $attributeStmt = $pdo->prepare("SELECT a.name,v.attribute_value FROM shop_product_attribute_values v INNER JOIN shop_product_attributes a ON a.id=v.attribute_id WHERE v.product_id=:id AND a.is_active=1 ORDER BY a.sort_order ASC,a.name ASC");
    $attributeStmt->execute([':id' => $product['id']]);
    $attributes = $attributeStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Mephys public product page error: ' . $e->getMessage());
    http_response_code(503);
    exit('The product is temporarily unavailable. Please try again later.');
}

$isOutOfStock = (int) $product['track_inventory'] === 1 && (int) $product['stock_quantity'] < 1 && (int) $product['allow_backorder'] !== 1;
$hasDiscount = $product['compare_at_price'] !== null && (float) $product['compare_at_price'] > (float) $product['price'];
$shopPageTitle = (string) ($product['seo_title'] ?: $product['name']);
$shopPageDescription = (string) ($product['seo_description'] ?: ($product['short_description'] ?: 'Product details from Mephys Physiotherapy Clinic.'));
require __DIR__ . '/_public-header.php';
?>

<section class="py-4 py-lg-5">
    <div class="container">
        <div class="shop-breadcrumb mb-4"><a href="../index.php">Home</a> <span class="mx-2">/</span> <a href="index.php">Products</a><?php if (!empty($product['category_name'])): ?> <span class="mx-2">/</span> <?= mephysShopPublicEscape($product['category_name']) ?><?php endif; ?> <span class="mx-2">/</span> <?= mephysShopPublicEscape($product['name']) ?></div>

        <div class="row g-4 g-lg-5">
            <div class="col-lg-6">
                <div class="rounded-4 border overflow-hidden mb-3" style="background:#f6f9fb">
                    <?php if ($images): ?>
                        <img id="main-product-image" src="<?= mephysShopPublicEscape(mephysShopPublicImageUrl($images[0]['image_path'])) ?>" alt="<?= mephysShopPublicEscape($images[0]['alt_text'] ?: $product['name']) ?>" class="w-100" style="height:440px;object-fit:contain;padding:18px">
                    <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center text-secondary" style="height:440px"><i class="fa-regular fa-image fa-3x"></i></div>
                    <?php endif; ?>
                </div>
                <?php if (count($images) > 1): ?>
                    <div class="row g-2">
                        <?php foreach ($images as $image): ?>
                            <div class="col-3 col-md-2"><button type="button" class="border rounded-3 p-1 bg-white w-100 product-image-thumb" data-src="<?= mephysShopPublicEscape(mephysShopPublicImageUrl($image['image_path'])) ?>" data-alt="<?= mephysShopPublicEscape($image['alt_text'] ?: $product['name']) ?>"><img src="<?= mephysShopPublicEscape(mephysShopPublicImageUrl($image['image_path'])) ?>" alt="<?= mephysShopPublicEscape($image['alt_text'] ?: $product['name']) ?>" class="w-100" style="height:75px;object-fit:contain"></button></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-6">
                <?php if (!empty($product['category_name'])): ?><div class="shop-eyebrow mb-2"><?= mephysShopPublicEscape($product['category_name']) ?></div><?php endif; ?>
                <h1 class="shop-heading mb-3"><?= mephysShopPublicEscape($product['name']) ?></h1>
                <?php if (!empty($product['short_description'])): ?><p class="lead shop-muted mb-4"><?= mephysShopPublicEscape($product['short_description']) ?></p><?php endif; ?>
                <div class="d-flex flex-wrap align-items-center gap-3 mb-3"><span class="fw-bold" style="font-size:1.8rem;color:#006bb3"><?= mephysShopPublicMoney($product['price']) ?></span><?php if ($hasDiscount): ?><span class="fs-5 text-secondary text-decoration-line-through"><?= mephysShopPublicMoney($product['compare_at_price']) ?></span><?php endif; ?></div>
                <?php if ($isOutOfStock): ?><div class="alert alert-secondary py-2"><i class="fa-solid fa-circle-minus me-2"></i>Currently out of stock</div><?php elseif ((int) $product['track_inventory'] === 1): ?><div class="small text-success mb-3"><i class="fa-solid fa-circle-check me-1"></i><?= (int) $product['stock_quantity'] ?> available</div><?php else: ?><div class="small text-success mb-3"><i class="fa-solid fa-circle-check me-1"></i>Available</div><?php endif; ?>

                <?php if (!empty($product['description'])): ?>
                    <div class="border-top pt-4 mt-4"><h2 class="h5 fw-bold mb-3">Product description</h2><div class="shop-muted" style="line-height:1.8"><?= nl2br(mephysShopPublicEscape($product['description'])) ?></div></div>
                <?php endif; ?>

                <?php if ($attributes): ?>
                    <div class="border-top pt-4 mt-4"><h2 class="h5 fw-bold mb-3">Product information</h2><div class="table-responsive"><table class="table table-sm"><tbody><?php foreach ($attributes as $attribute): ?><tr><th class="text-secondary fw-semibold" style="width:40%"><?= mephysShopPublicEscape($attribute['name']) ?></th><td><?= mephysShopPublicEscape($attribute['attribute_value']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
                <?php endif; ?>

                <div class="border rounded-4 p-3 mt-4" style="background:#f8fbfd"><div class="fw-semibold mb-1"><i class="fa-solid fa-headset me-2 text-primary"></i>Need help with this product?</div><p class="small shop-muted mb-2">Contact the Mephys team for product information and assistance.</p><a href="../contact.php" class="btn btn-outline-primary btn-sm">Contact Mephys</a></div>
            </div>
        </div>
    </div>
</section>

<section class="pb-5"><div class="container"><a href="index.php" class="text-decoration-none"><i class="fa-solid fa-arrow-left me-2"></i>Back to all products</a></div></section>

<script>
document.querySelectorAll('.product-image-thumb').forEach(function(button){
    button.addEventListener('click', function(){
        const main = document.getElementById('main-product-image');
        if (!main) return;
        main.src = this.dataset.src;
        main.alt = this.dataset.alt || 'Product image';
    });
});
</script>

<?php require __DIR__ . '/_public-footer.php'; ?>
