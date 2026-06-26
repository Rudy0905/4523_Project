<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'staff') { header("Location: index.php"); exit; }
$staff_name = $_SESSION['user_name'] ?? 'Admin';

// 🌟 终极防崩溃机制：先检查数据库是否已经有 rating 字段
$has_rating = false;
try {
    $pdo->query("SELECT rating FROM Orders LIMIT 1");
    $has_rating = true;
} catch (Exception $e) {
    $has_rating = false;
}

try {
    // 1. 销量排行榜数据 (用于柱状图) - 这个不依赖评价，绝对安全
    $stmtSales = $pdo->query("SELECT f.fname, COALESCE(SUM(of.oqty), 0) AS total_qty, COALESCE(SUM(of.oqty * f.fprice), 0) AS total_amount FROM Furnitures f LEFT JOIN OrderFurnitures of ON f.fid = of.fid GROUP BY f.fid ORDER BY total_qty DESC LIMIT 5");
    $sales_data = $stmtSales->fetchAll();
    $bar_labels = []; $bar_data = [];
    foreach($sales_data as $row) {
        $bar_labels[] = $row['fname'];
        $bar_data[] = $row['total_qty'];
    }

    $stmtTotalItems = $pdo->query("SELECT SUM(oqty) as total_units FROM OrderFurnitures");
    $total_units = $stmtTotalItems->fetchColumn() ?: 0;

    // 5. 详细销售表格数据
    $stmtFullReport = $pdo->query("SELECT f.fid, f.fname, f.fprice, COALESCE(SUM(of.oqty), 0) AS total_qty_sold, COALESCE(SUM(of.oqty * f.fprice), 0) AS total_sales_amount FROM Furnitures f LEFT JOIN OrderFurnitures of ON f.fid = of.fid GROUP BY f.fid ORDER BY total_sales_amount DESC");
    $full_report = $stmtFullReport->fetchAll();

    // 如果数据库正常更新了，拉取评价数据；否则给默认值 0
    if ($has_rating) {
        $stmtMetrics = $pdo->query("SELECT COUNT(*) as total_orders, SUM(ototalamount) as gross_revenue, AVG(rating) as avg_rating, COUNT(rating) as total_reviews FROM Orders");
        $metrics = $stmtMetrics->fetch();

        $stmtRatings = $pdo->query("SELECT rating, COUNT(*) as rcount FROM Orders WHERE rating IS NOT NULL GROUP BY rating ORDER BY rating DESC");
        $rating_counts = [5=>0, 4=>0, 3=>0, 2=>0, 1=>0];
        while($row = $stmtRatings->fetch()) { $rating_counts[$row['rating']] = $row['rcount']; }

        $stmtFeedbacks = $pdo->query("SELECT o.oid, c.cname, o.rating, o.review_comment, o.odate FROM Orders o JOIN Customers c ON o.cid = c.cid WHERE o.rating IS NOT NULL ORDER BY o.odate DESC LIMIT 6");
        $feedbacks = $stmtFeedbacks->fetchAll();
    } else {
        // 如果没更新数据库，为了防止崩溃，给出兜底数据
        $stmtMetricsFallback = $pdo->query("SELECT COUNT(*) as total_orders, SUM(ototalamount) as gross_revenue FROM Orders");
        $metrics = $stmtMetricsFallback->fetch();
        $metrics['avg_rating'] = 0; $metrics['total_reviews'] = 0;
        $rating_counts = [5=>0, 4=>0, 3=>0, 2=>0, 1=>0];
        $feedbacks = [];
    }

} catch (Exception $e) { die("Database Error: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Analytics Dashboard - Staff Portal</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; background: #f0f2f5; display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 260px; background: #1a252f; color: #ecf0f1; display: flex; flex-direction: column; box-shadow: 2px 0 10px rgba(0,0,0,0.1); z-index: 100; flex-shrink: 0; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid #2c3e50; background: #141d26; }
        .sidebar-menu { flex: 1; padding: 20px 0; overflow-y: auto; }
        .sidebar-menu a { display: block; padding: 15px 25px; color: #bdc3c7; text-decoration: none; font-size: 15px; font-weight: 600; border-left: 4px solid transparent; transition: 0.2s; }
        .sidebar-menu a:hover { background: #2c3e50; color: #fff; }
        .sidebar-menu a.active { background: #2980b9; color: #fff; border-left-color: #3498db; }
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-navbar { background: #fff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .btn-logout { background: #e74c3c; color: #fff; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: bold; }
        
        .dashboard-body { padding: 30px; max-width: 1400px; margin: 0 auto; width: 100%; box-sizing: border-box; }
        
        .metrics-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .metric-card { background: #fff; padding: 25px; border-radius: 12px; border: 1px solid #eaeaea; box-shadow: 0 4px 15px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 20px; }
        .metric-icon { width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 28px; }
        .metric-info h4 { margin: 0 0 5px 0; color: #7f8c8d; font-size: 14px; text-transform: uppercase; }
        .metric-info h2 { margin: 0; color: #2c3e50; font-size: 28px; font-weight: 900; }
        
        .bg-blue { background: #ebf5fb; color: #3498db; }
        .bg-green { background: #e8f8f5; color: #27ae60; }
        .bg-yellow { background: #fef9e7; color: #f39c12; }
        .bg-purple { background: #f4ecf8; color: #8e44ad; }

        .charts-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; margin-bottom: 30px; }
        .chart-box { background: #fff; padding: 25px; border-radius: 12px; border: 1px solid #eaeaea; box-shadow: 0 4px 15px rgba(0,0,0,0.02); display: flex; flex-direction: column;}
        .chart-box h3 { margin: 0 0 20px 0; color: #2c3e50; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;}

        .bottom-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 25px; margin-bottom: 50px; }
        
        .feedback-list { display: flex; flex-direction: column; gap: 15px; }
        .feedback-item { background: #fdfbf7; border-left: 4px solid #f1c40f; padding: 15px; border-radius: 6px; }
        .feedback-header { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 13px; color: #7f8c8d; }
        .feedback-stars { color: #f39c12; font-size: 16px; letter-spacing: 2px; margin-bottom: 5px; }
        .feedback-text { font-size: 14px; color: #333; line-height: 1.4; margin: 0; }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eaeaea; font-size: 14px; }
        .data-table th { background-color: #fafbfc; color: #7f8c8d; text-transform: uppercase; font-size: 12px; }
        .btn-print { background: #34495e; color: #fff; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 13px; transition: 0.2s; }
        .btn-print:hover { background: #2c3e50; }
        
        @media print {
            .sidebar, .top-navbar, .btn-print { display: none !important; }
            .main-content { overflow: visible !important; }
            body { background: #fff !important; }
            .chart-box, .metric-card { box-shadow: none !important; border: 1px solid #ccc !important; }
        }
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
            <a href="staff_generate_report.php" class="active">📊 Analytics & Report</a>
            <a href="staff_support.php">💬 Customer Support</a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-navbar">
            <h2>Business Analytics Dashboard</h2>
            <a href="index.php?logout=1" class="btn-logout">Logout</a>
        </div>
        
        <div class="dashboard-body">
            
            <?php if(!$has_rating): ?>
                <div style="background:#fff3cd; color:#856404; padding:15px; border-radius:6px; margin-bottom:20px; font-weight:bold; border:1px solid #ffeeba;">
                    ⚠️ Notice: Please run the SQL script to add the `rating` and `review_comment` columns to the database to view customer feedback!
                </div>
            <?php endif; ?>

            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-icon bg-green">💰</div>
                    <div class="metric-info"><h4>Gross Revenue</h4><h2>HKD <?php echo number_format($metrics['gross_revenue'] ?? 0, 0); ?></h2></div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon bg-blue">📦</div>
                    <div class="metric-info"><h4>Units Sold</h4><h2><?php echo $total_units; ?></h2></div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon bg-yellow">⭐</div>
                    <div class="metric-info"><h4>Average Rating</h4><h2><?php echo number_format($metrics['avg_rating'], 1); ?> / 5</h2></div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon bg-purple">📝</div>
                    <div class="metric-info"><h4>Total Reviews</h4><h2><?php echo $metrics['total_reviews']; ?></h2></div>
                </div>
            </div>

            <div class="charts-grid">
                <div class="chart-box">
                    <h3>Top 5 Best Sellers <span>📊</span></h3>
                    <div style="position: relative; height: 260px; width: 100%;">
                        <canvas id="barChart"></canvas>
                    </div>
                </div>
                <div class="chart-box">
                    <h3>Rating Distribution <span>🎯</span></h3>
                    <div style="position: relative; height: 260px; width: 100%;">
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="bottom-grid">
                <div class="chart-box" style="height: 400px; overflow-y: auto;">
                    <h3 style="position:sticky; top:-25px; background:#fff; padding:10px 0; margin-top:-10px; z-index:10;">Recent Reviews</h3>
                    <div class="feedback-list">
                        <?php if(empty($feedbacks)): ?>
                            <p style="text-align:center; color:#999; margin-top:50px;">No reviews available yet.</p>
                        <?php else: ?>
                            <?php foreach($feedbacks as $fb): ?>
                                <div class="feedback-item">
                                    <div class="feedback-header">
                                        <strong><?php echo htmlspecialchars($fb['cname']); ?></strong>
                                        <span>Order #<?php echo $fb['oid']; ?></span>
                                    </div>
                                    <div class="feedback-stars">
                                        <?php echo str_repeat('★', $fb['rating']) . str_repeat('☆', 5 - $fb['rating']); ?>
                                    </div>
                                    <p class="feedback-text">"<?php echo htmlspecialchars($fb['review_comment']); ?>"</p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="chart-box">
                    <h3>
                        Detailed Sales Report
                        <button class="btn-print" onclick="window.print()">🖨️ Print PDF</button>
                    </h3>
                    <div style="overflow-y:auto; height: 350px;">
                        <table class="data-table">
                            <thead>
                                <tr style="position:sticky; top:0; background:#fafbfc; box-shadow:0 1px 0 #eaeaea;">
                                    <th>Item ID</th>
                                    <th>Product Name</th>
                                    <th>Unit Price</th>
                                    <th>Units Sold</th>
                                    <th style="text-align:right;">Total Sales (HKD)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($full_report as $row): ?>
                                <tr>
                                    <td>#<?php echo $row['fid']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['fname']); ?></strong></td>
                                    <td><?php echo number_format($row['fprice'], 2); ?></td>
                                    <td><?php echo $row['total_qty_sold']; ?></td>
                                    <td style="text-align:right; font-weight:bold; color:#27ae60;">
                                        <?php echo number_format($row['total_sales_amount'], 2); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 渲染柱状图 (加上了 maintainAspectRatio: false 防溢出)
        const barCtx = document.getElementById('barChart').getContext('2d');
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($bar_labels); ?>,
                datasets: [{
                    label: 'Units Sold',
                    data: <?php echo json_encode($bar_data); ?>,
                    backgroundColor: 'rgba(52, 152, 219, 0.7)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });

        // 渲染饼图 (防溢出设计)
        const pieCtx = document.getElementById('pieChart').getContext('2d');
        new Chart(pieCtx, {
            type: 'doughnut', 
            data: {
                labels: ['5 Stars', '4 Stars', '3 Stars', '2 Stars', '1 Star'],
                datasets: [{
                    data: [
                        <?php echo $rating_counts[5]; ?>, 
                        <?php echo $rating_counts[4]; ?>, 
                        <?php echo $rating_counts[3]; ?>, 
                        <?php echo $rating_counts[2]; ?>, 
                        <?php echo $rating_counts[1]; ?>
                    ],
                    backgroundColor: ['#2ecc71', '#3498db', '#f1c40f', '#e67e22', '#e74c3c'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: { legend: { position: 'right' } }
            }
        });
    </script>
</body>
</html>