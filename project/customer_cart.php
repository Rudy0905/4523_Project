<?php
session_start();
require_once 'db_connect.php';
// ...(保留结账功能的PHP原代码)...
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cart - Premium Living</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f9f9f9; margin: 0; }
        .modern-header { background: #fff; padding: 5px 40px; display: flex; justify-content: space-between; border-bottom: 1px solid #eaeaea; }
        .container { max-width: 1000px; margin: 40px auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .cart-item { display: flex; align-items: center; border-bottom: 1px solid #eee; padding: 20px 0; }
        .cart-item img { width: 100px; height: 100px; object-fit: contain; background: #f4f6f7; border-radius: 8px; margin-right: 20px; }
        .cart-details { flex: 1; }
        .cart-details h3 { margin: 0 0 5px 0; }
        .badge { display: inline-block; padding: 4px 10px; background: #eee; border-radius: 20px; font-size: 12px; font-weight: bold; margin-right: 10px; }
        .price { font-size: 20px; font-weight: bold; color: #e74c3c; }
        .btn-checkout { background: #111; color: #fff; padding: 15px 30px; font-size: 18px; border: none; border-radius: 30px; cursor: pointer; float: right; margin-top: 20px; }
    </style>
</head>
<body>
    <!-- (此处放相同的 modern-header 导航栏) -->
    <div class="container">
        <h1 style="border-bottom: 2px solid #111; padding-bottom: 10px;">Your Shopping Cart</h1>
        
        <?php if(empty($_SESSION['cart'])): ?>
            <p style="text-align:center; padding: 50px; color: #999;">Cart is empty.</p>
        <?php else: ?>
            <?php foreach($_SESSION['cart'] as $key => $item): ?>
                <div class="cart-item">
                    <img src="<?= $item['base_name'] ?>.png">
                    <div class="cart-details">
                        <h3><?= $item['name'] ?></h3>
                        <span class="badge"><?= $item['size'] ?></span>
                        <span class="badge"><?= $item['color'] ?></span>
                        <p style="color:#7f8c8d; font-size:13px;">Remarks: <?= $item['remarks'] ?: 'None' ?></p>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size: 14px; color: #7f8c8d;">Qty: <?= $item['qty'] ?></div>
                        <div class="price">HKD <?= number_format($item['price'] * $item['qty'], 2) ?></div>
                        <a href="?delete_key=<?= $key ?>" style="color:#e74c3c; text-decoration:none; font-size:12px;">Remove</a>
                    </div>
                </div>
            <?php endforeach; ?>
            <div style="overflow: hidden;">
                <form method="POST">
                    <button type="submit" name="checkout" class="btn-checkout">Confirm Checkout</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>