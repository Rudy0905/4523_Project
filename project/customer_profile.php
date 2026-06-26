<?php
session_start();
require_once 'db_connect.php'; 
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: index.php"); exit;
}
$customer_id = $_SESSION['user_id']; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $stmtUpdate = $pdo->prepare("UPDATE Customers SET cname = ?, cpassword = ?, ctel = ?, caddr = ? WHERE cid = ?");
    $stmtUpdate->execute([$_POST['cname'], $_POST['cpassword'], $_POST['ctel'], $_POST['caddr'], $customer_id]);
    $_SESSION['user_name'] = $_POST['cname'];
}

$stmtUser = $pdo->prepare("SELECT * FROM Customers WHERE cid = ?");
$stmtUser->execute([$customer_id]);
$user_info = $stmtUser->fetch();
$user_name = $user_info['cname'];

// 🌟 提前计算顶部导航栏需要的购物车/收藏数字
$total_cart_count = 0;
if (!empty($_SESSION['cart'])) { foreach ($_SESSION['cart'] as $item) { $total_cart_count += $item['qty']; } }
$total_wishlist_count = isset($_SESSION['wishlist']) ? count($_SESSION['wishlist']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile - Premium Living</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f9f9f9; margin: 0; color: #333; }
        
        /* 🌟 补齐与全站统一的导航栏样式 */
        .modern-header { background: #fff; padding: 5px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eaeaea; position: sticky; top: 0; z-index: 1000; }
        .brand-area img { height: 130px; width: auto; object-fit: contain; margin: -20px 0; }
        .nav-links { display: flex; gap: 30px; align-items: center; }
        .nav-links a { text-decoration: none; color: #111; font-weight: 600; font-size: 15px; transition: 0.2s; }
        .nav-links a:hover, .nav-links a.active { color: #f39c12; }
        .user-profile-btn { display: flex; align-items: center; gap: 8px; background: #111; padding: 8px 15px; border-radius: 20px; text-decoration: none; color: #fff !important; font-weight: bold; font-size: 14px; }
        .mini-avatar { width: 16px; height: 16px; background-color: #fff; border-radius: 50%; position: relative; }
        .mini-avatar::after { content: ''; position: absolute; width: 24px; height: 10px; background-color: #fff; border-radius: 12px 12px 0 0; bottom: -12px; left: -4px; }

        .profile-container { max-width: 600px; margin: 50px auto; background: #fff; padding: 40px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); text-align: center; }
        .avatar-huge { width: 100px; height: 100px; background: #bdc3c7; border-radius: 50%; margin: 0 auto 20px auto; position: relative; overflow: hidden; }
        .avatar-huge::before { content: ''; position: absolute; width: 40px; height: 40px; background: #7f8c8d; border-radius: 50%; top: 15px; left: 30px; }
        .avatar-huge::after { content: ''; position: absolute; width: 80px; height: 40px; background: #7f8c8d; border-radius: 40px 40px 0 0; bottom: -10px; left: 10px; }
        .info-group { text-align: left; background: #f4f6f7; padding: 15px; border-radius: 8px; margin-bottom: 15px; }
        .info-group label { display: block; font-size: 12px; color: #7f8c8d; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; }
        .info-group p { margin: 0; font-size: 16px; font-weight: bold; }
        .btn { padding: 12px 25px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; font-size: 14px; width: 100%; transition: 0.2s; }
        .btn-edit { background: #111; color: #fff; margin-top: 10px; }
        .btn-edit:hover { background: #333; }
        .btn-logout { background: transparent; color: #e74c3c; border: 2px solid #e74c3c; margin-top: 20px; text-decoration: none; display: inline-block; box-sizing: border-box; }
        .btn-logout:hover { background: #e74c3c; color: #fff; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; z-index: 2000; }
        .modal-content { background: #fff; padding: 30px; border-radius: 12px; width: 90%; max-width: 400px; text-align: left; }
        .custom-input { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        .btn-row { display: flex; gap: 10px; }
        .btn-cancel { background: #eee; color: #333; }
    </style>
</head>
<body>
    <div class="modern-header">
        <a href="customer_home.php" class="brand-area"><img src="Logo(text).png?v=1" alt="Logo"></a>
        <div class="nav-links">
            <a href="customer_make_order.php">Products</a>
            <a href="customer_wishlist.php" style="color:#e74c3c;">❤️ Wishlist (<?php echo $total_wishlist_count; ?>)</a>
            <a href="customer_cart.php">Cart (<?php echo $total_cart_count; ?>)</a>
            <a href="customer_view_orders.php">My Orders</a>
            <a href="customer_profile.php" class="user-profile-btn">
                <div style="width:16px; height:24px; display:flex; align-items:center; justify-content:center; overflow:hidden; margin-right:5px;"><div class="mini-avatar"></div></div>
                <?php echo htmlspecialchars($user_name); ?>
            </a>
        </div>
    </div>

    <div class="profile-container">
        <div class="avatar-huge"></div>
        <h2 style="margin:0 0 30px 0;"><?php echo htmlspecialchars($user_name); ?></h2>

        <div class="info-group">
            <label>Login Password</label>
            <p>********</p>
        </div>
        <div class="info-group">
            <label>Phone Number</label>
            <p><?php echo htmlspecialchars($user_info['ctel']); ?></p>
        </div>
        <div class="info-group">
            <label>Default Shipping Address</label>
            <p><?php echo htmlspecialchars($user_info['caddr']); ?></p>
        </div>

        <button class="btn btn-edit" onclick="document.getElementById('editModal').style.display='flex'">Edit Profile</button>
        
        <a href="index.php?logout=1" class="btn btn-logout" onclick="return confirm('Are you sure you want to securely log out?');">🚪 Secure Logout</a>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-top:0;">Edit Profile</h3>
            <form method="POST">
                <label style="font-weight:bold; font-size:14px;">Full Name:</label>
                <input type="text" name="cname" class="custom-input" value="<?php echo htmlspecialchars($user_info['cname']); ?>" required>
                
                <label style="font-weight:bold; font-size:14px;">Password:</label>
                <input type="text" name="cpassword" class="custom-input" value="<?php echo htmlspecialchars($user_info['cpassword']); ?>" required>

                <label style="font-weight:bold; font-size:14px;">Contact Number:</label>
                <input type="text" name="ctel" class="custom-input" value="<?php echo htmlspecialchars($user_info['ctel']); ?>" required>
                
                <label style="font-weight:bold; font-size:14px;">Address:</label>
                <textarea name="caddr" class="custom-input" style="height:80px;" required><?php echo htmlspecialchars($user_info['caddr']); ?></textarea>
                
                <div class="btn-row">
                    <button type="button" class="btn btn-cancel" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
                    <button type="submit" name="update_profile" class="btn btn-edit" style="margin:0;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>