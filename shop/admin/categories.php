<?php

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

global $pdo;
$error = '';
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$editing = null;
if ($editId) {
    $find = $pdo->prepare('SELECT * FROM shop_categories WHERE id = :id LIMIT 1');
    $find->execute([':id' => $editId]);
    $editing = $find->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$editing) {
        $editId = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mephysShopAdminVerifyCsrf();
    $action = (string) ($_POST['action'] ?? 'save');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;

    if ($action === 'toggle') {
        if (!$id) {
            mephysShopAdminFlash('danger', 'Invalid category selected.');
        } else {
            $toggle = $pdo->prepare('UPDATE shop_categories SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id');
            $toggle->execute([':id' => $id]);
            mephysShopAdminAudit('shop_category_status_changed', 'shop_category', $id);
            mephysShopAdminFlash('success', 'Category status updated.');
        }
        header('Location: categories.php');
        exit;
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $parentId = filter_var($_POST['parent_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    $sortOrder = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '' || mephysShopAdminLength($name) > 120) {
        $error = 'Enter a category name of no more than 120 characters.';
    } elseif ($parentId !== null && $parentId === $id) {
        $error = 'A category cannot be its own parent.';
    } else {
        if ($parentId !== null) {
            $parentCheck = $pdo->prepare('SELECT id,parent_id FROM shop_categories WHERE id = :id LIMIT 1');
            $parentCheck->execute([':id' => $parentId]);
            $ancestor = $parentCheck->fetch(PDO::FETCH_ASSOC);
            if (!$ancestor) {
                $error = 'The selected parent category does not exist.';
            } elseif ($id !== null) {
                // Prevent assigning a category beneath one of its own descendants.
                $visited = [];
                while ($ancestor) {
                    $ancestorId = (int) $ancestor['id'];
                    if ($ancestorId === $id || isset($visited[$ancestorId])) {
                        $error = 'This parent would create a circular category structure.';
                        break;
                    }
                    $visited[$ancestorId] = true;
                    $nextParentId = (int) ($ancestor['parent_id'] ?? 0);
                    if ($nextParentId < 1) {
                        break;
                    }
                    $parentCheck->execute([':id' => $nextParentId]);
                    $ancestor = $parentCheck->fetch(PDO::FETCH_ASSOC) ?: null;
                }
            }
        }

        if ($error === '') {
            $slug = mephysShopAdminSlug($pdo, 'shop_categories', $name, $id);
            try {
                if ($id) {
                    $save = $pdo->prepare('UPDATE shop_categories SET parent_id=:parent_id, name=:name, slug=:slug, description=:description, sort_order=:sort_order, is_active=:is_active WHERE id=:id');
                    $save->execute([
                        ':parent_id' => $parentId, ':name' => $name, ':slug' => $slug,
                        ':description' => $description !== '' ? $description : null,
                        ':sort_order' => $sortOrder === false ? 0 : $sortOrder,
                        ':is_active' => $isActive, ':id' => $id,
                    ]);
                    mephysShopAdminAudit('shop_category_updated', 'shop_category', $id);
                    mephysShopAdminFlash('success', 'Category updated successfully.');
                } else {
                    $save = $pdo->prepare('INSERT INTO shop_categories (parent_id,name,slug,description,sort_order,is_active) VALUES (:parent_id,:name,:slug,:description,:sort_order,:is_active)');
                    $save->execute([
                        ':parent_id' => $parentId, ':name' => $name, ':slug' => $slug,
                        ':description' => $description !== '' ? $description : null,
                        ':sort_order' => $sortOrder === false ? 0 : $sortOrder,
                        ':is_active' => $isActive,
                    ]);
                    $newId = (int) $pdo->lastInsertId();
                    mephysShopAdminAudit('shop_category_created', 'shop_category', $newId);
                    mephysShopAdminFlash('success', 'Category created successfully.');
                }
                header('Location: categories.php');
                exit;
            } catch (PDOException $e) {
                error_log('Mephys shop category save failed: ' . $e->getMessage());
                $error = 'The category could not be saved. Please check that its details are valid.';
            }
        }
    }
}

$categories = $pdo->query("SELECT c.*, p.name AS parent_name, (SELECT COUNT(*) FROM shop_products pr WHERE pr.category_id=c.id) AS product_count FROM shop_categories c LEFT JOIN shop_categories p ON p.id=c.parent_id ORDER BY c.sort_order ASC,c.name ASC")->fetchAll(PDO::FETCH_ASSOC);
$parents = $pdo->query('SELECT id,name FROM shop_categories ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);

mephysShopAdminLayoutStart('Product categories', $shopAdminStaff);
?>

<div class="mb-4"><h1 class="shop-title">Product categories</h1><div class="shop-subtitle">Organise products into clear sections customers can browse on the website.</div></div>

<div class="row g-4">
    <div class="col-lg-4">
        <section class="shop-card">
            <div class="shop-card-head"><strong><?= $editId ? 'Edit category' : 'Create category' ?></strong><?php if ($editId): ?><a class="small" href="categories.php">Cancel edit</a><?php endif; ?></div>
            <div class="shop-card-body">
                <?php if ($error !== ''): ?><div class="alert alert-danger"><?= mephysShopAdminEscape($error) ?></div><?php endif; ?>
                <form method="POST">
                    <?= mephysShopAdminCsrfField() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? $editId ?? 0) ?>">
                    <div class="mb-3"><label class="form-label" for="name">Category name <span class="required-mark">*</span></label><input class="form-control" id="name" name="name" maxlength="120" required value="<?= mephysShopAdminEscape($_POST['name'] ?? $editing['name'] ?? '') ?>" placeholder="e.g. Mobility Aids"></div>
                    <div class="mb-3"><label class="form-label" for="parent_id">Parent category</label><select class="form-select" id="parent_id" name="parent_id"><option value="">Top-level category</option><?php foreach ($parents as $parent): if ((int) $parent['id'] === (int) ($editing['id'] ?? 0)) continue; ?><option value="<?= (int) $parent['id'] ?>" <?= (string) ($_POST['parent_id'] ?? $editing['parent_id'] ?? '') === (string) $parent['id'] ? 'selected' : '' ?>><?= mephysShopAdminEscape($parent['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="3" placeholder="Optional explanation of this category"><?= mephysShopAdminEscape($_POST['description'] ?? $editing['description'] ?? '') ?></textarea></div>
                    <div class="mb-3"><label class="form-label" for="sort_order">Display order</label><input class="form-control" id="sort_order" type="number" name="sort_order" value="<?= mephysShopAdminEscape($_POST['sort_order'] ?? $editing['sort_order'] ?? 0) ?>"></div>
                    <div class="form-check mb-4"><input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (string) ($_POST ? (isset($_POST['is_active']) ? '1' : '0') : ($editing['is_active'] ?? 1)) === '1' ? 'checked' : '' ?>><label class="form-check-label" for="is_active">Active category</label></div>
                    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-check2-circle me-1"></i><?= $editId ? 'Save changes' : 'Create category' ?></button>
                </form>
            </div>
        </section>
    </div>

    <div class="col-lg-8">
        <section class="shop-card">
            <div class="shop-card-head"><strong>Categories</strong><span class="small text-secondary"><?= count($categories) ?> total</span></div>
            <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Name</th><th>Parent</th><th>Products</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            <?php if (!$categories): ?><tr><td colspan="5" class="text-center py-5 text-secondary">No categories yet.</td></tr><?php else: foreach ($categories as $category): ?>
                <tr><td><div class="fw-semibold"><?= mephysShopAdminEscape($category['name']) ?></div><div class="small text-secondary"><?= mephysShopAdminEscape($category['slug']) ?></div></td><td><?= mephysShopAdminEscape($category['parent_name'] ?? '—') ?></td><td><?= (int) $category['product_count'] ?></td><td><span class="badge <?= (int) $category['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int) $category['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td><td><div class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="/shop/admin/categories.php?edit=<?= (int) $category['id'] ?>">Edit</a><form method="POST"><?= mephysShopAdminCsrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $category['id'] ?>"><button class="btn btn-sm btn-outline-secondary" type="submit"><?= (int) $category['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button></form></div></td></tr>
            <?php endforeach; endif; ?>
            </tbody></table></div>
        </section>
    </div>
</div>

<?php mephysShopAdminLayoutEnd(); ?>
