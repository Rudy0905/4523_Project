<?php
session_start();
require_once 'db_connect.php';

// 权限拦截：必须是 staff
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'staff') {
    header("Location: index.php");
    exit;
}

$staff_name = $_SESSION['user_name'] ?? 'Admin';
$success_msg = "";
$error_msg = "";

// 🌟 处理订单状态更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    $oid = intval($_POST['oid']);
    $new_status = intval($_POST['ostatus']);
    $new_date = $_POST['odeliverydate'];

    try {
        $stmt = $pdo->prepare("UPDATE Orders SET ostatus = ?, odeliverydate = ? WHERE oid = ?");
        $stmt->execute([$new_status, $new_date, $oid]);
        $success_msg = "✅ Order #$oid has been successfully updated!";
    } catch (Exception $e) {
        $error_msg = "🚨 Update failed: " . $e->getMessage();
    }
}

// 🌟 获取所有订单，并进行复杂的 客户+商品+材料 联查
$orders_data = [];
try {
    // 1. 查主订单和客户信息
    $stmtOrders = $pdo->query("
        SELECT o.*, c.cname, c.ctel 
        FROM Orders o 
        JOIN Customers c ON o.cid = c.cid 
        ORDER BY o.odate DESC
    ");
    $all_orders = $stmtOrders->fetchAll();

    foreach ($all_orders as $order) {
        $oid = $order['oid'];
        
        // 2. 查订单包含的家具
        $stmtFurn = $pdo->prepare("
            SELECT of.fid, of.oqty, f.fname, f.fprice 
            FROM OrderFurnitures of
            JOIN Furnitures f ON of.fid = f.fid
            WHERE of.oid = ?
        ");
        $stmtFurn->execute([$oid]);
        $furnitures = $stmtFurn->fetchAll();

        // 3. 查这些家具到底消耗了什么原材料，以及当前库存对比
        $stmtMat = $pdo->prepare("
            SELECT m.mname, m.mqty AS available_qty, m.munit, SUM(fm.pmqty * of.oqty) AS used_qty
            FROM OrderFurnitures of
            JOIN FurnitureMaterials fm ON of.fid = fm.fid
            JOIN Materials m ON fm.mid = m.mid
            WHERE of.oid = ?
            GROUP BY m.mid
        ");
        $stmtMat->execute([$oid]);
        $materials = $stmtMat->fetchAll();

        $order['furnitures'] = $furnitures;
        $order['materials'] = $materials;
        $orders_data[] = $order;
    }
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Orders - Staff Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background: #f0f2f5; color: #333; display: flex; height: 100vh; overflow: hidden; }
        
        /* 🌟 专业的左侧 Admin 侧边栏 */
        .sidebar { width: 260px; background: #1a252f; color: #ecf0f1; display: flex; flex-direction: column; box-shadow: 2px 0 10px rgba(0,0,0,0.1); z-index: 100; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid #2c3e50; background: #141d26; }
        .sidebar-header img { width: 80%; object-fit: contain; filter: brightness(0) invert(1); /* Logo反白 */ }
        .sidebar-menu { flex: 1; padding: 20px 0; overflow-y: auto; }
        .sidebar-menu a { display: block; padding: 15px 25px; color: #bdc3c7; text-decoration: none; font-size: 15px; font-weight: 600; border-left: 4px solid transparent; transition: all 0.2s; }
        .sidebar-menu a:hover { background: #2c3e50; color: #fff; }
        .sidebar-menu a.active { background: #2980b9; color: #fff; border-left-color: #3498db; }
        .sidebar-footer { padding: 20px; border-top: 1px solid #2c3e50; font-size: 13px; text-align: center; color: #7f8c8d; }

        /* 右侧主内容区 */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-navbar { background: #fff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .top-navbar h2 { margin: 0; font-size: 20px; color: #2c3e50; }
        .btn-logout { background: #e74c3c; color: #fff; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: bold; }

        .content-body { padding: 30px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; font-weight: bold; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

        /* 订单管理卡片 */
        .admin-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; overflow: hidden; border: 1px solid #eaeaea; }
        .card-header { background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #eaeaea; display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 20px; display: flex; gap: 30px; }
        
        .info-col { flex: 1; }
        .info-col h4 { margin: 0 0 10px 0; color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 5px; font-size: 15px; }
        
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 15px; }
        .data-table th, .data-table td { padding: 8px; border-bottom: 1px solid #eee; text-align: left; }
        .data-table th { color: #7f8c8d; font-weight: 600; }
        
        /* 状态与表单 */
        .form-control { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; margin-bottom: 10px; box-sizing: border-box; }
        .btn-update { background: #3498db; color: #fff; border: none; padding: 10px; width: 100%; border-radius: 4px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-update:hover { background: #2980b9; }

        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; color: #fff; }
        .status-1 { background: #f39c12; } /* Pending */
        .status-2 { background: #3498db; } /* Processing */
        .status-3 { background: #9b59b6; } /* Approved */
        .status-5 { background: #2ecc71; } /* Completed */
        
        .mat-warning { color: #e74c3c; font-weight: bold; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-header">
            <h3 style="margin:0; color:#fff;">Premium Living</h3>
            <span style="font-size:12px; color:#3498db;">Staff Portal</span>
        </div>
        <div class="sidebar-menu">
            <a href="staff_update_order.php" class="active">📦 Manage Orders</a>
            <a href="staff_insert_item.php">🛋️ Add Furniture</a>
            <a href="staff_insert_material.php">🛠️ Add Materials</a>
            <a href="staff_delete_item.php">🗑️ Delete Catalog</a>
            <a href="staff_generate_report.php">📊 Sales Reports</a>
            <a href="staff_support.php">💬 Customer Support</a>
        </div>
        <div class="sidebar-footer">
            Logged in as: <strong><?php echo htmlspecialchars($staff_name); ?></strong>
        </div>
    </div>

    <div class="main-content">
        <div class="top-navbar">
            <h2>Order Processing & Material Control</h2>
            <a href="index.php?logout=1" class="btn-logout">Logout</a>
        </div>

        <div class="content-body">
            <?php if (!empty($success_msg)): ?><div class="alert alert-success"><?php echo $success_msg; ?></div><?php endif; ?>
            <?php if (!empty($error_msg)): ?><div class="alert alert-danger" style="background:#f8d7da; color:#721c24;"><?php echo $error_msg; ?></div><?php endif; ?>

            <?php foreach ($orders_data as $order): ?>
                <div class="admin-card">
                    <div class="card-header">
                        <div>
                            <strong style="font-size:18px;">Order #<?php echo $order['oid']; ?></strong>
                            <span style="color:#7f8c8d; margin-left:15px;">Date: <?php echo $order['odate']; ?></span>
                        </div>
                        <div>
                            <span class="status-badge status-<?php echo $order['ostatus']; ?>">
                                Status ID: <?php echo $order['ostatus']; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="info-col" style="flex: 0.8;">
                            <h4>Customer Details</h4>
                            <p style="font-size:13px; margin:5px 0;"><strong>Name:</strong> <?php echo htmlspecialchars($order['cname']); ?></p>
                            <p style="font-size:13px; margin:5px 0;"><strong>Tel:</strong> <?php echo htmlspecialchars($order['ctel']); ?></p>
                            <p style="font-size:13px; margin:5px 0; line-height:1.4;"><strong>Address:</strong><br><?php echo htmlspecialchars($order['odeliveraddress']); ?></p>
                            
                            <h4 style="margin-top:20px;">Update Order</h4>
                            <form method="POST">
                                <input type="hidden" name="oid" value="<?php echo $order['oid']; ?>">
                                
                                <label style="font-size:12px; font-weight:bold;">Order Status:</label>
                                <select name="ostatus" class="form-control">
                                    <option value="1" <?php if($order['ostatus']==1) echo 'selected'; ?>>1 - Pending / Open</option>
                                    <option value="2" <?php if($order['ostatus']==2) echo 'selected'; ?>>2 - Processing</option>
                                    <option value="3" <?php if($order['ostatus']==3) echo 'selected'; ?>>3 - Dispatched </option>
                                    <option value="4" <?php if($order['ostatus']==4) echo 'selected'; ?>>4 - Delivered </option>
                                    <option value="5" <?php if($order['ostatus']==5) echo 'selected'; ?>>5 - Completed / Finished</option>
                                </select>

                                <label style="font-size:12px; font-weight:bold;">Delivery Date:</label>
                                <input type="datetime-local" name="odeliverydate" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($order['odeliverydate'])); ?>">
                                
                                <button type="submit" name="update_order" class="btn-update">Save Changes</button>
                            </form>
                        </div>

                        <div class="info-col" style="flex: 1.2;">
                            <h4>Ordered Furniture</h4>
                            <table class="data-table">
                                <tr><th>FID</th><th>Product Name</th><th>Price</th><th>Qty</th></tr>
                                <?php foreach($order['furnitures'] as $f): ?>
                                <tr>
                                    <td>#<?php echo $f['fid']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($f['fname']); ?></strong></td>
                                    <td>$<?php echo $f['fprice']; ?></td>
                                    <td>x<?php echo $f['oqty']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                            <div style="text-align:right; font-weight:bold; color:#e74c3c; font-size:16px;">
                                Total: HKD <?php echo number_format($order['ototalamount'], 2); ?>
                            </div>
                        </div>

                        <div class="info-col" style="flex: 1; background: #fdfbf7; padding: 15px; border-radius: 6px; border: 1px solid #f1c40f;">
                            <h4 style="color:#f39c12; border-color:#f1c40f;">Material Usage Check</h4>
                            <table class="data-table" style="border:none;">
                                <tr><th>Material</th><th>Used</th><th>Available Stock</th></tr>
                                <?php foreach($order['materials'] as $m): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($m['mname']); ?></td>
                                    <td style="font-weight:bold;">-<?php echo $m['used_qty']; ?> <?php echo $m['munit']; ?></td>
                                    <td class="<?php echo ($m['available_qty'] < 50) ? 'mat-warning' : ''; ?>">
                                        <?php echo $m['available_qty']; ?> <?php echo $m['munit']; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                            <p style="font-size:11px; color:#7f8c8d; margin:0;">* Low stock items are highlighted in red.</p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</body>
</html>