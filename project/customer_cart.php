<?php
// 开启 Session 记录购物车数据
session_start();

// 1. 引入数据库连接工具文件
require_once 'db_connect.php'; 

// 权限拦截
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: index.php");
    exit;
}

$customer_id = $_SESSION['user_id'] ?? 1; 

$cart_items = [];
$total_amount = 0.00;
$success_msg = "";
$error_msg = "";

// 处理功能 1：单个商品删除请求
if (isset($_GET['delete_key'])) {
    $target_key = $_GET['delete_key'];
    if (isset($_SESSION['cart'][$target_key])) {
        unset($_SESSION['cart'][$target_key]); 
        $success_msg = "Item removed from cart.";
    }
    header("Location: customer_cart.php");
    exit;
}

// 处理功能 2：一键清理购物车
if (isset($_GET['clear']) && $_GET['clear'] == 1) {
    $_SESSION['cart'] = [];
    header("Location: customer_cart.php");
    exit;
}

// 处理功能 3：结账落盘 + 原材料库存联动扣减
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    if (!empty($_SESSION['cart'])) {
        try {
            $pdo->beginTransaction();
            $materials_to_deduct = [];

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
            }

            foreach ($_SESSION['cart'] as $item) {
                $fid = intval($item['fid'] ?? 0);
                $qty = intval($item['qty'] ?? 1);
                $stmtPrice = $pdo->prepare("SELECT fprice FROM Furnitures WHERE fid = ?");
                $stmtPrice->execute([$fid]);
                $fprice = $stmtPrice->fetchColumn();
                $final_p = $fprice ? $fprice : ($item['price'] ?? 0);
                $calc_total += $final_p * $qty;
            }

            $sqlOrder = "INSERT INTO Orders (ototalamount, cid, odate, odeliverydate, odeliveraddress, ostatus) 
                         VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 'Flat A, 12/F, Sunshine Building, Mong Kok, Kowloon', 1)";
            $stmtOrder = $pdo->prepare($sqlOrder);
            $stmtOrder->execute([$calc_total, $customer_id]);
            $new_oid = $pdo->lastInsertId();

            $stmtInsertDetail = $pdo->prepare("INSERT INTO OrderFurnitures (oid, fid, oqty) VALUES (?, ?, ?)");
            $stmtDeductStock = $pdo->prepare("UPDATE Materials SET mqty = mqty - ? WHERE mid = ?");

            foreach ($_SESSION['cart'] as $item) {
                $fid = intval($item['fid'] ?? 0);
                $qty = intval($item['qty'] ?? 1);
                if ($fid <= 0) continue;
                $stmtInsertDetail->execute([$new_oid, $fid, $qty]);
            }

            foreach ($materials_to_deduct as $mid => $qty_to_sub) {
                $stmtDeductStock->execute([$qty_to_sub, $mid]);
            }

            $pdo->commit();
            $_SESSION['cart'] = [];
            header("Location: customer_view_orders.php?order_success=" . $new_oid);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "🚨 Checkout Failed: " . $e->getMessage();
        }
    }
}

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $key => $item) {
        $db_name = ""; $db_price = 0.00;
        if (isset($item['fid'])) {
            $stmtFurniture = $pdo->prepare("SELECT fname, fprice FROM Furnitures WHERE fid = ?");
            $stmtFurniture->execute([$item['fid']]);
            $furn = $stmtFurniture->fetch();
            if ($furn) { $db_name = $furn['fname']; $db_price = $furn['fprice']; }
        }
        $final_name = !empty($db_name) ? $db_name : ($item['name'] ?? 'Furniture Item');
        $final_price = ($db_price > 0) ? $db_price : ($item['price'] ?? 0.00);
        $subtotal = $final_price * $item['qty'];
        $total_amount += $subtotal;
        
        $prefix = !empty($item['base_name']) ? $item['base_name'] : "WoodenRider1";
        $display_img = $prefix . ".png";
        if (isset($item['color']) && $item['color'] === 'Red') { $display_img = $prefix . "_red.png"; }
        elseif (isset($item['color']) && $item['color'] === 'Blue') { $display_img = $prefix . "_blue.png"; }

        $cart_items[] = [
            'key' => $key, 'fid' => $item['fid'] ?? 1, 'name' => $final_name, 'price' => $final_price,
            'qty' => $item['qty'], 'size' => $item['size'] ?? 'Standard', 'color' => $item['color'] ?? 'Original',
            'img' => $display_img, 'base_img' => $prefix . ".png", 'subtotal' => $subtotal
        ];
    }
}

$total_cart_count = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) { $total_cart_count += $item['qty']; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Cart - Premium Living Furniture</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* 🎨 核心修复：纯 CSS 独立补全小人图案，与商品图片代码完全隔离 */
        .nav-avatar-icon {
            width: 24px;
            height: 24px;
            background-color: #bdc3c7;
            border-radius: 50%;
            position: relative;
            display: inline-block;
        }
        .nav-avatar-icon::before {
            content: ''; position: absolute; width: 10px; height: 10px; background-color: #7f8c8d; border-radius: 50%; top: 3px; left: 7px;
        }
        .nav-avatar-icon::after {
            content: ''; position: absolute; width: 18px; height: 8px; background-color: #7f8c8d; border-radius: 6px 6px 0 0; bottom: 1px; left: 3px;
        }
        
        /* 保持原有的购物车表格样式布局 */
        .cart-table { width: 100%; border-collapse: collapse; margin-top: 20px; background-color: #fff; }
        .cart-table th, .cart-table td { padding: 15px; text-align: left; border-bottom: 1px solid #ddd; vertical-align: middle; }
        .cart-table th { background-color: #f4f6f7; color: #34495e; font-weight: bold; }
        .cart-thumb { width: 70px; height: 70px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; }
        .badge { display: inline-block; padding: 4px 8px; font-size: 12px; font-weight: bold; border-radius: 4px; margin-right: 5px; }
        .badge-size { background-color: #e8f4fd; color: #2980b9; }
        .badge-color { background-color: #fef9e7; color: #f39c12; }
        .btn-delete-item { background: none; border: none; color: #e74c3c; font-size: 20px; cursor: pointer; text-decoration: none; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 14px; display: inline-block; }
        .btn-success { background-color: #2ecc71; color: white; font-weight: bold; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert-success { background-color: #d4edda; color: #155724; }
        .alert-danger { background-color: #f8d7da; color: #721c24; font-weight: bold; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Premium Living Furniture</h1>
    </div>

    <div class="navbar">
        <a href="customer_make_order.php">Browse Furniture</a>
        <a href="customer_cart.php" class="active">My Cart (<?php echo $total_cart_count; ?>)</a>
        <a href="customer_view_orders.php">My Orders</a>
        
        <a href="customer_profile.php" style="float: right; display: flex; align-items: center; justify-content: center; padding: 10px 20px; cursor: pointer; text-decoration: none; background-color: #34495e; border-left: 1px solid #4f5f6f;" title="My Profile">
            <div class="nav-avatar-icon"></div>
            <span style="color: white; margin-left: 8px; font-size: 14px; font-weight: bold;"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
        </a>
    </div>

    <div class="container">
        <h2>Your Shopping Cart</h2>
        <?php if (!empty($success_msg)): ?><div class="alert alert-success"><?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (!empty($error_msg)): ?><div class="alert alert-danger"><?php echo $error_msg; ?></div><?php endif; ?>

        <table class="cart-table">
            <thead>
                <tr>
                    <th width="120">Preview</th>
                    <th>Furniture Item</th>
                    <th>Custom Options</th>
                    <th>Unit Price</th>
                    <th width="80">Quantity</th>
                    <th>Subtotal</th>
                    <th width="60" style="text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cart_items)): ?>
                    <tr><td colspan="7" style="text-align: center; color: #7f8c8d; padding: 40px;">Your shopping cart is empty.</td></tr>
                <?php else: ?>
                    <?php foreach ($cart_items as $item): ?>
                        <tr>
                            <td><img src="<?php echo $item['img']; ?>" class="cart-thumb" onerror="this.src='<?php echo $item['base_img']; ?>'; this.onerror=null;"></td>
                            <td><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                            <td>
                                <span class="badge badge-size">📏 <?php echo htmlspecialchars($item['size']); ?></span>
                                <span class="badge badge-color">🎨 <?php echo htmlspecialchars($item['color']); ?></span>
                            </td>
                            <td>HKD <?php echo number_format($item['price'], 2); ?></td>
                            <td><?php echo $item['qty']; ?></td>
                            <td style="font-weight: bold; color: #2c3e50;">HKD <?php echo number_format($item['subtotal'], 2); ?></td>
                            <td style="text-align: center;"><a href="customer_cart.php?delete_key=<?php echo urlencode($item['key']); ?>" class="btn-delete-item" onclick="return confirm('Remove this item?');">🗑️</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if (!empty($cart_items)): ?>
            <div style="text-align: right; margin-top: 30px;">
                <h2>Total Amount: <span style="color: #e74c3c;">HKD <?php echo number_format($total_amount, 2); ?></span></h2>
                <a href="customer_cart.php?clear=1" class="btn" style="background-color: #7f8c8d; color: white; margin-right: 10px;">Clear All</a>
                <form action="customer_cart.php" method="POST" style="display: inline-block;">
                    <button type="submit" name="checkout" class="btn btn-success" style="font-size: 16px;">Confirm Checkout</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>