<?php
// 引入数据库连接工具文件
// Include database connection utility file
require_once 'db_connect.php';

$error_msg = "";

// 检查是否为表单提交请求
// Check if the request method is POST for form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'customer';
    $user_id = trim($_POST['user_id'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($user_id) && !empty($password)) {
        
        // 获取当前脚本运行的目录路径，防止跳转时丢失子文件夹名
        // Get the current script directory path to prevent losing subfolder name during redirection
        $current_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

        if ($role === 'customer') {
            // 使用预处理语句防止 SQL 注入（匹配顾客）
            // Use prepared statements to prevent SQL Injection (Match customer)
            $stmt = $pdo->prepare("SELECT * FROM Customers WHERE cid = ? AND cpassword = ?");
            $stmt->execute([$user_id, $password]);
            $user = $stmt->fetch();

            if ($user) {
                // 登录成功，保存顾客状态到 Session
                // Login successful, save customer state into Session
                $_SESSION['user_role'] = 'customer';
                $_SESSION['user_id'] = $user['cid'];
                $_SESSION['user_name'] = $user['cname'];
                
                // 动态拼接完整路径，安全重定向到顾客商品浏览页面
                // Dynamically concatenate full path, safely redirect to customer browse product page
                header("Location: " . $current_dir . "/customer_make_order.php");
                exit;
            } else {
                $error_msg = "Invalid Customer ID or Password!";
            }
        } else if ($role === 'staff') {
            // 使用预处理语句匹配员工
            // Use prepared statements to match staff
            $stmt = $pdo->prepare("SELECT * FROM Staffs WHERE sid = ? AND spassword = ?");
            $stmt->execute([$user_id, $password]);
            $staff = $stmt->fetch();

            if ($staff) {
                // 登录成功，保存员工状态到 Session
                // Login successful, save staff state into Session
                $_SESSION['user_role'] = 'staff';
                $_SESSION['user_id'] = $staff['sid'];
                $_SESSION['user_name'] = $staff['sname'];
                
                // 动态拼接完整路径，安全重定向到员工订单管理页面
                // Dynamically concatenate full path, safely redirect to staff order management page
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
    <link rel="stylesheet" href="style.css">
    <style>
        .login-box {
            max-width: 380px; 
            margin: 80px auto;
            background: white;
            padding: 25px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .role-selector {
            display: flex;
            gap: 20px;
            margin-top: 6px;
            margin-bottom: 6px;
            align-items: center;
        }
        .role-selector input[type="radio"] {
            width: 25px;
            height: 25px;
            margin: 0;
            cursor: pointer;
        }
        .role-selector label {
            font-size: 13px;
            font-weight: normal;
            color: #555;
            cursor: pointer;
        }
        .error-block {
            background-color: #fce4e4;
            border: 1px solid #f6b0b0;
            color: #cc0000;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 14px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Premium Living Furniture</h1>
    </div>

    <div class="login-box">
        <h2>System Login</h2>
        
        <?php if (!empty($error_msg)): ?>
            <div class="error-block"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <div class="form-group">
                <label>Identify Yourself:</label>
                <div class="role-selector">
                    <input type="radio" id="role_customer" name="role" value="customer" checked>
                    <label for="role_customer">Customer</label>
                    
                    <input type="radio" id="role_staff" name="role" value="staff">
                    <label for="role_staff">Staff / Admin</label>
                </div>
            </div>

            <div class="form-group">
                <label>User ID / Account Number:</label>
                <input type="text" name="user_id" placeholder="e.g. 1" required>
            </div>

            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>

            <div class="login-btn-container">
                <button type="submit" class="btn-login" style="width:100%; padding: 12px; background-color: #2c3e50; color:white; border:none; border-radius:4px; font-size:16px; cursor:pointer;">Login</button>
            </div>
        </form>

        <div class="hint-text" style="margin-top: 15px; font-size: 12px; color: #666;">
            <hr style="border: 0; border-top: 1px solid #eee; margin-bottom: 12px;">
            <strong>Database Hint:</strong><br>
            According to SQL script setup:<br>
            - Customer Login ID: <code>1</code>, Password: <code>password123</code><br>
            - Staff Login ID: <code>1</code>, Password: <code>staffpass</code>
        </div>
    </div>

</body>
</html>