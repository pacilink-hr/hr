<?php
session_start();
// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 只有员工和经理可以申请请假
if ($_SESSION['role'] == 'admin') {
    header("Location: index.php");
    exit();
}

include 'db_connect.php';
$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'];

// 获取用户年假信息
$stmt = $conn->prepare("SELECT total_annual_leave, used_annual_leave FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_info = $result->fetch_assoc();
$stmt->close();

$remaining_days = $user_info['total_annual_leave'] - $user_info['used_annual_leave'];

// 获取用户的直属上司
$stmt = $conn->prepare("SELECT u.id, u.name FROM users u WHERE u.id = (SELECT manager_id FROM users WHERE id = ?)");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$manager_result = $stmt->get_result();
$manager = $manager_result->fetch_assoc();
$stmt->close();

$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $days = $_POST['days'];
    $reason = $_POST['reason'];
    
    // 验证日期
    $today = date('Y-m-d');
    $max_date = date('Y-m-d', strtotime('+1 year'));
    
    if (strtotime($start_date) > strtotime($max_date)) {
        $message = "请假日期不能超过未来1年";
        $message_type = "error";
    } elseif (strtotime($start_date) > strtotime($end_date)) {
        $message = "开始日期不能晚于结束日期";
        $message_type = "error";
    } elseif ($days <= 0) {
        $message = "请假天数必须大于0";
        $message_type = "error";
    } elseif ($days > $remaining_days) {
        $message = "请假天数超过剩余年假天数";
        $message_type = "error";
    } else {
        // 保存请假申请
        $stmt = $conn->prepare("INSERT INTO leave_records (user_id, start_date, end_date, days, reason, type, status, approver_id) 
                              VALUES (?, ?, ?, ?, ?, 'annual', 'pending', ?)");
        $stmt->bind_param("issddi", $user_id, $start_date, $end_date, $days, $reason, $manager['id']);
        
        if ($stmt->execute()) {
            $message = "请假申请已提交，请等待上司批准";
            $message_type = "success";
        } else {
            $message = "提交失败，请重试: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>请假申请 - 人力资源管理系统</title>
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
                        <a href="change_password.php" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">
                            <i class="fas fa-key mr-2"></i>修改密码
                        </a>
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
        <div class="bg-white rounded-lg shadow p-6 max-w-2xl mx-auto">
            <h2 class="text-2xl font-bold mb-6 text-center">请假申请</h2>
            
            <?php if ($message): ?>
                <div class="mb-4 p-4 rounded <?php echo $message_type == 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="mb-6 bg-blue-50 p-4 rounded-lg">
                <h3 class="font-semibold mb-2">年假状态</h3>
                <div class="flex justify-between items-center mb-2">
                    <span>总年假天数：</span>
                    <span class="font-bold"><?php echo $user_info['total_annual_leave']; ?> 天</span>
                </div>
                <div class="flex justify-between items-center mb-2">
                    <span>已使用天数：</span>
                    <span class="font-bold"><?php echo $user_info['used_annual_leave']; ?> 天</span>
                </div>
                <div class="flex justify-between items-center">
                    <span>剩余天数：</span>
                    <span class="font-bold text-green-600"><?php echo $remaining_days; ?> 天</span>
                </div>
            </div>
            
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" onsubmit="return validateForm()">
                <div class="mb-4">
                    <label for="start_date" class="block text-gray-700 mb-2">开始日期 <span class="text-red-500">*</span></label>
                    <input type="date" id="start_date" name="start_date" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                </div>
                
                <div class="mb-4">
                    <label for="end_date" class="block text-gray-700 mb-2">结束日期 <span class="text-red-500">*</span></label>
                    <input type="date" id="end_date" name="end_date" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                </div>
                
                <div class="mb-4">
                    <label for="days" class="block text-gray-700 mb-2">请假天数（天） <span class="text-red-500">*</span></label>
                    <div class="flex items-center">
                        <button type="button" id="decreaseDays" 
                                class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded-l-md">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" id="days" name="days" min="0.5" step="0.5" value="1" 
                               class="w-full px-3 py-2 border-t border-b border-gray-300 text-center focus:outline-none"
                               required>
                        <button type="button" id="increaseDays" 
                                class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded-r-md">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">提示：系统会自动计算工作日，您也可以手动调整（以0.5天为单位）</p>
                </div>
                
                <div class="mb-4">
                    <label for="reason" class="block text-gray-700 mb-2">请假原因 <span class="text-red-500">*</span></label>
                    <textarea id="reason" name="reason" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                              required></textarea>
                </div>
                
                <div class="mb-6 bg-yellow-50 p-4 rounded-lg">
                    <h3 class="font-semibold mb-2">审批信息</h3>
                    <p>您的直属上司：<span class="font-bold"><?php echo $manager['name']; ?></span></p>
                    <p class="text-sm text-gray-600 mt-1">提交后将等待上司审批，审批通过后才会扣除年假天数</p>
                </div>
                
                <div class="flex justify-center gap-4">
                    <button type="submit" 
                            class="bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-6 rounded transition duration-300">
                        <i class="fas fa-paper-plane mr-1"></i> 提交申请
                    </button>
                    <a href="index.php" 
                       class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-6 rounded transition duration-300">
                        <i class="fas fa-times mr-1"></i> 取消
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        
        // 调整请假天数
        document.getElementById('decreaseDays').addEventListener('click', function() {
            const daysInput = document.getElementById('days');
            let current = parseFloat(daysInput.value);
            if (current > 0.5) {
                daysInput.value = (current - 0.5).toFixed(1);
            }
        });
        
        document.getElementById('increaseDays').addEventListener('click', function() {
            const daysInput = document.getElementById('days');
            let current = parseFloat(daysInput.value);
            daysInput.value = (current + 0.5).toFixed(1);
        });
        
        // 计算请假天数
        function calculateDays() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            if (startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                
                if (start > end) {
                    return;
                }
                
                let totalDays = 0;
                const currentDate = new Date(start);
                
                while (currentDate <= end) {
                    const dayOfWeek = currentDate.getDay();
                    // 跳过周日(0)，周六(6)算0.5天，其他工作日算1天
                    if (dayOfWeek !== 0) {
                        totalDays += (dayOfWeek === 6) ? 0.5 : 1;
                    }
                    currentDate.setDate(currentDate.getDate() + 1);
                }
                
                document.getElementById('days').value = totalDays.toFixed(1);
            }
        }
        
        // 当日期改变时自动计算天数
        document.getElementById('start_date').addEventListener('change', calculateDays);
        document.getElementById('end_date').addEventListener('change', calculateDays);
        
        // 表单验证
        function validateForm() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const days = parseFloat(document.getElementById('days').value);
            
            // 检查日期是否超过未来1年
            const maxDate = new Date();
            maxDate.setFullYear(maxDate.getFullYear() + 1);
            const start = new Date(startDate);
            
            if (start > maxDate) {
                alert('请假日期不能超过未来1年');
                return false;
            }
            
            // 检查开始日期是否晚于结束日期
            if (new Date(startDate) > new Date(endDate)) {
                alert('开始日期不能晚于结束日期');
                return false;
            }
            
            // 检查天数是否有效
            if (days <= 0) {
                alert('请假天数必须大于0');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>
