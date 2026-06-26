<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'staff') { header("Location: index.php"); exit; }
$staff_name = $_SESSION['user_name'] ?? 'Admin';
$success_msg = ""; $error_msg = "";

try {
    $stmtMat = $pdo->query("SELECT * FROM Materials ORDER BY mname ASC");
    $all_materials = $stmtMat->fetchAll();
} catch (Exception $e) { die("Database Error: " . $e->getMessage()); }

// 🌟 核心：处理表单提交 + 图片上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $fname = trim($_POST['fname'] ?? '');
    $fprice = floatval($_POST['fprice'] ?? 0);
    $fdesc = trim($_POST['fdesc'] ?? '');
    $fcategory = $_POST['fcategory'] ?? 'seating';
    $froom = $_POST['froom'] ?? 'living';
    $mids = $_POST['mids'] ?? [];
    $pmqtys = $_POST['pmqtys'] ?? [];

    // 🌟 图片上传逻辑
    $image_name = 'default.png'; // 默认图片
    if (isset($_FILES['fimage']) && $_FILES['fimage']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['fimage']['tmp_name'];
        // 为防止重名，加上时间戳
        $image_name = time() . '_' . basename($_FILES['fimage']['name']);
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
        move_uploaded_file($tmp_name, $upload_dir . $image_name);
    }

    if (!empty($fname) && $fprice > 0 && !empty($mids)) {
        try {
            $pdo->beginTransaction();
            // 插入商品基础信息（包含新加的分类、场景、图片名）
            $stmtF = $pdo->prepare("INSERT INTO Furnitures (fname, fdesc, fprice, fimage, fcategory, froom) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtF->execute([$fname, $fdesc, $fprice, $image_name, $fcategory, $froom]);
            $new_fid = $pdo->lastInsertId();

            // 插入材料配方
            $stmtFM = $pdo->prepare("INSERT INTO FurnitureMaterials (fid, mid, pmqty) VALUES (?, ?, ?)");
            for ($i = 0; $i < count($mids); $i++) {
                $mid = intval($mids[$i]);
                $pmqty = intval($pmqtys[$i]);
                if ($mid > 0 && $pmqty > 0) { $stmtFM->execute([$new_fid, $mid, $pmqty]); }
            }
            $pdo->commit();
            $success_msg = "🎉 Success! Product #$new_fid has been cataloged with its image.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "🚨 Error: " . $e->getMessage();
        }
    } else { $error_msg = "Please fill in all required fields."; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Furniture - Staff Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; background: #f0f2f5; display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 260px; background: #1a252f; color: #ecf0f1; display: flex; flex-direction: column; z-index: 100; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid #2c3e50; background: #141d26; }
        .sidebar-menu { flex: 1; padding: 20px 0; overflow-y: auto; }
        .sidebar-menu a { display: block; padding: 15px 25px; color: #bdc3c7; text-decoration: none; font-weight: 600; border-left: 4px solid transparent; transition: 0.2s; }
        .sidebar-menu a:hover { background: #2c3e50; color: #fff; }
        .sidebar-menu a.active { background: #2980b9; color: #fff; border-left-color: #3498db; }
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-navbar { background: #fff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .content-body { padding: 30px; }
        .form-layout { display: flex; gap: 30px; max-width: 1100px; margin: 0 auto; }
        .card { background: #fff; border-radius: 8px; padding: 30px; border: 1px solid #eaeaea; flex: 1; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
        .btn-submit { background: #2ecc71; color: white; border: none; padding: 15px; width: 100%; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h3 style="margin:0; color:#fff;">Premium Living</h3></div>
        <div class="sidebar-menu">
            <a href="staff_update_order.php">📦 Manage Orders</a>
            <a href="staff_insert_item.php" class="active">🛋️ Add Furniture</a>
            <a href="staff_insert_material.php">🛠️ Add Materials</a>
            <a href="staff_delete_item.php">🗑️ Delete Catalog</a>
            <a href="staff_generate_report.php">📊 Sales Reports</a>
            <a href="staff_support.php">💬 Customer Support</a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-navbar"><h2>Product Catalog & Engineering</h2><a href="index.php?logout=1" class="btn-logout">Logout</a></div>
        <div class="content-body">
            <?php if (!empty($success_msg)): ?><div style="color:green; margin-bottom:15px; font-weight:bold;"><?php echo $success_msg; ?></div><?php endif; ?>
            
            <form action="staff_insert_item.php" method="POST" enctype="multipart/form-data" class="form-layout">
                <div class="card">
                    <h3>1. Basic Information</h3>
                    <div class="form-group"><label>Product Name</label><input type="text" name="fname" class="form-control" required></div>
                    <div class="form-group"><label>Base Price (HKD)</label><input type="number" name="fprice" step="0.01" class="form-control" required></div>
                    
                    <div style="display:flex; gap:10px;">
                        <div class="form-group" style="flex:1;">
                            <label>Category</label>
                            <select name="fcategory" class="form-control">
                                <option value="seating">Seating</option>
                                <option value="tables">Tables</option>
                                <option value="beds">Beds</option>
                                <option value="storage">Storage</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label>Room</label>
                            <select name="froom" class="form-control">
                                <option value="living">Living Room</option>
                                <option value="bedroom">Bedroom</option>
                                <option value="dining">Dining Room</option>
                                <option value="study">Study / Office</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Product Image Upload</label>
                        <input type="file" name="fimage" class="form-control" accept="image/*" required>
                    </div>

                    <div class="form-group"><label>Description</label><textarea name="fdesc" rows="3" class="form-control" required></textarea></div>
                </div>

                <div class="card">
                    <h3>2. Bill of Materials (Recipe)</h3>
                    <div id="recipe-container">
                        <div style="display:flex; gap:10px; margin-bottom:10px;">
                            <select name="mids[]" class="form-control" required>
                                <option value="">-- Select Material --</option>
                                <?php foreach($all_materials as $mat): ?>
                                    <option value="<?php echo $mat['mid']; ?>"><?php echo htmlspecialchars($mat['mname']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="pmqtys[]" min="1" class="form-control" placeholder="Qty" style="width: 80px;" required>
                        </div>
                    </div>
                    <button type="button" onclick="addRecipeRow()" style="width:100%; padding:10px; margin-bottom:20px;">➕ Add Material</button>
                    <button type="submit" name="save_product" class="btn-submit">Publish Product</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function addRecipeRow() {
            const container = document.getElementById('recipe-container');
            const row = document.createElement('div');
            row.style.cssText = "display:flex; gap:10px; margin-bottom:10px;";
            row.innerHTML = `
                <select name="mids[]" class="form-control" required>
                    <option value="">-- Select Material --</option>
                    <?php foreach($all_materials as $mat): ?><option value="<?php echo $mat['mid']; ?>"><?php echo addslashes(htmlspecialchars($mat['mname'])); ?></option><?php endforeach; ?>
                </select>
                <input type="number" name="pmqtys[]" min="1" class="form-control" placeholder="Qty" style="width: 80px;" required>
                <button type="button" onclick="this.parentElement.remove()">X</button>
            `;
            container.appendChild(row);
        }
    </script>
</body>
</html>