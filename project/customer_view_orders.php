<?php
session_start();
require_once 'db_connect.php'; 

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: index.php");
    exit;
}

$customer_id = $_SESSION['user_id'] ?? 1; 
$success_msg = "";
if (isset($_GET['order_success'])) {
    $success_msg = "🎉 Checkout successful! Order #" . intval($_GET['order_success']) . " placed.";
}

try {
    $stmtOrders = $pdo->prepare("SELECT * FROM Orders WHERE cid = ? ORDER BY odate DESC");
    $stmtOrders->execute([$customer_id]);
    $all_orders = $stmtOrders->fetchAll();
    $orders_with_items = [];

    foreach ($all_orders as $order) {
        $sqlItems = "SELECT of.fid, of.oqty, f.fname, f.fprice FROM OrderFurnitures of JOIN Furnitures f ON of.fid = f.fid WHERE of.oid = ?";
        $stmtItems = $pdo->prepare($sqlItems);
        $stmtItems->execute([$order['oid']]);
        $order['items'] = $stmtItems->fetchAll();
        $orders_with_items[] = $order;
    }
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
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
    <title>My Orders - Premium Living</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* 🎨 核心修复：纯 CSS 独立补全小人图案，各页面表现彻底统一 */
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
        .order-card { background: white; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 25px; }
        .order-meta-header { display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding-bottom: 12px; font-size: 14px; color: #666; }
        .badge { display: inline-block; padding: 5px 12px; font-weight: bold; border-radius: 20px; font-size: 12px; }
        .status-pending { background-color: #fdebd0; color: #e67e22; }
        .status-completed { background-color: #d4efdf; color: #27ae60; }
        .order-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .order-table th, .order-table td { padding: 10px; border-bottom: 1px solid #f1f1f1; }
        .order-table th { background-color: #f8f9fa; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Premium Living Furniture</h1>
    </div>

    <div class="navbar">
        <a href="customer_make_order.php">Browse Furniture</a>
        <a href="customer_cart.php">My Cart (<?php echo $total_cart_count; ?>)</a>
        <a href="customer_view_orders.php" class="active">My Orders</a>
        
        <a href="customer_profile.php" style="float: right; display: flex; align-items: center; justify-content: center; padding: 10px 20px; cursor: pointer; text-decoration: none; background-color: #34495e; border-left: 1px solid #4f5f6f;" title="My Profile">
            <div class="nav-avatar-icon"></div>
            <span style="color: white; margin-left: 8px; font-size: 14px; font-weight: bold;"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
        </a>
    </div>

    <div class="container">
        <h2>My Order History</h2>
        <?php if (!empty($success_msg)): ?><div class="alert-success"><?php echo $success_msg; ?></div><?php endif; ?>

        <?php if (empty($orders_with_items)): ?>
            <div style="text-align: center; color: #7f8c8d; padding: 40px; background: white; border:1px solid #ddd; border-radius: 8px;">You have no past orders yet.</div>
        <?php else: ?>
            <?php foreach ($orders_with_items as $order): ?>
                <div class="order-card">
                    <div class="order-meta-header">
                        <div><strong>Order ID: #<?php echo $order['oid']; ?></strong><span style="margin-left: 20px;">📅 Date: <?php echo $order['odate']; ?></span></div>
                        <div><?php echo $order['ostatus'] == 1 ? '<span class="badge status-pending">⏳ Pending</span>' : '<span class="badge status-completed">🚚 Dispatched</span>'; ?></div>
                    </div>
                    <table class="order-table">
                        <thead><tr><th width="100">Furniture ID</th><th>Furniture Name</th><th>Unit Price</th><th>Quantity</th><th>Subtotal</th></tr></thead>
                        <tbody>
                            <?php foreach ($order['items'] as $item): $subtotal = $item['fprice'] * $item['oqty']; ?>
                                <tr><td>#<?php echo $item['fid']; ?></td><td><strong style="color: #2c3e50; font-size: 15px;"><?php echo htmlspecialchars($item['fname']); ?></strong></td><td>HKD <?php echo number_format($item['fprice'], 2); ?></td><td><?php echo $item['oqty']; ?></td><td style="font-weight:bold; color: #2c3e50;">HKD <?php echo number_format($subtotal, 2); ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="text-align: right; margin-top: 15px;"><span style="font-size: 16px; font-weight: bold;">Total paid: </span><span style="font-size: 18px; font-weight: bold; color: #e74c3c;">HKD <?php echo number_format($order['ototalamount'], 2); ?></span></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>