<?php
// 包含数据库连接及 Session 启动
// Include database connection and Session startup

$host = '127.0.0.1';
$db   = 'ProjectDB'; 
$user = 'root';      
$pass = '';          
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // 生产环境建议更改，此处方便你在 XAMPP 调试
     // Recommended to change in production, kept here for easy debugging in XAMPP
     die("Database connection failed: " . $e->getMessage());
}

// 如果未开启 Session，则启动它
// Start session if it is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>