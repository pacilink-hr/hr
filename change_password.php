<?php
session_start();
// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db_connect.php';
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$name = $_SESSION['name'];

$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // 验证新密码是否一致
    if ($new_password !== $confirm_password) {
        $message = "新密码和确认密码不一致";
        $message_type = "error";
    } else {
        // 获取当前密码
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // 验证当前密码
        if ($current_password !== $user['password']) { // 实际应用中应使用password_verify()
            $message = "当前密码不正确";
            $message_type = "error";
        } else {
            // 更新密码
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $new_password, $user_id);
            
            if ($stmt->execute()) {
                $message = "密码修改成功，请使用新密码登录";
                $message_type = "success";
                // 密码修改成功后登出用户
                session_destroy();
                // 3秒后跳转到登录页
                echo "<meta http-equiv='refresh' content='3;url=login.php'>";
            } else {
                $message = "修改失败，请重试: " . $conn->error;
                $message_type = "error";
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修改密码 - 人力资源管理系统</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- 顶部导航栏 -->
    <nav class="bg-blue-600 text-white shadow-md">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <i class="fas fa-users text-2xl mr-2"></i>
                    <h1 class="text-xl font-bold">人力资源管理系统</h1>
                </div>
                
                <div class="relative" id="userMenuContainer">
                    <button id="userMenuButton" class="flex items-center focus:outline-none">
                        <span class="mr-2"><?php echo $name; ?> (<?php echo ucfirst($_SESSION['role']); ?>)</span>
                        <i class="fas fa-user-circle text-xl"></i>
                    </button>
                    
                    <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10">
                        <a href="index.php" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">
                            <i class="fas fa-home mr-2"></i>返回主页
                        </a>
                        <a href="logout.php" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">
                            <i class="fas fa-sign-out-alt mr-2"></i>退出登录
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-6">
        <div class="bg-white rounded-lg shadow p-6 max-w-md mx-auto">
            <h2 class="text-2xl font-bold mb-6 text-center">修改密码</h2>
            
            <?php if ($message): ?>
                <div class="mb-4 p-4 rounded <?php echo $message_type == 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($message_type != 'success'): ?>
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="mb-4">
                        <label for="current_password" class="block text-gray-700 mb-2">当前密码 <span class="text-red-500">*</span></label>
                        <input type="password" id="current_password" name="current_password" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="new_password" class="block text-gray-700 mb-2">新密码 <span class="text-red-500">*</span></label>
                        <input type="password" id="new_password" name="new_password" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               required>
                    </div>
                    
                    <div class="mb-6">
                        <label for="confirm_password" class="block text-gray-700 mb-2">确认新密码 <span class="text-red-500">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               required>
                    </div>
                    
                    <div class="flex justify-center gap-4">
                        <a href="index.php" 
                           class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-6 rounded transition duration-300">
                            <i class="fas fa-times mr-1"></i> 取消
                        </a>
                        <button type="submit" 
                                class="bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-6 rounded transition duration-300">
                            <i class="fas fa-save mr-1"></i> 保存修改
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center py-6">
                    <i class="fas fa-check-circle text-5xl text-green-500 mb-4"></i>
                    <p class="text-gray-600">即将跳转到登录页面...</p>
                    <a href="login.php" class="mt-4 inline-block text-blue-500 hover:text-blue-700">
                        立即前往登录页面
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // 用户菜单点击触发
        document.getElementById('userMenuButton').addEventListener('click', function() {
            const menu = document.getElementById('userMenu');
            menu.classList.toggle('hidden');
        });
        
        // 点击页面其他地方关闭菜单
        document.addEventListener('click', function(event) {
            const container = document.getElementById('userMenuContainer');
            if (!container.contains(event.target)) {
                document.getElementById('userMenu').classList.add('hidden');
            }
        });
    </script>
</body>
</html>
