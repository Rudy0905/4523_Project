<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'staff') { header("Location: index.php"); exit; }
$staff_name = $_SESSION['user_name'] ?? 'Admin';
$success_msg = ""; $error_msg = "";

if (isset($_GET['delete_fid'])) {
    $delete_fid = intval($_GET['delete_fid']);
    try {
        // 核心安全检查：计算该家具是否存在于活跃订单中
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM OrderFurnitures WHERE fid = ?");
        $stmtCheck->execute([$delete_fid]);
        $existing_orders = $stmtCheck->fetchColumn();

        if ($existing_orders > 0) {
            $error_msg = "🚨 Action Denied: Blueprint #$delete_fid is currently locked. It is tied to $existing_orders customer order(s).";
        } else {
            $pdo->beginTransaction();
            // 解绑配方表
            $stmtDelRecipe = $pdo->prepare("DELETE FROM FurnitureMaterials WHERE fid = ?");
            $stmtDelRecipe->execute([$delete_fid]);
            // 删除主目录
            $stmtDelFurn = $pdo->prepare("DELETE FROM Furnitures WHERE fid = ?");
            $stmtDelFurn->execute([$delete_fid]);
            $pdo->commit();
            $success_msg = "🗑️ Furniture #$delete_fid has been successfully removed from the catalog.";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $error_msg = "🚨 Database Error: " . $e->getMessage();
    }
}

// 提取全量家具，并提前计算它的被占用状态
try {
    $stmtCatalog = $pdo->query("
        SELECT f.*, (SELECT COUNT(*) FROM OrderFurnitures of WHERE of.fid = f.fid) as lock_count 
        FROM Furnitures f ORDER BY f.fid ASC
    ");
    $catalog = $stmtCatalog->fetchAll();
} catch (Exception $e) { die("Database Error: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delete Product - Staff Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; background: #f0f2f5; display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 260px; background: #1a252f; color: #ecf0f1; display: flex; flex-direction: column; z-index: 100; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid #2c3e50; background: #141d26; }
        .sidebar-menu { flex: 1; padding: 20px 0; }
        .sidebar-menu a { display: block; padding: 15px 25px; color: #bdc3c7; text-decoration: none; font-weight: 600; transition: 0.2s; border-left: 4px solid transparent;}
        .sidebar-menu a:hover { background: #2c3e50; color: #fff; }
        .sidebar-menu a.active { background: #2980b9; color: #fff; border-left-color: #3498db; }
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-navbar { background: #fff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .btn-logout { background: #e74c3c; color: #fff; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: bold; }
        .content-body { padding: 30px; }
        .table-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 30px; border: 1px solid #eaeaea; }
        
        .report-table { width: 100%; border-collapse: collapse; }
        .report-table th, .report-table td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        .report-table th { background: #f8f9fa; color: #2c3e50; font-weight: bold; }
        .btn-danger { background-color: #e74c3c; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: bold; transition: 0.2s; display: inline-block; }
        .btn-danger:hover { background-color: #c0392b; }
        .btn-disabled { background-color: #bdc3c7; color: white; padding: 8px 16px; border-radius: 4px; font-size: 13px; font-weight: bold; display: inline-block; cursor: not-allowed; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; font-weight: bold; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h3 style="margin:0; color:#fff;">Premium Living</h3><span style="font-size:12px; color:#3498db;">Staff Portal</span></div>
        <div class="sidebar-menu">
            <a href="staff_update_order.php">📦 Manage Orders</a>
            <a href="staff_insert_item.php">🛋️ Add Furniture</a>
            <a href="staff_insert_material.php">🛠️ Add Materials</a>
            <a href="staff_delete_item.php" class="active">🗑️ Delete Catalog</a>
            <a href="staff_generate_report.php">📊 Sales Reports</a>
            <a href="staff_support.php">💬 Customer Support</a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-navbar"><h2>Product Lifecycle Management</h2><a href="index.php?logout=1" class="btn-logout">Logout</a></div>
        <div class="content-body">
            <div class="table-card">
                <h3 style="margin-top:0; color:#2c3e50; border-bottom:2px solid #eee; padding-bottom:10px;">Retire Furniture Blueprint</h3>
                <?php if (!empty($success_msg)): ?><div class="alert alert-success"><?php echo $success_msg; ?></div><?php endif; ?>
                <?php if (!empty($error_msg)): ?><div class="alert alert-danger"><?php echo $error_msg; ?></div><?php endif; ?>
                
                <table class="report-table">
                    <thead>
                        <tr>
                            <th width="80">FID</th>
                            <th>Furniture Info</th>
                            <th>Base Price</th>
                            <th>Active Orders</th>
                            <th width="120">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($catalog as $row): ?>
                        <tr>
                            <td>#<?php echo $row['fid']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['fname']); ?></strong><br>
                                <span style="color:#7f8c8d; font-size:12px;"><?php echo htmlspecialchars($row['fdesc']); ?></span>
                            </td>
                            <td style="font-weight:bold;">HKD <?php echo number_format($row['fprice'], 2); ?></td>
                            <td>
                                <?php if($row['lock_count'] > 0): ?>
                                    <span style="color:#e74c3c; font-weight:bold; background:#fdedec; padding:4px 8px; border-radius:10px; font-size:12px;">🔒 <?php echo $row['lock_count']; ?> Used</span>
                                <?php else: ?>
                                    <span style="color:#27ae60; font-weight:bold; font-size:12px;">🟢 0 (Clear)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($row['lock_count'] > 0): ?>
                                    <span class="btn-disabled" title="Cannot delete: In use by orders">🔒 Locked</span>
                                <?php else: ?>
                                    <a href="staff_delete_item.php?delete_fid=<?php echo $row['fid']; ?>" class="btn-danger" onclick="return confirm('⚠️ Are you sure you want to permanently delete this furniture blueprint?');">🗑️ Delete</a>
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