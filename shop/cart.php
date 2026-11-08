<?php
declare(strict_types=1);
require_once __DIR__ . '/_public-bootstrap.php';
global $pdo;
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mephysShopPublicVerifyCsrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'add') {
        $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $qty = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>99]]);
        if (!$id || !$qty) { $error = 'Choose a valid product quantity.'; }
        else {
            $st=$pdo->prepare("SELECT id,stock_quantity,track_inventory,allow_backorder FROM shop_products WHERE id=:id AND status='published' LIMIT 1");
            $st->execute([':id'=>$id]); $product=$st->fetch(PDO::FETCH_ASSOC);
            if (!$product) $error='That product is no longer available.';
            elseif ((int)$product['track_inventory']===1 && (int)$product['allow_backorder']!==1 && ((int)($_SESSION['mephys_shop_cart'][$id]??0)+$qty) > (int)$product['stock_quantity']) $error='The requested total quantity is not currently in stock.';
            elseif (!mephysShopCustomer()) {
                // Save the intended item and show the account prompt on the shop page.
                $_SESSION['mephys_shop_pending_add'] = ['product_id' => (int)$id, 'quantity' => (int)$qty];
                header('Location: index.php?signup=1');
                exit;
            } else {
                $_SESSION['mephys_shop_cart'][$id]=min(99,(int)($_SESSION['mephys_shop_cart'][$id]??0)+$qty);
                $message='Product added to your cart.';
            }
        }
    } elseif ($action === 'update') {
        foreach (($_POST['quantities'] ?? []) as $id=>$qty) {
            $id=filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $qty=filter_var($qty,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>99]]);
            if (!$id || $qty===false) continue;
            if ($qty===0) unset($_SESSION['mephys_shop_cart'][$id]); else $_SESSION['mephys_shop_cart'][$id]=$qty;
        }
        $message='Your cart has been updated.';
    } elseif ($action === 'remove') {
        $id=filter_var($_POST['product_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if($id) unset($_SESSION['mephys_shop_cart'][$id]);
        $message='Product removed from your cart.';
    }

    // Keep the signed-in customer's database cart in sync with the session cart.
    // Guest carts remain in the session until the customer signs in.
    if ($error === '' && function_exists('mephysShopSaveCustomerCart')) {
        mephysShopSaveCustomerCart();
    }
}
$items=[]; $total=0;
$cart=$_SESSION['mephys_shop_cart'];
if($cart){
    $ids=array_values(array_filter(array_map('intval',array_keys($cart))));
    if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));$st=$pdo->prepare("SELECT p.id,p.name,p.slug,p.price,p.stock_quantity,p.track_inventory,p.allow_backorder,COALESCE(i.image_path,'') image_path FROM shop_products p LEFT JOIN shop_product_images i ON i.product_id=p.id AND i.is_primary=1 WHERE p.status='published' AND p.id IN ($marks)");$st->execute($ids);$rows=$st->fetchAll(PDO::FETCH_ASSOC);foreach($rows as $row){$qty=(int)$cart[$row['id']];$row['quantity']=$qty;$row['line_total']=(float)$row['price']*$qty;$items[]=$row;$total+=$row['line_total'];}}
}
$shopPageTitle='Your cart';require __DIR__.'/_public-header.php';
?>
<section class="py-5"><div class="container"><div class="shop-eyebrow mb-2">Your selection</div><h1 class="shop-heading mb-4">Shopping cart</h1>
<?php if($message):?><div class="alert alert-success"><?=mephysShopPublicEscape($message)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=mephysShopPublicEscape($error)?></div><?php endif;?>
<?php if(!$items):?><div class="border rounded-4 p-5 text-center"><i class="fa-solid fa-cart-shopping fa-2x text-primary mb-3"></i><h2 class="h4">Your cart is empty</h2><p class="shop-muted">Browse the Mephys shop and add products to get started.</p><a class="btn btn-primary" href="index.php">Browse products</a></div>
<?php else:?>
<form method="POST" class="row g-4"><?=mephysShopPublicCsrfField()?><input type="hidden" name="action" value="update"><div class="col-lg-8"><div class="border rounded-4 overflow-hidden"><div class="table-responsive"><table class="table align-middle mb-0"><thead class="table-light"><tr><th>Product</th><th>Price</th><th style="width:120px">Quantity</th><th>Total</th><th></th></tr></thead><tbody>
<?php foreach($items as $item):?><tr><td><div class="d-flex align-items-center gap-3"><img src="<?=mephysShopPublicEscape(mephysShopPublicImageUrl($item['image_path']))?>" alt="" style="width:64px;height:64px;object-fit:contain;background:#f7f9fb;border-radius:8px"><a class="fw-semibold text-decoration-none" href="product.php?slug=<?=rawurlencode($item['slug'])?>"><?=mephysShopPublicEscape($item['name'])?></a></div></td><td><?=mephysShopPublicMoney($item['price'])?></td><td><input class="form-control" type="number" min="0" max="99" name="quantities[<?= (int)$item['id']?>]" value="<?= (int)$item['quantity']?>"></td><td class="fw-bold"><?=mephysShopPublicMoney($item['line_total'])?></td><td><button class="btn btn-sm btn-outline-danger" type="submit" formaction="cart.php" formmethod="POST" name="remove_product_id" value="<?= (int)$item['id']?>" onclick="this.form.action='cart.php';this.form.querySelector('[name=action]').value='remove';this.form.querySelector('[name=product_id]').value=this.value;" aria-label="Remove item"><i class="fa-solid fa-trash"></i></button></td></tr><?php endforeach;?>
</tbody></table></div></div><input type="hidden" name="product_id" value=""><div class="d-flex justify-content-between mt-3"><a href="index.php" class="text-decoration-none">Continue shopping</a><button class="btn btn-outline-primary" type="submit">Update cart</button></div></div>
<div class="col-lg-4"><div class="border rounded-4 p-4"><h2 class="h5 fw-bold">Order summary</h2><div class="d-flex justify-content-between my-3"><span class="shop-muted">Subtotal</span><strong><?=mephysShopPublicMoney($total)?></strong></div><div class="small shop-muted mb-3">Delivery charges, if applicable, will be confirmed by Mephys.</div><div class="d-flex justify-content-between border-top pt-3 mb-3"><strong>Estimated total</strong><strong class="text-primary fs-5"><?=mephysShopPublicMoney($total)?></strong></div><a href="checkout.php" class="btn btn-primary btn-lg w-100">Proceed to checkout</a></div></div></form>
<?php endif;?></div></section>
<?php require __DIR__.'/_public-footer.php';?>
