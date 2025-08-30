<?php
session_start();
require_once 'functions.php';

// 检查登录状态
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];
$success = '';
$error = '';

// 处理退出登录
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// 处理密码修改
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $currentPassword = trim($_POST['current_password']);
    $newPassword = trim($_POST['new_password']);
    $confirmPassword = trim($_POST['confirm_password']);
    
    // 验证当前密码
    if (!password_verify($currentPassword, $currentUser['password'])) {
        $error = '当前密码不正确';
    } 
    // 验证新密码
    elseif (empty($newPassword)) {
        $error = '新密码不能为空';
    } 
    // 验证密码一致性
    elseif ($newPassword != $confirmPassword) {
        $error = '两次输入的新密码不一致';
    } 
    // 密码强度检查
    elseif (strlen($newPassword) < 6) {
        $error = '新密码长度不能少于6个字符';
    } 
    // 更新密码
    else {
        updateUser($currentUser['id'], ['password' => $newPassword]);
        
        // 更新会话中的用户信息
        $currentUser['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $_SESSION['user'] = $currentUser;
        
        $success = '密码已成功更新，请使用新密码登录';
        
        // 3秒后跳转到登录页面
        header('Refresh: 3; url=login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修改密码 - 员工年假记录系统</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        h1 {
            text-align: center;
            color: #202124;
            margin-bottom: 1.5rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #5f6368;
            font-weight: 500;
        }
        input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #dadce0;
            border-radius: 4px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        input:focus {
            outline: none;
            border-color: #1a73e8;
            box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.2);
        }
        .btn {
            width: 100%;
            background-color: #1a73e8;
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 1rem;
        }
        .btn:hover {
            background-color: #1765cc;
        }
        .btn-secondary {
            background-color: #f1f3f4;
            color: #202124;
            margin-top: 0.5rem;
        }
        .btn-secondary:hover {
            background-color: #e8eaed;
        }
        .success {
            color: #137333;
            background-color: #e6f4ea;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: center;
        }
        .error {
            color: #d93025;
            background-color: #fce8e6;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: center;
        }
        .links {
            text-align: center;
            margin-top: 1.5rem;
        }
        .links a {
            color: #1a73e8;
            text-decoration: none;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>修改密码</h1>
        
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php elseif (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (empty($success)): ?>
            <form method="post">
                <div class="form-group">
                    <label for="current_password">当前密码</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">新密码</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">确认新密码</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn">确认修改</button>
                <?php if ($currentUser['role'] == ADMIN_ROLE): ?>
                    <a href="admin.php"><button type="button" class="btn btn-secondary">返回管理面板</button></a>
                <?php else: ?>
                    <a href="employee.php"><button type="button" class="btn btn-secondary">返回员工面板</button></a>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
