<?php
session_start();
require_once 'functions.php';

// 如果已登录，跳转到相应的主页
if (isset($_SESSION['user'])) {
    $user = $_SESSION['user'];
    if ($user['role'] == ADMIN_ROLE) {
        header('Location: admin.php');
    } else {
        header('Location: employee.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } else {
        $user = verifyLogin($username, $password);
        if ($user) {
            $_SESSION['user'] = $user;
            if ($user['role'] == ADMIN_ROLE) {
                header('Location: admin.php');
            } else {
                header('Location: employee.php');
            }
            exit;
        } else {
            $error = '用户名或密码不正确';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>员工年假记录系统 - 登录</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 350px;
        }
        h1 {
            text-align: center;
            color: #1a73e8;
            margin-bottom: 1.5rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #5f6368;
        }
        input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #dadce0;
            border-radius: 4px;
            font-size: 1rem;
        }
        input:focus {
            outline: none;
            border-color: #1a73e8;
            box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.2);
        }
        button {
            width: 100%;
            padding: 0.75rem;
            background-color: #1a73e8;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        button:hover {
            background-color: #1765cc;
        }
        .error {
            color: #d93025;
            text-align: center;
            margin: 1rem 0;
            padding: 0.5rem;
            background-color: #fce8e6;
            border-radius: 4px;
        }
        .footer {
            text-align: center;
            margin-top: 1rem;
            color: #5f6368;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>员工年假记录系统</h1>
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">登录</button>
        </form>
        <div class="footer">
            公司内部系统 &copy; <?php echo date('Y'); ?>
        </div>
    </div>
</body>
</html>
