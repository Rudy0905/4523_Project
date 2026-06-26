<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'staff') { header("Location: index.php"); exit; }
$staff_name = $_SESSION['user_name'] ?? 'Admin';
$success_msg = ""; $error_msg = "";

// 处理添加材料
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_material'])) {
    $mname = trim($_POST['mname'] ?? '');
    $mqty = intval($_POST['mqty'] ?? 0);
    $munit = $_POST['munit'] ?? 'pcs';

    if (!empty($mname) && $mqty >= 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO Materials (mname, mqty, munit) VALUES (?, ?, ?)");
            $stmt->execute([$mname, $mqty, $munit]);
            $success_msg = "🪵 Material '{$mname}' successfully added to inventory.";
        } catch (Exception $e) {
            $error_msg = "🚨 Error: " . $e->getMessage();
        }
    } else { $error_msg = "Please verify your input values."; }
}

// 提取当前所有材料库存用于展示
try {
    $stmtMat = $pdo->query("SELECT * FROM Materials ORDER BY mqty ASC");
    $inventory = $stmtMat->fetchAll();
} catch (Exception $e) { die("Database Error: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Management - Staff</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; background: #f0f2f5; display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 260px; background: #1a252f; color: #ecf0f1; display: flex; flex-direction: column; z-index: 100; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid #2c3e50; background: #141d26; }
        .sidebar-menu { flex: 1; padding: 20px 0; }
        .sidebar-menu a { display: block; padding: 15px 25px; color: #bdc3c7; text-decoration: none; font-weight: 600; transition: 0.2s; border-left: 4px solid transparent;}
        .sidebar-menu a:hover { background: #2c3e50; color: #fff; }
        .sidebar-menu a.active { background: #2980b9; color: #fff; border-left-color: #3498db; }
        .sidebar-footer { padding: 20px; border-top: 1px solid #2c3e50; font-size: 13px; text-align: center; color: #7f8c8d; }
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-navbar { background: #fff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .btn-logout { background: #e74c3c; color: #fff; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: bold; }
        .content-body { padding: 30px; display: flex; gap: 30px; align-items: flex-start;}
        
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 30px; border: 1px solid #eaeaea; }
        .form-card { flex: 1; }
        .table-card { flex: 2; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #2c3e50; font-size: 13px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 14px; outline: none; }
        .btn-success { background: #2ecc71; color: white; border: none; padding: 14px; width: 100%; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; }
        
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
        .data-table th { background: #f8f9fa; color: #7f8c8d; }
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .badge-danger { background: #f8d7da; color: #c0392b; border: 1px solid #f5c6cb; }
        .badge-success { background: #d4edda; color: #27ae60; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h3 style="margin:0; color:#fff;">Premium Living</h3><span style="font-size:12px; color:#3498db;">Staff Portal</span></div>
        <div class="sidebar-menu">
            <a href="staff_update_order.php">📦 Manage Orders</a>
            <a href="staff_insert_item.php">🛋️ Add Furniture</a>
            <a href="staff_insert_material.php">🛠️ Add Materials</a>
            <a href="staff_delete_item.php">🗑️ Delete Catalog</a>
            <a href="staff_generate_report.php">📊 Sales Reports</a>
            <a href="staff_support.php">💬 Customer Support</a>
        </div>
        <div class="sidebar-footer">Logged in as: <strong><?php echo htmlspecialchars($staff_name); ?></strong></div>
    </div>

    <div class="main-content">
        <div class="top-navbar"><h2>Raw Material & Inventory Control</h2><a href="index.php?logout=1" class="btn-logout">Logout</a></div>
        
        <div class="content-body">
            <div class="card form-card">
                <h3 style="margin-top:0;">Register New Material</h3>
                <?php if (!empty($success_msg)): ?><div style="color:green; margin-bottom:15px;"><b><?php echo $success_msg; ?></b></div><?php endif; ?>
                
                <form action="staff_insert_material.php" method="POST">
                    <div class="form-group"><label>Material Name:</label><input type="text" name="mname" class="form-control" required></div>
                    <div class="form-group"><label>Initial Quantity:</label><input type="number" name="mqty" value="0" class="form-control" required></div>
                    <div class="form-group">
                        <label>Unit:</label>
                        <select name="munit" class="form-control">
                            <option value="pcs">Pieces (pcs)</option>
                            <option value="meter">Meters</option>
                            <option value="block">Blocks</option>
                            <option value="kg">Kilograms (kg)</option>
                        </select>
                    </div>
                    <button type="submit" name="save_material" class="btn-success">Add to Inventory</button>
                </form>
            </div>

            <div class="card table-card">
                <h3 style="margin-top:0;">Live Inventory Dashboard</h3>
                <table class="data-table">
                    <thead><tr><th>MID</th><th>Material Name</th><th>Stock Level</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach($inventory as $mat): ?>
                        <tr>
                            <td>#<?php echo $mat['mid']; ?></td>
                            <td><strong><?php echo htmlspecialchars($mat['mname']); ?></strong></td>
                            <td style="font-weight:bold;"><?php echo $mat['mqty'] . ' ' . $mat['munit']; ?></td>
                            <td>
                                <?php if($mat['mqty'] < 50): ?>
                                    <span class="badge badge-danger">⚠️ Low Stock</span>
                                <?php else: ?>
                                    <span class="badge badge-success">✅ Healthy</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>