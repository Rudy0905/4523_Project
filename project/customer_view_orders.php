<?php
session_start();
require_once 'db_connect.php'; 

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') { header("Location: index.php"); exit; }
$customer_id = $_SESSION['user_id'] ?? 1; 
$user_name = $_SESSION['user_name'] ?? 'User';
$success_msg = ""; $error_msg = "";

if (isset($_GET['order_success'])) { $success_msg = "🎉 Checkout successful! Order #" . intval($_GET['order_success']) . " has been placed."; }

// ==========================================
// 🌟 核心修复 1：取消订单 (包含2天限制 + 原料退库)
// ==========================================
if (isset($_GET['cancel_oid'])) {
    $cancel_oid = intval($_GET['cancel_oid']);
    
    // 查询订单状态及送货日期
    $stmtCheck = $pdo->prepare("SELECT ostatus, odeliverydate FROM Orders WHERE oid = ? AND cid = ?");
    $stmtCheck->execute([$cancel_oid, $customer_id]);
    $order_to_cancel = $stmtCheck->fetch();

    if ($order_to_cancel && $order_to_cancel['ostatus'] == 1) {
        $today = new DateTime();
        $delivery_date = new DateTime($order_to_cancel['odeliverydate']);
        $days_diff = intval($today->diff($delivery_date)->format('%R%a'));

        // 检查：必须大于等于2天才能取消
        if ($days_diff < 2) {
            $error_msg = "🚨 Cannot cancel: Order is within 2 days of delivery (Delivery Date: " . $order_to_cancel['odeliverydate'] . "). Please contact support.";
        } else {
            $pdo->beginTransaction();
            try {
                // A. 查出订单里买了什么家具
                $stmtGetItems = $pdo->prepare("SELECT fid, oqty FROM OrderFurnitures WHERE oid = ?");
                $stmtGetItems->execute([$cancel_oid]);
                $items = $stmtGetItems->fetchAll();
                
                // B. 根据家具配方，精准退还原材料库存
                foreach ($items as $item) {
                    $stmtMat = $pdo->prepare("SELECT mid, pmqty FROM FurnitureMaterials WHERE fid = ?");
                    $stmtMat->execute([$item['fid']]);
                    $recipe = $stmtMat->fetchAll();
                    foreach ($recipe as $mat) {
                        $restore_qty = $mat['pmqty'] * $item['oqty'];
                        $pdo->prepare("UPDATE Materials SET mqty = mqty + ? WHERE mid = ?")->execute([$restore_qty, $mat['mid']]);
                    }
                }

                // C. 删除工单记录 (如果有的话)
                $stmtTid = $pdo->prepare("SELECT tid FROM Tickets WHERE oid = ?");
                $stmtTid->execute([$cancel_oid]);
                $tid_to_delete = $stmtTid->fetchColumn();
                if ($tid_to_delete) {
                    $pdo->prepare("DELETE FROM TicketMessages WHERE tid = ?")->execute([$tid_to_delete]);
                    $pdo->prepare("DELETE FROM Tickets WHERE tid = ?")->execute([$tid_to_delete]);
                }

                // D. 删除订单本身
                $pdo->prepare("DELETE FROM OrderFurnitures WHERE oid = ?")->execute([$cancel_oid]);
                $pdo->prepare("DELETE FROM Orders WHERE oid = ?")->execute([$cancel_oid]);
                
                $pdo->commit();
                $success_msg = "✅ Order #" . $cancel_oid . " successfully deleted, and material inventory has been restocked.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// 确认收货
if (isset($_GET['confirm_oid'])) {
    $confirm_oid = intval($_GET['confirm_oid']);
    $pdo->prepare("UPDATE Orders SET ostatus = 5 WHERE oid = ? AND cid = ?")->execute([$confirm_oid, $customer_id]);
    $success_msg = "✅ Order #" . $confirm_oid . " confirmed! Thank you.";
}

// 提交工单
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $ticket_oid = intval($_POST['ticket_oid']);
    $message = trim($_POST['message'] ?? '');
    if (!empty($message)) {
        try {
            $stmtCheckTid = $pdo->prepare("SELECT tid FROM Tickets WHERE oid = ?");
            $stmtCheckTid->execute([$ticket_oid]);
            $existing_tid = $stmtCheckTid->fetchColumn();
            if ($existing_tid) {
                $pdo->prepare("INSERT INTO TicketMessages (tid, sender_role, message) VALUES (?, 'customer', ?)")->execute([$existing_tid, $message]);
                $pdo->prepare("UPDATE Tickets SET status = 'Pending' WHERE tid = ?")->execute([$existing_tid]);
            } else {
                $pdo->prepare("INSERT INTO Tickets (oid, cid, status, created_at) VALUES (?, ?, 'Pending', NOW())")->execute([$ticket_oid, $customer_id]);
                $new_tid = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO TicketMessages (tid, sender_role, message) VALUES (?, 'customer', ?)")->execute([$new_tid, $message]);
            }
            $success_msg = "📨 Message sent to support successfully!";
        } catch (Exception $e) { $error_msg = "🚨 Error: " . $e->getMessage(); }
    }
}

// 提交评价
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $review_oid = intval($_POST['review_oid']);
    $rating = intval($_POST['rating'] ?? 5);
    $comments = trim($_POST['comments'] ?? '');
    try {
        $stmtReview = $pdo->prepare("UPDATE Orders SET rating = ?, review_comment = ? WHERE oid = ? AND cid = ?");
        $stmtReview->execute([$rating, $comments, $review_oid, $customer_id]);
        $success_msg = "⭐ Thank you! Your " . $rating . "-star rating has been securely saved.";
    } catch (Exception $e) { $error_msg = "🚨 Failed to submit review: " . $e->getMessage(); }
}

// ==========================================
// 🌟 核心修复 2：接收排序参数并生成查询
// ==========================================
$sort_by = $_GET['sort_by'] ?? 'odate';
$sort_order = $_GET['sort_order'] ?? 'DESC';
$allowed_sort = ['odate', 'ototalamount'];
$allowed_order = ['ASC', 'DESC'];
if (!in_array($sort_by, $allowed_sort)) $sort_by = 'odate';
if (!in_array($sort_order, $allowed_order)) $sort_order = 'DESC';

// 获取订单与完整聊天记录 (应用排序)
try {
    $stmtTickets = $pdo->prepare("SELECT * FROM Tickets WHERE cid = ?");
    $stmtTickets->execute([$customer_id]);
    $ticket_map = [];
    foreach ($stmtTickets->fetchAll() as $t) { 
        $stmtMsg = $pdo->prepare("SELECT sender_role, message, created_at FROM TicketMessages WHERE tid = ? ORDER BY created_at ASC");
        $stmtMsg->execute([$t['tid']]);
        $t['chat_history'] = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);
        $ticket_map[$t['oid']] = $t; 
    }

    // 这里执行了动态排序 SQL
    $stmtOrders = $pdo->prepare("SELECT * FROM Orders WHERE cid = ? ORDER BY $sort_by $sort_order");
    $stmtOrders->execute([$customer_id]);
    $orders_data = [];
    foreach ($stmtOrders->fetchAll() as $order) {
        $order_id = $order['oid'];
        $stmtItems = $pdo->prepare("SELECT of.fid, of.oqty, f.fname, f.fprice, f.fimage FROM OrderFurnitures of JOIN Furnitures f ON of.fid = f.fid WHERE of.oid = ?");
        $stmtItems->execute([$order_id]);
        $items = $stmtItems->fetchAll();
        foreach($items as &$item) {
            $img_path = $item['fimage'];
            if (!file_exists($img_path) && file_exists('uploads/' . $img_path)) { $img_path = 'uploads/' . $img_path; }
            $item['img'] = $img_path;
        }
        $order['items'] = $items;
        $order['ticket'] = $ticket_map[$order_id] ?? null; 
        $orders_data[$order_id] = $order;
    }
} catch (Exception $e) { die("Database Error: " . $e->getMessage()); }

$total_cart_count = 0;
if (!empty($_SESSION['cart'])) { foreach ($_SESSION['cart'] as $item) { $total_cart_count += $item['qty']; } }
$total_wishlist_count = isset($_SESSION['wishlist']) ? count($_SESSION['wishlist']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Orders - Premium Living</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f9f9f9; margin: 0; color: #333; }
        .modern-header { background: #fff; padding: 5px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eaeaea; position: sticky; top: 0; z-index: 1000; }
        .brand-area img { height: 130px; width: auto; object-fit: contain; margin: -20px 0; }
        .nav-links { display: flex; gap: 30px; align-items: center; }
        .nav-links a { text-decoration: none; color: #111; font-weight: 600; font-size: 15px; transition: 0.2s; }
        .nav-links a.active { color: #e67e22; border-bottom: 2px solid #e67e22; padding-bottom: 5px; }
        .nav-links a:hover { color: #f39c12; }
        .user-profile-btn { display: flex; align-items: center; gap: 8px; background: #f4f6f7; padding: 8px 15px; border-radius: 20px; text-decoration: none; color: #111; font-weight: bold; font-size: 14px; }
        .mini-avatar { width: 16px; height: 16px; background-color: #7f8c8d; border-radius: 50%; position: relative; }
        .mini-avatar::after { content: ''; position: absolute; width: 24px; height: 10px; background-color: #7f8c8d; border-radius: 12px 12px 0 0; bottom: -12px; left: -4px; }
        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .page-title { font-size: 32px; font-weight: 800; margin-bottom: 20px; border-bottom: 2px solid #111; padding-bottom: 15px; }
        .alert { padding: 15px; margin-bottom: 25px; border-radius: 8px; font-weight: 600; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* 排序工具栏 */
        .sort-toolbar { display: flex; gap: 10px; align-items: center; margin-bottom: 25px; background: #fff; padding: 15px 20px; border-radius: 12px; border: 1px solid #eaeaea; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .custom-select { padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; outline: none; }
        
        .order-card { background: #fff; border-radius: 16px; padding: 25px 30px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #eaeaea; transition: box-shadow 0.3s; }
        .order-card:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        .order-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f1f1; padding-bottom: 15px; margin-bottom: 20px; }
        .order-info h3 { margin: 0 0 5px 0; font-size: 20px; color: #111; }
        .order-info p { margin: 0; color: #7f8c8d; font-size: 14px; }
        .status-badge { padding: 6px 15px; border-radius: 20px; font-size: 13px; font-weight: bold; display: inline-flex; align-items: center; gap: 6px; }
        .order-summary { display: flex; justify-content: space-between; align-items: flex-end; }
        .summary-price { font-size: 24px; font-weight: 900; color: #e74c3c; }
        .action-btns { display: flex; gap: 12px; }
        .btn { padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; border: none; font-size: 14px; text-decoration: none; display: inline-block; transition: 0.2s; }
        .btn-details { background: #f4f6f7; color: #111; }
        .btn-details:hover { background: #e2e6e9; }
        .btn-cancel { background: #fff; color: #e74c3c; border: 1px solid #e74c3c; }
        .btn-cancel:hover { background: #fdedec; }
        .btn-confirm { background: #27ae60; color: #fff; border: 1px solid #2ecc71; box-shadow: 0 4px 10px rgba(46, 204, 113, 0.3); }
        .btn-confirm:hover { background: #2ecc71; transform: translateY(-2px); }
        .btn-review { background: #111; color: #fff; }
        .btn-review:hover { background: #333; }
        .btn-support { background: #fff; color: #3498db; border: 1px solid #3498db; }
        .btn-support:hover { background: #ebf5fb; }
        .btn-support-pending { background: #f39c12; color: #fff; border: 1px solid #e67e22; }
        .btn-support-replied { background: #27ae60; color: #fff; border: 1px solid #2ecc71; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(46, 204, 113, 0); } 100% { box-shadow: 0 0 0 0 rgba(46, 204, 113, 0); } }
        
        .modal { display: none; position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .modal-content { background: #fff; border-radius: 16px; width: 90%; position: relative; animation: slideUp 0.3s ease; overflow: hidden; display: flex; flex-direction: column; max-height: 90vh; }
        @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { padding: 25px 30px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { margin: 0; font-size: 22px; color: #111; }
        .close-btn { font-size: 28px; cursor: pointer; color: #aaa; background: none; border: none; padding: 0; }
        .close-btn:hover { color: #111; }
        .modal-body { padding: 30px; overflow-y: auto; }
        .item-list { border: 1px solid #eaeaea; border-radius: 12px; overflow: hidden; }
        .item-row { display: flex; padding: 15px; border-bottom: 1px solid #eaeaea; align-items: center; }
        .item-row:last-child { border-bottom: none; }
        .item-row img { width: 60px; height: 60px; object-fit: contain; background: #f4f6f7; border-radius: 8px; margin-right: 15px; }
        .item-info { flex: 1; }
        .item-info h4 { margin: 0 0 5px 0; font-size: 15px; }
        .item-price { font-weight: bold; color: #111; }
        .custom-textarea { width: 100%; padding: 15px; border: 1px solid #ccc; border-radius: 8px; box-sizing: border-box; font-family: inherit; font-size: 14px; resize: vertical; height: 100px; margin-bottom: 15px; outline: none; }
        .custom-textarea:focus { border-color: #111; }
        .star-rating { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 5px; margin-bottom: 20px; }
        .star-rating input { display: none; }
        .star-rating label { font-size: 35px; color: #ddd; cursor: pointer; transition: color 0.2s; }
        .star-rating input:checked ~ label, .star-rating label:hover, .star-rating label:hover ~ label { color: #f1c40f; }
        
        .chat-container { max-height: 350px; overflow-y: auto; padding-right: 10px; margin-bottom: 20px; display: flex; flex-direction: column; gap: 15px; }
        .chat-bubble { max-width: 80%; padding: 12px 18px; border-radius: 20px; font-size: 14px; line-height: 1.5; position: relative; word-wrap: break-word; }
        .chat-customer { align-self: flex-end; background: #111; color: #fff; border-bottom-right-radius: 4px; }
        .chat-staff { align-self: flex-start; background: #f4f6f7; color: #111; border-bottom-left-radius: 4px; border: 1px solid #eaeaea; }
        .chat-time { font-size: 11px; opacity: 0.7; margin-top: 5px; display: block; }
    </style>
</head>
<body>

    <div class="modern-header">
        <a href="customer_home.php" class="brand-area"><img src="Logo(text).png?v=1" alt="Logo"></a>
        <div class="nav-links">
            <a href="customer_make_order.php">Products</a>
            <a href="customer_wishlist.php" style="color:#e74c3c;">❤️ Wishlist (<span id="nav-wish-count"><?php echo $total_wishlist_count; ?></span>)</a>
            <a href="customer_cart.php">Cart (<?php echo $total_cart_count; ?>)</a>
            <a href="customer_view_orders.php" class="active">My Orders</a>
            <a href="customer_profile.php" class="user-profile-btn">
                <div style="width:16px; height:24px; display:flex; align-items:center; justify-content:center; overflow:hidden; margin-right:5px;"><div class="mini-avatar"></div></div>
                <?php echo htmlspecialchars($user_name); ?>
            </a>
        </div>
    </div>

    <div class="container">
        <h1 class="page-title">My Order History</h1>
        
        <form method="GET" action="customer_view_orders.php" class="sort-toolbar">
            <label style="font-weight:bold; font-size:14px;">Sort orders by:</label>
            <select name="sort_by" class="custom-select">
                <option value="odate" <?php if($sort_by=='odate') echo 'selected'; ?>>Order Date</option>
                <option value="ototalamount" <?php if($sort_by=='ototalamount') echo 'selected'; ?>>Total Amount</option>
            </select>
            <select name="sort_order" class="custom-select">
                <option value="DESC" <?php if($sort_order=='DESC') echo 'selected'; ?>>Descending (Highest/Newest)</option>
                <option value="ASC" <?php if($sort_order=='ASC') echo 'selected'; ?>>Ascending (Lowest/Oldest)</option>
            </select>
            <button type="submit" class="btn btn-review" style="padding: 8px 20px;">Apply Filter</button>
        </form>

        <?php if (!empty($success_msg)): ?><div class="alert alert-success"><?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (!empty($error_msg)): ?><div class="alert alert-danger"><?php echo $error_msg; ?></div><?php endif; ?>

        <?php if (empty($orders_data)): ?>
            <div style="text-align:center; padding: 60px; background:#fff; border-radius:16px; border:1px dashed #ccc;">
                <h2 style="color:#111;">No orders found.</h2>
                <a href="customer_make_order.php" class="btn btn-review" style="margin-top:15px;">Start Shopping</a>
            </div>
        <?php else: ?>
            <?php foreach ($orders_data as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-info">
                            <h3>Order #<?php echo $order['oid']; ?></h3>
                            <p>Placed on: <?php echo date('M d, Y H:i', strtotime($order['odate'])); ?></p>
                            <p style="color:#e67e22; font-weight:bold; font-size:13px; margin-top:5px;">Est. Delivery: <?php echo date('M d, Y', strtotime($order['odeliverydate'])); ?></p>
                        </div>
                        <div>
                            <?php if ($order['ostatus'] == 1): ?> <span class="status-badge" style="background:#fef9e7; color:#f39c12; border:1px solid #fcf3cf;">⏳ Pending</span>
                            <?php elseif ($order['ostatus'] == 2): ?> <span class="status-badge" style="background:#e8f4fd; color:#2980b9; border:1px solid #d6eaf8;">🔨 Processing</span>
                            <?php elseif ($order['ostatus'] == 3): ?> <span class="status-badge" style="background:#e8f4fd; color:#2980b9; border:1px solid #d6eaf8;">🚚 Dispatched</span>
                            <?php elseif ($order['ostatus'] == 4): ?> <span class="status-badge" style="background:#fcf3cf; color:#d35400; border:1px solid #f5b041;">📦 Delivered</span>
                            <?php elseif ($order['ostatus'] == 5): ?> <span class="status-badge" style="background:#e8f8f5; color:#1abc9c; border:1px solid #d1f2eb;">✅ Completed</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="order-summary">
                        <div>
                            <div style="font-size: 13px; color: #7f8c8d; margin-bottom: 5px;">Total Amount</div>
                            <div class="summary-price">HKD <?php echo number_format($order['ototalamount'], 2); ?></div>
                        </div>
                        <div class="action-btns">
                            <button class="btn btn-details" onclick="openDetails(<?php echo $order['oid']; ?>)">📄 Details</button>
                            <?php if ($order['ostatus'] == 1): ?> <a href="customer_view_orders.php?cancel_oid=<?php echo $order['oid']; ?>" class="btn btn-cancel" onclick="return confirm('⚠️ Cancel this order?\n\nPlease note: This action is irreversible. Are you sure you want to proceed?');">❌ Cancel</a>
                            <?php elseif ($order['ostatus'] == 4): ?> <a href="customer_view_orders.php?confirm_oid=<?php echo $order['oid']; ?>" class="btn btn-confirm" onclick="return confirm('Confirm receipt?');">✅ Confirm Receipt</a>
                            <?php elseif ($order['ostatus'] == 5): ?> <button class="btn btn-review" onclick="openReview(<?php echo $order['oid']; ?>)">⭐ Rate Product</button>
                            <?php endif; ?>
                            
                            <button class="btn <?php echo ($order['ticket'] && $order['ticket']['status'] === 'Replied') ? 'btn-support-replied' : ($order['ticket'] ? 'btn-support-pending' : 'btn-support'); ?>" 
                                    onclick='openTicket(<?php echo json_encode($order); ?>)'>
                                <?php 
                                    if ($order['ticket']) {
                                        echo $order['ticket']['status'] === 'Replied' ? '✅ Staff Replied' : '💬 Track Issue';
                                    } else {
                                        echo '💬 Contact Support';
                                    }
                                ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="detailsModal" class="modal"><div class="modal-content" style="max-width: 700px;"><div class="modal-header"><h2 id="modalTitle">Order Details</h2><button class="close-btn" onclick="document.getElementById('detailsModal').style.display='none'">&times;</button></div><div class="modal-body"><div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;"><strong style="display:block; margin-bottom:5px;">📍 Delivery Address:</strong><span id="detailAddress"></span></div><h3 style="font-size: 16px; margin-top: 0;">Purchased Items:</h3><div class="item-list" id="detailItems"></div><div style="text-align: right; margin-top: 20px; font-size: 20px;"><strong>Total: </strong> <span style="color: #e74c3c; font-weight: 900;" id="detailTotal"></span></div></div></div></div>

    <div id="reviewModal" class="modal"><div class="modal-content" style="max-width: 500px;"><div class="modal-header"><h2>Product Review</h2><button class="close-btn" onclick="document.getElementById('reviewModal').style.display='none'">&times;</button></div><div class="modal-body"><form action="customer_view_orders.php" method="POST"><input type="hidden" id="reviewOid" name="review_oid" value=""><p style="margin-top: 0; font-weight: bold;">How satisfied are you with the furniture?</p><div class="star-rating"><input type="radio" id="star5" name="rating" value="5" checked><label for="star5">★</label><input type="radio" id="star4" name="rating" value="4"><label for="star4">★</label><input type="radio" id="star3" name="rating" value="3"><label for="star3">★</label><input type="radio" id="star2" name="rating" value="2"><label for="star2">★</label><input type="radio" id="star1" name="rating" value="1"><label for="star1">★</label></div><p style="font-weight: bold; margin-bottom: 8px;">Leave a review for other customers:</p><textarea name="comments" class="custom-textarea" placeholder="Tell us what you loved..." required></textarea><button type="submit" name="submit_review" class="btn btn-review" style="width: 100%; padding: 15px; font-size: 16px;">Submit Review</button></form></div></div></div>

    <div id="ticketModal" class="modal">
        <div class="modal-content" style="max-width: 550px;">
            <div class="modal-header">
                <h2>Support Team <span style="font-size:14px; font-weight:normal; color:#7f8c8d;">(Order #<span id="displayTicketOid"></span>)</span></h2>
                <button class="close-btn" onclick="document.getElementById('ticketModal').style.display='none'">&times;</button>
            </div>
            <div class="modal-body" style="background:#f0f2f5; display:flex; flex-direction:column; height: 500px; padding: 20px;">
                <div class="chat-container" id="chatHistoryBox" style="flex:1;">
                    </div>
                <div style="background:#fff; padding:15px; border-radius:12px; box-shadow:0 -2px 10px rgba(0,0,0,0.05); margin-top:10px;">
                    <form action="customer_view_orders.php" method="POST" style="display:flex; gap:10px;">
                        <input type="hidden" id="ticketFormOid" name="ticket_oid" value="">
                        <input type="text" name="message" placeholder="Type a message to support..." required style="flex:1; padding:12px 15px; border:1px solid #eaeaea; border-radius:20px; font-size:14px; outline:none; background:#f4f6f7;">
                        <button type="submit" name="submit_ticket" style="background:#111; color:#fff; border:none; padding:0 20px; border-radius:20px; font-weight:bold; cursor:pointer;">Send</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const ordersData = <?php echo json_encode($orders_data); ?>;

        function openDetails(oid) {
            const order = ordersData[oid];
            document.getElementById('modalTitle').innerText = 'Order #' + oid; document.getElementById('detailAddress').innerText = order.odeliveraddress; document.getElementById('detailTotal').innerText = 'HKD ' + parseFloat(order.ototalamount).toFixed(2);
            const itemsContainer = document.getElementById('detailItems'); itemsContainer.innerHTML = '';
            order.items.forEach(item => { const sub = parseFloat(item.fprice) * parseInt(item.oqty); itemsContainer.innerHTML += `<div class="item-row"><img src="${item.img}" onerror="this.style.display='none';"><div class="item-info"><h4>${item.fname}</h4><span style="color: #7f8c8d; font-size: 13px;">Qty: ${item.oqty}</span></div><div class="item-price">HKD ${sub.toFixed(2)}</div></div>`; });
            document.getElementById('detailsModal').style.display = 'flex';
        }

        function openReview(oid) { document.getElementById('reviewOid').value = oid; document.getElementById('reviewModal').style.display = 'flex'; }

        function openTicket(orderObj) {
            const oid = orderObj.oid;
            document.getElementById('displayTicketOid').innerText = oid;
            document.getElementById('ticketFormOid').value = oid;
            
            const chatBox = document.getElementById('chatHistoryBox');
            chatBox.innerHTML = ''; 

            if (orderObj.ticket && orderObj.ticket.chat_history && orderObj.ticket.chat_history.length > 0) {
                orderObj.ticket.chat_history.forEach(msg => {
                    if (msg.sender_role === 'customer') {
                        chatBox.innerHTML += `<div class="chat-bubble chat-customer">${msg.message}<span class="chat-time">${msg.created_at}</span></div>`;
                    } else {
                        chatBox.innerHTML += `<div class="chat-bubble chat-staff"><strong>Staff:</strong><br>${msg.message}<span class="chat-time">${msg.created_at}</span></div>`;
                    }
                });
            } else {
                chatBox.innerHTML = `<div style="text-align:center; color:#7f8c8d; font-size:13px; margin-top:20px;">Start a conversation with our support team regarding this order.</div>`;
            }
            document.getElementById('ticketModal').style.display = 'flex';
            setTimeout(() => { chatBox.scrollTop = chatBox.scrollHeight; }, 50);
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('detailsModal')) document.getElementById('detailsModal').style.display='none';
            if (event.target == document.getElementById('reviewModal')) document.getElementById('reviewModal').style.display='none';
            if (event.target == document.getElementById('ticketModal')) document.getElementById('ticketModal').style.display='none';
        }
    </script>
</body>
</html>