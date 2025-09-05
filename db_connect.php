<?php
// 数据库连接配置
$host = 'mysql2.sqlpub.com';
$port = '3307';
$dbname = 'pacilink_hr';
$username = 'pacilink_hr';
$password = 'gsLXSXTLiFhj2k6e';

// 创建数据库连接
$conn = new mysqli("$host:$port", $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}
?>
