<?php
// 开启 Session 记录购物车数据
session_start();
require_once 'db_connect.php'; 

// 权限拦截
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: index.php"); exit;
}

$customer_id = $_SESSION['user_id'] ?? 1; 
$user_name = $_SESSION['user_name'] ?? 'User';

// 🌟 获取用户当前的默认收货地址
$stmtUser = $pdo->prepare("SELECT caddr FROM Customers WHERE cid = ?");
$stmtUser->execute([$customer_id]);
$user_address = $stmtUser->fetchColumn() ?: 'Default Profile Address';

$cart_items = [];
$total_amount = 0.00;
$total_cart_count = 0;
$success_msg = "";
$error_msg = "";

if (isset($_GET['delete_key'])) {
    $target_key = $_GET['delete_key'];
    if (isset($_SESSION['cart'][$target_key])) {
        unset($_SESSION['cart'][$target_key]); 
        $success_msg = "Item successfully removed from cart.";
    }
    header("Location: customer_cart.php"); exit;
}

if (isset($_GET['clear']) && $_GET['clear'] == 1) {
    $_SESSION['cart'] = [];
    header("Location: customer_cart.php"); exit;
}

// 结账 + 原料级库存联动
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    if (!empty($_SESSION['cart'])) {
        try {
            $pdo->beginTransaction();
            $materials_to_deduct = [];
            $calc_total = 0.00;

            foreach ($_SESSION['cart'] as $item) {
                $fid = intval($item['fid'] ?? 0);
                $qty = intval($item['qty'] ?? 1);
                if ($fid <= 0) continue;

                $stmtMat = $pdo->prepare("SELECT mid, pmqty FROM FurnitureMaterials WHERE fid = ?");
                $stmtMat->execute([$fid]);
                $recipe = $stmtMat->fetchAll(PDO::FETCH_ASSOC);

                foreach ($recipe as $mat) {
                    $mid = $mat['mid'];
                    $needed = $mat['pmqty'] * $qty;
                    if (isset($materials_to_deduct[$mid])) {
                        $materials_to_deduct[$mid] += $needed;
                    } else {
                        $materials_to_deduct[$mid] = $needed;
                    }
                }
                
                $stmtPrice = $pdo->prepare("SELECT fprice FROM Furnitures WHERE fid = ?");
                $stmtPrice->execute([$fid]);
                $fprice = $stmtPrice->fetchColumn();
                $calc_total += ($fprice ? $fprice : ($item['price'] ?? 0)) * $qty;
            }

            // 优先接单写记录
            $sqlOrder = "INSERT INTO Orders (ototalamount, cid, odate, odeliverydate, odeliveraddress, ostatus) 
                         VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), ?, 1)";
            $stmtOrder = $pdo->prepare($sqlOrder);
            $stmtOrder->execute([$calc_total, $customer_id, $user_address]);
            $new_oid = $pdo->lastInsertId();

            $stmtInsertDetail = $pdo->prepare("INSERT INTO OrderFurnitures (oid, fid, oqty) VALUES (?, ?, ?)");
            foreach ($_SESSION['cart'] as $item) {
                $fid = intval($item['fid'] ?? 0);
                $qty = intval($item['qty'] ?? 1);
                if ($fid > 0) $stmtInsertDetail->execute([$new_oid, $fid, $qty]);
            }

            // 扣除材料库存 (允许出现负数，由Staff端红字跟进采购)
            $stmtDeductStock = $pdo->prepare("UPDATE Materials SET mqty = mqty - ? WHERE mid = ?");
            foreach ($materials_to_deduct as $mid => $qty_to_sub) {
                $stmtDeductStock->execute([$qty_to_sub, $mid]);
            }

            $pdo->commit();
            $_SESSION['cart'] = [];
            header("Location: customer_view_orders.php?order_success=" . $new_oid);
            exit;
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error_msg = "🚨 Checkout Failed: " . $e->getMessage();
        }
    }
}

// ==========================================
// 🌟 核心修复区：纯净版智能图片解析逻辑
// ==========================================
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $key => $item) {
        $db_name = ""; $db_price = 0.00; $db_fimage = "";
        
        // 1. 从数据库拉取最新真实数据
        if (isset($item['fid'])) {
            $stmtFurniture = $pdo->prepare("SELECT fname, fprice, fimage FROM Furnitures WHERE fid = ?");
            $stmtFurniture->execute([$item['fid']]);
            $furn = $stmtFurniture->fetch();
            if ($furn) { 
                $db_name = $furn['fname']; 
                $db_price = $furn['fprice'];
                $db_fimage = $furn['fimage'];
            }
        }
        
        $final_name = !empty($db_name) ? $db_name : ($item['name'] ?? 'Furniture Item');
        $final_price = ($db_price > 0) ? $db_price : ($item['price'] ?? 0.00);
        $subtotal = $final_price * $item['qty'];
        $total_amount += $subtotal;
        $total_cart_count += $item['qty'];
        
        // 2. 解析图片
        $color = $item['color'] ?? 'Original';
        $display_img = 'Logo(text).png'; // 兜底用 Logo

        if (!empty($db_fimage)) {
            // 先找它的真实路径在哪
            $base_path = $db_fimage;
            if (!file_exists($base_path) && file_exists('uploads/' . $db_fimage)) {
                $base_path = 'uploads/' . $db_fimage;
            } elseif (!file_exists($base_path)) {
                // 如果系统找不到，强制猜测它在新文件夹里
                $base_path = 'uploads/' . $db_fimage; 
            }

            // ⚠️ 智能判断：如果是根目录的原生 PNG 老图片，并且选了颜色，才允许换色！
            if ($color !== 'Original' && strpos($db_fimage, '.png') !== false && strpos($base_path, 'uploads/') === false) {
                $baseClean = str_replace('.png', '', $db_fimage);
                if ($color === 'Red') { $display_img = $baseClean . "_red.png"; }
                elseif ($color === 'Blue') { $display_img = $baseClean . "_blue.png"; }
                else { $display_img = $base_path; }
            } else {
                // 🌟 新上传的家具 (.jfif等)，或者放在 uploads 里的，绝不乱改名字！
                $display_img = $base_path;
            }
        } else {
            // 数据库没有时用 Session 缓存兜底
            $display_img = !empty($item['web_img']) ? $item['web_img'] : ($item['base_name'] . '.png');
        }

        $cart_items[] = [
            'key' => $key, 'fid' => $item['fid'] ?? 1, 'name' => $final_name, 
            'price' => $final_price, 'qty' => $item['qty'], 'size' => $item['size'] ?? 'Standard', 
            'color' => $color, 'remarks' => $item['remarks'] ?? '',
            'img' => $display_img, 'subtotal' => $subtotal
        ];
    }
}
$total_wishlist_count = isset($_SESSION['wishlist']) ? count($_SESSION['wishlist']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Cart - Premium Living Furniture</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background-color: #f4f7f6; color: #333; }
        .modern-header { background-color: #ffffff; padding: 5px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eaeaea; position: sticky; top: 0; z-index: 1000; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .brand-area { display: flex; align-items: center; text-decoration: none; }
        .brand-area img { height: 130px; width: auto; object-fit: contain; margin: -20px 0; }
        .nav-links { display: flex; align-items: center; gap: 30px; }
        .nav-links a { text-decoration: none; color: #111; font-weight: 600; font-size: 15px; transition: color 0.2s; }
        .nav-links a.active { color: #e67e22; border-bottom: 2px solid #e67e22; padding-bottom: 5px; }
        .nav-links a:hover { color: #f39c12; }
        .user-profile-btn { display: flex; align-items: center; gap: 8px; background: #f4f6f7; padding: 8px 15px; border-radius: 20px; text-decoration: none; color: #111; font-weight: bold; font-size: 14px; transition: background 0.2s; }
        .user-profile-btn:hover { background: #e2e6e9; }
        .mini-avatar { width: 16px; height: 16px; background-color: #7f8c8d; border-radius: 50%; position: relative; }
        .mini-avatar::after { content: ''; position: absolute; width: 24px; height: 10px; background-color: #7f8c8d; border-radius: 12px 12px 0 0; bottom: -12px; left: -4px; }
        .cart-wrapper { max-width: 1300px; margin: 40px auto; padding: 0 20px; display: flex; gap: 40px; align-items: flex-start; }
        .cart-main { flex: 2; }
        .cart-sidebar { flex: 1; }
        .page-title { font-size: 32px; font-weight: 800; margin-top: 0; margin-bottom: 25px; color: #111; border-bottom: 2px solid #111; padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: 600; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .cart-list { background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #eaeaea; padding: 20px 30px; }
        .cart-item { display: flex; align-items: center; padding: 25px 0; border-bottom: 1px solid #eee; }
        .cart-item:last-child { border-bottom: none; padding-bottom: 10px; }
        .item-img-box { width: 140px; height: 140px; background: #f4f6f7; border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-right: 25px; border: 1px solid #eaeaea; flex-shrink: 0; }
        .item-img-box img { width: 90%; height: 90%; object-fit: contain; }
        .item-details { flex: 1; }
        .item-details h3 { margin: 0 0 10px 0; font-size: 20px; color: #111; }
        .item-badges { display: flex; gap: 8px; margin-bottom: 10px; }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; background: #f4f6f7; color: #555; border: 1px solid #ddd; }
        .badge.size { background: #e8f4fd; color: #2980b9; border-color: #d6eaf8; }
        .badge.color { background: #fef9e7; color: #f39c12; border-color: #fcf3cf; }
        .item-remarks { font-size: 13px; color: #7f8c8d; background: #fdfbf7; padding: 8px 12px; border-radius: 6px; border-left: 3px solid #f1c40f; display: inline-block; margin-top: 5px; }
        .item-pricing { text-align: right; min-width: 150px; display: flex; flex-direction: column; align-items: flex-end; gap: 15px; }
        .price-text { font-size: 22px; font-weight: 800; color: #e74c3c; }
        .qty-text { font-size: 14px; color: #555; font-weight: 600; background: #eee; padding: 4px 12px; border-radius: 20px; }
        .btn-remove { color: #e74c3c; text-decoration: none; font-size: 14px; font-weight: bold; padding: 6px 12px; border-radius: 6px; transition: background 0.2s; }
        .btn-remove:hover { background: #fdedec; }
        .summary-box { background: #fff; padding: 30px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid #eaeaea; position: sticky; top: 100px; }
        .summary-box h3 { margin: 0 0 20px 0; font-size: 22px; color: #111; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 15px; color: #555; font-size: 15px; }
        .summary-row.total { border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px; font-size: 24px; font-weight: 900; color: #111; }
        .summary-row.total span:last-child { color: #e74c3c; }
        .address-box { margin-bottom: 25px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px dashed #ccc; font-size: 13px; color: #555; line-height: 1.5; }
        .address-box strong { color: #111; display: block; margin-bottom: 5px; }
        .btn-checkout { background-color: #2ecc71; color: white; padding: 18px; width: 100%; border: none; border-radius: 30px; font-size: 18px; font-weight: 800; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3); }
        .btn-checkout:hover { background-color: #27ae60; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(46, 204, 113, 0.4); }
        .btn-clear { display: block; text-align: center; margin-top: 15px; color: #7f8c8d; text-decoration: none; font-size: 14px; font-weight: 600; transition: color 0.2s; }
        .btn-clear:hover { color: #111; }
        .empty-cart { text-align: center; padding: 80px 20px; background: #fff; border-radius: 16px; border: 1px dashed #ccc; }
        .empty-cart h2 { color: #111; margin-bottom: 10px; }
        .empty-cart p { color: #7f8c8d; margin-bottom: 30px; }
        .btn-browse { background: #111; color: #fff; padding: 12px 30px; border-radius: 30px; text-decoration: none; font-weight: bold; transition: background 0.3s; }
        .btn-browse:hover { background: #333; }
    </style>
</head>
<body>

    <div class="modern-header">
        <a href="customer_home.php" class="brand-area">
            <img src="Logo(text).png?v=1" alt="Premium Living Logo">
        </a>
        <div class="nav-links">
            <a href="customer_make_order.php">Products</a>
            <a href="customer_wishlist.php" style="color:#e74c3c;">❤️ Wishlist (<?php echo $total_wishlist_count; ?>)</a>
            <a href="customer_cart.php" class="active">Cart (<?php echo $total_cart_count; ?>)</a>
            <a href="customer_view_orders.php">My Orders</a>
            <a href="customer_profile.php" class="user-profile-btn">
                <div style="width:16px; height:24px; display:flex; align-items:center; justify-content:center; overflow:hidden; margin-right:5px;">
                    <div class="mini-avatar"></div>
                </div>
                <?php echo htmlspecialchars($user_name); ?>
            </a>
        </div>
    </div>

    <div class="cart-wrapper">
        <div class="cart-main">
            <div class="page-title">
                <span>Shopping Cart</span>
                <span style="font-size: 16px; color: #7f8c8d; font-weight: 600;"><?php echo count($cart_items); ?> Item(s)</span>
            </div>

            <?php if (!empty($success_msg)): ?><div class="alert alert-success"><?php echo $success_msg; ?></div><?php endif; ?>
            <?php if (!empty($error_msg)): ?><div class="alert alert-danger"><?php echo $error_msg; ?></div><?php endif; ?>

            <?php if (empty($cart_items)): ?>
                <div class="empty-cart">
                    <h2>Your cart is looking a little empty.</h2>
                    <p>Discover our new collection of premium furniture and bring your dream home to life.</p>
                    <a href="customer_make_order.php" class="btn-browse">Browse Furniture</a>
                </div>
            <?php else: ?>
                <div class="cart-list">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <div class="item-img-box">
                                <img src="<?php echo htmlspecialchars($item['img']); ?>" onerror="this.style.display='none';">
                            </div>
                            <div class="item-details">
                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                <div class="item-badges">
                                    <span class="badge size">📏 <?php echo htmlspecialchars($item['size']); ?></span>
                                    <span class="badge color">🎨 <?php echo htmlspecialchars($item['color']); ?></span>
                                </div>
                                <div style="font-size: 13px; color: #999; margin-bottom: 5px;">Unit Price: HKD <?php echo number_format($item['price'], 2); ?></div>
                                <?php if(!empty($item['remarks'])): ?>
                                    <div class="item-remarks"><strong>Note:</strong> <?php echo htmlspecialchars($item['remarks']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="item-pricing">
                                <div class="price-text">HKD <?php echo number_format($item['subtotal'], 2); ?></div>
                                <div class="qty-text">Qty: <?php echo $item['qty']; ?></div>
                                <a href="customer_cart.php?delete_key=<?php echo urlencode($item['key']); ?>" class="btn-remove" onclick="return confirm('Remove this item from your cart?');">🗑️ Remove</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($cart_items)): ?>
            <div class="cart-sidebar">
                <div class="summary-box">
                    <h3>Order Summary</h3>
                    
                    <div class="summary-row">
                        <span>Subtotal (<?php echo $total_cart_count; ?> items)</span>
                        <span>HKD <?php echo number_format($total_amount, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Estimated Shipping</span>
                        <span style="color: #27ae60; font-weight: bold;">Free</span>
                    </div>
                    
                    <div class="address-box">
                        <strong>📍 Delivering to:</strong>
                        <?php echo htmlspecialchars($user_address); ?>
                        <br><a href="customer_profile.php" style="color:#3498db; font-size:12px; text-decoration:none; margin-top:5px; display:inline-block;">Edit Address</a>
                    </div>

                    <div class="summary-row total">
                        <span>Total</span>
                        <span>HKD <?php echo number_format($total_amount, 2); ?></span>
                    </div>

                    <form action="customer_cart.php" method="POST" style="margin-top: 25px;">
                        <button type="submit" name="checkout" class="btn-checkout">Confirm Checkout</button>
                    </form>
                    
                    <a href="customer_cart.php?clear=1" class="btn-clear" onclick="return confirm('Are you sure you want to empty your entire cart?');">Clear Entire Cart</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>