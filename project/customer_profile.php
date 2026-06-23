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

$customer_id = $_SESSION['user_id'] ?? 1; 
$success_msg = "";
$error_msg = "";

// ==========================================
// 处理功能：用户提交更新个人资料表单
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $cname = trim($_POST['cname'] ?? '');
    $ctel = trim($_POST['ctel'] ?? '');
    $caddr = trim($_POST['caddr'] ?? '');

    if (!empty($cname) && !empty($ctel) && !empty($caddr)) {
        try {
            // 对齐官方字段：cname, ctel, caddr, cid
            $stmtUpdate = $pdo->prepare("UPDATE Customers SET cname = ?, ctel = ?, caddr = ? WHERE cid = ?");
            $stmtUpdate->execute([$cname, $ctel, $caddr, $customer_id]);
            
            // 同步更新 Session 里的名字
            $_SESSION['user_name'] = $cname;
            $success_msg = "✨ Profile updated successfully!";
        } catch (Exception $e) {
            $error_msg = "🚨 Update failed: " . $e->getMessage();
        }
    } else {
        $error_msg = "All required fields must be filled.";
    }
}

// ==========================================
// 核心读取：获取当前用户的最新资料
// ==========================================
try {
    $stmtUser = $pdo->prepare("SELECT * FROM Customers WHERE cid = ?");
    $stmtUser->execute([$customer_id]);
    $user_info = $stmtUser->fetch();
    
    if (!$user_info) {
        die("User record not found in database.");
    }
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

// 动态计算导航栏购物车数量
$total_cart_count = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $total_cart_count += $item['qty'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile - Premium Living</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* 灰色卡通头像样式定义 */
        .user-avatar-btn {
            float: right;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            cursor: pointer;
            text-decoration: none;
            background-color: #4f5f6f;
        }
        .user-avatar-btn:hover { background-color: #2c3e50; }
        .avatar-icon {
            width: 24px;
            height: 24px;
            background-color: #bdc3c7;
            border-radius: 50%;
            position: relative;
            display: inline-block;
        }
        /* 绘制卡通人头顶 */
        .avatar-icon::before {
            content: '';
            position: absolute;
            width: 10px;
            height: 10px;
            background-color: #7f8c8d;
            border-radius: 50%;
            top: 3px;
            left: 7px;
        }
        /* 绘制卡通人肩膀 */
        .avatar-icon::after {
            content: '';
            position: absolute;
            width: 18px;
            height: 8px;
            background-color: #7f8c8d;
            border-radius: 6px 6px 0 0;
            bottom: 1px;
            left: 3px;
        }
        .profile-container { max-width: 600px; margin: 30px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .btn-logout { background-color: #e74c3c; color: white; padding: 12px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block; text-align: center; }
        .btn-logout:hover { background-color: #c0392b; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Premium Living Furniture</h1>
    </div>

    <div class="navbar">
        <a href="customer_make_order.php">Browse Furniture</a>
        <a href="customer_cart.php">My Cart (<?php echo $total_cart_count; ?>)</a>
        <a href="customer_view_orders.php">My Orders</a>
        
        <a href="customer_profile.php" class="user-avatar-btn" title="My Profile" style="border-left: 1px solid #4f5f6f;">
            <div class="avatar-icon"></div>
            <span style="color: white; margin-left: 8px; font-size: 14px; font-weight: bold;"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
        </a>
    </div>

    <div class="container" style="max-width: 700px;">
        <h2>My Account Profile</h2>
        <p>Manage your contact details, default shipping address, or securely log out from your session.</p>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <form action="customer_profile.php" method="POST" style="margin-top: 20px;">
            <div class="form-group">
                <label>Customer ID (Read-only):</label>
                <input type="text" value="<?php echo $user_info['cid']; ?>" disabled style="background-color: #f4f6f7; color: #7f8c8d;">
            </div>

            <div class="form-group">
                <label>Full Name:</label>
                <input type="text" name="cname" value="<?php echo htmlspecialchars($user_info['cname']); ?>" required>
            </div>

            <div class="form-group">
                <label>Contact Number:</label>
                <input type="tel" name="ctel" value="<?php echo htmlspecialchars($user_info['ctel']); ?>" required>
            </div>

            <div class="form-group">
                <label>Default Shipping Address:</label>
                <textarea name="caddr" rows="4" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 14px; font-family: inherit;"><?php echo htmlspecialchars($user_info['caddr']); ?></textarea>
            </div>

            <div style="margin-top: 30px; display: flex; justify-content: space-between; align-items: center;">
                <button type="submit" name="update_profile" class="btn btn-success" style="padding: 12px 30px; font-size: 15px; font-weight: bold;">Save Changes</button>
                <a href="index.php" class="btn-logout" onclick="return confirm('Are you sure you want to log out?');">🚪 Logout from Account</a>
            </div>
        </form>
    </div>

</body>
</html>