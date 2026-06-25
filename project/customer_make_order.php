<?php
// 开启 Session 记录购物车数据
session_start();

// 引入数据库连接工具文件
require_once 'db_connect.php'; 

// 权限拦截
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$is_logged_in = true;
$user_name = $_SESSION['user_name'] ?? 'User';
$customer_id = $_SESSION['user_id'];

// 🌟 获取当前用户的默认资料库信息
$stmtUser = $pdo->prepare("SELECT * FROM Customers WHERE cid = ?");
$stmtUser->execute([$customer_id]);
$curr_user = $stmtUser->fetch();

// 处理弹窗表单提交，加入购物车
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
            'fid'       => $fid,        
            'base_name' => $img_name, 
            'qty'       => $qty,
            'size'      => $size,
            'color'     => $color,
            'remarks'   => $remarks, 
            'name'      => $prod_name,
            'price'     => $prod_price,
            'web_img'   => $web_url,
            'caddr'     => trim($_POST['caddr'] ?? '') // 记录下单时的默认地址
        ];
    }
    
    header("Location: customer_make_order.php");
    exit;
}

$total_cart_count = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) { $total_cart_count += $item['qty']; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Products - Premium Living</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background-color: #f9f9f9; color: #333; overflow-x: hidden; }
        
        /* 导航栏 */
        .modern-header { background-color: #ffffff; padding: 5px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eaeaea; position: sticky; top: 0; z-index: 1000; }
        .brand-area { display: flex; align-items: center; text-decoration: none; }
        .brand-area img { height: 130px; width: auto; object-fit: contain; margin: -20px 0; }
        .nav-links { display: flex; align-items: center; gap: 30px; }
        .nav-links a { text-decoration: none; color: #111; font-weight: 600; font-size: 15px; transition: color 0.2s; }
        .nav-links a:hover { color: #f39c12; }
        .user-profile-btn { display: flex; align-items: center; gap: 8px; background: #f4f6f7; padding: 8px 15px; border-radius: 20px; text-decoration: none; color: #111; font-weight: bold; font-size: 14px; transition: background 0.2s; }
        .user-profile-btn:hover { background: #e2e6e9; }
        .mini-avatar { width: 16px; height: 16px; background-color: #7f8c8d; border-radius: 50%; position: relative; }
        .mini-avatar::after { content: ''; position: absolute; width: 24px; height: 10px; background-color: #7f8c8d; border-radius: 12px 12px 0 0; bottom: -12px; left: -4px; }

        /* 左侧悬浮抽屉式场景栏 */
        .scenario-sidebar {
            position: fixed;
            left: -240px; 
            top: 0;
            width: 240px;
            height: 100vh;
            background-color: #ffffff;
            box-shadow: 5px 0 25px rgba(0,0,0,0.15);
            z-index: 2000; 
            transition: left 0.4s cubic-bezier(0.25, 1, 0.5, 1);
            padding-top: 100px; 
            box-sizing: border-box;
        }
        .scenario-sidebar:hover { left: 0; } 
        
        .sidebar-trigger {
            position: absolute;
            right: -36px;
            top: 40%;
            width: 36px;
            height: 120px;
            background-color: #111;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0 12px 12px 0;
            font-weight: 800;
            letter-spacing: 2px;
            cursor: pointer;
            writing-mode: vertical-rl;
            text-orientation: mixed;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-content { padding: 0 20px; display: flex; flex-direction: column; gap: 10px; }
        .sidebar-content h3 { margin: 0 0 20px 0; color: #111; font-size: 22px; font-weight: 800; border-bottom: 2px solid #eaeaea; padding-bottom: 10px; }
        .room-btn { 
            background: transparent; border: none; text-align: left; font-size: 16px; font-weight: 700; 
            color: #7f8c8d; padding: 12px 15px; cursor: pointer; transition: all 0.2s; border-radius: 8px; display: flex; align-items: center; gap: 10px;
        }
        .room-btn:hover { background-color: #f4f6f7; color: #111; transform: translateX(5px); }
        .room-btn.active { background-color: #111; color: #fff; }

        /* 页面主体优化（百分比自适应） */
        .container { 
            width: 95%; 
            margin: 30px auto; 
            padding-left: 40px; /* 为左侧栏留点触发空间，但不推离整个容器 */
            box-sizing: border-box;
        }
        .page-title { font-size: 32px; font-weight: 800; margin-bottom: 10px; color: #111; }
        
        /* 工具栏 */
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .category-filters { display: flex; gap: 10px; }
        .filter-btn { padding: 8px 20px; border-radius: 20px; border: 1px solid #ddd; background: #fff; cursor: pointer; font-weight: bold; font-size: 14px; transition: all 0.2s; color: #555; }
        .filter-btn.active { background: #111; color: #fff; border-color: #111; }
        .filter-btn:hover:not(.active) { background: #f4f6f7; }
        .search-input { padding: 10px 20px; width: 280px; border: 1px solid #ddd; border-radius: 20px; font-size: 14px; outline: none; transition: border-color 0.3s; background: #fff; }
        .search-input:focus { border-color: #111; }

        /* 商品网格（自动填充列数，解决右侧空白） */
        .product-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); 
            gap: 25px; 
            margin-bottom: 50px; 
        }
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

        /* 弹窗样式 */
        .modal { display: none; position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(5px); }
        .modal-content { background-color: #fff; margin: 3% auto; border-radius: 16px; width: 90%; max-width: 900px; box-shadow: 0 20px 40px rgba(0,0,0,0.25); position: relative; animation: slideDown 0.4s ease; overflow: hidden; display: flex; flex-direction: column; }
        @keyframes slideDown { from { transform: translateY(-30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .close-btn { position: absolute; right: 25px; top: 20px; font-size: 28px; cursor: pointer; color: #aaa; transition: color 0.2s; z-index: 10; }
        .close-btn:hover { color: #111; }
        .modal-header { padding: 25px 30px; border-bottom: 1px solid #eee; }
        .modal-header h2 { margin: 0; font-size: 26px; color: #111; font-weight: 800; }
        .modal-header p { color: #e74c3c; font-size: 20px; font-weight: 800; margin: 8px 0 0 0; }
        
        /* 弹窗分栏 */
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
        <a href="customer_home.php" class="brand-area">
            <img src="Logo(text).png?v=1" alt="Premium Living Logo">
        </a>
        <div class="nav-links">
            <a href="customer_make_order.php">Products</a>
            <a href="customer_cart.php">Cart (<?php echo $total_cart_count; ?>)</a>
            <a href="customer_view_orders.php">My Orders</a>
            <a href="customer_profile.php" class="user-profile-btn">
                <div style="width:16px; height:24px; display:flex; align-items:center; justify-content:center; overflow:hidden; margin-right:5px;">
                    <div class="mini-avatar"></div>
                </div>
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

    <div class="container" id="mainContainer">
        <h1 class="page-title">All Products</h1>
        
        <div class="toolbar">
            <div class="category-filters" id="filterContainer">
                <button class="filter-btn active" onclick="filterCategory('all')">All Types</button>
                <button class="filter-btn" onclick="filterCategory('seating')">Seating</button>
                <button class="filter-btn" onclick="filterCategory('tables')">Tables</button>
                <button class="filter-btn" onclick="filterCategory('beds')">Beds</button>
                <button class="filter-btn" onclick="filterCategory('storage')">Storage</button>
            </div>
            <div>
                <input type="text" id="searchInput" class="search-input" onkeyup="searchProducts()" placeholder="🔍 Search furniture...">
            </div>
        </div>

        <div class="product-grid" id="productGrid">
            
            <div class="product-card" data-category="seating" data-room="dining">
                <button class="btn-fav" onclick="toggleFav(this)">❤</button>
                <img src="WoodenRider1.png" alt="Oak Dining Chair" class="product-image">
                <div class="product-info">
                    <h3>Oak Dining Chair</h3>
                    <p>Classic style solid oak chair for your dining room.</p>
                    <div class="price-tag">HKD 450.00</div>
                    <button class="btn-add-cart" onclick="openModal(1, 'WoodenRider1', 'Oak Dining Chair', 450.00, 'WoodenRider1.png')">Configure & Add</button>
                </div>
            </div>

            <div class="product-card" data-category="tables" data-room="dining">
                <button class="btn-fav" onclick="toggleFav(this)">❤</button>
                <img src="WoodenTable1.png" alt="Large Dining Table" class="product-image">
                <div class="product-info">
                    <h3>Large Dining Table</h3>
                    <p>6-seater family dining table with premium finish.</p>
                    <div class="price-tag">HKD 2,500.00</div>
                    <button class="btn-add-cart" onclick="openModal(2, 'WoodenTable1', 'Large Dining Table', 2500.00, 'WoodenTable1.png')">Configure & Add</button>
                </div>
            </div>

            <div class="product-card" data-category="seating" data-room="living">
                <button class="btn-fav" onclick="toggleFav(this)">❤</button>
                <img src="Sofa1.png" alt="3-Seater Fabric Sofa" class="product-image">
                <div class="product-info">
                    <h3>3-Seater Fabric Sofa</h3>
                    <p>Comfortable sofa with high density foam filling.</p>
                    <div class="price-tag">HKD 3,800.00</div>
                    <button class="btn-add-cart" onclick="openModal(3, 'Sofa1', '3-Seater Fabric Sofa', 3800.00, 'Sofa1.png')">Configure & Add</button>
                </div>
            </div>

            <div class="product-card" data-category="storage" data-room="bedroom">
                <button class="btn-fav" onclick="toggleFav(this)">❤</button>
                <img src="WoodenWardrobe1.png" alt="Wooden Wardrobe" class="product-image">
                <div class="product-info">
                    <h3>Wooden Wardrobe</h3>
                    <p>Double door wardrobe with ample hanging space.</p>
                    <div class="price-tag">HKD 1,800.00</div>
                    <button class="btn-add-cart" onclick="openModal(4, 'WoodenWardrobe1', 'Wooden Wardrobe', 1800.00, 'WoodenWardrobe1.png')">Configure & Add</button>
                </div>
            </div>
            
            <div class="product-card" data-category="storage" data-room="study">
                <button class="btn-fav" onclick="toggleFav(this)">❤</button>
                <img src="Shelf1.png" alt="Industrial Bookshelf" class="product-image">
                <div class="product-info">
                    <h3>Industrial Bookshelf</h3>
                    <p>Modern steel frame bookshelf for your study.</p>
                    <div class="price-tag">HKD 1,200.00</div>
                    <button class="btn-add-cart" onclick="openModal(5, 'Shelf1', 'Industrial Bookshelf', 1200.00, 'Shelf1.png')">Configure & Add</button>
                </div>
            </div>

            <div class="product-card" data-category="beds" data-room="bedroom">
                <button class="btn-fav" onclick="toggleFav(this)">❤</button>
                <img src="WoodenBed1.png" alt="Queen Size Bed Frame" class="product-image">
                <div class="product-info">
                    <h3>Queen Size Bed Frame</h3>
                    <p>Sturdy oak frame for queen size mattress.</p>
                    <div class="price-tag">HKD 2,200.00</div>
                    <button class="btn-add-cart" onclick="openModal(6, 'WoodenBed1', 'Queen Size Bed Frame', 2200.00, 'WoodenBed1.png')">Configure & Add</button>
                </div>
            </div>

            <div class="product-card" data-category="seating" data-room="living">
                <button class="btn-fav" onclick="toggleFav(this)">❤</button>
                <img src="https://images.unsplash.com/photo-1506439016147-160fa44cdbdc?auto=format&fit=crop&w=500&q=80" alt="Modern Armchair" class="product-image" style="object-fit: cover;">
                <div class="product-info">
                    <h3>Modern Armchair</h3>
                    <p>Elegant velvet armchair for reading and relaxing.</p>
                    <div class="price-tag">HKD 1,600.00</div>
                    <button class="btn-add-cart" onclick="openModal(7, 'Armchair1', 'Modern Armchair', 1600.00, 'https://images.unsplash.com/photo-1506439016147-160fa44cdbdc?auto=format&fit=crop&w=500&q=80')">Configure & Add</button>
                </div>
            </div>

            <div class="product-card" data-category="tables" data-room="living">
                <button class="btn-fav" onclick="toggleFav(this)">❤</button>
                <img src="https://images.unsplash.com/photo-1532323544230-7191fd51bc1b?auto=format&fit=crop&w=500&q=80" alt="Glass Coffee Table" class="product-image" style="object-fit: cover;">
                <div class="product-info">
                    <h3>Glass Coffee Table</h3>
                    <p>Tempered glass top with solid wood base.</p>
                    <div class="price-tag">HKD 850.00</div>
                    <button class="btn-add-cart" onclick="openModal(8, 'CoffeeTable1', 'Glass Coffee Table', 850.00, 'https://images.unsplash.com/photo-1532323544230-7191fd51bc1b?auto=format&fit=crop&w=500&q=80')">Configure & Add</button>
                </div>
            </div>

            <div class="product-card" data-category="beds" data-room="bedroom">
                <button class="btn-fav" onclick="toggleFav(this)">❤</button>
                <img src="https://images.unsplash.com/photo-1540518614846-7eded433c457?auto=format&fit=crop&w=500&q=80" alt="King Size Storage Bed" class="product-image" style="object-fit: cover;">
                <div class="product-info">
                    <h3>King Size Storage Bed</h3>
                    <p>Premium bed with hidden under-mattress storage.</p>
                    <div class="price-tag">HKD 3,500.00</div>
                    <button class="btn-add-cart" onclick="openModal(9, 'StorageBed1', 'King Size Storage Bed', 3500.00, 'https://images.unsplash.com/photo-1540518614846-7eded433c457?auto=format&fit=crop&w=500&q=80')">Configure & Add</button>
                </div>
            </div>

            <div class="product-card" data-category="storage" data-room="living">
                <button class="btn-fav" onclick="toggleFav(this)">❤</button>
                <img src="https://images.unsplash.com/photo-1595526114101-23b5bf7123ce?auto=format&fit=crop&w=500&q=80" alt="Minimalist TV Cabinet" class="product-image" style="object-fit: cover;">
                <div class="product-info">
                    <h3>Minimalist TV Cabinet</h3>
                    <p>Low profile TV stand with cable management.</p>
                    <div class="price-tag">HKD 1,450.00</div>
                    <button class="btn-add-cart" onclick="openModal(10, 'TVCabinet1', 'Minimalist TV Cabinet', 1450.00, 'https://images.unsplash.com/photo-1595526114101-23b5bf7123ce?auto=format&fit=crop&w=500&q=80')">Configure & Add</button>
                </div>
            </div>

            <div class="product-card" data-category="tables" data-room="study">
                <button class="btn-fav" onclick="toggleFav(this)">❤</button>
                <img src="https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?auto=format&fit=crop&w=500&q=80" alt="Oak Study Desk" class="product-image" style="object-fit: cover;">
                <div class="product-info">
                    <h3>Oak Study Desk</h3>
                    <p>Perfect for home office, includes 2 drawers.</p>
                    <div class="price-tag">HKD 1,100.00</div>
                    <button class="btn-add-cart" onclick="openModal(11, 'StudyDesk1', 'Oak Study Desk', 1100.00, 'https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?auto=format&fit=crop&w=500&q=80')">Configure & Add</button>
                </div>
            </div>

            <div class="product-card" data-category="seating" data-room="living">
                <button class="btn-fav" onclick="toggleFav(this)">❤</button>
                <img src="https://images.unsplash.com/photo-1617361270800-4b553e1a065c?auto=format&fit=crop&w=500&q=80" alt="Leather Loveseat" class="product-image" style="object-fit: cover;">
                <div class="product-info">
                    <h3>Leather Loveseat</h3>
                    <p>Premium faux leather 2-seater compact sofa.</p>
                    <div class="price-tag">HKD 2,900.00</div>
                    <button class="btn-add-cart" onclick="openModal(12, 'LeatherSofa1', 'Leather Loveseat', 2900.00, 'https://images.unsplash.com/photo-1617361270800-4b553e1a065c?auto=format&fit=crop&w=500&q=80')">Configure & Add</button>
                </div>
            </div>

        </div>
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

                        <div style="display:flex; gap:10px;">
                            <div style="flex:1;">
                                <label class="form-label">Contact Phone:</label>
                                <input type="text" name="ctel" class="custom-select" value="<?php echo htmlspecialchars($curr_user['ctel'] ?? ''); ?>" required>
                            </div>
                            <div style="flex:1;">
                                <label class="form-label">Quantity:</label>
                                <input type="number" id="customQty" name="qty" value="1" min="1" max="20" class="custom-select">
                            </div>
                        </div>

                        <label class="form-label">Delivery Address:</label>
                        <textarea name="caddr" class="custom-textarea" required><?php echo htmlspecialchars($curr_user['caddr'] ?? ''); ?></textarea>

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

        // 🌟 读取网址联动参数
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const roomParam = urlParams.get('room');
            if(roomParam) {
                // 找到对应的左侧菜单按钮并模拟点击
                let btnId = 'btnRoom' + roomParam.charAt(0).toUpperCase() + roomParam.slice(1);
                let btn = document.getElementById(btnId);
                if(btn) { btn.click(); }
            }
        });

        function toggleFav(btn) {
            btn.classList.toggle('active');
            btn.innerHTML = btn.classList.contains('active') ? '❤️' : '❤';
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
            let btns = document.querySelectorAll('.room-btn');
            btns.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
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

                // 只有三者都匹配，才显示商品
                card.style.display = (matchSearch && matchCategory && matchRoom) ? "block" : "none";
            });
        }

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
            var isLocalImage = !activeWebUrl.startsWith("http");

            if (isLocalImage) {
                if (selectedColor === "Red") { previewImg.src = activeImageName + "_red.png"; } 
                else if (selectedColor === "Blue") { previewImg.src = activeImageName + "_blue.png"; } 
                else { previewImg.src = activeImageName + ".png"; }
                previewImg.onerror = function() { this.src = activeImageName + ".png"; this.onerror = null; };
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