<?php

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

global $pdo;

$productId = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$error = '';
$uploadedPaths = [];
$originalStockQuantity = 0;

// Allow staff to choose which uploaded image is the product's main image.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_primary_image_id'])) {
    mephysShopAdminVerifyCsrf();
    $imageId = filter_var($_POST['make_primary_image_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$productId || !$imageId) {
        mephysShopAdminFlash('danger', 'Invalid image selected.');
        header('Location: products.php');
        exit;
    }
    $checkImage = $pdo->prepare('SELECT id FROM shop_product_images WHERE id=:image_id AND product_id=:product_id LIMIT 1');
    $checkImage->execute([':image_id' => $imageId, ':product_id' => $productId]);
    if (!$checkImage->fetchColumn()) {
        mephysShopAdminFlash('warning', 'That image does not belong to this product.');
    } else {
        $pdo->beginTransaction();
        try {
            $clearPrimary = $pdo->prepare('UPDATE shop_product_images SET is_primary=0 WHERE product_id=:product_id');
            $clearPrimary->execute([':product_id' => $productId]);
            $setPrimary = $pdo->prepare('UPDATE shop_product_images SET is_primary=1 WHERE id=:image_id AND product_id=:product_id');
            $setPrimary->execute([':image_id' => $imageId, ':product_id' => $productId]);
            $pdo->commit();
            mephysShopAdminAudit('shop_product_primary_image_changed', 'shop_product', $productId);
            mephysShopAdminFlash('success', 'Main product image updated.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Mephys shop primary image update failed: ' . $e->getMessage());
            mephysShopAdminFlash('danger', 'Unable to change the main image.');
        }
    }
    header('Location: product-form.php?id=' . $productId);
    exit;
}

// Handle image removal as a separate POST action.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_image_id'])) {
    mephysShopAdminVerifyCsrf();
    $imageId = filter_var($_POST['remove_image_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$productId || !$imageId) {
        mephysShopAdminFlash('danger', 'Invalid image selected.');
        header('Location: products.php');
        exit;
    }

    $findImage = $pdo->prepare('SELECT id,image_path,is_primary FROM shop_product_images WHERE id=:image_id AND product_id=:product_id LIMIT 1');
    $findImage->execute([':image_id' => $imageId, ':product_id' => $productId]);
    $image = $findImage->fetch(PDO::FETCH_ASSOC);
    $productStatusStmt = $pdo->prepare('SELECT status FROM shop_products WHERE id=:id LIMIT 1');
    $productStatusStmt->execute([':id' => $productId]);
    $currentProductStatus = $productStatusStmt->fetchColumn();
    $imageCountStmt = $pdo->prepare('SELECT COUNT(*) FROM shop_product_images WHERE product_id=:id');
    $imageCountStmt->execute([':id' => $productId]);
    $currentImageCount = (int) $imageCountStmt->fetchColumn();

    if (!$image) {
        mephysShopAdminFlash('warning', 'That image could not be found.');
    } elseif ($currentProductStatus === 'published' && $currentImageCount <= 1) {
        mephysShopAdminFlash('warning', 'This is the last image on a published product. Unpublish the product or upload a replacement image before removing it.');
    } else {
        $pdo->beginTransaction();
        try {
            $deleteImage = $pdo->prepare('DELETE FROM shop_product_images WHERE id=:id AND product_id=:product_id');
            $deleteImage->execute([':id' => $imageId, ':product_id' => $productId]);
            if ((int) $image['is_primary'] === 1) {
                $nextImage = $pdo->prepare('SELECT id FROM shop_product_images WHERE product_id=:product_id ORDER BY sort_order ASC,id ASC LIMIT 1');
                $nextImage->execute([':product_id' => $productId]);
                $nextId = $nextImage->fetchColumn();
                if ($nextId) {
                    $setPrimary = $pdo->prepare('UPDATE shop_product_images SET is_primary=1 WHERE id=:id');
                    $setPrimary->execute([':id' => $nextId]);
                }
            }
            $pdo->commit();

            $relativePath = (string) $image['image_path'];
            if (str_starts_with($relativePath, 'shop/uploads/products/')) {
                $absolutePath = dirname(__DIR__, 2) . '/' . $relativePath;
                if (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            }
            mephysShopAdminAudit('shop_product_image_deleted', 'shop_product', $productId);
            mephysShopAdminFlash('success', 'Product image removed.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Mephys shop image removal failed: ' . $e->getMessage());
            mephysShopAdminFlash('danger', 'The image could not be removed.');
        }
    }
    header('Location: product-form.php?id=' . $productId);
    exit;
}

$product = [
    'id' => null, 'category_id' => '', 'sku' => '', 'name' => '', 'short_description' => '',
    'description' => '', 'price' => '', 'compare_at_price' => '', 'stock_quantity' => 0,
    'track_inventory' => 1, 'allow_backorder' => 0, 'status' => 'draft', 'is_featured' => 0,
    'sort_order' => 0, 'seo_title' => '', 'seo_description' => '',
];

if ($productId) {
    $findProduct = $pdo->prepare('SELECT * FROM shop_products WHERE id=:id LIMIT 1');
    $findProduct->execute([':id' => $productId]);
    $existingProduct = $findProduct->fetch(PDO::FETCH_ASSOC);
    if (!$existingProduct) {
        http_response_code(404);
        exit('Product not found.');
    }
    $product = array_merge($product, $existingProduct);
    $originalStockQuantity = (int) $existingProduct['stock_quantity'];
}

$categories = $pdo->query('SELECT id,name FROM shop_categories WHERE is_active=1 ORDER BY sort_order ASC,name ASC')->fetchAll(PDO::FETCH_ASSOC);
$attributes = $pdo->query('SELECT id,name,input_type,options_text FROM shop_product_attributes WHERE is_active=1 ORDER BY sort_order ASC,name ASC')->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? 'save') === 'save') {
    mephysShopAdminVerifyCsrf();

    $name = trim((string) ($_POST['name'] ?? ''));
    $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    $sku = trim((string) ($_POST['sku'] ?? ''));
    $shortDescription = trim((string) ($_POST['short_description'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $priceInput = trim((string) ($_POST['price'] ?? ''));
    $compareInput = trim((string) ($_POST['compare_at_price'] ?? ''));
    $stockInput = filter_var($_POST['stock_quantity'] ?? 0, FILTER_VALIDATE_INT);
    $trackInventory = isset($_POST['track_inventory']) ? 1 : 0;
    $allowBackorder = isset($_POST['allow_backorder']) ? 1 : 0;
    $status = (string) ($_POST['status'] ?? 'draft');
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $sortOrder = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);
    $seoTitle = trim((string) ($_POST['seo_title'] ?? ''));
    $seoDescription = trim((string) ($_POST['seo_description'] ?? ''));

    $product = array_merge($product, [
        'category_id' => $categoryId ?? '', 'sku' => $sku, 'name' => $name,
        'short_description' => $shortDescription, 'description' => $description,
        'price' => $priceInput, 'compare_at_price' => $compareInput,
        'stock_quantity' => $stockInput === false ? 0 : $stockInput,
        'track_inventory' => $trackInventory, 'allow_backorder' => $allowBackorder,
        'status' => $status, 'is_featured' => $isFeatured,
        'sort_order' => $sortOrder === false ? 0 : $sortOrder,
        'seo_title' => $seoTitle, 'seo_description' => $seoDescription,
    ]);

    $validPrice = preg_match('/^\d{1,10}(\.\d{1,2})?$/', $priceInput) === 1 && (float) $priceInput > 0;
    $validCompare = $compareInput === '' || (preg_match('/^\d{1,10}(\.\d{1,2})?$/', $compareInput) === 1 && (float) $compareInput > (float) $priceInput);

    if ($name === '' || mephysShopAdminLength($name) > 180) {
        $error = 'Enter a product name of no more than 180 characters.';
    } elseif (mephysShopAdminLength($shortDescription) > 500) {
        $error = 'The short description must not exceed 500 characters.';
    } elseif (!$validPrice) {
        $error = 'Enter a valid product price greater than zero.';
    } elseif (!$validCompare) {
        $error = 'The optional comparison price must be greater than the selling price.';
    } elseif ($stockInput === false || $stockInput < 0) {
        $error = 'Enter a valid stock quantity (zero or greater).';
    } elseif (!in_array($status, ['draft', 'published', 'archived'], true)) {
        $error = 'Select a valid publication status.';
    } elseif ($sku !== '' && mephysShopAdminLength($sku) > 80) {
        $error = 'SKU must not exceed 80 characters.';
    }

    if ($error === '' && $categoryId !== null) {
        $categoryCheck = $pdo->prepare('SELECT id FROM shop_categories WHERE id=:id AND is_active=1 LIMIT 1');
        $categoryCheck->execute([':id' => $categoryId]);
        if (!$categoryCheck->fetchColumn()) {
            $error = 'Select an active product category.';
        }
    }

    $files = $_FILES['product_images'] ?? null;
    $newImageCount = 0;
    if (is_array($files) && isset($files['error']) && is_array($files['error'])) {
        foreach ($files['error'] as $fileError) {
            if ((int) $fileError !== UPLOAD_ERR_NO_FILE) {
                $newImageCount++;
            }
        }
    }
    if ($newImageCount > 8) {
        $error = 'You can upload a maximum of eight images at a time.';
    }

    $existingImageCount = 0;
    if ($productId) {
        $imageCountStmt = $pdo->prepare('SELECT COUNT(*) FROM shop_product_images WHERE product_id=:id');
        $imageCountStmt->execute([':id' => $productId]);
        $existingImageCount = (int) $imageCountStmt->fetchColumn();
    }
    if ($error === '' && $status === 'published' && $existingImageCount === 0 && $newImageCount === 0) {
        $error = 'Upload at least one product image before publishing this product. You can save it as a draft without an image.';
    }

    if ($error === '') {
        $pdo->beginTransaction();
        try {
            $slug = mephysShopAdminSlug($pdo, 'shop_products', $name, $productId);
            $staffId = (int) $shopAdminStaff['id'];

            if ($productId) {
                $save = $pdo->prepare('UPDATE shop_products SET category_id=:category_id,sku=:sku,name=:name,slug=:slug,short_description=:short_description,description=:description,price=:price,compare_at_price=:compare_at_price,stock_quantity=:stock_quantity,track_inventory=:track_inventory,allow_backorder=:allow_backorder,status=:status,is_featured=:is_featured,sort_order=:sort_order,seo_title=:seo_title,seo_description=:seo_description,updated_by_staff_id=:staff_id WHERE id=:id');
                $save->execute([
                    ':category_id' => $categoryId, ':sku' => $sku !== '' ? $sku : null,
                    ':name' => $name, ':slug' => $slug,
                    ':short_description' => $shortDescription !== '' ? $shortDescription : null,
                    ':description' => $description !== '' ? $description : null,
                    ':price' => $priceInput, ':compare_at_price' => $compareInput !== '' ? $compareInput : null,
                    ':stock_quantity' => $stockInput, ':track_inventory' => $trackInventory,
                    ':allow_backorder' => $allowBackorder, ':status' => $status,
                    ':is_featured' => $isFeatured, ':sort_order' => $sortOrder === false ? 0 : $sortOrder,
                    ':seo_title' => $seoTitle !== '' ? $seoTitle : null,
                    ':seo_description' => $seoDescription !== '' ? $seoDescription : null,
                    ':staff_id' => $staffId, ':id' => $productId,
                ]);
                $savedProductId = $productId;
                $auditAction = 'shop_product_updated';
            } else {
                $save = $pdo->prepare('INSERT INTO shop_products (category_id,sku,name,slug,short_description,description,price,compare_at_price,stock_quantity,track_inventory,allow_backorder,status,is_featured,sort_order,seo_title,seo_description,created_by_staff_id,updated_by_staff_id) VALUES (:category_id,:sku,:name,:slug,:short_description,:description,:price,:compare_at_price,:stock_quantity,:track_inventory,:allow_backorder,:status,:is_featured,:sort_order,:seo_title,:seo_description,:created_by_staff_id,:updated_by_staff_id)');
                $save->execute([
                    ':category_id' => $categoryId, ':sku' => $sku !== '' ? $sku : null,
                    ':name' => $name, ':slug' => $slug,
                    ':short_description' => $shortDescription !== '' ? $shortDescription : null,
                    ':description' => $description !== '' ? $description : null,
                    ':price' => $priceInput, ':compare_at_price' => $compareInput !== '' ? $compareInput : null,
                    ':stock_quantity' => $stockInput, ':track_inventory' => $trackInventory,
                    ':allow_backorder' => $allowBackorder, ':status' => $status,
                    ':is_featured' => $isFeatured, ':sort_order' => $sortOrder === false ? 0 : $sortOrder,
                    ':seo_title' => $seoTitle !== '' ? $seoTitle : null,
                    ':seo_description' => $seoDescription !== '' ? $seoDescription : null,
                    ':created_by_staff_id' => $staffId, ':updated_by_staff_id' => $staffId,
                ]);
                $savedProductId = (int) $pdo->lastInsertId();
                $auditAction = 'shop_product_created';
            }

            // Keep an auditable stock movement whenever the stored quantity changes.
            if ($stockInput !== $originalStockQuantity) {
                $movementType = $productId ? 'adjustment' : 'opening';
                $movementReason = $productId
                    ? 'Manual quantity change from product management'
                    : 'Initial stock quantity recorded when product was created';
                $movement = $pdo->prepare('INSERT INTO shop_inventory_movements (product_id,movement_type,quantity_change,quantity_before,quantity_after,reason,changed_by_staff_id) VALUES (:product_id,:movement_type,:quantity_change,:quantity_before,:quantity_after,:reason,:staff_id)');
                $movement->execute([
                    ':product_id' => $savedProductId,
                    ':movement_type' => $movementType,
                    ':quantity_change' => $stockInput - $originalStockQuantity,
                    ':quantity_before' => $originalStockQuantity,
                    ':quantity_after' => $stockInput,
                    ':reason' => $movementReason,
                    ':staff_id' => $staffId,
                ]);
            }

            // Save optional product specifications.
            $submittedAttributes = $_POST['attributes'] ?? [];
            if (!is_array($submittedAttributes)) {
                $submittedAttributes = [];
            }
            foreach ($attributes as $attribute) {
                $attributeId = (int) $attribute['id'];
                $value = trim((string) ($submittedAttributes[$attributeId] ?? ''));
                if ($attribute['input_type'] === 'boolean' && $value !== '') {
                    if (!in_array($value, ['Yes', 'No'], true)) {
                        throw new RuntimeException('Invalid Yes/No value for ' . $attribute['name'] . '.');
                    }
                } elseif ($attribute['input_type'] === 'select' && $value !== '') {
                    $options = preg_split('/\R/', (string) $attribute['options_text']) ?: [];
                    $options = array_values(array_filter(array_map('trim', $options), static fn($v) => $v !== ''));
                    if (!in_array($value, $options, true)) {
                        throw new RuntimeException('Choose a valid option for ' . $attribute['name'] . '.');
                    }
                }
                if (mephysShopAdminLength($value) > 1000) {
                    throw new RuntimeException('A product specification is too long.');
                }
                if ($value === '') {
                    $deleteValue = $pdo->prepare('DELETE FROM shop_product_attribute_values WHERE product_id=:product_id AND attribute_id=:attribute_id');
                    $deleteValue->execute([':product_id' => $savedProductId, ':attribute_id' => $attributeId]);
                } else {
                    $upsertValue = $pdo->prepare('INSERT INTO shop_product_attribute_values (product_id,attribute_id,attribute_value) VALUES (:product_id,:attribute_id,:attribute_value) ON DUPLICATE KEY UPDATE attribute_value=VALUES(attribute_value)');
                    $upsertValue->execute([':product_id' => $savedProductId, ':attribute_id' => $attributeId, ':attribute_value' => $value]);
                }
            }

            // Validate and save uploaded product images.
            if ($newImageCount > 0) {
                $uploadDirectory = __DIR__ . '/../uploads/products';
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                    throw new RuntimeException('The product image folder could not be created.');
                }
                if (!is_writable($uploadDirectory)) {
                    throw new RuntimeException('The product image folder is not writable by PHP.');
                }

                $primaryCheck = $pdo->prepare('SELECT COUNT(*) FROM shop_product_images WHERE product_id=:id AND is_primary=1');
                $primaryCheck->execute([':id' => $savedProductId]);
                $hasPrimary = (int) $primaryCheck->fetchColumn() > 0;

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),-1)+1 FROM shop_product_images WHERE product_id=:id');
                $sortStmt->execute([':id' => $savedProductId]);
                $sortOrderImage = (int) $sortStmt->fetchColumn();

                foreach ($files['error'] as $index => $uploadError) {
                    if ((int) $uploadError === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    if ((int) $uploadError !== UPLOAD_ERR_OK) {
                        throw new RuntimeException('One of the images could not be uploaded. Please try again.');
                    }

                    $tmpName = (string) ($files['tmp_name'][$index] ?? '');
                    $size = (int) ($files['size'][$index] ?? 0);
                    if ($size < 1 || $size > 5 * 1024 * 1024 || !is_uploaded_file($tmpName)) {
                        throw new RuntimeException('Each image must be a valid upload no larger than 5 MB.');
                    }

                    $mime = $finfo->file($tmpName);
                    if (!isset($allowedMimes[$mime]) || @getimagesize($tmpName) === false) {
                        throw new RuntimeException('Only valid JPG, PNG and WEBP images are allowed.');
                    }

                    $filename = bin2hex(random_bytes(16)) . '.' . $allowedMimes[$mime];
                    $destination = $uploadDirectory . '/' . $filename;
                    if (!move_uploaded_file($tmpName, $destination)) {
                        throw new RuntimeException('Unable to save an uploaded product image.');
                    }
                    $relativePath = 'shop/uploads/products/' . $filename;
                    $uploadedPaths[] = $destination;

                    $isPrimary = $hasPrimary ? 0 : 1;
                    $insertImage = $pdo->prepare('INSERT INTO shop_product_images (product_id,image_path,alt_text,is_primary,sort_order) VALUES (:product_id,:image_path,:alt_text,:is_primary,:sort_order)');
                    $insertImage->execute([
                        ':product_id' => $savedProductId,
                        ':image_path' => $relativePath,
                        ':alt_text' => $name,
                        ':is_primary' => $isPrimary,
                        ':sort_order' => $sortOrderImage++,
                    ]);
                    if ($isPrimary === 1) {
                        $hasPrimary = true;
                    }
                }
            }

            $pdo->commit();
            mephysShopAdminAudit($auditAction, 'shop_product', $savedProductId);
            mephysShopAdminFlash('success', $productId ? 'Product updated successfully.' : 'Product created successfully.');
            header('Location: product-form.php?id=' . $savedProductId);
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($uploadedPaths as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            error_log('Mephys shop product save failed: ' . $e->getMessage());
            if ($e instanceof RuntimeException && !($e instanceof PDOException)) {
                $error = $e->getMessage();
            } elseif ($e instanceof PDOException && (string) $e->getCode() === '23000') {
                $error = 'That SKU is already in use. Enter a different SKU.';
            } else {
                $error = 'The product could not be saved. Please check the details and try again.';
            }
        }
    }
}

$productImages = [];
$attributeValues = [];
if ($productId) {
    $imagesStmt = $pdo->prepare('SELECT * FROM shop_product_images WHERE product_id=:id ORDER BY is_primary DESC,sort_order ASC,id ASC');
    $imagesStmt->execute([':id' => $productId]);
    $productImages = $imagesStmt->fetchAll(PDO::FETCH_ASSOC);

    $valuesStmt = $pdo->prepare('SELECT attribute_id,attribute_value FROM shop_product_attribute_values WHERE product_id=:id');
    $valuesStmt->execute([':id' => $productId]);
    foreach ($valuesStmt->fetchAll(PDO::FETCH_ASSOC) as $valueRow) {
        $attributeValues[(int) $valueRow['attribute_id']] = $valueRow['attribute_value'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? 'save') === 'save') {
    foreach ($attributes as $attribute) {
        $attributeValues[(int) $attribute['id']] = (string) ($_POST['attributes'][$attribute['id']] ?? '');
    }
}

mephysShopAdminLayoutStart($productId ? 'Edit product' : 'Add product', $shopAdminStaff);
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><h1 class="shop-title"><?= $productId ? 'Edit product' : 'Add a product' ?></h1><div class="shop-subtitle">Add the information customers need to understand and purchase this product.</div></div>
    <a class="btn btn-outline-secondary" href="products.php"><i class="bi bi-arrow-left me-1"></i>Back to products</a>
</div>

<?php if ($error !== ''): ?><div class="alert alert-danger"><?= mephysShopAdminEscape($error) ?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="row g-4">
    <?= mephysShopAdminCsrfField() ?>
    <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($product['id'] ?? 0) ?>">

    <div class="col-lg-8">
        <section class="shop-card mb-4">
            <div class="shop-card-head"><strong>Product information</strong></div>
            <div class="shop-card-body">
                <div class="mb-3"><label class="form-label" for="name">Product name <span class="required-mark">*</span></label><input class="form-control" id="name" name="name" maxlength="180" required value="<?= mephysShopAdminEscape($_POST['name'] ?? $product['name']) ?>" placeholder="Enter the product name"></div>
                <div class="mb-3"><label class="form-label" for="short_description">Short description</label><textarea class="form-control" id="short_description" name="short_description" rows="2" maxlength="500" placeholder="A concise summary for product cards and search results"><?= mephysShopAdminEscape($_POST['short_description'] ?? $product['short_description']) ?></textarea><div class="form-text">Up to 500 characters.</div></div>
                <div class="mb-0"><label class="form-label" for="description">Full description</label><textarea class="form-control" id="description" name="description" rows="7" placeholder="Describe the product, its purpose, use and important details"><?= mephysShopAdminEscape($_POST['description'] ?? $product['description']) ?></textarea></div>
            </div>
        </section>

        <section class="shop-card mb-4">
            <div class="shop-card-head"><strong>Product images</strong><span class="small text-secondary">JPG, PNG or WEBP · up to 5 MB each</span></div>
            <div class="shop-card-body">
                <?php if ($productImages): ?><div class="row g-3 mb-3">
                    <?php foreach ($productImages as $image): ?><div class="col-6 col-sm-4 col-md-3"><div class="border rounded-3 p-2 h-100"><img src="../../<?= mephysShopAdminEscape($image['image_path']) ?>" alt="<?= mephysShopAdminEscape($image['alt_text'] ?? '') ?>" class="w-100 rounded-2" style="height:115px;object-fit:cover"><div class="small mt-2 mb-2"><?= (int) $image['is_primary'] === 1 ? '<span class="badge text-bg-primary">Main image</span>' : '<span class="text-secondary">Gallery image</span>' ?></div><?php if ((int) $image['is_primary'] !== 1): ?><button type="submit" name="make_primary_image_id" value="<?= (int) $image['id'] ?>" formnovalidate class="btn btn-sm btn-outline-primary w-100 mb-2">Make main image</button><?php endif; ?><button type="submit" name="remove_image_id" value="<?= (int) $image['id'] ?>" formnovalidate class="btn btn-sm btn-outline-danger w-100" onclick="return confirm('Remove this image from the product?');">Remove</button></div></div><?php endforeach; ?>
                </div><?php endif; ?>
                <label class="form-label" for="product_images">Upload images</label><input class="form-control" type="file" id="product_images" name="product_images[]" accept="image/jpeg,image/png,image/webp" multiple><div class="form-text">You can select up to eight images per upload. The first image becomes the main image when the product has no main image yet.</div>
                <div id="image-preview" class="row g-2 mt-2"></div>
            </div>
        </section>

        <section class="shop-card mb-4">
            <div class="shop-card-head"><strong>Product specifications</strong><span class="small text-secondary">Optional fields configured by the team</span></div>
            <div class="shop-card-body">
                <?php if (!$attributes): ?><p class="mb-0 text-secondary small">No specifications have been configured yet. Add them under Specifications in the shop administration menu.</p><?php else: ?>
                    <div class="row g-3">
                    <?php foreach ($attributes as $attribute): $attributeId=(int)$attribute['id']; $attributeValue=(string)($attributeValues[$attributeId] ?? ''); ?>
                        <div class="col-md-6"><label class="form-label" for="attribute_<?= $attributeId ?>"><?= mephysShopAdminEscape($attribute['name']) ?></label>
                            <?php if ($attribute['input_type'] === 'select'): ?><select class="form-select" id="attribute_<?= $attributeId ?>" name="attributes[<?= $attributeId ?>]"><option value="">Not specified</option><?php $opts=preg_split('/\R/',(string)$attribute['options_text']) ?: []; foreach ($opts as $opt): $opt=trim($opt); if ($opt==='') continue; ?><option value="<?= mephysShopAdminEscape($opt) ?>" <?= $attributeValue === $opt ? 'selected' : '' ?>><?= mephysShopAdminEscape($opt) ?></option><?php endforeach; ?></select>
                            <?php elseif ($attribute['input_type'] === 'boolean'): ?><select class="form-select" id="attribute_<?= $attributeId ?>" name="attributes[<?= $attributeId ?>]"><option value="">Not specified</option><option value="Yes" <?= $attributeValue==='Yes'?'selected':'' ?>>Yes</option><option value="No" <?= $attributeValue==='No'?'selected':'' ?>>No</option></select>
                            <?php elseif ($attribute['input_type'] === 'number'): ?><input class="form-control" type="number" step="any" id="attribute_<?= $attributeId ?>" name="attributes[<?= $attributeId ?>]" value="<?= mephysShopAdminEscape($attributeValue) ?>">
                            <?php else: ?><input class="form-control" type="text" maxlength="1000" id="attribute_<?= $attributeId ?>" name="attributes[<?= $attributeId ?>]" value="<?= mephysShopAdminEscape($attributeValue) ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="shop-card">
            <div class="shop-card-head"><strong>Search engine information</strong><span class="small text-secondary">Optional</span></div>
            <div class="shop-card-body">
                <div class="mb-3"><label class="form-label" for="seo_title">SEO title</label><input class="form-control" id="seo_title" name="seo_title" maxlength="180" value="<?= mephysShopAdminEscape($_POST['seo_title'] ?? $product['seo_title']) ?>"></div>
                <div><label class="form-label" for="seo_description">SEO description</label><textarea class="form-control" id="seo_description" name="seo_description" rows="2" maxlength="320"><?= mephysShopAdminEscape($_POST['seo_description'] ?? $product['seo_description']) ?></textarea></div>
            </div>
        </section>
    </div>

    <div class="col-lg-4">
        <section class="shop-card mb-4">
            <div class="shop-card-head"><strong>Publication</strong></div>
            <div class="shop-card-body">
                <div class="mb-3"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="draft" <?= (string)($_POST['status'] ?? $product['status'])==='draft'?'selected':'' ?>>Draft – hidden from customers</option><option value="published" <?= (string)($_POST['status'] ?? $product['status'])==='published'?'selected':'' ?>>Published – visible in shop</option><option value="archived" <?= (string)($_POST['status'] ?? $product['status'])==='archived'?'selected':'' ?>>Archived – no longer for sale</option></select></div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1" <?= (string)($_POST ? (isset($_POST['is_featured'])?'1':'0') : $product['is_featured'])==='1'?'checked':'' ?>><label class="form-check-label" for="is_featured">Feature this product</label></div>
                <div class="mb-0"><label class="form-label" for="sort_order">Display order</label><input class="form-control" type="number" id="sort_order" name="sort_order" value="<?= mephysShopAdminEscape($_POST['sort_order'] ?? $product['sort_order']) ?>"></div>
            </div>
        </section>

        <section class="shop-card mb-4">
            <div class="shop-card-head"><strong>Pricing</strong></div>
            <div class="shop-card-body">
                <div class="mb-3"><label class="form-label" for="price">Selling price (KES) <span class="required-mark">*</span></label><input class="form-control" type="number" id="price" name="price" min="0.01" step="0.01" required value="<?= mephysShopAdminEscape($_POST['price'] ?? $product['price']) ?>" placeholder="0.00"></div>
                <div><label class="form-label" for="compare_at_price">Previous / comparison price (KES)</label><input class="form-control" type="number" id="compare_at_price" name="compare_at_price" min="0.01" step="0.01" value="<?= mephysShopAdminEscape($_POST['compare_at_price'] ?? $product['compare_at_price']) ?>" placeholder="Optional"><div class="form-text">Must be greater than the selling price.</div></div>
            </div>
        </section>

        <section class="shop-card mb-4">
            <div class="shop-card-head"><strong>Organisation</strong></div>
            <div class="shop-card-body">
                <div class="mb-3"><label class="form-label" for="category_id">Category</label><select class="form-select" id="category_id" name="category_id"><option value="">Uncategorised</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (string)($_POST['category_id'] ?? $product['category_id'])===(string)$category['id']?'selected':'' ?>><?= mephysShopAdminEscape($category['name']) ?></option><?php endforeach; ?></select><div class="form-text"><a href="categories.php">Manage categories</a></div></div>
                <div><label class="form-label" for="sku">SKU / product code</label><input class="form-control" id="sku" name="sku" maxlength="80" value="<?= mephysShopAdminEscape($_POST['sku'] ?? $product['sku']) ?>" placeholder="Optional unique code"></div>
            </div>
        </section>

        <section class="shop-card mb-4">
            <div class="shop-card-head"><strong>Inventory</strong></div>
            <div class="shop-card-body">
                <div class="mb-3"><label class="form-label" for="stock_quantity">Available quantity</label><input class="form-control" type="number" id="stock_quantity" name="stock_quantity" min="0" step="1" value="<?= mephysShopAdminEscape($_POST['stock_quantity'] ?? $product['stock_quantity']) ?>"></div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="track_inventory" name="track_inventory" value="1" <?= (string)($_POST ? (isset($_POST['track_inventory'])?'1':'0') : $product['track_inventory'])==='1'?'checked':'' ?>><label class="form-check-label" for="track_inventory">Track stock for this product</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" id="allow_backorder" name="allow_backorder" value="1" <?= (string)($_POST ? (isset($_POST['allow_backorder'])?'1':'0') : $product['allow_backorder'])==='1'?'checked':'' ?>><label class="form-check-label" for="allow_backorder">Allow orders when out of stock</label></div>
            </div>
        </section>

        <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-check2-circle me-1"></i><?= $productId ? 'Save product changes' : 'Create product' ?></button>
    </div>
</form>

<script>
(function(){
    const input=document.getElementById('product_images');
    const preview=document.getElementById('image-preview');
    if(!input||!preview)return;
    input.addEventListener('change',function(){
        preview.innerHTML='';
        Array.from(input.files||[]).slice(0,8).forEach(function(file){
            if(!file.type.startsWith('image/'))return;
            const url=URL.createObjectURL(file);
            const col=document.createElement('div');col.className='col-4 col-md-3';
            const img=document.createElement('img');img.src=url;img.alt='Selected product image';img.className='w-100 rounded-2 border';img.style.height='90px';img.style.objectFit='cover';
            col.appendChild(img);preview.appendChild(col);
        });
    });
})();
</script>

<?php mephysShopAdminLayoutEnd(); ?>