<?php
// 引入数据库连接工具文件
require_once 'db_connect.php';

// 🌟 新增逻辑：如果用户点击了“访客浏览”，立刻销毁任何残留的登录状态！
if (isset($_GET['action']) && $_GET['action'] === 'guest') {
    session_unset();    // 清空所有 Session 变量
    session_destroy();  // 彻底销毁 Session
    
    // 获取当前目录并跳转到主页
    $current_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    header("Location: " . $current_dir . "/customer_home.php");
    exit;
}

$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'customer';
    $user_id = trim($_POST['user_id'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($user_id) && !empty($password)) {
        
        $current_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

        if ($role === 'customer') {
            // 对齐你的数据库 `customers` 表 (cid, cpassword)
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE cid = ? AND cpassword = ?");
            $stmt->execute([$user_id, $password]);
            $user = $stmt->fetch();

            if ($user) {
                $_SESSION['user_role'] = 'customer';
                $_SESSION['user_id'] = $user['cid'];
                $_SESSION['user_name'] = $user['cname'];
                
                header("Location: " . $current_dir . "/customer_home.php");
                exit;
            } else {
                $error_msg = "Invalid Customer ID or Password!";
            }
        } else if ($role === 'staff') {
            // 对齐你的数据库 `staffs` 表 (sid, spassword)
            $stmt = $pdo->prepare("SELECT * FROM staffs WHERE sid = ? AND spassword = ?");
            $stmt->execute([$user_id, $password]);
            $staff = $stmt->fetch();

            if ($staff) {
                $_SESSION['user_role'] = 'staff';
                $_SESSION['user_id'] = $staff['sid'];
                $_SESSION['user_name'] = $staff['sname'];
                
                header("Location: " . $current_dir . "/staff_update_order.php");
                exit;
            } else {
                $error_msg = "Invalid Staff ID or Password!";
            }
        }
    } else {
        $error_msg = "Please fill in all fields!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Premium Living Furniture</title>
    <link rel="stylesheet" href="style.css?v=1.1">
    <style>
        .login-box { max-width: 380px; margin: 80px auto; background: white; padding: 25px 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .role-selector { display: flex; gap: 20px; margin-top: 6px; margin-bottom: 6px; align-items: center; }
        .role-selector input[type="radio"] { width: 25px; height: 25px; margin: 0; cursor: pointer; }
        .role-selector label { font-size: 13px; font-weight: normal; color: #555; cursor: pointer; }
        .error-block { background-color: #fce4e4; border: 1px solid #f6b0b0; color: #cc0000; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 14px; }
        .header { background-color: #ffffff; padding: 5px 40px; display: flex; justify-content: center; align-items: center; border-bottom: 1px solid #eaeaea; }
        .header img { height: 200px; width: auto; object-fit: contain; margin: -20px 0; }
    </style>
</head>
<body style="background-color: #f9f9f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0;">

    <div class="header">
        <img src="Logo(text).png?v=1" alt="Premium Living Logo">
    </div>

    <div class="login-box">
        <h2>System Login</h2>
        
        <?php if (!empty($error_msg)): ?>
            <div class="error-block"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #2c3e50;">Identify Yourself:</label>
                <div class="role-selector">
                    <input type="radio" id="role_customer" name="role" value="customer" checked>
                    <label for="role_customer">Customer</label>
                    <input type="radio" id="role_staff" name="role" value="staff">
                    <label for="role_staff">Staff / Admin</label>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #2c3e50;">User ID / Account Number:</label>
                <input type="text" name="user_id" placeholder="e.g. 1" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 14px;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #2c3e50;">Password:</label>
                <input type="password" name="password" placeholder="••••••••" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 14px;">
            </div>

            <div class="login-btn-container">
                <button type="submit" style="width:100%; padding: 12px; background-color: #2c3e50; color:white; border:none; border-radius:4px; font-size:16px; font-weight:bold; cursor:pointer;">Login</button>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="index.php?action=guest" style="color: #3498db; text-decoration: none; font-size: 15px; font-weight: bold; display: inline-block; padding: 10px; border-radius: 4px; transition: background 0.3s;">
                    🏠 Browse as Guest (No Login Required)
                </a>
            </div>
        </form>

        <div class="hint-text" style="margin-top: 15px; font-size: 12px; color: #666;">
            <hr style="border: 0; border-top: 1px solid #eee; margin-bottom: 12px;">
            <strong>Database Hint:</strong><br>
            According to SQL script setup:<br>
            - Customer Login ID: <code>1</code>, Password: <code>cust123</code><br>
            - Staff Login ID: <code>1</code>, Password: <code>admin</code>
        </div>
    </div>

</body>
</html>