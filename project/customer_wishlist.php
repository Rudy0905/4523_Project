<?php
session_start();
require_once 'db_connect.php'; 

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: index.php"); exit;
}
if (!isset($_SESSION['wishlist'])) { $_SESSION['wishlist'] = []; }

$user_name = $_SESSION['user_name'] ?? 'User';
$customer_id = $_SESSION['user_id'];

// 🌟 核心修改：处理加入购物车的请求，同样去掉了无关的联系方式
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

    if (isset($_SESSION['cart'][$cart_key])) {
        $_SESSION['cart'][$cart_key]['qty'] += $qty;
    } else {
        $_SESSION['cart'][$cart_key] = [
            'fid' => $fid, 'base_name' => $img_name, 'qty' => $qty, 'size' => $size,
            'color' => $color, 'remarks' => $remarks, 'name' => $prod_name,
            'price' => $prod_price, 'web_img' => $web_url
        ];
    }
    header("Location: customer_wishlist.php"); exit;
}

// 移除收藏
if (isset($_GET['remove_fid'])) {
    $remove_fid = intval($_GET['remove_fid']);
    if (($key = array_search($remove_fid, $_SESSION['wishlist'])) !== false) {
        unset($_SESSION['wishlist'][$key]);
        $_SESSION['wishlist'] = array_values($_SESSION['wishlist']);
        $stmtDel = $pdo->prepare("DELETE FROM Wishlists WHERE cid = ? AND fid = ?");
        $stmtDel->execute([$customer_id, $remove_fid]);
    }
    header("Location: customer_wishlist.php"); exit;
}

$total_cart_count = 0;
if (!empty($_SESSION['cart'])) { foreach ($_SESSION['cart'] as $item) { $total_cart_count += $item['qty']; } }
$total_wishlist_count = count($_SESSION['wishlist']);

$wishlist_items = [];
if (!empty($_SESSION['wishlist'])) {
    $in  = str_repeat('?,', count($_SESSION['wishlist']) - 1) . '?';
    $sql = "SELECT * FROM Furnitures WHERE fid IN ($in)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($_SESSION['wishlist']);
    $wishlist_items = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Wishlist - Premium Living</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; background-color: #f9f9f9; color: #333; }
        .modern-header { background-color: #ffffff; padding: 5px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eaeaea; position: sticky; top: 0; z-index: 1000; }
        .brand-area img { height: 130px; width: auto; object-fit: contain; margin: -20px 0; }
        .nav-links { display: flex; align-items: center; gap: 30px; }
        .nav-links a { text-decoration: none; color: #111; font-weight: 600; font-size: 15px; transition: color 0.2s; }
        .nav-links a:hover, .nav-links a.active { color: #e74c3c; }
        .user-profile-btn { display: flex; align-items: center; gap: 8px; background: #f4f6f7; padding: 8px 15px; border-radius: 20px; text-decoration: none; color: #111 !important; font-weight: bold; font-size: 14px; }
        .mini-avatar { width: 16px; height: 16px; background-color: #7f8c8d; border-radius: 50%; position: relative; }
        .mini-avatar::after { content: ''; position: absolute; width: 24px; height: 10px; background-color: #7f8c8d; border-radius: 12px 12px 0 0; bottom: -12px; left: -4px; }

        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .page-title { font-size: 32px; font-weight: 800; border-bottom: 2px solid #111; padding-bottom: 15px; margin-bottom: 30px; }
        
        .empty-wishlist { text-align: center; padding: 80px 20px; background: #fff; border-radius: 16px; border: 1px dashed #ccc; }
        .empty-wishlist h2 { color: #111; margin-bottom: 10px; }
        .empty-wishlist p { color: #7f8c8d; margin-bottom: 30px; }
        .btn-browse { background: #111; color: #fff; padding: 12px 30px; border-radius: 30px; text-decoration: none; font-weight: bold; transition: 0.3s; }
        .btn-browse:hover { background: #333; }

        .wishlist-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; }
        .wishlist-card { background: #fff; border-radius: 12px; padding: 20px; border: 1px solid #eaeaea; box-shadow: 0 4px 15px rgba(0,0,0,0.03); text-align: center; transition: transform 0.3s; position: relative; }
        .wishlist-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.08); }
        .wishlist-card img { width: 100%; height: 220px; object-fit: contain; background: #f4f6f7; border-radius: 8px; margin-bottom: 15px; cursor: pointer; }
        .wishlist-card h3 { margin: 0 0 5px 0; font-size: 18px; color: #111; }
        .wishlist-card p { margin: 0 0 15px 0; color: #e74c3c; font-weight: bold; font-size: 18px; }
        
        .btn-remove { position: absolute; top: 10px; right: 10px; background: #fff; border: 1px solid #eaeaea; width: 35px; height: 35px; border-radius: 50%; color: #e74c3c; font-size: 16px; font-weight: bold; cursor: pointer; transition: 0.2s; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-decoration: none; display: flex; align-items: center; justify-content: center; z-index: 10; }
        .btn-remove:hover { background: #e74c3c; color: white; }
        
        .btn-buy { background: #111; color: #fff; border: none; padding: 12px; width: 100%; border-radius: 6px; font-size: 14px; font-weight: bold; text-decoration: none; display: inline-block; box-sizing: border-box; transition: 0.2s; cursor: pointer; }
        .btn-buy:hover { background: #333; }

        .modal { display: none; position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(5px); }
        .modal-content { background-color: #fff; margin: 3% auto; border-radius: 16px; width: 90%; max-width: 900px; box-shadow: 0 20px 40px rgba(0,0,0,0.25); position: relative; animation: slideDown 0.4s ease; overflow: hidden; display: flex; flex-direction: column; }
        @keyframes slideDown { from { transform: translateY(-30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .close-btn { position: absolute; right: 25px; top: 20px; font-size: 28px; cursor: pointer; color: #aaa; transition: color 0.2s; z-index: 10; }
        .close-btn:hover { color: #111; }
        .modal-header { padding: 25px 30px; border-bottom: 1px solid #eee; }
        .modal-header h2 { margin: 0; font-size: 26px; color: #111; font-weight: 800; }
        .modal-header p { color: #e74c3c; font-size: 20px; font-weight: 800; margin: 8px 0 0 0; }
        
        .modal-body { display: flex; flex-wrap: wrap; }
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
    </style>
</head>
<body>

    <div class="modern-header">
        <a href="customer_home.php" class="brand-area"><img src="Logo(text).png?v=1" alt="Logo"></a>
        <div class="nav-links">
            <a href="customer_make_order.php">Products</a>
            <a href="customer_wishlist.php" class="active">❤️ Wishlist (<span id="nav-wish-count"><?php echo $total_wishlist_count; ?></span>)</a>
            <a href="customer_cart.php">Cart (<?php echo $total_cart_count; ?>)</a>
            <a href="customer_view_orders.php">My Orders</a>
            <a href="customer_profile.php" class="user-profile-btn">
                <div style="width:16px; height:24px; display:flex; align-items:center; justify-content:center; overflow:hidden; margin-right:5px;"><div class="mini-avatar"></div></div>
                <?php echo htmlspecialchars($user_name); ?>
            </a>
        </div>
    </div>

    <div class="container">
        <h1 class="page-title">My Hearted Items ❤️</h1>

        <?php if (empty($wishlist_items)): ?>
            <div class="empty-wishlist">
                <h2>No items in your Wishlist.</h2>
                <p>Save items you love to your wishlist to easily find them later or purchase them when you're ready.</p>
                <a href="customer_make_order.php" class="btn-browse">Explore Furniture</a>
            </div>
        <?php else: ?>
            <div class="wishlist-grid">
                <?php foreach ($wishlist_items as $prod): 
                    $img_path = $prod['fimage'];
                    if (!file_exists($img_path) && file_exists('uploads/' . $img_path)) {
                        $img_path = 'uploads/' . $img_path;
                    }
                ?>
                <div class="wishlist-card">
                    <a href="customer_wishlist.php?remove_fid=<?php echo $prod['fid']; ?>" class="btn-remove" title="Remove from Wishlist">✖</a>
                    
                    <img src="<?php echo htmlspecialchars($img_path); ?>" alt="<?php echo htmlspecialchars($prod['fname']); ?>" 
                         onclick="openModal(<?php echo $prod['fid']; ?>, '<?php echo addslashes($prod['fimage']); ?>', '<?php echo addslashes($prod['fname']); ?>', <?php echo $prod['fprice']; ?>, '<?php echo addslashes($img_path); ?>')">
                    
                    <h3><?php echo htmlspecialchars($prod['fname']); ?></h3>
                    <p>HKD <?php echo number_format($prod['fprice'], 2); ?></p>
                    
                    <button class="btn-buy" onclick="openModal(<?php echo $prod['fid']; ?>, '<?php echo addslashes($prod['fimage']); ?>', '<?php echo addslashes($prod['fname']); ?>', <?php echo $prod['fprice']; ?>, '<?php echo addslashes($img_path); ?>')">
                        Configure & Add to Cart
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="customizeModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            
            <div class="modal-header">
                <h2 id="modalFurnitureName">Customize Furniture</h2>
                <p id="modalFurniturePrice"></p>
            </div>

            <div class="modal-body">
                <div class="modal-left">
                    <div class="modal-image-container">
                        <img id="modalPreviewImg" src="" alt="Custom preview" class="modal-preview-img">
                    </div>
                    
                    <div class="live-preview-box">
                        <h4>Your Selection</h4>
                        <div class="live-row"><span>Size:</span> <span id="liveSize">Standard Size</span></div>
                        <div class="live-row"><span>Color/Finish:</span> <span id="liveColor">Original (Default)</span></div>
                        <div class="live-row"><span>Remarks:</span> <span id="liveRemarks" style="color: #e67e22;">None</span></div>
                    </div>
                </div>

                <div class="modal-right">
                    <form action="customer_wishlist.php" method="POST">
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

    <script>
        var activeImageName = "";
        var activeWebUrl = "";

        function openModal(fid, imgName, name, price, webUrl) {
            activeImageName = imgName; 
            activeWebUrl = webUrl;     

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
            document.getElementById('customizeModal').style.display = "block";
        }

        function syncPreview() {
            let sizeText = document.getElementById('customSize').options[document.getElementById('customSize').selectedIndex].text;
            let colorText = document.getElementById('customColor').options[document.getElementById('customColor').selectedIndex].text;
            let remarks = document.getElementById('customRemarks').value.trim();

            document.getElementById('liveSize').innerText = sizeText;
            document.getElementById('liveColor').innerText = colorText;
            
            if(remarks === "") {
                document.getElementById('liveRemarks').innerText = "None";
                document.getElementById('liveRemarks').style.color = "#999";
            } else {
                document.getElementById('liveRemarks').innerText = '"' + remarks + '"';
                document.getElementById('liveRemarks').style.color = "#e74c3c"; 
            }
        }

        function updateModalImageAndSync() {
            var selectedColor = document.getElementById('customColor').value;
            var previewImg = document.getElementById('modalPreviewImg');

            if (selectedColor !== "Original" && activeImageName.endsWith(".png") && !activeWebUrl.includes("uploads/")) {
                let baseClean = activeImageName.replace('.png', '');
                
                if (selectedColor === "Red") { 
                    previewImg.src = baseClean + "_red.png"; 
                } else if (selectedColor === "Blue") { 
                    previewImg.src = baseClean + "_blue.png"; 
                } 
                
                previewImg.onerror = function() { 
                    this.src = activeWebUrl; 
                    this.onerror = null; 
                };
            } else {
                previewImg.src = activeWebUrl;
            }
            syncPreview();
        }

        function closeModal() { document.getElementById('customizeModal').style.display = "none"; }
        window.onclick = function(event) { let modal = document.getElementById('customizeModal'); if (event.target == modal) { modal.style.display = "none"; } }
    </script>
</body>
</html>