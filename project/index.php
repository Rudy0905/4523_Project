<?php
require_once 'db_connect.php';

// 🌟 核心修复：安全登出逻辑 (彻底销毁 Session)
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}

// 访客浏览逻辑
if (isset($_GET['action']) && $_GET['action'] === 'guest') {
    session_unset();    
    session_destroy();  
    $current_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    header("Location: " . $current_dir . "/customer_home.php");
    exit;
}

$error_msg = ""; $success_msg = "";
$show_register = false; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? 'login';
    $current_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

    // 处理登录请求
    if ($form_action === 'login') {
        $role = $_POST['role'] ?? 'customer';
        $user_id = trim($_POST['user_id'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!empty($user_id) && !empty($password)) {
            if ($role === 'customer') {
                $stmt = $pdo->prepare("SELECT * FROM Customers WHERE cid = ? AND cpassword = ?");
                $stmt->execute([$user_id, $password]);
                $user = $stmt->fetch();

                if ($user) {
                    $_SESSION['user_role'] = 'customer';
                    $_SESSION['user_id'] = $user['cid'];
                    $_SESSION['user_name'] = $user['cname'];
                    
                    $stmtWish = $pdo->prepare("SELECT fid FROM Wishlists WHERE cid = ?");
                    $stmtWish->execute([$user['cid']]);
                    $_SESSION['wishlist'] = $stmtWish->fetchAll(PDO::FETCH_COLUMN) ?: [];

                    header("Location: " . $current_dir . "/customer_home.php");
                    exit;
                } else {
                    $error_msg = "Invalid Customer ID or Password!";
                }
            } else if ($role === 'staff') {
                $stmt = $pdo->prepare("SELECT * FROM Staffs WHERE sid = ? AND spassword = ?");
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
    // 处理注册请求
    elseif ($form_action === 'register') {
        $show_register = true; 
        $reg_name = trim($_POST['reg_name'] ?? '');
        $reg_password = trim($_POST['reg_password'] ?? '');
        $reg_tel = trim($_POST['reg_tel'] ?? '');
        $reg_addr = trim($_POST['reg_addr'] ?? '');

        if (!empty($reg_name) && !empty($reg_password)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO Customers (cname, cpassword, ctel, caddr) VALUES (?, ?, ?, ?)");
                $stmt->execute([$reg_name, $reg_password, $reg_tel, $reg_addr]);
                $new_cid = $pdo->lastInsertId();
                $success_msg = "🎉 Account created successfully! <br><br>Your Customer ID is: <strong style='font-size:20px; color:#e74c3c;'>$new_cid</strong><br><br>Please use this ID to log in.";
                $show_register = false; 
            } catch (Exception $e) {
                $error_msg = "🚨 Registration Failed: " . $e->getMessage();
            }
        } else {
            $error_msg = "Full Name and Password are required to register.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login / Register - Premium Living</title>
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; }
        .login-header { background-color: #ffffff; padding: 5px 40px; display: flex; justify-content: center; align-items: center; border-bottom: 1px solid #eaeaea; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .login-header img { height: 130px; width: auto; object-fit: contain; margin: -20px 0; }
        .auth-container { max-width: 400px; margin: 50px auto; position: relative; perspective: 1000px; }
        .auth-box { background: white; padding: 35px 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid #eaeaea; transition: transform 0.6s; backface-visibility: hidden; }
        .auth-box h2 { margin-top: 0; margin-bottom: 25px; font-weight: 800; color: #111; font-size: 24px; text-align: center; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 700; color: #333; font-size: 14px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 14px; outline: none; transition: border-color 0.2s; }
        .form-control:focus { border-color: #2980b9; box-shadow: 0 0 5px rgba(41, 128, 185, 0.2); }
        .role-selector { display: flex; gap: 25px; margin-top: 6px; margin-bottom: 6px; align-items: center; }
        .role-selector input[type="radio"] { width: 20px; height: 24px; margin: 0; cursor: pointer; }
        .role-selector label { font-size: 15px; font-weight: 600; color: #555; cursor: pointer; margin-bottom: 0; display: inline; }
        .error-block { background-color: #fce4e4; border: 1px solid #f6b0b0; color: #cc0000; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; font-weight: bold; text-align: center; }
        .success-block { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 20px; border-radius: 6px; margin-bottom: 20px; font-size: 15px; text-align: center; line-height: 1.5; }
        .btn-submit { width: 100%; padding: 14px; background-color: #1a252f; color: white; border: none; border-radius: 30px; font-size: 16px; font-weight: bold; cursor: pointer; transition: background 0.2s; }
        .btn-submit:hover { background-color: #2c3e50; }
        .btn-register-submit { background-color: #27ae60; }
        .btn-register-submit:hover { background-color: #2ecc71; }
        .switch-link { color: #3498db; text-decoration: none; font-size: 14px; font-weight: bold; display: block; text-align: center; margin-top: 20px; cursor: pointer; transition: color 0.3s; }
        .switch-link:hover { color: #2980b9; }
        .btn-guest-link { color: #7f8c8d; text-decoration: none; font-size: 14px; font-weight: bold; display: block; text-align: center; margin-top: 15px; transition: color 0.3s; }
        .btn-guest-link:hover { color: #111; }
    </style>
</head>
<body>
    <div class="login-header"><img src="Logo(text).png?v=1" alt="Logo"></div>
    <div class="auth-container">
        <?php if (!empty($success_msg)): ?><div class="success-block"><?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (!empty($error_msg)): ?><div class="error-block"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>

        <div class="auth-box" id="login-box" style="display: <?php echo $show_register ? 'none' : 'block'; ?>;">
            <h2>System Login</h2>
            <form action="index.php" method="POST">
                <input type="hidden" name="form_action" value="login">
                <div class="form-group"><label>Identify Yourself:</label><div class="role-selector"><input type="radio" id="role_customer" name="role" value="customer" checked><label for="role_customer">Customer</label><input type="radio" id="role_staff" name="role" value="staff"><label for="role_staff">Staff / Admin</label></div></div>
                <div class="form-group"><label>User ID / Account Number:</label><input type="text" name="user_id" class="form-control" required></div>
                <div class="form-group"><label>Password:</label><input type="password" name="password" class="form-control" required></div>
                <div style="margin-top: 25px;"><button type="submit" class="btn-submit">Sign In</button></div>
                <a class="switch-link" onclick="toggleForms()">🆕 New Customer? Create an Account</a>
                <a href="index.php?action=guest" class="btn-guest-link">🏠 Browse as Guest (No Login Required)</a>
            </form>
        </div>

        <div class="auth-box" id="register-box" style="display: <?php echo $show_register ? 'block' : 'none'; ?>;">
            <h2>Create Customer Account</h2>
            <form action="index.php" method="POST">
                <input type="hidden" name="form_action" value="register">
                <div class="form-group"><label>Full Name:</label><input type="text" name="reg_name" class="form-control" required></div>
                <div class="form-group"><label>Create Password:</label><input type="password" name="reg_password" class="form-control" required></div>
                <div class="form-group"><label>Contact Number (Optional):</label><input type="text" name="reg_tel" class="form-control"></div>
                <div class="form-group"><label>Default Shipping Address (Optional):</label><textarea name="reg_addr" class="form-control" rows="2" style="resize:vertical;"></textarea></div>
                <div style="margin-top: 25px;"><button type="submit" class="btn-submit btn-register-submit">Register Account</button></div>
                <a class="switch-link" style="color:#7f8c8d;" onclick="toggleForms()">🔙 Back to Login</a>
            </form>
        </div>
    </div>
    <script>
        function toggleForms() {
            var loginBox = document.getElementById('login-box');
            var registerBox = document.getElementById('register-box');
            if (loginBox.style.display === 'none') { loginBox.style.display = 'block'; registerBox.style.display = 'none'; } 
            else { loginBox.style.display = 'none'; registerBox.style.display = 'block'; }
        }
    </script>
</body>
</html>