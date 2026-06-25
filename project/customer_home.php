<?php
session_start();
require_once 'db_connect.php'; 

// 🌟 严格判断登录状态
$is_logged_in = false;
$user_name = '';
$total_cart_count = 0;

if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'customer') {
    $is_logged_in = true;
    $user_name = $_SESSION['user_name'];
    if (!empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $total_cart_count += $item['qty'];
        }
    }
}

// 🌟 如果未登录，所有功能链接全部指向登录页 index.php
$target_url = $is_logged_in ? "customer_make_order.php" : "index.php";
$cart_url   = $is_logged_in ? "customer_cart.php" : "index.php";
$order_url  = $is_logged_in ? "customer_view_orders.php" : "index.php";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Premium Living - Welcome Home</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background-color: #f9f9f9; color: #333; }
        
        .modern-header {
            background-color: #ffffff; padding: 5px 40px; display: flex;
            justify-content: space-between; align-items: center; border-bottom: 1px solid #eaeaea;
            position: sticky; top: 0; z-index: 1000;
        }
        .brand-area { display: flex; align-items: center; text-decoration: none; }
        .brand-area img { height: 130px; width: auto; object-fit: contain; margin: -20px 0; }
        
        .nav-links { display: flex; align-items: center; gap: 30px; }
        .nav-links a { text-decoration: none; color: #111; font-weight: 600; font-size: 15px; transition: color 0.2s; }
        .nav-links a:hover { color: #f39c12; }
        
        .user-profile-btn {
            display: flex; align-items: center; gap: 8px; background: #f4f6f7; padding: 8px 15px; border-radius: 20px; text-decoration: none; color: #111; font-weight: bold; font-size: 14px; transition: background 0.2s;
        }
        .user-profile-btn:hover { background: #e2e6e9; }
        .btn-login-guest { background-color: #111; color: #fff !important; }
        .btn-login-guest:hover { background-color: #333; }

        .mini-avatar { width: 16px; height: 16px; background-color: #7f8c8d; border-radius: 50%; position: relative; }
        .mini-avatar::after { content: ''; position: absolute; width: 24px; height: 10px; background-color: #7f8c8d; border-radius: 12px 12px 0 0; bottom: -12px; left: -4px; }

        /* 轮播图样式 */
        .home-container { max-width: 1300px; margin: 30px auto; padding: 0 20px; }
        .slider-container { position: relative; height: 550px; border-radius: 16px; overflow: hidden; margin-bottom: 50px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); background-color: #000; }
        .slide {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0;
            transition: opacity 0.8s ease-in-out; display: flex; align-items: center; padding-left: 80px; box-sizing: border-box; background-size: cover; background-position: center; z-index: 0;
        }
        .slide.active { opacity: 1; z-index: 1; }
        .slide::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to right, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.3) 55%, rgba(0,0,0,0) 100%); z-index: 0; }
        .slide-content { position: relative; z-index: 2; color: white; max-width: 550px; }
        .slide-content h2 { font-size: 54px; margin: 0 0 15px 0; line-height: 1.1; font-weight: 800; }
        .slide-content p { font-size: 18px; margin: 0 0 35px 0; line-height: 1.6; color: #eee; }
        
        .btn-shop-now {
            background-color: #ffffff; color: #111; padding: 15px 40px; font-size: 16px; font-weight: 800;
            text-decoration: none; border-radius: 30px; display: inline-block; transition: all 0.3s ease; border: 2px solid #ffffff;
        }
        .btn-shop-now:hover { background-color: transparent; color: #ffffff; }

        .slider-btn {
            position: absolute; top: 50%; transform: translateY(-50%); background-color: rgba(200, 200, 200, 0.4); 
            color: #ffffff; border: none; width: 50px; height: 50px; border-radius: 50%; font-size: 20px; font-weight: bold; cursor: pointer; z-index: 10;
            display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease, background-color 0.3s ease;
        }
        .slider-container:hover .slider-btn { opacity: 1; }
        .slider-btn:hover { background-color: rgba(200, 200, 200, 0.8); }
        .slider-btn.prev { left: 20px; }
        .slider-btn.next { right: 20px; }

        /* 分类网格样式 */
        .section-title { font-size: 28px; font-weight: 800; margin-bottom: 25px; margin-top: 50px; color: #111; border-bottom: 2px solid #111; padding-bottom: 10px; display: inline-block; }
        
        .category-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .category-card {
            position: relative; height: 350px; border-radius: 12px; overflow: hidden;
            display: flex; align-items: flex-end; padding: 30px; text-decoration: none; background-color: #eee;
        }
        .category-card img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s ease; z-index: 0; }
        .category-card:hover img { transform: scale(1.05); }
        .category-card::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to top, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0) 40%); z-index: 1; }
        .category-title { position: relative; z-index: 2; color: white; font-size: 26px; font-weight: 800; }

        /* 热门推荐网格 */
        .popular-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 60px; }
        .popular-card {
            background: #fff; border-radius: 8px; text-decoration: none; color: #333; overflow: hidden;
            transition: box-shadow 0.3s ease, transform 0.3s ease; border: 1px solid #eaeaea;
        }
        .popular-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
        .popular-card img { width: 100%; height: 200px; object-fit: cover; background-color: #f4f6f7; border-bottom: 1px solid #eaeaea; }
        .popular-info { padding: 15px; }
        .popular-info h3 { margin: 0 0 5px 0; font-size: 16px; font-weight: 700; color: #111; }
        .popular-info p { margin: 0; color: #e74c3c; font-weight: bold; font-size: 16px; }
    </style>
</head>
<body>

    <div class="modern-header">
        <a href="customer_home.php" class="brand-area">
            <img src="Logo(text).png?v=1" alt="Premium Living Logo">
        </a>
        <div class="nav-links">
            <a href="<?php echo $target_url; ?>">Products</a>
            <a href="<?php echo $cart_url; ?>">Cart <?php echo $is_logged_in ? "($total_cart_count)" : ""; ?></a>
            <a href="<?php echo $order_url; ?>">My Orders</a>
            
            <?php if ($is_logged_in): ?>
                <a href="customer_profile.php" class="user-profile-btn">
                    <div style="width:16px; height:24px; display:flex; align-items:center; justify-content:center; overflow:hidden; margin-right:5px;">
                        <div class="mini-avatar"></div>
                    </div>
                    <?php echo htmlspecialchars($user_name); ?>
                </a>
            <?php else: ?>
                <a href="index.php" class="user-profile-btn btn-login-guest">
                    Login / Sign In
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="home-container">
        
        <div class="slider-container">
            <button class="slider-btn prev" onclick="changeSlide(-1)">&#10094;</button>
            <button class="slider-btn next" onclick="changeSlide(1)">&#10095;</button>

            <div class="slide active" style="background-image: url('https://images.unsplash.com/photo-1586023492125-27b2c045efd7?auto=format&fit=crop&w=1600&q=80');">
                <div class="slide-content">
                    <h2>Bring your dream<br>home to life.</h2>
                    <p>Discover our new collection of premium, customizable furniture designed for modern living. Comfort meets elegance.</p>
                    <a href="<?php echo $target_url; ?>" class="btn-shop-now">Shop Living Room</a>
                </div>
            </div>

            <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1600&q=80');">
                <div class="slide-content">
                    <h2>Dine with<br>absolute elegance.</h2>
                    <p>Experience our solid oak dining tables. Perfect for family gatherings and creating unforgettable memories.</p>
                    <a href="<?php echo $target_url; ?>" class="btn-shop-now">Explore Dining</a>
                </div>
            </div>

            <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1505693314120-0d443867891c?auto=format&fit=crop&w=1600&q=80');">
                <div class="slide-content">
                    <h2>Sleep in<br>pure comfort.</h2>
                    <p>Sturdy, beautifully crafted queen size bed frames designed to give you the rest you deserve.</p>
                    <a href="<?php echo $target_url; ?>" class="btn-shop-now">View Bedrooms</a>
                </div>
            </div>
        </div>

        <div style="width: 100%;"><h2 class="section-title">Shop by Room</h2></div>
        <div class="category-grid">
            <a href="<?php echo $target_url; ?>" class="category-card">
                <img src="https://images.unsplash.com/photo-1493663284031-b7e3aefcae8e?auto=format&fit=crop&w=600&q=80" alt="Living Room">
                <span class="category-title">Living Room</span>
            </a>
            <a href="<?php echo $target_url; ?>" class="category-card">
                <img src="https://images.unsplash.com/photo-1540518614846-7eded433c457?auto=format&fit=crop&w=600&q=80" alt="Bedroom">
                <span class="category-title">Bedroom</span>
            </a>
            <a href="<?php echo $target_url; ?>" class="category-card">
                <img src="https://images.unsplash.com/photo-1595428774223-ef52624120d2?auto=format&fit=crop&w=600&q=80" onerror="this.src='https://images.unsplash.com/photo-1583847268964-b28dc8f51f92?auto=format&fit=crop&w=600&q=80';" alt="Storage">
                <span class="category-title">Storage & Wardrobes</span>
            </a>
        </div>

        <div style="width: 100%;"><h2 class="section-title">Popular Furniture</h2></div>
        <div class="popular-grid">
            <a href="<?php echo $target_url; ?>" class="popular-card">
                <img src="WoodenRider1.png" alt="Oak Dining Chair">
                <div class="popular-info">
                    <h3>Oak Dining Chair</h3>
                    <p>HKD 450.00</p>
                </div>
            </a>
            <a href="<?php echo $target_url; ?>" class="popular-card">
                <img src="Sofa1.png" alt="Fabric Sofa">
                <div class="popular-info">
                    <h3>3-Seater Fabric Sofa</h3>
                    <p>HKD 3,800.00</p>
                </div>
            </a>
            <a href="<?php echo $target_url; ?>" class="popular-card">
                <img src="WoodenBed1.png" alt="Queen Size Bed">
                <div class="popular-info">
                    <h3>Queen Size Bed Frame</h3>
                    <p>HKD 2,200.00</p>
                </div>
            </a>
            <a href="<?php echo $target_url; ?>" class="popular-card">
                <img src="WoodenTable1.png" alt="Dining Table">
                <div class="popular-info">
                    <h3>Large Dining Table</h3>
                    <p>HKD 2,500.00</p>
                </div>
            </a>
        </div>

    </div>

    <script>
        const slides = document.querySelectorAll('.slide');
        let currentSlide = 0;
        const slideInterval = 5000; 
        let slideTimer;

        function showSlide(index) {
            slides[currentSlide].classList.remove('active');
            currentSlide = (index + slides.length) % slides.length;
            slides[currentSlide].classList.add('active');
        }

        function nextSlide() { showSlide(currentSlide + 1); }

        function changeSlide(direction) {
            showSlide(currentSlide + direction);
            resetTimer(); 
        }

        function resetTimer() {
            clearInterval(slideTimer);
            slideTimer = setInterval(nextSlide, slideInterval);
        }

        document.addEventListener('DOMContentLoaded', function() {
            slideTimer = setInterval(nextSlide, slideInterval);
        });
    </script>
</body>
</html>