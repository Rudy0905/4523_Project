<?php
// 开启 Session 记录购物车数据
session_start();

// 权限拦截：如果未登录或不是 customer，退回登录页
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: index.html");
    exit;
}

// 初始化购物车 Session 结构（如果不存在就建一个空数组）
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// 🌟 核心处理：处理弹窗表单提交，将带有定制属性的商品加入购物车
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart_custom'])) {
    $fid = intval($_POST['fid'] ?? 0); 
    $img_name = $_POST['base_name'] ?? ''; 
    $qty = intval($_POST['qty'] ?? 1);
    $size = $_POST['size'] ?? 'Standard';
    $color = $_POST['color'] ?? 'Original';
    $prod_name = $_POST['prod_name'] ?? 'Furniture Item';
    $prod_price = floatval($_POST['prod_price'] ?? 0);

    // 建立一个唯一的商品规格 Key（商品图片基准名 + 尺寸 + 颜色）
    $cart_key = $img_name . "_" . md5($size . "_" . $color);

    if (isset($_SESSION['cart'][$cart_key])) {
        $_SESSION['cart'][$cart_key]['qty'] += $qty;
    } else {
        $_SESSION['cart'][$cart_key] = [
            'fid'       => $fid,        
            'base_name' => $img_name, 
            'qty'       => $qty,
            'size'      => $size,
            'color'     => $color,
            'name'      => $prod_name,
            'price'     => $prod_price
        ];
    }
    
    header("Location: customer_make_order.php");
    exit;
}

// 计算导航栏购物车计数
$total_cart_count = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $total_cart_count += $item['qty'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Make Order - Premium Living Furniture</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .product-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px; }
        .product-card { background-color: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 15px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .product-image { width: 100%; height: 200px; background-color: #ecf0f1; border-radius: 4px; object-fit: cover; margin-bottom: 12px; }
        .price-tag { color: #e74c3c; font-size: 18px; font-weight: bold; margin: 10px 0; }
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fff; margin: 5% auto; padding: 25px; border-radius: 8px; width: 90%; max-width: 450px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); position: relative; }
        .close-btn { position: absolute; right: 20px; top: 15px; font-size: 24px; cursor: pointer; color: #aaa; }
        .close-btn:hover { color: #000; }
        .custom-select { width: 100%; padding: 10px; margin-top: 5px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; }
        .modal-image-container { width: 100%; height: 220px; background-color: #f8f9fa; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 15px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .modal-preview-img { max-width: 100%; max-height: 100%; object-fit: contain; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Premium Living Furniture</h1>
    </div>

    <div class="navbar">
        <a href="customer_make_order.php" class="active">Browse Furniture</a>
        <a href="customer_cart.php">My Cart (<?php echo $total_cart_count; ?>)</a>
        <a href="customer_view_orders.php">My Orders</a>
        
        <a href="customer_profile.php" style="float: right; display: flex; align-items: center; justify-content: center; padding: 10px 20px; cursor: pointer; text-decoration: none; background-color: #34495e; border-left: 1px solid #4f5f6f;" title="My Profile">
            <div style="width: 24px; height: 24px; background-color: #bdc3c7; border-radius: 50%; position: relative; display: inline-block;">
                <style>
                    .nav-avatar-icon::before { content: ''; position: absolute; width: 10px; height: 10px; background-color: #7f8c8d; border-radius: 50%; top: 3px; left: 7px; }
                    .nav-avatar-icon::after { content: ''; position: absolute; width: 18px; height: 8px; background-color: #7f8c8d; border-radius: 6px 6px 0 0; bottom: 1px; left: 3px; }
                </style>
                <div class="nav-avatar-icon"></div>
            </div>
            <span style="color: white; margin-left: 8px; font-size: 14px; font-weight: bold;"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
        </a>
    </div>

    <div class="container">
        <h2>Our Furniture Collection</h2>
        <p>Explore our high-quality pieces and add them to your order.</p>

        <div class="product-grid">
            
            <div class="product-card">
                <img src="WoodenRider1.png" alt="Oak Dining Chair" class="product-image">
                <h3>Oak Dining Chair</h3>
                <p style="color: #7f8c8d; font-size: 14px;">Classic style solid oak chair.</p>
                <div class="price-tag">HKD 450.00</div>
                <button class="btn-add-cart" style="background-color: #3498db; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; width: 100%; font-size: 14px;" onclick="openCustomizeModal(1, 'WoodenRider1', 'Oak Dining Chair', 450.00)">Configure & Add</button>
            </div>

            <div class="product-card">
                <img src="WoodenTable1.png" alt="Large Dining Table" class="product-image">
                <h3>Large Dining Table</h3>
                <p style="color: #7f8c8d; font-size: 14px;">6-seater family dining table.</p>
                <div class="price-tag">HKD 2,500.00</div>
                <button class="btn-add-cart" style="background-color: #3498db; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; width: 100%; font-size: 14px;" onclick="openCustomizeModal(2, 'WoodenTable1', 'Large Dining Table', 2500.00)">Configure & Add</button>
            </div>

            <div class="product-card">
                <img src="Sofa1.png" alt="3-Seater Fabric Sofa" class="product-image">
                <h3>3-Seater Fabric Sofa</h3>
                <p style="color: #7f8c8d; font-size: 14px;">Comfortable sofa with foam filling.</p>
                <div class="price-tag">HKD 3,800.00</div>
                <button class="btn-add-cart" style="background-color: #3498db; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; width: 100%; font-size: 14px;" onclick="openCustomizeModal(3, 'Sofa1', '3-Seater Fabric Sofa', 3800.00)">Configure & Add</button>
            </div>

            <div class="product-card">
                <img src="WoodenWardrobe1.png" alt="Wooden Wardrobe" class="product-image">
                <h3>Wooden Wardrobe</h3>
                <p style="color: #7f8c8d; font-size: 14px;">Double door wardrobe with hanging space.</p>
                <div class="price-tag">HKD 1,800.00</div>
                <button class="btn-add-cart" style="background-color: #3498db; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; width: 100%; font-size: 14px;" onclick="openCustomizeModal(4, 'WoodenWardrobe1', 'Wooden Wardrobe', 1800.00)">Configure & Add</button>
            </div>
            
            <div class="product-card">
                <img src="Shelf1.png" alt="Industrial Bookshelf" class="product-image">
                <h3>Industrial Bookshelf</h3>
                <p style="color: #7f8c8d; font-size: 14px;">Modern steel frame bookshelf.</p>
                <div class="price-tag">HKD 1,200.00</div>
                <button class="btn-add-cart" style="background-color: #3498db; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; width: 100%; font-size: 14px;" onclick="openCustomizeModal(5, 'Shelf1', 'Industrial Bookshelf', 1200.00)">Configure & Add</button>
            </div>

            <div class="product-card">
                <img src="WoodenBed1.png" alt="Queen Size Bed Frame" class="product-image">
                <h3>Queen Size Bed Frame</h3>
                <p style="color: #7f8c8d; font-size: 14px;">Sturdy frame for queen size mattress.</p>
                <div class="price-tag">HKD 2,200.00</div>
                <button class="btn-add-cart" style="background-color: #3498db; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; width: 100%; font-size: 14px;" onclick="openCustomizeModal(6, 'WoodenBed1', 'Queen Size Bed Frame', 2200.00)">Configure & Add</button>
            </div>

        </div>
    </div>

    <div id="customizeModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeCustomizeModal()">&times;</span>
            <h3 id="modalFurnitureName">Customize Furniture</h3>
            <p id="modalFurniturePrice" style="color:#e74c3c; font-weight:bold; margin-top:5px; margin-bottom:15px;"></p>
            
            <div class="modal-image-container">
                <img id="modalPreviewImg" src="" alt="Custom preview" class="modal-preview-img">
            </div>

            <form action="customer_make_order.php" method="POST">
                <input type="hidden" id="modalFid" name="fid" value="">
                <input type="hidden" id="modalBaseName" name="base_name" value="">
                <input type="hidden" id="modalProdName" name="prod_name" value="">
                <input type="hidden" id="modalProdPrice" name="prod_price" value="">

                <div class="form-group">
                    <label for="customSize"><strong>Select Size Option:</strong></label>
                    <select id="customSize" name="size" class="custom-select">
                        <option value="Standard">Standard Size</option>
                        <option value="Compact (Space-Saving)">Compact (Space-Saving)</option>
                        <option value="Extra Large (Premium)">Extra Large (Premium)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="customColor"><strong>Select Color & Finish:</strong></label>
                    <select id="customColor" name="color" class="custom-select" onchange="updateModalImage()">
                        <option value="Original">Original (Default)</option>
                        <option value="Red">Red Color</option>
                        <option value="Blue">Blue Color</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="customQty"><strong>Quantity:</strong></label>
                    <input type="number" id="customQty" name="qty" value="1" min="1" max="20" style="padding:10px; width:100%; box-sizing:border-box; border:1px solid #ccc; border-radius:4px;">
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" name="add_to_cart_custom" class="btn-add-cart" style="background-color: #2ecc71; color: white; border: none; padding: 12px; border-radius: 4px; cursor: pointer; width: 100%; font-size: 16px;">Add Customized Item to Cart</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        var activeImageName = "";

        function openCustomizeModal(fid, imgName, name, price) {
            activeImageName = imgName; 
            
            document.getElementById('modalFid').value = fid;
            document.getElementById('modalBaseName').value = imgName;
            document.getElementById('modalProdName').value = name;
            document.getElementById('modalProdPrice').value = price;

            document.getElementById('modalFurnitureName').innerText = "Configure: " + name;
            document.getElementById('modalFurniturePrice').innerText = "Price: HKD " + price.toFixed(2);
            
            document.getElementById('customColor').selectedIndex = 0;
            document.getElementById('customQty').value = 1;
            
            updateModalImage();
            document.getElementById('customizeModal').style.display = "block";
        }

        function updateModalImage() {
            var colorSelect = document.getElementById('customColor');
            var selectedColor = colorSelect.value;
            var previewImg = document.getElementById('modalPreviewImg');
            
            // 🎯 完美切回真正的 .png 弹窗后缀
            var baseImage = activeImageName + ".png";
            
            if (selectedColor === "Red") {
                previewImg.src = activeImageName + "_red.png";
            } else if (selectedColor === "Blue") {
                previewImg.src = activeImageName + "_blue.png";
            } else {
                previewImg.src = baseImage;
            }

            previewImg.onerror = function() {
                this.src = baseImage; 
                this.onerror = null; 
            };
        }

        function closeCustomizeModal() {
            document.getElementById('customizeModal').style.display = "none";
        }

        window.onclick = function(event) {
            var modal = document.getElementById('customizeModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>