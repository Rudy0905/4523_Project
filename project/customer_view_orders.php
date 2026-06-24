<?php
// 开启 Session
session_start();

// 1. 引入数据库连接工具文件
require_once 'db_connect.php'; 

// 权限拦截：如果未登录，退回登录页
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: index.php");
    exit;
}

// 对齐官方字段：从 Session 中读取当前登录的客户 cid
$customer_id = $_SESSION['user_id'] ?? 1; 

$success_msg = "";
$error_msg = "";

// 接收来自购物车结算成功后的提示
if (isset($_GET['order_success'])) {
    $success_msg = "🎉 Checkout successful! Order #" . intval($_GET['order_success']) . " placed.";
}

// ==========================================\
// 🌟 核心新功能：处理客户取消订单请求 (Cancel Order)
// ==========================================\
if (isset($_GET['cancel_oid'])) {
    $cancel_oid = intval($_GET['cancel_oid']);
    
    try {
        // 1. 安全防线：先查询该订单是否真的属于当前登录的客户，且状态必须是 1 (Pending)
        $stmtCheck = $pdo->prepare("SELECT ostatus FROM Orders WHERE oid = ? AND cid = ?");
        $stmtCheck->execute([$cancel_oid, $customer_id]);
        $order_status = $stmtCheck->fetchColumn();
        
        if ($order_status === false) {
            $error_msg = "🚨 Order not found or access denied.";
        } elseif (intval($order_status) !== 1) {
            // 只有 Pending 状态可以取消
            $error_msg = "🚨 Cannot cancel this order. It is already being processed or dispatched by staff.";
        } else {
            // 2. 状态合规，启动数据库事务（Transaction）进行双表联删
            $pdo->beginTransaction();
            
            // 先删明细表 (OrderFurnitures)
            $stmtDelItems = $pdo->prepare("DELETE FROM OrderFurnitures WHERE oid = ?");
            $stmtDelItems->execute([$cancel_oid]);
            
            // 再删主表 (Orders)
            $stmtDelOrder = $pdo->prepare("DELETE FROM Orders WHERE oid = ?");
            $stmtDelOrder->execute([$cancel_oid]);
            
            $pdo->commit();
            $success_msg = "❌ Order #" . $cancel_oid . " has been successfully cancelled.";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_msg = "🚨 Cancel Failed: " . $e->getMessage();
    }
}

// ==========================================\
// 2. 查询属于当前 cid 的所有订单
// ==========================================\
try {
    $stmtOrders = $pdo->prepare("SELECT * FROM Orders WHERE cid = ? ORDER BY odate DESC");
    $stmtOrders->execute([$customer_id]);
    $all_orders = $stmtOrders->fetchAll();

    $orders_with_items = [];

    // 循环主订单，去关联查询具体的家具信息
    foreach ($all_orders as $order) {
        $order_id = $order['oid'];
        
        // 连表查询明细
        $sqlItems = "SELECT of.fid, of.oqty, f.fname, f.fprice 
                     FROM OrderFurnitures of
                     JOIN Furnitures f ON of.fid = f.fid
                     WHERE of.oid = ?";
        $stmtItems = $pdo->prepare($sqlItems);
        $stmtItems->execute([$order_id]);
        $order['items'] = $stmtItems->fetchAll();
        $orders_with_items[] = $order;
    }
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

// 统计当前购物车小红点数量
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
        /* 🎨 完美保持：纯 CSS 补全右上角小人图案，与商品图互不干涉 */
        .nav-avatar-icon {
            width: 24px; height: 24px; background-color: #bdc3c7; border-radius: 50%; position: relative; display: inline-block;
        }
        .nav-avatar-icon::before {
            content: ''; position: absolute; width: 10px; height: 10px; background-color: #7f8c8d; border-radius: 50%; top: 3px; left: 7px;
        }
        .nav-avatar-icon::after {
            content: ''; position: absolute; width: 18px; height: 8px; background-color: #7f8c8d; border-radius: 6px 6px 0 0; bottom: 1px; left: 3px;
        }
        
        /* 订单卡片设计 */
        .order-card { background: white; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .order-meta-header { display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding-bottom: 12px; font-size: 14px; color: #666; align-items: center; }
        
        /* 状态微标 */
        .badge { display: inline-block; padding: 5px 12px; font-weight: bold; border-radius: 20px; font-size: 12px; }
        .status-pending { background-color: #fdebd0; color: #e67e22; }
        .status-completed { background-color: #d4efdf; color: #27ae60; }
        
        /* 取消订单按钮样式 */
        .btn-cancel-order {
            background-color: #e74c3c; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: bold; margin-left: 15px; transition: background 0.2s;
        }
        .btn-cancel-order:hover { background-color: #c0392b; }
        
        .order-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .order-table th, .order-table td { padding: 10px; border-bottom: 1px solid #f1f1f1; text-align: left; }
        .order-table th { background-color: #f8f9fa; color: #34495e; }
        
        /* 消息框体 */
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; border: 1px solid transparent; }
        .alert-success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; font-weight: bold; }
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
        
        <?php if (!empty($success_msg)): ?><div class="alert alert-success"><?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (!empty($error_msg)): ?><div class="alert alert-danger"><?php echo $error_msg; ?></div><?php endif; ?>

        <?php if (empty($orders_with_items)): ?>
            <div style="text-align: center; color: #7f8c8d; padding: 40px; background: white; border:1px solid #ddd; border-radius: 8px;">You have no past orders yet.</div>
        <?php else: ?>
            <?php foreach ($orders_with_items as $order): ?>
                <div class="order-card">
                    <div class="order-meta-header">
                        <div>
                            <strong>Order ID: #<?php echo $order['oid']; ?></strong>
                            <span style="margin-left: 20px;">📅 Date: <?php echo $order['odate']; ?></span>
                        </div>
                        <div style="display: flex; align-items: center;">
                            <?php 
                            // 动态判断状态
                            if ($order['ostatus'] == 1) {
                                echo '<span class="badge status-pending">⏳ Pending</span>';
                                // 🌟 核心呈现：只有在 Pending 状态，才渲染带有安全确认的取消按钮
                                echo '<a href="customer_view_orders.php?cancel_oid=' . $order['oid'] . '" class="btn-cancel-order" onclick="return confirm(\'Are you sure you want to cancel and delete Order #' . $order['oid'] . '?\');">❌ Cancel Order</a>';
                            } else {
                                echo '<span class="badge status-completed">🚚 Dispatched</span>';
                            }
                            ?>
                        </div>
                    </div>
                    <table class="order-table">
                        <thead>
                            <tr>
                                <th width="120">Furniture ID</th>
                                <th>Furniture Name</th>
                                <th>Unit Price</th>
                                <th width="100">Quantity</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order['items'] as $item): $subtotal = $item['fprice'] * $item['oqty']; ?>
                                <tr>
                                    <td>#<?php echo $item['fid']; ?></td>
                                    <td><strong style="color: #2c3e50; font-size: 15px;"><?php echo htmlspecialchars($item['fname']); ?></strong></td>
                                    <td>HKD <?php echo number_format($item['fprice'], 2); ?></td>
                                    <td><?php echo $item['oqty']; ?></td>
                                    <td style="font-weight:bold; color: #2c3e50;">HKD <?php echo number_format($subtotal, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="text-align: right; margin-top: 15px;">
                        <span style="font-size: 14px; color: #7f8c8d; margin-right: 15px;">📍 Delivery to: <?php echo htmlspecialchars($order['odeliveraddress'] ?? 'Default Profile Address'); ?></span>
                        <span style="font-size: 16px; font-weight: bold;">Total paid: </span>
                        <span style="font-size: 18px; font-weight: bold; color: #e74c3c;">HKD <?php echo number_format($order['ototalamount'], 2); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>