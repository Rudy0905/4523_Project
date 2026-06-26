<?php
session_start();
require_once 'db_connect.php'; 

$is_logged_in = false;
$user_name = '';
$customer_id = 0; 
$total_cart_count = 0;
$total_wishlist_count = 0;

if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'customer') {
    $is_logged_in = true;
    $user_name = $_SESSION['user_name'];
    $customer_id = $_SESSION['user_id'];
    if (!empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) { $total_cart_count += $item['qty']; }
    }
    if (isset($_SESSION['wishlist'])) {
        $total_wishlist_count = count($_SESSION['wishlist']);
    }
}

// 🌟 售前聊天逻辑 (复用工单系统)
if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_presales'])) {
    $msg = trim($_POST['presales_msg'] ?? '');
    if (!empty($msg)) {
        try {
            if (!isset($_SESSION['presales_tid'])) {
                $stmt = $pdo->prepare("INSERT INTO Tickets (oid, cid, status, created_at) VALUES (0, ?, 'Pending', NOW())");
                $stmt->execute([$customer_id]);
                $_SESSION['presales_tid'] = $pdo->lastInsertId();
            } else {
                $pdo->prepare("UPDATE Tickets SET status = 'Pending' WHERE tid = ?")->execute([$_SESSION['presales_tid']]);
            }
            $stmtMsg = $pdo->prepare("INSERT INTO TicketMessages (tid, sender_role, message) VALUES (?, 'customer', ?)");
            $stmtMsg->execute([$_SESSION['presales_tid'], $msg]);
            
            header("Location: customer_home.php?chat_open=1");
            exit;
        } catch (Exception $e) { echo "Error: " . $e->getMessage(); }
    }
}

// 提取当前用户的售前聊天记录
$presales_chat = [];
if ($is_logged_in && isset($_SESSION['presales_tid'])) {
    $stmtHistory = $pdo->prepare("SELECT sender_role, message, created_at FROM TicketMessages WHERE tid = ? ORDER BY created_at ASC");
    $stmtHistory->execute([$_SESSION['presales_tid']]);
    $presales_chat = $stmtHistory->fetchAll();
}

function getLink($is_logged_in, $params = "") {
    if (!$is_logged_in) return "index.php";
    return "customer_make_order.php" . ($params ? "?$params" : "");
}

$cart_url     = $is_logged_in ? "customer_cart.php" : "index.php";
$order_url    = $is_logged_in ? "customer_view_orders.php" : "index.php";
$wishlist_url = $is_logged_in ? "customer_wishlist.php" : "index.php";

// 判断是否需要自动打开聊天框
$auto_open_chat = isset($_GET['chat_open']) && $_GET['chat_open'] == '1' ? 'flex' : 'none';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Premium Living - Welcome Home</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background-color: #f9f9f9; color: #333; overflow-x: hidden; }
        .modern-header { background-color: #ffffff; padding: 5px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eaeaea; position: sticky; top: 0; z-index: 1000; }
        .brand-area { display: flex; align-items: center; text-decoration: none; }
        .brand-area img { height: 130px; width: auto; object-fit: contain; margin: -20px 0; }
        .nav-links { display: flex; align-items: center; gap: 30px; }
        .nav-links a { text-decoration: none; color: #111; font-weight: 600; font-size: 15px; transition: color 0.2s; }
        .nav-links a:hover { color: #f39c12; }
        .user-profile-btn { display: flex; align-items: center; gap: 8px; background: #f4f6f7; padding: 8px 15px; border-radius: 20px; text-decoration: none; color: #111; font-weight: bold; font-size: 14px; transition: background 0.2s; }
        .user-profile-btn:hover { background: #e2e6e9; }
        .btn-login-guest { background-color: #111; color: #fff !important; }
        .btn-login-guest:hover { background-color: #333; }
        .mini-avatar { width: 16px; height: 16px; background-color: #7f8c8d; border-radius: 50%; position: relative; }
        .mini-avatar::after { content: ''; position: absolute; width: 24px; height: 10px; background-color: #7f8c8d; border-radius: 12px 12px 0 0; bottom: -12px; left: -4px; }

        .home-container { max-width: 1300px; margin: 30px auto; padding: 0 20px; }
        .slider-container { position: relative; height: 550px; border-radius: 16px; overflow: hidden; margin-bottom: 50px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); background-color: #000; }
        .slide { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; transition: opacity 0.8s ease-in-out; display: flex; align-items: center; padding-left: 80px; box-sizing: border-box; background-size: cover; background-position: center; z-index: 0; }
        .slide.active { opacity: 1; z-index: 1; }
        .slide::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to right, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.3) 55%, rgba(0,0,0,0) 100%); z-index: 0; }
        .slide-content { position: relative; z-index: 2; color: white; max-width: 550px; }
        .slide-content h2 { font-size: 54px; margin: 0 0 15px 0; line-height: 1.1; font-weight: 800; }
        .slide-content p { font-size: 18px; margin: 0 0 35px 0; line-height: 1.6; color: #eee; }
        .btn-shop-now { background-color: #ffffff; color: #111; padding: 15px 40px; font-size: 16px; font-weight: 800; text-decoration: none; border-radius: 30px; display: inline-block; transition: all 0.3s ease; border: 2px solid #ffffff; }
        .btn-shop-now:hover { background-color: transparent; color: #ffffff; }
        .slider-btn { position: absolute; top: 50%; transform: translateY(-50%); background-color: rgba(200, 200, 200, 0.4); color: #ffffff; border: none; width: 50px; height: 50px; border-radius: 50%; font-size: 20px; font-weight: bold; cursor: pointer; z-index: 10; display: flex; align-items: center; justify-content: center; opacity: 0; transition: 0.3s; }
        .slider-container:hover .slider-btn { opacity: 1; }
        .slider-btn:hover { background-color: rgba(200, 200, 200, 0.8); }
        .slider-btn.prev { left: 20px; }
        .slider-btn.next { right: 20px; }
        .section-title { font-size: 28px; font-weight: 800; margin-bottom: 25px; margin-top: 50px; color: #111; border-bottom: 2px solid #111; padding-bottom: 10px; display: inline-block; }
        .category-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .category-card { position: relative; height: 350px; border-radius: 12px; overflow: hidden; display: flex; align-items: flex-end; padding: 30px; text-decoration: none; background-color: #eee; }
        .category-card img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s ease; z-index: 0; }
        .category-card:hover img { transform: scale(1.05); }
        .category-card::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to top, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0) 40%); z-index: 1; }
        .category-title { position: relative; z-index: 2; color: white; font-size: 24px; font-weight: 800; }

        /* 恢复丢失的样式 */
        .popular-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 60px; }
        .popular-card { background: #fff; border-radius: 8px; text-decoration: none; color: #333; overflow: hidden; transition: box-shadow 0.3s ease, transform 0.3s ease; border: 1px solid #eaeaea; }
        .popular-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
        .popular-card img { width: 100%; height: 200px; object-fit: cover; background-color: #f4f6f7; border-bottom: 1px solid #eaeaea; }
        .popular-info { padding: 15px; }
        .popular-info h3 { margin: 0 0 5px 0; font-size: 16px; font-weight: 700; color: #111; }
        .popular-info p { margin: 0; color: #e74c3c; font-weight: bold; font-size: 16px; }

        .values-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 80px; }
        .value-card { background: #fff; padding: 30px 20px; border-radius: 12px; text-align: center; border: 1px solid #eaeaea; transition: transform 0.3s; }
        .value-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .value-icon { font-size: 40px; margin-bottom: 15px; }
        .value-card h4 { margin: 0 0 10px 0; font-size: 18px; color: #111; }
        .value-card p { margin: 0; font-size: 14px; color: #7f8c8d; line-height: 1.5; }

        .newsletter-section { background: #111; color: #fff; padding: 60px 20px; text-align: center; border-radius: 16px; margin-bottom: 60px; box-shadow: 0 15px 30px rgba(0,0,0,0.15); }
        .newsletter-section h2 { margin: 0 0 15px 0; font-size: 32px; font-weight: 800; }
        .newsletter-section p { color: #ccc; margin: 0 0 30px 0; font-size: 16px; }
        .newsletter-form { display: flex; max-width: 500px; margin: 0 auto; gap: 10px; }
        .newsletter-form input { flex: 1; padding: 15px 20px; border: none; border-radius: 30px; font-size: 16px; outline: none; }
        .newsletter-form button { background: #f1c40f; color: #111; border: none; padding: 15px 30px; border-radius: 30px; font-size: 16px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .newsletter-form button:hover { background: #f39c12; }

        .modern-footer { background: #ffffff; border-top: 1px solid #eaeaea; padding: 60px 40px 20px 40px; }
        .footer-content { max-width: 1300px; margin: 0 auto; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 40px; margin-bottom: 40px; }
        .footer-col p { color: #7f8c8d; font-size: 14px; line-height: 1.6; margin-top: 15px; max-width: 300px; }
        .footer-col h4 { margin: 0 0 20px 0; color: #111; font-size: 16px; font-weight: 800; }
        .footer-col a { display: block; color: #7f8c8d; text-decoration: none; margin-bottom: 12px; font-size: 14px; transition: color 0.2s; }
        .footer-col a:hover { color: #111; font-weight: 600; }
        .footer-bottom { text-align: center; padding-top: 20px; border-top: 1px solid #eaeaea; color: #999; font-size: 13px; max-width: 1300px; margin: 0 auto; }

        /* ==========================================
           🌟 悬浮式弹窗客服 (FAB)
           ========================================== */
        .floating-chat-btn {
            position: fixed; bottom: 30px; right: 30px; background: #111; color: #fff; border: none;
            padding: 15px 25px; border-radius: 30px; font-weight: bold; font-size: 16px; cursor: pointer;
            box-shadow: 0 10px 20px rgba(0,0,0,0.15); z-index: 2000; transition: transform 0.2s, background 0.2s;
            display: flex; align-items: center; gap: 10px;
        }
        .floating-chat-btn:hover { transform: translateY(-3px); background: #333; }
        
        .floating-chat-window {
            position: fixed; bottom: 90px; right: 30px; width: 350px; height: 500px; background: #fff;
            border-radius: 16px; box-shadow: 0 15px 35px rgba(0,0,0,0.2); z-index: 2000; display: none;
            flex-direction: column; overflow: hidden; border: 1px solid #eaeaea;
            animation: popUp 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes popUp { from { opacity: 0; transform: translateY(20px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
        
        .fc-header { background: #3498db; color: #fff; padding: 20px; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
        .fc-close { cursor: pointer; font-size: 20px; transition: 0.2s; }
        .fc-close:hover { transform: scale(1.2); }
        
        .fc-body { flex: 1; padding: 20px; overflow-y: auto; background: #f9f9f9; display: flex; flex-direction: column; gap: 15px; }
        .chat-bubble { max-width: 85%; padding: 12px 16px; border-radius: 16px; font-size: 14px; line-height: 1.4; word-wrap: break-word; }
        .chat-user { align-self: flex-end; background: #111; color: #fff; border-bottom-right-radius: 4px; }
        .chat-staff { align-self: flex-start; background: #fff; color: #333; border: 1px solid #ddd; border-bottom-left-radius: 4px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .chat-time { display: block; font-size: 11px; opacity: 0.6; margin-top: 5px; }
        
        .fc-input-area { padding: 15px; background: #fff; border-top: 1px solid #eee; display: flex; gap: 10px; }
        .fc-input { flex: 1; padding: 12px 15px; border: 1px solid #ddd; border-radius: 20px; font-size: 14px; outline: none; }
        .fc-send-btn { background: #3498db; color: #fff; border: none; padding: 0 20px; border-radius: 20px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .fc-send-btn:hover { background: #2980b9; }
    </style>
</head>
<body>

    <!-- 顶部导航 -->
    <div class="modern-header">
        <a href="customer_home.php" class="brand-area"><img src="Logo(text).png?v=1" alt="Logo"></a>
        <div class="nav-links">
            <a href="<?php echo getLink($is_logged_in); ?>">Products</a>
            <?php if ($is_logged_in): ?>
                <a href="<?php echo $wishlist_url; ?>" style="color:#e74c3c;">❤️ Wishlist (<span id="nav-wish-count"><?php echo $total_wishlist_count; ?></span>)</a>
            <?php endif; ?>
            <a href="<?php echo $cart_url; ?>">Cart <?php echo $is_logged_in ? "($total_cart_count)" : ""; ?></a>
            <a href="<?php echo $order_url; ?>">My Orders</a>
            <?php if ($is_logged_in): ?>
                <a href="customer_profile.php" class="user-profile-btn">
                    <div style="width:16px; height:24px; display:flex; align-items:center; justify-content:center; overflow:hidden; margin-right:5px;"><div class="mini-avatar"></div></div>
                    <?php echo htmlspecialchars($user_name); ?>
                </a>
            <?php else: ?>
                <a href="index.php" class="user-profile-btn btn-login-guest">Login / Sign In</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 主页专用的悬浮式气泡聊天窗口 -->
    <?php if ($is_logged_in): ?>
    <button class="floating-chat-btn" onclick="toggleChat()">💬 Chat with us</button>
    
    <div class="floating-chat-window" id="floatingChatWindow" style="display: <?php echo $auto_open_chat; ?>;">
        <div class="fc-header">
            <div>
                Live Support<br>
                <span style="font-size: 11px; font-weight: normal; opacity: 0.8;">We typically reply in minutes</span>
            </div>
            <span class="fc-close" onclick="toggleChat()">✖</span>
        </div>
        
        <div class="fc-body" id="chatBody">
            <div class="chat-bubble chat-staff">
                Hi <?php echo htmlspecialchars($user_name); ?>! 👋 Need help picking the right furniture? I'm here!
            </div>
            <?php foreach ($presales_chat as $msg): ?>
                <?php if ($msg['sender_role'] === 'customer'): ?>
                    <div class="chat-bubble chat-user">
                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                        <span class="chat-time"><?php echo date('H:i', strtotime($msg['created_at'])); ?></span>
                    </div>
                <?php else: ?>
                    <div class="chat-bubble chat-staff">
                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                        <span class="chat-time"><?php echo date('H:i', strtotime($msg['created_at'])); ?></span>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        
        <form action="customer_home.php" method="POST" class="fc-input-area">
            <input type="text" name="presales_msg" class="fc-input" placeholder="Ask a question..." required>
            <button type="submit" name="send_presales" class="fc-send-btn">Send</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- 主页内容 -->
    <div class="home-container">
        <!-- 轮播图 -->
        <div class="slider-container">
            <button class="slider-btn prev" onclick="changeSlide(-1)">&#10094;</button>
            <button class="slider-btn next" onclick="changeSlide(1)">&#10095;</button>

            <div class="slide active" style="background-image: url('https://images.unsplash.com/photo-1586023492125-27b2c045efd7?auto=format&fit=crop&w=1600&q=80');">
                <div class="slide-content">
                    <h2>Bring your dream<br>home to life.</h2>
                    <p>Discover our new collection of premium, customizable furniture designed for modern living.</p>
                    <a href="<?php echo getLink($is_logged_in, 'room=living'); ?>" class="btn-shop-now">Shop Living Room</a>
                </div>
            </div>
            <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1600&q=80');">
                <div class="slide-content">
                    <h2>Dine with<br>absolute elegance.</h2>
                    <p>Experience our solid oak dining tables. Perfect for family gatherings.</p>
                    <a href="<?php echo getLink($is_logged_in, 'room=dining'); ?>" class="btn-shop-now">Explore Dining</a>
                </div>
            </div>
            <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1505693314120-0d443867891c?auto=format&fit=crop&w=1600&q=80');">
                <div class="slide-content">
                    <h2>Sleep in<br>pure comfort.</h2>
                    <p>Sturdy, beautifully crafted queen size bed frames designed to give you the rest you deserve.</p>
                    <a href="<?php echo getLink($is_logged_in, 'room=bedroom'); ?>" class="btn-shop-now">View Bedrooms</a>
                </div>
            </div>
        </div>

        <!-- 分类浏览 -->
        <div style="width: 100%;"><h2 class="section-title">Shop by Room</h2></div>
        <div class="category-grid">
            <a href="<?php echo getLink($is_logged_in, 'room=living'); ?>" class="category-card"><img src="https://images.unsplash.com/photo-1493663284031-b7e3aefcae8e?auto=format&fit=crop&w=600&q=80" alt="Living Room"><span class="category-title">Living Room</span></a>
            <a href="<?php echo getLink($is_logged_in, 'room=bedroom'); ?>" class="category-card"><img src="https://images.unsplash.com/photo-1540518614846-7eded433c457?auto=format&fit=crop&w=600&q=80" alt="Bedroom"><span class="category-title">Bedroom</span></a>
            <a href="<?php echo getLink($is_logged_in, 'room=dining'); ?>" class="category-card"><img src="https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=600&q=80" alt="Dining Room"><span class="category-title">Dining Room</span></a>
            <a href="<?php echo getLink($is_logged_in, 'room=study'); ?>" class="category-card"><img src="https://images.unsplash.com/photo-1595428774223-ef52624120d2?auto=format&fit=crop&w=600&q=80" onerror="this.src='https://images.unsplash.com/photo-1583847268964-b28dc8f51f92?auto=format&fit=crop&w=600&q=80';" alt="Storage & Study"><span class="category-title">Study / Office</span></a>
        </div>

        <!-- 🌟 恢复：Popular Furniture 热门商品 -->
        <div style="width: 100%;"><h2 class="section-title">Popular Furniture</h2></div>
        <div class="popular-grid">
            <a href="<?php echo getLink($is_logged_in); ?>" class="popular-card">
                <img src="WoodenRider1.png" alt="Oak Dining Chair">
                <div class="popular-info">
                    <h3>Oak Dining Chair</h3>
                    <p>HKD 450.00</p>
                </div>
            </a>
            <a href="<?php echo getLink($is_logged_in); ?>" class="popular-card">
                <img src="Sofa1.png" alt="Fabric Sofa">
                <div class="popular-info">
                    <h3>3-Seater Fabric Sofa</h3>
                    <p>HKD 3,800.00</p>
                </div>
            </a>
            <a href="<?php echo getLink($is_logged_in); ?>" class="popular-card">
                <img src="WoodenBed1.png" alt="Queen Size Bed">
                <div class="popular-info">
                    <h3>Queen Size Bed Frame</h3>
                    <p>HKD 2,200.00</p>
                </div>
            </a>
            <a href="<?php echo getLink($is_logged_in); ?>" class="popular-card">
                <img src="WoodenTable1.png" alt="Dining Table">
                <div class="popular-info">
                    <h3>Large Dining Table</h3>
                    <p>HKD 2,500.00</p>
                </div>
            </a>
        </div>

        <!-- 🌟 恢复：Why Premium Living 品牌优势 -->
        <div style="width: 100%; margin-top: 20px;"><h2 class="section-title">Why Premium Living?</h2></div>
        <div class="values-grid">
            <div class="value-card"><div class="value-icon">🚚</div><h4>Free Delivery</h4><p>Enjoy free shipping across Hong Kong on all orders over HKD 5,000.</p></div>
            <div class="value-card"><div class="value-icon">🌳</div><h4>Sustainable Wood</h4><p>We source our raw materials responsibly from certified sustainable forests.</p></div>
            <div class="value-card"><div class="value-icon">🛡️</div><h4>10-Year Warranty</h4><p>Quality you can trust. Our solid wood frames are guaranteed for a decade.</p></div>
            <div class="value-card"><div class="value-icon">👩‍🎨</div><h4>Expert Support</h4><p>Our Home Planning Specialists are here to help you design your dream space.</p></div>
        </div>

        <!-- 🌟 恢复：Newsletter 订阅框 -->
        <div class="newsletter-section">
            <h2>Join the Premium Living Club</h2>
            <p>Subscribe to our newsletter to receive the latest design inspiration, product updates, and exclusive offers.</p>
            <form class="newsletter-form" onsubmit="event.preventDefault(); alert('🎉 Thank you for subscribing!');">
                <input type="email" placeholder="Enter your email address" required>
                <button type="submit">Subscribe</button>
            </form>
        </div>
    </div>

    <!-- 🌟 恢复：完整页脚 -->
    <footer class="modern-footer">
        <div class="footer-content">
            <div class="footer-col">
                <img src="Logo(text).png?v=1" style="height: 65px; margin-left: -15px;" alt="Logo">
                <p>Established in 2012, Premium Living Furniture Co. Ltd is a premier manufacturer of high-quality furniture and household goods.</p>
                <p>Headquartered in Hong Kong, we operate factories across Mainland China, Vietnam, Thailand, and the Philippines.</p>
            </div>
            <div class="footer-col">
                <h4>Customer Service</h4><a href="#">Track My Order</a><a href="#">Returns & Exchanges</a><a href="#">Shipping Information</a><a href="#">Home Planning Service</a>
            </div>
            <div class="footer-col">
                <h4>About Us</h4><a href="#">Our Story</a><a href="#">Sustainability</a><a href="#">Careers</a><a href="#">Press & Media</a>
            </div>
            <div class="footer-col">
                <h4>Contact Us</h4>
                <p style="margin-top: 0; color: #111;"><strong>Email:</strong><br>support@premiumliving.com.hk</p>
                <p style="color: #111;"><strong>Phone:</strong><br>+852 1234 5678</p>
                <p style="color: #111;"><strong>Working Hours:</strong><br>Mon-Fri, 9:00 AM - 6:00 PM</p>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; 2026 Premium Living Furniture Co. Ltd. All rights reserved. | IT114105 Software Engineering Project
        </div>
    </footer>

    <script>
        // 控制主页悬浮窗的开启与关闭
        function toggleChat() {
            var win = document.getElementById('floatingChatWindow');
            if (win.style.display === 'none' || win.style.display === '') {
                win.style.display = 'flex';
                // 打开时滚到底部
                const chatBody = document.getElementById('chatBody');
                if (chatBody) { chatBody.scrollTop = chatBody.scrollHeight; }
            } else {
                win.style.display = 'none';
            }
        }
        
        // 如果页面加载带有聊天标记，自动滚到底部
        const chatBody = document.getElementById('chatBody');
        if (chatBody && chatBody.offsetParent !== null) { chatBody.scrollTop = chatBody.scrollHeight; }

        // 轮播图控制
        const slides = document.querySelectorAll('.slide');
        let currentSlide = 0; const slideInterval = 5000; let slideTimer;
        function showSlide(index) { slides[currentSlide].classList.remove('active'); currentSlide = (index + slides.length) % slides.length; slides[currentSlide].classList.add('active'); }
        function nextSlide() { showSlide(currentSlide + 1); }
        function changeSlide(direction) { showSlide(currentSlide + direction); resetTimer(); }
        function resetTimer() { clearInterval(slideTimer); slideTimer = setInterval(nextSlide, slideInterval); }
        document.addEventListener('DOMContentLoaded', function() { slideTimer = setInterval(nextSlide, slideInterval); });
    </script>
</body>
</html>