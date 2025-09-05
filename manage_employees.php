<?php
session_start();
// 检查用户是否登录且为管理员
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

include 'db_connect.php';
$admin_id = $_SESSION['user_id'];
$name = $_SESSION['name'];

$message = '';
$message_type = '';

// 获取所有员工
$employees = [];
$managers = [];
$stmt = $conn->prepare("SELECT * FROM users ORDER BY role, name");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
    if ($row['role'] == 'manager' && $row['id'] != $admin_id) {
        $managers[] = $row;
    }
}
$stmt->close();

// 处理添加员工
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_employee'])) {
    $username = $_POST['username'];
    $password = $_POST['password']; // 实际应用中应使用password_hash()
    $name = $_POST['name'];
    $role = $_POST['role'];
    $department = $_POST['department'];
    $manager_id = $_POST['manager_id'] ?? null;
    $total_annual_leave = $_POST['total_annual_leave'];
    
    // 检查用户名是否已存在
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $message = "用户名已存在";
        $message_type = "error";
    } else {
        // 插入新员工
        $stmt = $conn->prepare("INSERT INTO users (username, password, name, role, department, manager_id, total_annual_leave, used_annual_leave) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->bind_param("sssssdd", $username, $password, $name, $role, $department, $manager_id, $total_annual_leave);
        
        if ($stmt->execute()) {
            $message = "员工添加成功";
            $message_type = "success";
            // 刷新页面以显示新员工
            header("Location: manage_employees.php?message=success&text=员工添加成功");
            exit();
        } else {
            $message = "添加失败: " . $conn->error;
            $message_type = "error";
        }
    }
    $stmt->close();
}

// 处理更新年假
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_leave'])) {
    $user_id = $_POST['user_id'];
    $total_annual_leave = $_POST['total_annual_leave'];
    
    $stmt = $conn->prepare("UPDATE users SET total_annual_leave = ? WHERE id = ?");
    $stmt->bind_param("di", $total_annual_leave, $user_id);
    
    if ($stmt->execute()) {
        $message = "年假设置更新成功";
        $message_type = "success";
    } else {
        $message = "更新失败: " . $conn->error;
        $message_type = "error";
    }
    $stmt->close();
}

// 处理修改密码
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_emp_password'])) {
    $user_id = $_POST['user_id'];
    $new_password = $_POST['new_password'];
    
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $new_password, $user_id);
    
    if ($stmt->execute()) {
        $message = "密码修改成功";
        $message_type = "success";
    } else {
        $message = "修改失败: " . $conn->error;
        $message_type = "error";
    }
    $stmt->close();
}

// 处理删除员工
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = $_GET['delete'];
    
    // 检查是否为管理员自身
    if ($user_id == $admin_id) {
        $message = "不能删除管理员自身";
        $message_type = "error";
    } else {
        // 删除员工的请假记录
        $stmt = $conn->prepare("DELETE FROM leave_records WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        
        // 删除员工
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $message = "员工删除成功";
            $message_type = "success";
            header("Location: manage_employees.php?message=success&text=员工删除成功");
            exit();
        } else {
            $message = "删除失败: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

$conn->close();

// 处理URL参数传递的消息
if (isset($_GET['message']) && isset($_GET['text'])) {
    $message = $_GET['text'];
    $message_type = $_GET['message'];
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员工 - 人力资源管理系统</title>
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
                        <span class="mr-2"><?php echo $name; ?> (Admin)</span>
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
        <div class="mb-6">
            <h2 class="text-2xl font-bold mb-2">员工管理</h2>
            <p class="text-gray-600">管理员可以添加、编辑和删除员工，设置年假天数和修改密码</p>
        </div>
        
        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded <?php echo $message_type == 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- 添加员工按钮 -->
        <div class="mb-6">
            <button id="addEmployeeBtn" 
                    class="bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded transition duration-300">
                <i class="fas fa-plus mr-1"></i> 添加新员工
            </button>
        </div>
        
        <!-- 添加员工表单（默认隐藏） -->
        <div id="addEmployeeForm" class="mb-8 bg-white rounded-lg shadow p-6 hidden">
            <h3 class="text-xl font-bold mb-4">添加新员工</h3>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" name="add_employee" value="1">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="username" class="block text-gray-700 mb-2">用户名 <span class="text-red-500">*</span></label>
                        <input type="text" id="username" name="username" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               required>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-gray-700 mb-2">初始密码 <span class="text-red-500">*</span></label>
                        <input type="password" id="password" name="password" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               required>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="name" class="block text-gray-700 mb-2">姓名 <span class="text-red-500">*</span></label>
                        <input type="text" id="name" name="name" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               required>
                    </div>
                    
                    <div>
                        <label for="role" class="block text-gray-700 mb-2">角色 <span class="text-red-500">*</span></label>
                        <select id="role" name="role" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required>
                            <option value="employee">员工</option>
                            <option value="manager">经理</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="department" class="block text-gray-700 mb-2">部门</label>
                        <input type="text" id="department" name="department" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label for="manager_id" class="block text-gray-700 mb-2">直属上司（可选）</label>
                        <select id="manager_id" name="manager_id" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">无</option>
                            <?php foreach ($managers as $manager): ?>
                                <option value="<?php echo $manager['id']; ?>"><?php echo $manager['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label for="total_annual_leave" class="block text-gray-700 mb-2">年假总天数 <span class="text-red-500">*</span></label>
                    <input type="number" id="total_annual_leave" name="total_annual_leave" min="0" step="0.5" value="15" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                </div>
                
                <div class="flex justify-end gap-4">
                    <button type="button" id="cancelAddBtn" 
                            class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded transition duration-300">
                        取消
                    </button>
                    <button type="submit" 
                            class="bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-6 rounded transition duration-300">
                        <i class="fas fa-save mr-1"></i> 保存员工
                    </button>
                </div>
            </form>
        </div>
        
        <!-- 员工列表 -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                用户名
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                姓名
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                角色
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                部门
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                直属上司
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                年假总天数
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                已使用天数
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                操作
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                    暂无员工记录
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($employees as $emp): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo $emp['username']; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo $emp['name']; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo $emp['role'] == 'admin' ? 'bg-purple-100 text-purple-800' : 
                                                      ($emp['role'] == 'manager' ? 'bg-blue-100 text-blue-800' : 
                                                      'bg-green-100 text-green-800'); ?>">
                                            <?php echo ucfirst($emp['role']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo $emp['department'] ?? '-'; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php 
                                            $manager_name = '-';
                                            foreach ($employees as $m) {
                                                if ($m['id'] == $emp['manager_id']) {
                                                    $manager_name = $m['name'];
                                                    break;
                                                }
                                            }
                                            echo $manager_name;
                                            ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <!-- 年假设置表单 -->
                                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="inline">
                                            <input type="hidden" name="update_leave" value="1">
                                            <input type="hidden" name="user_id" value="<?php echo $emp['id']; ?>">
                                            <input type="number" name="total_annual_leave" min="0" step="0.5" 
                                                   value="<?php echo $emp['total_annual_leave']; ?>" 
                                                   class="w-20 px-2 py-1 border border-gray-300 rounded-md text-center"
                                                   onchange="this.form.submit()">
                                        </form>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo $emp['used_annual_leave']; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <!-- 修改密码按钮 -->
                                        <button onclick="showChangePasswordForm(<?php echo $emp['id']; ?>, '<?php echo $emp['name']; ?>')"
                                                class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        
                                        <!-- 删除按钮 -->
                                        <?php if ($emp['id'] != $admin_id): ?>
                                            <button onclick="confirmDelete(<?php echo $emp['id']; ?>)"
                                                    class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-gray-300 cursor-not-allowed"><i class="fas fa-trash"></i></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- 修改密码模态框 -->
    <div id="changePasswordModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
            <h3 class="text-xl font-bold mb-4">修改密码 - <span id="modalEmployeeName"></span></h3>
            <form id="changePasswordForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" name="change_emp_password" value="1">
                <input type="hidden" id="modalUserId" name="user_id" value="">
                
                <div class="mb-4">
                    <label for="new_password" class="block text-gray-700 mb-2">新密码</label>
                    <input type="password" id="new_password" name="new_password" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                </div>
                
                <div class="flex justify-end gap-4 mt-6">
                    <button type="button" id="cancelPasswordBtn" 
                            class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded transition duration-300">
                        取消
                    </button>
                    <button type="submit" 
                            class="bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-6 rounded transition duration-300">
                        <i class="fas fa-save mr-1"></i> 保存密码
                    </button>
                </div>
            </form>
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
        
        // 添加员工表单显示/隐藏
        document.getElementById('addEmployeeBtn').addEventListener('click', function() {
            document.getElementById('addEmployeeForm').classList.remove('hidden');
        });
        
        document.getElementById('cancelAddBtn').addEventListener('click', function() {
            document.getElementById('addEmployeeForm').classList.add('hidden');
        });
        
        // 修改密码模态框
        function showChangePasswordForm(userId, userName) {
            document.getElementById('modalUserId').value = userId;
            document.getElementById('modalEmployeeName').textContent = userName;
            document.getElementById('changePasswordModal').classList.remove('hidden');
            document.getElementById('new_password').focus();
        }
        
        document.getElementById('cancelPasswordBtn').addEventListener('click', function() {
            document.getElementById('changePasswordModal').classList.add('hidden');
            document.getElementById('changePasswordForm').reset();
        });
        
        // 点击模态框外部关闭
        document.getElementById('changePasswordModal').addEventListener('click', function(event) {
            if (event.target === this) {
                this.classList.add('hidden');
                document.getElementById('changePasswordForm').reset();
            }
        });
        
        // 确认删除
        function confirmDelete(userId) {
            if (confirm('确定要删除这个员工吗？此操作不可恢复！')) {
                window.location.href = 'manage_employees.php?delete=' + userId;
            }
        }
    </script>
</body>
</html>
