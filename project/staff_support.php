<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'staff') { header("Location: index.php"); exit; }
$staff_name = $_SESSION['user_name'] ?? 'Admin';
$success_msg = ""; $error_msg = "";

// ==========================================
// 1. 提交回复写入聊天记录表
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_ticket'])) {
    $tid = intval($_POST['tid']);
    $reply_msg = trim($_POST['reply_msg'] ?? '');
    if (!empty($reply_msg)) {
        try {
            $pdo->prepare("INSERT INTO TicketMessages (tid, sender_role, message) VALUES (?, 'staff', ?)")->execute([$tid, $reply_msg]);
            $pdo->prepare("UPDATE Tickets SET status = 'Replied' WHERE tid = ?")->execute([$tid]);
            $success_msg = "✅ Reply sent successfully!";
        } catch (Exception $e) { $error_msg = "🚨 Error: " . $e->getMessage(); }
    }
}

// ==========================================
// 🌟 2. 核心新增：处理“关闭/归档”工单请求
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_ticket'])) {
    $tid = intval($_POST['tid']);
    try {
        $pdo->prepare("UPDATE Tickets SET status = 'Closed' WHERE tid = ?")->execute([$tid]);
        $success_msg = "🗂️ Ticket #$tid has been marked as Resolved and moved to Archive.";
    } catch (Exception $e) { $error_msg = "🚨 Error: " . $e->getMessage(); }
}

// ==========================================
// 提取所有工单，并进行智能分类
// ==========================================
$active_tickets = [];
$closed_tickets = [];
$pending_count = 0;

try {
    // 拉取所有记录，包括未登录访客(Guest)
    $stmtTickets = $pdo->query("SELECT t.*, COALESCE(c.cname, 'Guest User') as cname, COALESCE(c.ctel, 'N/A') as ctel FROM Tickets t LEFT JOIN Customers c ON t.cid = c.cid ORDER BY FIELD(t.status, 'Pending') DESC, t.created_at DESC");
    $all_tickets = $stmtTickets->fetchAll();
    
    foreach ($all_tickets as $t) {
        $stmtMsg = $pdo->prepare("SELECT sender_role, message, created_at FROM TicketMessages WHERE tid = ? ORDER BY created_at ASC");
        $stmtMsg->execute([$t['tid']]);
        $t['chat_history'] = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);

        // 🌟 将工单分流到活跃和归档两个数组中
        if ($t['status'] === 'Closed') {
            $closed_tickets[] = $t;
        } else {
            $active_tickets[] = $t;
            if ($t['status'] === 'Pending') { $pending_count++; } // 统计待处理数量
        }
    }
} catch (Exception $e) { die("Database Error: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Support - Staff Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; background: #f0f2f5; display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 260px; background: #1a252f; color: #ecf0f1; display: flex; flex-direction: column; box-shadow: 2px 0 10px rgba(0,0,0,0.1); z-index: 100; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid #2c3e50; background: #141d26; }
        .sidebar-menu { flex: 1; padding: 20px 0; overflow-y: auto; }
        .sidebar-menu a { display: block; padding: 15px 25px; color: #bdc3c7; text-decoration: none; font-size: 15px; font-weight: 600; border-left: 4px solid transparent; transition: 0.2s; display: flex; justify-content: space-between; align-items: center;}
        .sidebar-menu a:hover { background: #2c3e50; color: #fff; }
        .sidebar-menu a.active { background: #2980b9; color: #fff; border-left-color: #3498db; }
        .nav-badge { background: #e74c3c; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-navbar { background: #fff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .btn-logout { background: #e74c3c; color: #fff; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: bold; }
        .content-body { padding: 30px; max-width: 1200px; margin: 0 auto; width: 100%; box-sizing: border-box; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; font-weight: bold; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        
        /* 🌟 Tab 导航栏样式 */
        .tab-container { display: flex; gap: 25px; margin-bottom: 25px; border-bottom: 2px solid #eaeaea; padding-bottom: 10px; }
        .tab-btn { background: none; border: none; font-size: 18px; font-weight: 800; color: #7f8c8d; cursor: pointer; padding: 5px 10px; transition: 0.2s; position: relative; }
        .tab-btn.active { color: #2c3e50; }
        .tab-btn.active::after { content: ''; position: absolute; bottom: -12px; left: 0; width: 100%; height: 4px; background: #3498db; border-radius: 4px; }
        .tab-btn:hover:not(.active) { color: #3498db; }

        .ticket-card { background: #fff; border-radius: 12px; margin-bottom: 25px; border: 1px solid #eaeaea; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .ticket-header { padding: 15px 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #fafbfc; }
        .ticket-header h3 { margin: 0; font-size: 18px; color: #2c3e50; }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .badge-pending { background: #fef9e7; color: #f39c12; border: 1px solid #fcf3cf; }
        .badge-replied { background: #e8f8f5; color: #27ae60; border: 1px solid #d1f2eb; }
        .badge-closed { background: #f4f6f7; color: #7f8c8d; border: 1px solid #bdc3c7; }

        .ticket-body { display: flex; padding: 0; height: 350px; }
        .customer-info { width: 300px; background: #fdfbf7; padding: 25px; border-right: 1px solid #eee; }
        .info-row { margin-bottom: 10px; font-size: 14px; color: #555; }
        .info-row strong { color: #2c3e50; display: block; margin-bottom: 3px; }
        
        .chat-section { flex: 1; display: flex; flex-direction: column; background: #fff; }
        .chat-history { flex: 1; padding: 25px; overflow-y: auto; background: #f4f6f7; display: flex; flex-direction: column; gap: 15px; }
        .chat-bubble { max-width: 75%; padding: 12px 18px; border-radius: 15px; font-size: 14px; line-height: 1.5; }
        .chat-customer { align-self: flex-start; background: #fff; border: 1px solid #ddd; border-bottom-left-radius: 4px; }
        .chat-staff { align-self: flex-end; background: #dcf8c6; border: 1px solid #a3e4d7; border-bottom-right-radius: 4px; }
        .chat-time { display: block; font-size: 11px; color: #999; margin-top: 5px; }

        .chat-input { padding: 20px; border-top: 1px solid #eee; background: #fff; }
        .custom-textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; box-sizing: border-box; font-family: inherit; font-size: 14px; resize: vertical; height: 80px; margin-bottom: 10px; outline: none; }
        .btn-reply { background: #3498db; color: #fff; border: none; padding: 10px 25px; border-radius: 6px; font-weight: bold; cursor: pointer; float: right; }
        .btn-reply:hover { background: #2980b9; }

        .btn-close-ticket { background: #fff; color: #7f8c8d; border: 1px solid #ccc; padding: 8px 20px; border-radius: 20px; font-size: 13px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-close-ticket:hover { background: #f4f6f7; color: #111; border-color: #111; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h3 style="margin:0; color:#fff;">Premium Living</h3></div>
        <div class="sidebar-menu">
            <a href="staff_update_order.php">📦 Manage Orders</a>
            <a href="staff_insert_item.php">🛋️ Add Furniture</a>
            <a href="staff_insert_material.php">🛠️ Add Materials</a>
            <a href="staff_delete_item.php">🗑️ Delete Catalog</a>
            <a href="staff_generate_report.php">📊 Sales Reports</a>
            <a href="staff_support.php" class="active">
                <span>💬 Customer Support</span>
                <?php if($pending_count > 0): ?><span class="nav-badge"><?php echo $pending_count; ?></span><?php endif; ?>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-navbar"><h2>Live Support Dashboard</h2><a href="index.php?logout=1" class="btn-logout">Logout</a></div>
        <div class="content-body">
            <?php if (!empty($success_msg)): ?><div class="alert alert-success"><?php echo $success_msg; ?></div><?php endif; ?>
            
            <div class="tab-container">
                <button class="tab-btn active" onclick="switchTab('active')" id="tabBtnActive">📬 Active Inbox (<?php echo count($active_tickets); ?>)</button>
                <button class="tab-btn" onclick="switchTab('closed')" id="tabBtnClosed">🗂️ Archived (<?php echo count($closed_tickets); ?>)</button>
            </div>

            <div id="viewActive">
                <?php if (empty($active_tickets)): ?>
                    <div style="text-align:center; padding: 60px; background:#fff; border-radius:12px; border:1px dashed #ccc;">
                        <h2 style="color:#2c3e50;">Inbox is Empty! 🎉</h2>
                        <p style="color:#7f8c8d;">You have replied to all customer inquiries.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($active_tickets as $ticket): ?>
                        <div class="ticket-card">
                            <div class="ticket-header">
                                <h3>🎫 Ticket #<?php echo $ticket['tid']; ?> 
                                    <?php if ($ticket['oid'] == 0): ?><span style="color:#8e44ad;">(Pre-sales Inquiry)</span>
                                    <?php else: ?>(Order #<?php echo $ticket['oid']; ?>)<?php endif; ?>
                                </h3>
                                <div>
                                    <span class="badge <?php echo $ticket['status'] === 'Pending' ? 'badge-pending' : 'badge-replied'; ?>">
                                        <?php echo $ticket['status'] === 'Pending' ? '⏳ Needs Attention' : '✅ Replied'; ?>
                                    </span>
                                    
                                    <?php if ($ticket['status'] === 'Replied'): ?>
                                        <form action="staff_support.php" method="POST" style="display:inline-block; margin-left:15px;">
                                            <input type="hidden" name="tid" value="<?php echo $ticket['tid']; ?>">
                                            <button type="submit" name="close_ticket" class="btn-close-ticket" onclick="return confirm('Archive this ticket? It will be moved out of your inbox.');">🗂️ Mark as Resolved</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="ticket-body">
                                <div class="customer-info">
                                    <div class="info-row"><strong>Customer Name</strong> <?php echo htmlspecialchars($ticket['cname']); ?></div>
                                    <div class="info-row"><strong>Phone Number</strong> <?php echo htmlspecialchars($ticket['ctel']); ?></div>
                                    <?php if ($ticket['oid'] == 0): ?>
                                        <div class="info-row"><strong>Inquiry Type</strong> <span style="color:#8e44ad; font-weight:bold;">Pre-sales Consultation</span></div>
                                    <?php else: ?>
                                        <div class="info-row"><strong>Order Ref</strong> <a href="staff_update_order.php" style="color:#3498db; font-weight:bold;">#<?php echo $ticket['oid']; ?></a></div>
                                    <?php endif; ?>
                                    <div class="info-row" style="margin-top:20px; font-size:12px; color:#999;">Created at:<br><?php echo $ticket['created_at']; ?></div>
                                </div>

                                <div class="chat-section">
                                    <div class="chat-history">
                                        <?php foreach($ticket['chat_history'] as $msg): ?>
                                            <div class="chat-bubble <?php echo $msg['sender_role'] === 'customer' ? 'chat-customer' : 'chat-staff'; ?>">
                                                <strong><?php echo $msg['sender_role'] === 'customer' ? 'Customer:' : 'You:'; ?></strong><br>
                                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                                <span class="chat-time"><?php echo $msg['created_at']; ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="chat-input">
                                        <form action="staff_support.php" method="POST">
                                            <input type="hidden" name="tid" value="<?php echo $ticket['tid']; ?>">
                                            <textarea name="reply_msg" class="custom-textarea" placeholder="Type your reply here..." required></textarea>
                                            <button type="submit" name="reply_ticket" class="btn-reply">Send Reply</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div id="viewClosed" style="display:none;">
                <?php if (empty($closed_tickets)): ?>
                    <div style="text-align:center; padding: 60px; background:#fff; border-radius:12px; border:1px dashed #ccc;">
                        <h2 style="color:#2c3e50;">No Archived Tickets</h2>
                    </div>
                <?php else: ?>
                    <?php foreach ($closed_tickets as $ticket): ?>
                        <div class="ticket-card" style="opacity: 0.85;">
                            <div class="ticket-header" style="background:#f4f6f7;">
                                <h3>🎫 Ticket #<?php echo $ticket['tid']; ?> <span style="font-size:14px; color:#7f8c8d;">(Closed)</span></h3>
                                <span class="badge badge-closed">🗂️ Archived</span>
                            </div>

                            <div class="ticket-body" style="height: 250px;">
                                <div class="customer-info" style="background:transparent;">
                                    <div class="info-row"><strong>Customer Name</strong> <?php echo htmlspecialchars($ticket['cname']); ?></div>
                                    <?php if ($ticket['oid'] == 0): ?>
                                        <div class="info-row"><strong>Inquiry Type</strong> <span style="color:#8e44ad; font-weight:bold;">Pre-sales</span></div>
                                    <?php else: ?>
                                        <div class="info-row"><strong>Order Ref</strong> #<?php echo $ticket['oid']; ?></div>
                                    <?php endif; ?>
                                    <div class="info-row" style="margin-top:20px; font-size:12px; color:#999;">Closed Record</div>
                                </div>

                                <div class="chat-section" style="background:#fafafa;">
                                    <div class="chat-history">
                                        <?php foreach($ticket['chat_history'] as $msg): ?>
                                            <div class="chat-bubble <?php echo $msg['sender_role'] === 'customer' ? 'chat-customer' : 'chat-staff'; ?>" style="opacity:0.9;">
                                                <strong><?php echo $msg['sender_role'] === 'customer' ? 'Customer:' : 'Staff:'; ?></strong><br>
                                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                                <span class="chat-time"><?php echo $msg['created_at']; ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div style="padding:15px; text-align:center; color:#7f8c8d; font-size:13px; font-weight:bold; border-top:1px solid #eee;">
                                        This conversation is archived. If the customer replies, it will automatically reopen.
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>
    
    <script>
        // Tab 切换逻辑
        function switchTab(tabName) {
            document.getElementById('viewActive').style.display = (tabName === 'active') ? 'block' : 'none';
            document.getElementById('viewClosed').style.display = (tabName === 'closed') ? 'block' : 'none';
            
            document.getElementById('tabBtnActive').classList.toggle('active', tabName === 'active');
            document.getElementById('tabBtnClosed').classList.toggle('active', tabName === 'closed');
        }

        // 自动将所有的聊天记录滚动到底部
        document.querySelectorAll('.chat-history').forEach(box => { box.scrollTop = box.scrollHeight; });
    </script>
</body>
</html>