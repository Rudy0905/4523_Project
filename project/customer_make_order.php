<?php
session_start();
require_once 'db_connect.php'; 

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: index.php"); exit;
}
$is_logged_in = true; 
$user_name = $_SESSION['user_name'] ?? 'User';
$customer_id = $_SESSION['user_id'];

if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }
if (!isset($_SESSION['wishlist'])) { $_SESSION['wishlist'] = []; }

// 售前咨询
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_presales'])) {
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
            header("Location: customer_make_order.php?chat_open=1"); exit;
        } catch (Exception $e) { echo "Error: " . $e->getMessage(); }
    }
}

$presales_chat = [];
if (isset($_SESSION['presales_tid'])) {
    $stmtHistory = $pdo->prepare("SELECT sender_role, message, created_at FROM TicketMessages WHERE tid = ? ORDER BY created_at ASC");
    $stmtHistory->execute([$_SESSION['presales_tid']]);
    $presales_chat = $stmtHistory->fetchAll();
}
$auto_open_chat = isset($_GET['chat_open']) && $_GET['chat_open'] == '1' ? 'right: 0;' : '';

// 收藏与购物车
if (isset($_GET['ajax_wishlist'])) {
    $fid = intval($_GET['fid']);
    if (($key = array_search($fid, $_SESSION['wishlist'])) !== false) {
        unset($_SESSION['wishlist'][$key]);
        $_SESSION['wishlist'] = array_values($_SESSION['wishlist']); 
        $pdo->prepare("DELETE FROM Wishlists WHERE cid = ? AND fid = ?")->execute([$customer_id, $fid]);
        echo json_encode(['status' => 'removed', 'count' => count($_SESSION['wishlist'])]);
    } else {
        $_SESSION['wishlist'][] = $fid;
        $pdo->prepare("INSERT IGNORE INTO Wishlists (cid, fid) VALUES (?, ?)")->execute([$customer_id, $fid]);
        echo json_encode(['status' => 'added', 'count' => count($_SESSION['wishlist'])]);
    }
    exit; 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart_custom'])) {
    $fid = intval($_POST['fid'] ?? 0); 
    $img_name = $_POST['base_name'] ?? ''; 
    $qty = intval($_POST['qty'] ?? 1);
    $size = $_POST['size'] ?? 'Standard';
    $color = $_POST['color'] ?? 'Original';
    $prod_name = $_POST['prod_name'] ?? 'Furniture Item';
    $prod_price = floatval($_POST['prod_price'] ?? 0);
    $web_url = $_POST['web_url'] ?? ''; 
    $remarks = trim($_POST['remarks'] ?? '');
    $cart_key = $img_name . "_" . md5($size . "_" . $color . "_" . $remarks);

    if (isset($_SESSION['cart'][$cart_key])) { $_SESSION['cart'][$cart_key]['qty'] += $qty; } 
    else {
        $_SESSION['cart'][$cart_key] = [
            'fid' => $fid, 'base_name' => $img_name, 'qty' => $qty, 'size' => $size,
            'color' => $color, 'remarks' => $remarks, 'name' => $prod_name,
            'price' => $prod_price, 'web_img' => $web_url
        ];
    }
    header("Location: customer_make_order.php"); exit;
}

$total_cart_count = 0; foreach ($_SESSION['cart'] as $item) { $total_cart_count += $item['qty']; }
$total_wishlist_count = count($_SESSION['wishlist']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Products - Premium Living</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; background-color: #f9f9f9; color: #333; overflow-x: hidden; }
        .modern-header { background-color: #ffffff; padding: 5px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eaeaea; position: sticky; top: 0; z-index: 1000; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .brand-area img { height: 130px; width: auto; object-fit: contain; margin: -20px 0; }
        .nav-links { display: flex; align-items: center; gap: 30px; }
        .nav-links a { text-decoration: none; color: #111; font-weight: 600; font-size: 15px; transition: color 0.2s; }
        .nav-links a:hover, .nav-links a.active { color: #f39c12; }
        .user-profile-btn { display: flex; align-items: center; gap: 8px; background: #f4f6f7; padding: 8px 15px; border-radius: 20px; text-decoration: none; color: #111; font-weight: bold; font-size: 14px; transition: 0.2s; }
        .user-profile-btn:hover { background: #e2e6e9; }
        .mini-avatar { width: 16px; height: 16px; background-color: #7f8c8d; border-radius: 50%; position: relative; }
        .mini-avatar::after { content: ''; position: absolute; width: 24px; height: 10px; background-color: #7f8c8d; border-radius: 12px 12px 0 0; bottom: -12px; left: -4px; }
        
        .scenario-sidebar { position: fixed; left: -240px; top: 0; width: 240px; height: 100vh; background-color: #ffffff; box-shadow: 5px 0 25px rgba(0,0,0,0.15); z-index: 2000; transition: left 0.4s cubic-bezier(0.25, 1, 0.5, 1); padding-top: 100px; box-sizing: border-box; }
        .scenario-sidebar:hover { left: 0; } 
        .sidebar-trigger { position: absolute; right: -36px; top: 40%; width: 36px; height: 120px; background-color: #111; color: #fff; display: flex; align-items: center; justify-content: center; border-radius: 0 12px 12px 0; font-weight: 800; letter-spacing: 2px; cursor: pointer; writing-mode: vertical-rl; text-orientation: mixed; box-shadow: 4px 0 10px rgba(0,0,0,0.1); }
        .sidebar-content { padding: 0 20px; display: flex; flex-direction: column; gap: 10px; }
        .sidebar-content h3 { margin: 0 0 20px 0; color: #111; font-size: 22px; font-weight: 800; border-bottom: 2px solid #eaeaea; padding-bottom: 10px; }
        .room-btn { background: transparent; border: none; text-align: left; font-size: 16px; font-weight: 700; color: #7f8c8d; padding: 12px 15px; cursor: pointer; transition: all 0.2s; border-radius: 8px; display: flex; align-items: center; gap: 10px; }
        .room-btn:hover { background-color: #f4f6f7; color: #111; transform: translateX(5px); }
        .room-btn.active { background-color: #111; color: #fff; }

        .help-sidebar { position: fixed; right: -240px; top: 0; width: 240px; height: 100vh; background-color: #ffffff; box-shadow: -5px 0 25px rgba(0,0,0,0.15); z-index: 2000; transition: right 0.4s cubic-bezier(0.25, 1, 0.5, 1); padding-top: 100px; box-sizing: border-box; }
        .help-sidebar:hover { right: 0; }
        .sidebar-trigger-right { position: absolute; left: -36px; top: 40%; width: 36px; height: 120px; background-color: #3498db; color: #fff; display: flex; align-items: center; justify-content: center; border-radius: 12px 0 0 12px; font-weight: 800; letter-spacing: 2px; cursor: pointer; writing-mode: vertical-rl; text-orientation: mixed; box-shadow: -4px 0 10px rgba(0,0,0,0.1); }

        .container { width: 95%; margin: 30px auto; padding-left: 40px; box-sizing: border-box; }
        .page-title { font-size: 32px; font-weight: 800; margin-bottom: 10px; color: #111; transition: color 0.3s; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .category-filters { display: flex; gap: 10px; }
        .filter-btn { padding: 8px 20px; border-radius: 20px; border: 1px solid #ddd; background: #fff; cursor: pointer; font-weight: bold; font-size: 14px; transition: all 0.2s; color: #555; }
        .filter-btn.active { background: #111; color: #fff; border-color: #111; }
        .filter-btn:hover:not(.active) { background: #f4f6f7; }
        
        .search-input { padding: 10px 20px; width: 280px; border: 1px solid #ddd; border-radius: 20px; font-size: 14px; outline: none; transition: border-color 0.3s; background: #fff; }
        .search-input:focus { border-color: #111; }
        .custom-select-tool { padding: 10px 15px; border: 1px solid #ddd; border-radius: 20px; font-size: 14px; outline: none; cursor: pointer; font-weight: bold; color: #333; }

        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 25px; margin-bottom: 50px; }
        .product-card { background: #fff; border: 1px solid #eaeaea; border-radius: 12px; padding: 15px; text-align: left; box-shadow: 0 2px 5px rgba(0,0,0,0.02); transition: transform 0.3s, box-shadow 0.3s; position: relative; }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.08); }
        .product-image { width: 100%; height: 220px; background-color: #f4f6f7; border-radius: 8px; object-fit: contain; margin-bottom: 15px; cursor: pointer; } 
        .btn-fav { position: absolute; top: 25px; right: 25px; background: rgba(255,255,255,0.9); border: none; width: 35px; height: 35px; border-radius: 50%; font-size: 18px; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: transform 0.2s; color: #ccc; z-index: 10; }
        .btn-fav:hover { transform: scale(1.1); }
        .btn-fav.active { color: #e74c3c; animation: pop 0.3s ease; }
        @keyframes pop { 0% { transform: scale(1); } 50% { transform: scale(1.3); } 100% { transform: scale(1); } }
        .product-info h3 { margin: 0 0 5px 0; font-size: 16px; color: #111; font-weight: 700; }
        .product-info p { margin: 0 0 10px 0; color: #7f8c8d; font-size: 13px; line-height: 1.4; height: 36px; overflow: hidden; }
        .price-tag { color: #e74c3c; font-size: 18px; font-weight: 800; margin-bottom: 15px; }
        .btn-add-cart { background-color: #111; color: white; border: none; padding: 10px 15px; border-radius: 6px; cursor: pointer; width: 100%; font-size: 14px; font-weight: bold; transition: background 0.2s; }
        .btn-add-cart:hover { background-color: #333; }
        
        .modal { display: none; position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(5px); align-items: center; justify-content: center; }
        .modal-content { background-color: #fff; border-radius: 16px; width: 90%; max-width: 900px; box-shadow: 0 20px 40px rgba(0,0,0,0.25); position: relative; animation: slideDown 0.4s ease; overflow: hidden; display: flex; flex-direction: column; }
        @keyframes slideDown { from { transform: translateY(-30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .close-btn { position: absolute; right: 25px; top: 20px; font-size: 28px; cursor: pointer; color: #aaa; transition: color 0.2s; z-index: 10; }
        .close-btn:hover { color: #111; }
        .modal-header { padding: 25px 30px; border-bottom: 1px solid #eee; }
        .modal-header h2 { margin: 0; font-size: 26px; color: #111; font-weight: 800; }
        .modal-header p { color: #e74c3c; font-size: 20px; font-weight: 800; margin: 8px 0 0 0; }
        .modal-body-cart { display: flex; flex-wrap: wrap; }
        .modal-left { flex: 1; min-width: 350px; background-color: #fcfcfc; padding: 30px; border-right: 1px solid #eee; display: flex; flex-direction: column; }
        .modal-image-container { width: 100%; height: 300px; background-color: #f4f6f7; border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 20px; border: 1px solid #eaeaea; }
        .modal-preview-img { width: 100%; height: 100%; object-fit: contain; transition: opacity 0.3s; }
        .live-preview-box { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .live-preview-box h4 { margin: 0 0 10px 0; font-size: 15px; color: #111; border-bottom: 1px solid #eee; padding-bottom: 8px; }
        .live-row { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 14px; color: #555; }
        .live-row span:last-child { font-weight: bold; color: #111; text-align: right; word-break: break-word; max-width: 60%; }
        .modal-right { flex: 1; min-width: 350px; padding: 30px; }
        .form-label { font-weight: 700; color: #333; font-size: 14px; margin-bottom: 8px; display: block; }
        .custom-select, .custom-textarea { width: 100%; padding: 12px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; outline: none; transition: border-color 0.2s; font-family: inherit; box-sizing: border-box; }
        .custom-select:focus, .custom-textarea:focus { border-color: #111; box-shadow: 0 0 0 2px rgba(17,17,17,0.1); }
        .custom-textarea { resize: vertical; height: 60px; }
        .btn-submit-cart { background-color: #2ecc71; color: white; border: none; padding: 16px; border-radius: 8px; cursor: pointer; width: 100%; font-size: 18px; font-weight: 800; transition: background 0.2s; box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3); margin-top: 10px; }
        .btn-submit-cart:hover { background-color: #27ae60; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(46, 204, 113, 0.4); }

        .chat-modal-content { max-width: 500px; height: 600px; }
        .chat-body { flex: 1; padding: 20px; overflow-y: auto; background: #f0f2f5; display: flex; flex-direction: column; gap: 15px; }
        .chat-bubble { max-width: 80%; padding: 12px 16px; border-radius: 16px; font-size: 14px; line-height: 1.4; word-wrap: break-word; }
        .chat-user { align-self: flex-end; background: #111; color: #fff; border-bottom-right-radius: 4px; }
        .chat-staff { align-self: flex-start; background: #fff; color: #333; border: 1px solid #ddd; border-bottom-left-radius: 4px; }
        .chat-time { display: block; font-size: 11px; opacity: 0.6; margin-top: 5px; }
    </style>
</head>
<body>

    <div class="modern-header">
        <a href="customer_home.php" class="brand-area"><img src="Logo(text).png?v=1" alt="Logo"></a>
        <div class="nav-links">
            <a href="customer_make_order.php" class="active">Products</a>
            <a href="customer_wishlist.php" style="color:#e74c3c;">❤️ Wishlist (<span id="nav-wish-count"><?php echo $total_wishlist_count; ?></span>)</a>
            <a href="customer_cart.php">Cart (<?php echo $total_cart_count; ?>)</a>
            <a href="customer_view_orders.php">My Orders</a>
            <a href="customer_profile.php" class="user-profile-btn">
                <div style="width:16px; height:24px; display:flex; align-items:center; justify-content:center; overflow:hidden; margin-right:5px;"><div class="mini-avatar"></div></div>
                <?php echo htmlspecialchars($user_name); ?>
            </a>
        </div>
    </div>

    <div class="scenario-sidebar">
        <div class="sidebar-trigger">ROOMS</div>
        <div class="sidebar-content">
            <h3>Shop by Room</h3>
            <button class="room-btn active" onclick="filterRoom('all')" id="btnRoomAll">🏠 All Rooms</button>
            <button class="room-btn" onclick="filterRoom('living')" id="btnRoomLiving">🛋️ Living Room</button>
            <button class="room-btn" onclick="filterRoom('bedroom')" id="btnRoomBedroom">🛏️ Bedroom</button>
            <button class="room-btn" onclick="filterRoom('dining')" id="btnRoomDining">🍽️ Dining Room</button>
            <button class="room-btn" onclick="filterRoom('study')" id="btnRoomStudy">📚 Study / Office</button>
        </div>
    </div>

    <div class="help-sidebar">
        <div class="sidebar-trigger-right">HELP</div>
        <div class="sidebar-content">
            <h3>Support Center</h3>
            <button class="room-btn" onclick="openChatModal()">💬 Pre-sales Consultation</button>
            <button class="room-btn" onclick="alert('Feature coming soon in V2.0!')">🚚 Delivery FAQ</button>
            <button class="room-btn" onclick="alert('Feature coming soon in V2.0!')">🔄 Return Policy</button>
        </div>
    </div>

    <div class="container" id="mainContainer">
        <h1 class="page-title" id="pageMainTitle">All Products</h1>
        <div class="toolbar">
            <div class="category-filters" id="filterContainer">
                <button class="filter-btn active" onclick="filterCategory('all')">All Types</button>
                <button class="filter-btn" onclick="filterCategory('seating')">Seating</button>
                <button class="filter-btn" onclick="filterCategory('tables')">Tables</button>
                <button class="filter-btn" onclick="filterCategory('beds')">Beds</button>
                <button class="filter-btn" onclick="filterCategory('storage')">Storage</button>
            </div>
            
            <div style="display:flex; gap:10px; align-items:center;">
                <select id="sortSelect" class="custom-select-tool" onchange="sortProducts()">
                    <option value="default">Sort: Default (Newest)</option>
                    <option value="price_asc">Price: Low to High</option>
                    <option value="price_desc">Price: High to Low</option>
                    <option value="name_asc">Name: A to Z</option>
                    <option value="name_desc">Name: Z to A</option>
                </select>
                <input type="text" id="searchInput" class="search-input" onkeyup="searchProducts()" placeholder="🔍 Search furniture...">
            </div>
        </div>

        <div class="product-grid" id="productGrid">
            <?php
            $stmtDynamic = $pdo->query("SELECT * FROM Furnitures ORDER BY fid DESC");
            $all_products = $stmtDynamic->fetchAll();

            foreach ($all_products as $prod): 
                $img_path = $prod['fimage'];
                if (!file_exists($img_path) && file_exists('uploads/' . $img_path)) {
                    $img_path = 'uploads/' . $img_path;
                }
                $is_faved = in_array($prod['fid'], $_SESSION['wishlist']) ? 'active' : '';
            ?>
            <div class="product-card" data-category="<?php echo htmlspecialchars($prod['fcategory']); ?>" data-room="<?php echo htmlspecialchars($prod['froom']); ?>" data-price="<?php echo $prod['fprice']; ?>" data-name="<?php echo htmlspecialchars($prod['fname']); ?>" data-fid="<?php echo $prod['fid']; ?>">
                <button class="btn-fav <?php echo $is_faved; ?>" onclick="toggleFav(this, <?php echo $prod['fid']; ?>)">
                    <?php echo $is_faved ? '❤️' : '❤'; ?>
                </button>
                <img src="<?php echo htmlspecialchars($img_path); ?>" alt="<?php echo htmlspecialchars($prod['fname']); ?>" class="product-image">
                <div class="product-info">
                    <h3><?php echo htmlspecialchars($prod['fname']); ?></h3>
                    <p><?php echo htmlspecialchars($prod['fdesc']); ?></p>
                    <div class="price-tag">HKD <?php echo number_format($prod['fprice'], 2); ?></div>
                    <button class="btn-add-cart" onclick="openModal(<?php echo $prod['fid']; ?>, '<?php echo addslashes($prod['fimage']); ?>', '<?php echo addslashes($prod['fname']); ?>', <?php echo $prod['fprice']; ?>, '<?php echo addslashes($img_path); ?>')">Configure & Add</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="customizeModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <div class="modal-header">
                <h2 id="modalFurnitureName">Customize Furniture</h2>
                <p id="modalFurniturePrice"></p>
            </div>
            <div class="modal-body-cart">
                <div class="modal-left">
                    <div class="modal-image-container"><img id="modalPreviewImg" src="" alt="Custom preview" class="modal-preview-img"></div>
                    <div class="live-preview-box">
                        <h4>Your Selection</h4>
                        <div class="live-row"><span>Size:</span> <span id="liveSize">Standard Size</span></div>
                        <div class="live-row"><span>Color/Finish:</span> <span id="liveColor">Original (Default)</span></div>
                        <div class="live-row"><span>Remarks:</span> <span id="liveRemarks" style="color: #e67e22;">None</span></div>
                    </div>
                </div>
                <div class="modal-right">
                    <form action="customer_make_order.php" method="POST">
                        <input type="hidden" id="modalFid" name="fid" value="">
                        <input type="hidden" id="modalBaseName" name="base_name" value="">
                        <input type="hidden" id="modalProdName" name="prod_name" value="">
                        <input type="hidden" id="modalProdPrice" name="prod_price" value="">
                        <input type="hidden" id="modalWebUrl" name="web_url" value="">

                        <label class="form-label">Select Size Option:</label>
                        <select id="customSize" name="size" class="custom-select" onchange="syncPreview()">
                            <option value="Standard">Standard Size</option>
                            <option value="Compact (Space-Saving)">Compact (Space-Saving)</option>
                            <option value="Extra Large (Premium)">Extra Large (Premium)</option>
                        </select>

                        <label class="form-label">Select Color & Finish:</label>
                        <select id="customColor" name="color" class="custom-select" onchange="updateModalImageAndSync()">
                            <option value="Original">Original (Default)</option>
                            <option value="Red">Red Finish</option>
                            <option value="Blue">Blue Finish</option>
                        </select>

                        <label class="form-label">Quantity:</label>
                        <input type="number" id="customQty" name="qty" value="1" min="1" max="20" class="custom-select">

                        <label class="form-label">Special Custom Remarks (Optional):</label>
                        <textarea id="customRemarks" name="remarks" class="custom-textarea" placeholder="e.g. Please make the corners rounded..." onkeyup="syncPreview()"></textarea>

                        <button type="submit" name="add_to_cart_custom" class="btn-submit-cart">Add to Cart</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="chatModal" class="modal">
        <div class="modal-content chat-modal-content">
            <span class="close-btn" onclick="closeChatModal()">&times;</span>
            <div class="modal-header">
                <h2>Live Support</h2>
                <p style="font-size: 14px; color: #7f8c8d; font-weight: normal; margin-top: 5px;">We typically reply in minutes</p>
            </div>
            <div style="display: flex; flex-direction: column; flex: 1; overflow: hidden;">
                <div class="chat-body" id="presalesChatBody">
                    <div class="chat-bubble chat-staff">
                        Hi <?php echo htmlspecialchars($user_name); ?>! 👋 How can we assist you with our products today?
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
                <form action="customer_make_order.php" method="POST" style="display: flex; padding: 15px; background: #fff; border-top: 1px solid #eee; gap: 10px;">
                    <input type="text" name="presales_msg" style="flex: 1; padding: 12px 15px; border: 1px solid #ddd; border-radius: 20px; font-size: 14px; outline: none;" placeholder="Ask about materials, sizes..." required>
                    <button type="submit" name="send_presales" style="background: #3498db; color: #fff; border: none; padding: 0 20px; border-radius: 20px; font-weight: bold; cursor: pointer;">Send</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const roomParam = urlParams.get('room');
            if(roomParam) { filterRoom(roomParam); }
            if(urlParams.get('chat_open') == '1') { openChatModal(); }
        });

        function openChatModal() {
            document.getElementById('chatModal').style.display = "flex";
            const chatBody = document.getElementById('presalesChatBody');
            if (chatBody) chatBody.scrollTop = chatBody.scrollHeight;
        }
        function closeChatModal() { document.getElementById('chatModal').style.display = "none"; }

        function toggleFav(btn, fid) {
            fetch('customer_make_order.php?ajax_wishlist=1&fid=' + fid)
            .then(response => response.json())
            .then(data => {
                if(data.status === 'added') { btn.classList.add('active'); btn.innerHTML = '❤️'; } 
                else { btn.classList.remove('active'); btn.innerHTML = '❤'; }
                document.getElementById('nav-wish-count').innerText = data.count;
            })
            .catch(error => console.error('Error:', error));
        }

        let currentCategory = 'all';
        let currentRoom = 'all';
        
        function filterCategory(category) { 
            currentCategory = category; 
            let btns = document.querySelectorAll('.filter-btn'); 
            btns.forEach(btn => btn.classList.remove('active')); 
            event.target.classList.add('active'); 
            applyFilters(); 
        }
        
        function filterRoom(room) { 
            currentRoom = room; 
            let btns = document.querySelectorAll('.scenario-sidebar .room-btn'); 
            btns.forEach(btn => btn.classList.remove('active')); 
            let btnId = 'btnRoom' + room.charAt(0).toUpperCase() + room.slice(1);
            let activeBtn = document.getElementById(btnId);
            if (activeBtn) { activeBtn.classList.add('active'); }
            
            const roomTitles = { 'all': 'All Products', 'living': 'All Products (Living Room)', 'bedroom': 'All Products (Bedroom)', 'dining': 'All Products (Dining Room)', 'study': 'All Products (Study / Office)' };
            let titleEl = document.getElementById('pageMainTitle');
            if(titleEl) {
                titleEl.innerText = roomTitles[room] || 'All Products';
                titleEl.style.color = '#e67e22';
                setTimeout(() => { titleEl.style.color = '#111'; }, 300);
            }
            applyFilters(); 
        }

        function searchProducts() { applyFilters(); }

        function applyFilters() {
            let searchInput = document.getElementById('searchInput').value.toLowerCase();
            let cards = document.querySelectorAll('.product-card');
            cards.forEach(card => {
                let title = card.querySelector('h3').innerText.toLowerCase();
                let desc = card.querySelector('p').innerText.toLowerCase();
                let cardCategory = card.getAttribute('data-category');
                let cardRoom = card.getAttribute('data-room');
                let matchSearch = title.includes(searchInput) || desc.includes(searchInput);
                let matchCategory = (currentCategory === 'all' || currentCategory === cardCategory);
                let matchRoom = (currentRoom === 'all' || currentRoom === cardRoom);
                card.style.display = (matchSearch && matchCategory && matchRoom) ? "block" : "none";
            });
        }

        // 🌟 核心评分补全：动态网格排序 JS
        function sortProducts() {
            let grid = document.getElementById('productGrid');
            let cards = Array.from(grid.getElementsByClassName('product-card'));
            let sortVal = document.getElementById('sortSelect').value;

            cards.sort((a, b) => {
                if (sortVal === 'price_asc') {
                    return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
                } else if (sortVal === 'price_desc') {
                    return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
                } else if (sortVal === 'name_asc') {
                    return a.dataset.name.localeCompare(b.dataset.name);
                } else if (sortVal === 'name_desc') {
                    return b.dataset.name.localeCompare(a.dataset.name);
                } else {
                    return parseInt(b.dataset.fid) - parseInt(a.dataset.fid); 
                }
            });
            // 重新排列 DOM 节点
            cards.forEach(card => grid.appendChild(card));
        }

        function openModal(fid, imgName, name, price, webUrl) {
            activeImageName = imgName; activeWebUrl = webUrl;     
            document.getElementById('modalFid').value = fid;
            document.getElementById('modalBaseName').value = imgName;
            document.getElementById('modalProdName').value = name;
            document.getElementById('modalProdPrice').value = price;
            document.getElementById('modalWebUrl').value = webUrl;
            document.getElementById('modalFurnitureName').innerText = name;
            document.getElementById('modalFurniturePrice').innerText = "HKD " + price.toFixed(2);
            document.getElementById('customSize').selectedIndex = 0;
            document.getElementById('customColor').selectedIndex = 0;
            document.getElementById('customRemarks').value = "";
            document.getElementById('customQty').value = 1;
            updateModalImageAndSync();
            document.getElementById('customizeModal').style.display = "flex";
        }

        function syncPreview() {
            let sizeText = document.getElementById('customSize').options[document.getElementById('customSize').selectedIndex].text;
            let colorText = document.getElementById('customColor').options[document.getElementById('customColor').selectedIndex].text;
            let remarks = document.getElementById('customRemarks').value.trim();
            document.getElementById('liveSize').innerText = sizeText;
            document.getElementById('liveColor').innerText = colorText;
            if(remarks === "") {
                document.getElementById('liveRemarks').innerText = "None"; document.getElementById('liveRemarks').style.color = "#999";
            } else {
                document.getElementById('liveRemarks').innerText = '"' + remarks + '"'; document.getElementById('liveRemarks').style.color = "#e74c3c"; 
            }
        }

        function updateModalImageAndSync() {
            var selectedColor = document.getElementById('customColor').value;
            var previewImg = document.getElementById('modalPreviewImg');
            if (selectedColor !== "Original" && activeImageName.endsWith(".png") && !activeWebUrl.includes("uploads/")) {
                let baseClean = activeImageName.replace('.png', '');
                if (selectedColor === "Red") { previewImg.src = baseClean + "_red.png"; } 
                else if (selectedColor === "Blue") { previewImg.src = baseClean + "_blue.png"; } 
                previewImg.onerror = function() { this.src = activeWebUrl; this.onerror = null; };
            } else { previewImg.src = activeWebUrl; }
            syncPreview();
        }

        function closeModal() { document.getElementById('customizeModal').style.display = "none"; }
        
        window.onclick = function(event) { 
            if (event.target == document.getElementById('customizeModal')) { closeModal(); }
            if (event.target == document.getElementById('chatModal')) { closeChatModal(); }
        }
    </script>
</body>
</html>