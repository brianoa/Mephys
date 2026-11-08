<?php

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

global $pdo;
$error = '';
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$editing = null;
if ($editId) {
    $find = $pdo->prepare('SELECT * FROM shop_product_attributes WHERE id = :id LIMIT 1');
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
        if ($id) {
            $toggle = $pdo->prepare('UPDATE shop_product_attributes SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id');
            $toggle->execute([':id' => $id]);
            mephysShopAdminAudit('shop_product_attribute_status_changed', 'shop_product_attribute', $id);
            mephysShopAdminFlash('success', 'Specification status updated.');
        }
        header('Location: attributes.php');
        exit;
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $inputType = (string) ($_POST['input_type'] ?? 'text');
    $optionsText = trim((string) ($_POST['options_text'] ?? ''));
    $isFilterable = isset($_POST['is_filterable']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $sortOrder = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);

    if ($name === '' || mephysShopAdminLength($name) > 120) {
        $error = 'Enter a specification name of no more than 120 characters.';
    } elseif (!in_array($inputType, ['text', 'number', 'select', 'boolean'], true)) {
        $error = 'Select a valid input type.';
    } elseif ($inputType === 'select' && $optionsText === '') {
        $error = 'Enter at least one option for a dropdown specification.';
    } else {
        $slug = mephysShopAdminSlug($pdo, 'shop_product_attributes', $name, $id);
        try {
            if ($id) {
                $save = $pdo->prepare('UPDATE shop_product_attributes SET name=:name,slug=:slug,input_type=:input_type,options_text=:options_text,is_filterable=:is_filterable,is_active=:is_active,sort_order=:sort_order WHERE id=:id');
                $save->execute([
                    ':name' => $name, ':slug' => $slug, ':input_type' => $inputType,
                    ':options_text' => $inputType === 'select' ? $optionsText : null,
                    ':is_filterable' => $isFilterable, ':is_active' => $isActive,
                    ':sort_order' => $sortOrder === false ? 0 : $sortOrder, ':id' => $id,
                ]);
                mephysShopAdminAudit('shop_product_attribute_updated', 'shop_product_attribute', $id);
                mephysShopAdminFlash('success', 'Specification updated. Existing product values have been retained.');
            } else {
                $save = $pdo->prepare('INSERT INTO shop_product_attributes (name,slug,input_type,options_text,is_filterable,is_active,sort_order) VALUES (:name,:slug,:input_type,:options_text,:is_filterable,:is_active,:sort_order)');
                $save->execute([
                    ':name' => $name, ':slug' => $slug, ':input_type' => $inputType,
                    ':options_text' => $inputType === 'select' ? $optionsText : null,
                    ':is_filterable' => $isFilterable, ':is_active' => $isActive,
                    ':sort_order' => $sortOrder === false ? 0 : $sortOrder,
                ]);
                $newId = (int) $pdo->lastInsertId();
                mephysShopAdminAudit('shop_product_attribute_created', 'shop_product_attribute', $newId);
                mephysShopAdminFlash('success', 'Specification created. It will be available on the product form.');
            }
            header('Location: attributes.php');
            exit;
        } catch (PDOException $e) {
            error_log('Mephys shop product attribute save failed: ' . $e->getMessage());
            $error = 'The specification could not be saved. Check the details and try again.';
        }
    }
}

$attributes = $pdo->query('SELECT a.*, (SELECT COUNT(*) FROM shop_product_attribute_values v WHERE v.attribute_id=a.id) AS values_count FROM shop_product_attributes a ORDER BY a.sort_order ASC,a.name ASC')->fetchAll(PDO::FETCH_ASSOC);
mephysShopAdminLayoutStart('Product specifications', $shopAdminStaff);
?>

<div class="mb-4"><h1 class="shop-title">Product specifications</h1><div class="shop-subtitle">Define optional product details such as size, material, colour, model, capacity or other relevant information.</div></div>

<div class="row g-4">
    <div class="col-lg-4">
        <section class="shop-card">
            <div class="shop-card-head"><strong><?= $editId ? 'Edit specification' : 'Add specification' ?></strong><?php if ($editId): ?><a class="small" href="attributes.php">Cancel</a><?php endif; ?></div>
            <div class="shop-card-body">
                <?php if ($error !== ''): ?><div class="alert alert-danger"><?= mephysShopAdminEscape($error) ?></div><?php endif; ?>
                <form method="POST">
                    <?= mephysShopAdminCsrfField() ?>
                    <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? $editId ?? 0) ?>">
                    <div class="mb-3"><label class="form-label" for="name">Specification name <span class="required-mark">*</span></label><input class="form-control" id="name" name="name" maxlength="120" required value="<?= mephysShopAdminEscape($_POST['name'] ?? $editing['name'] ?? '') ?>" placeholder="e.g. Material"></div>
                    <div class="mb-3"><label class="form-label" for="input_type">Input type</label><select class="form-select" id="input_type" name="input_type"><?php foreach (['text'=>'Text','number'=>'Number','select'=>'Dropdown selection','boolean'=>'Yes / No'] as $key=>$label): ?><option value="<?= $key ?>" <?= (string) ($_POST['input_type'] ?? $editing['input_type'] ?? 'text') === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3" id="options-wrapper"><label class="form-label" for="options_text">Dropdown options</label><textarea class="form-control" id="options_text" name="options_text" rows="4" placeholder="One option per line"><?= mephysShopAdminEscape($_POST['options_text'] ?? $editing['options_text'] ?? '') ?></textarea><div class="form-text">Only required when the input type is Dropdown selection.</div></div>
                    <div class="mb-3"><label class="form-label" for="sort_order">Display order</label><input class="form-control" type="number" id="sort_order" name="sort_order" value="<?= mephysShopAdminEscape($_POST['sort_order'] ?? $editing['sort_order'] ?? 0) ?>"></div>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="is_filterable" name="is_filterable" value="1" <?= (string) ($_POST ? (isset($_POST['is_filterable']) ? '1' : '0') : ($editing['is_filterable'] ?? 0)) === '1' ? 'checked' : '' ?>><label class="form-check-label" for="is_filterable">Allow customers to filter by this specification</label></div>
                    <div class="form-check mb-4"><input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (string) ($_POST ? (isset($_POST['is_active']) ? '1' : '0') : ($editing['is_active'] ?? 1)) === '1' ? 'checked' : '' ?>><label class="form-check-label" for="is_active">Active and available on product forms</label></div>
                    <button class="btn btn-primary w-100" type="submit">Save specification</button>
                </form>
            </div>
        </section>
    </div>

    <div class="col-lg-8">
        <section class="shop-card">
            <div class="shop-card-head"><strong>Available specifications</strong><span class="small text-secondary"><?= count($attributes) ?> total</span></div>
            <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Name</th><th>Input type</th><th>Product values</th><th>Customer filter</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            <?php if (!$attributes): ?><tr><td colspan="6" class="text-center text-secondary py-5">No specifications defined yet.</td></tr><?php else: foreach ($attributes as $attribute): ?>
                <tr><td><div class="fw-semibold"><?= mephysShopAdminEscape($attribute['name']) ?></div><div class="small text-secondary"><?= mephysShopAdminEscape($attribute['slug']) ?></div></td><td><?= mephysShopAdminEscape(ucfirst($attribute['input_type'])) ?></td><td><?= (int) $attribute['values_count'] ?></td><td><?= (int) $attribute['is_filterable'] === 1 ? 'Yes' : 'No' ?></td><td><span class="badge <?= (int) $attribute['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int) $attribute['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td><td><div class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="attributes.php?edit=<?= (int) $attribute['id'] ?>">Edit</a><form method="POST"><?= mephysShopAdminCsrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $attribute['id'] ?>"><button class="btn btn-sm btn-outline-secondary" type="submit"><?= (int) $attribute['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button></form></div></td></tr>
            <?php endforeach; endif; ?>
            </tbody></table></div>
        </section>
    </div>
</div>

<script>
(function(){
    const type = document.getElementById('input_type');
    const wrapper = document.getElementById('options-wrapper');
    function sync(){ wrapper.style.display = type.value === 'select' ? '' : 'none'; }
    type.addEventListener('change', sync);
    sync();
})();
</script>

<?php mephysShopAdminLayoutEnd(); ?>
