<?php
session_start();
// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db_connect.php';
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$name = $_SESSION['name'];

// 获取用户信息
$user_info = [];
$stmt = $conn->prepare("SELECT total_annual_leave, used_annual_leave FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 1) {
    $user_info = $result->fetch_assoc();
}
$stmt->close();

// 根据角色获取不同数据
$leave_records = [];
if ($role == 'employee') {
    // 员工：获取自己的请假记录
    $stmt = $conn->prepare("SELECT * FROM leave_records WHERE user_id = ? ORDER BY start_date DESC");
    $stmt->bind_param("i", $user_id);
} elseif ($role == 'manager') {
    // 经理：获取下属的请假记录
    $stmt = $conn->prepare("SELECT lr.*, u.name as employee_name FROM leave_records lr 
                          JOIN users u ON lr.user_id = u.id 
                          WHERE u.manager_id = ? ORDER BY lr.start_date DESC");
    $stmt->bind_param("i", $user_id);
} else {
    // 管理员：获取所有请假记录
    $stmt = $conn->prepare("SELECT lr.*, u.name as employee_name FROM leave_records lr 
                          JOIN users u ON lr.user_id = u.id 
                          ORDER BY lr.start_date DESC");
}
$stmt->execute();
$leave_result = $stmt->get_result();
while ($row = $leave_result->fetch_assoc()) {
    $leave_records[] = $row;
}
$stmt->close();

// 获取员工列表（管理员和经理需要）
$employees = [];
if ($role == 'admin' || $role == 'manager') {
    if ($role == 'admin') {
        // 管理员：所有员工
        $sql = "SELECT * FROM users WHERE role = 'employee' OR role = 'manager' ORDER BY name";
    } else {
        // 经理：自己的下属
        $sql = "SELECT * FROM users WHERE manager_id = ? ORDER BY name";
    }
    
    $stmt = $conn->prepare($sql);
    if ($role == 'manager') {
        $stmt->bind_param("i", $user_id);
    }
    $stmt->execute();
    $emp_result = $stmt->get_result();
    while ($row = $emp_result->fetch_assoc()) {
        $employees[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>人力资源管理系统 - 主页</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css">
    <style>
        .calendar-event-pending { background-color: #ffc107; border-color: #ffc107; }
        .calendar-event-approved { background-color: #28a745; border-color: #28a745; }
        .calendar-event-rejected { background-color: #dc3545; border-color: #dc3545; }
        .half-day { position: relative; }
        .half-day::after { content: "½"; position: absolute; right: 5px; top: -5px; }
    </style>
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
                        <span class="mr-2"><?php echo $name; ?> (<?php echo ucfirst($role); ?>)</span>
                        <i class="fas fa-user-circle text-xl"></i>
                    </button>
                    
                    <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10">
                        <a href="change_password.php" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">
                            <i class="fas fa-key mr-2"></i>修改密码
                        </a>
                        <?php if ($role == 'admin'): ?>
                            <a href="manage_employees.php" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">
                                <i class="fas fa-user-cog mr-2"></i>管理员工
                            </a>
                        <?php endif; ?>
                        <a href="logout.php" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">
                            <i class="fas fa-sign-out-alt mr-2"></i>退出登录
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-6">
        <!-- 角色特定信息 -->
        <div class="mb-6 bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">欢迎回来，<?php echo $name; ?>！</h2>
            
            <?php if ($role == 'employee'): ?>
                <div class="flex flex-col md:flex-row gap-6">
                    <div class="flex-1 bg-blue-50 p-4 rounded-lg">
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
                            <span class="font-bold text-green-600">
                                <?php echo $user_info['total_annual_leave'] - $user_info['used_annual_leave']; ?> 天
                            </span>
                        </div>
                    </div>
                    
                    <div class="flex-1 bg-green-50 p-4 rounded-lg">
                        <h3 class="font-semibold mb-2">快速操作</h3>
                        <a href="leave_application.php" class="inline-block bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded transition duration-300">
                            <i class="fas fa-calendar-plus mr-1"></i> 申请请假
                        </a>
                    </div>
                </div>
            <?php elseif ($role == 'manager'): ?>
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <h3 class="font-semibold mb-2">待处理审批</h3>
                    <?php 
                    $pending_count = 0;
                    foreach ($leave_records as $record) {
                        if ($record['status'] == 'pending') $pending_count++;
                    }
                    ?>
                    <p>您有 <span class="font-bold text-red-600"><?php echo $pending_count; ?></span> 个待处理的请假申请</p>
                </div>
            <?php else: ?>
                <div class="bg-purple-50 p-4 rounded-lg">
                    <h3 class="font-semibold mb-2">系统状态</h3>
                    <p>当前系统共有 <span class="font-bold"><?php echo count($employees); ?></span> 名员工</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- 标签页导航 -->
        <div class="mb-6 border-b border-gray-200">
            <ul class="flex flex-wrap -mb-px" id="tabs" role="tablist">
                <li class="mr-2" role="presentation">
                    <button class="inline-block py-4 px-5 text-blue-600 border-b-2 border-blue-500 rounded-t-lg" 
                            id="list-tab" data-tabs-target="#list" type="button" role="tab" aria-selected="true">
                        请假记录列表
                    </button>
                </li>
                <li class="mr-2" role="presentation">
                    <button class="inline-block py-4 px-5 text-gray-500 hover:text-gray-600 border-b-2 border-transparent hover:border-gray-300 rounded-t-lg" 
                            id="calendar-tab" data-tabs-target="#calendar" type="button" role="tab" aria-selected="false">
                        日历视图
                    </button>
                </li>
                <li role="presentation">
                    <button class="inline-block py-4 px-5 text-gray-500 hover:text-gray-600 border-b-2 border-transparent hover:border-gray-300 rounded-t-lg" 
                            id="export-tab" data-tabs-target="#export" type="button" role="tab" aria-selected="false">
                        导出记录
                    </button>
                </li>
            </ul>
        </div>
        
        <!-- 标签页内容 -->
        <div id="tabsContent">
            <!-- 请假记录列表 -->
            <div class="block p-6 bg-white rounded-lg shadow" id="list" role="tabpanel">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <?php if ($role != 'employee'): ?>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        员工姓名
                                    </th>
                                <?php endif; ?>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    开始日期
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    结束日期
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    天数
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    原因
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    状态
                                </th>
                                <?php if ($role == 'manager' || $role == 'admin'): ?>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        操作
                                    </th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($leave_records)): ?>
                                <tr>
                                    <td colspan="<?php echo ($role == 'employee') ? 5 : ($role == 'manager' ? 7 : 7); ?>" class="px-6 py-4 text-center text-gray-500">
                                        暂无请假记录
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($leave_records as $record): ?>
                                    <tr>
                                        <?php if ($role != 'employee'): ?>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo $record['employee_name']; ?></div>
                                            </td>
                                        <?php endif; ?>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo $record['start_date']; ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo $record['end_date']; ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap <?php echo $record['days'] % 1 == 0 ? '' : 'half-day'; ?>">
                                            <div class="text-sm text-gray-900"><?php echo $record['days']; ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900"><?php echo $record['reason']; ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php 
                                            $status_class = '';
                                            $status_text = '';
                                            switch ($record['status']) {
                                                case 'pending':
                                                    $status_class = 'bg-yellow-100 text-yellow-800';
                                                    $status_text = '待审批';
                                                    break;
                                                case 'approved':
                                                    $status_class = 'bg-green-100 text-green-800';
                                                    $status_text = '已批准';
                                                    break;
                                                case 'rejected':
                                                    $status_class = 'bg-red-100 text-red-800';
                                                    $status_text = '已拒绝';
                                                    break;
                                            }
                                            ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <?php if ($role == 'manager' || $role == 'admin'): ?>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <?php if ($record['status'] == 'pending'): ?>
                                                    <button onclick="approveLeave(<?php echo $record['id']; ?>)" 
                                                            class="text-green-600 hover:text-green-900 mr-3">
                                                        批准
                                                    </button>
                                                    <button onclick="rejectLeave(<?php echo $record['id']; ?>)" 
                                                            class="text-red-600 hover:text-red-900">
                                                        拒绝
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-gray-500">已处理</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- 日历视图 -->
            <div class="hidden p-6 bg-white rounded-lg shadow" id="calendar" role="tabpanel">
                <div id="leaveCalendar" class="mb-4"></div>
                <div class="flex flex-wrap gap-3 mt-4">
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-yellow-400 rounded-full mr-2"></span>
                        <span class="text-sm">待审批</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-green-400 rounded-full mr-2"></span>
                        <span class="text-sm">已批准</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-red-400 rounded-full mr-2"></span>
                        <span class="text-sm">已拒绝</span>
                    </div>
                </div>
            </div>
            
            <!-- 导出记录 -->
            <div class="hidden p-6 bg-white rounded-lg shadow" id="export" role="tabpanel">
                <h3 class="text-lg font-semibold mb-4">导出请假记录</h3>
                
                <form id="exportForm" method="post" action="export_records.php">
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">选择日期范围</label>
                        <div class="flex gap-4">
                            <div class="flex-1">
                                <label for="exportStartDate" class="block text-sm text-gray-500 mb-1">开始日期</label>
                                <input type="date" id="exportStartDate" name="start_date" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                            </div>
                            <div class="flex-1">
                                <label for="exportEndDate" class="block text-sm text-gray-500 mb-1">结束日期</label>
                                <input type="date" id="exportEndDate" name="end_date" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($role != 'employee'): ?>
                        <div class="mb-4">
                            <label for="exportEmployee" class="block text-gray-700 mb-2">选择员工（可选）</label>
                            <select id="exportEmployee" name="employee_id" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="">所有员工</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>"><?php echo $emp['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">选择导出格式</label>
                        <div class="flex gap-4">
                            <label class="inline-flex items-center">
                                <input type="radio" name="format" value="excel" checked 
                                       class="form-radio text-blue-500">
                                <span class="ml-2">Excel (.xlsx)</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="format" value="pdf" 
                                       class="form-radio text-blue-500">
                                <span class="ml-2">PDF (.pdf)</span>
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" 
                            class="bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded transition duration-300">
                        <i class="fas fa-download mr-1"></i> 导出记录
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>
    <script src="app.js"></script>
    <script>
        // 初始化标签页
        document.querySelectorAll('#tabs button').forEach(button => {
            button.addEventListener('click', () => {
                // 移除所有活跃状态
                document.querySelectorAll('#tabs button').forEach(btn => {
                    btn.classList.remove('text-blue-600', 'border-blue-500');
                    btn.classList.add('text-gray-500', 'border-transparent', 'hover:border-gray-300');
                    btn.setAttribute('aria-selected', 'false');
                });
                
                // 隐藏所有内容
                document.querySelectorAll('#tabsContent > div').forEach(content => {
                    content.classList.add('hidden');
                });
                
                // 设置当前标签为活跃
                button.classList.remove('text-gray-500', 'border-transparent', 'hover:border-gray-300');
                button.classList.add('text-blue-600', 'border-blue-500');
                button.setAttribute('aria-selected', 'true');
                
                // 显示当前内容
                const target = button.getAttribute('data-tabs-target');
                document.querySelector(target).classList.remove('hidden');
                
                // 如果是日历标签，初始化日历
                if (target === '#calendar') {
                    initCalendar(<?php echo json_encode($leave_records); ?>, '<?php echo $role; ?>');
                }
            });
        });
        
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
        
        // 审批请假
        function approveLeave(leaveId) {
            if (confirm('确定要批准这个请假申请吗？')) {
                handleApproval(leaveId, 'approved');
            }
        }
        
        // 拒绝请假
        function rejectLeave(leaveId) {
            if (confirm('确定要拒绝这个请假申请吗？')) {
                handleApproval(leaveId, 'rejected');
            }
        }
        
        // 处理审批
        function handleApproval(leaveId, status) {
            $.ajax({
                url: 'approve_leave.php',
                type: 'POST',
                data: {
                    leave_id: leaveId,
                    status: status
                },
                success: function(response) {
                    alert('操作成功！');
                    window.location.reload();
                },
                error: function(xhr, status, error) {
                    alert('操作失败: ' + error);
                }
            });
        }
        
        // 初始化日历
        function initCalendar(records, role) {
            $('#leaveCalendar').fullCalendar({
                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'month,agendaWeek,agendaDay'
                },
                editable: false,
                eventLimit: true,
                events: records.map(record => {
                    let className = '';
                    switch (record.status) {
                        case 'pending':
                            className = 'calendar-event-pending';
                            break;
                        case 'approved':
                            className = 'calendar-event-approved';
                            break;
                        case 'rejected':
                            className = 'calendar-event-rejected';
                            break;
                    }
                    
                    let title = role === 'employee' ? 
                        `${record.days}天 - ${record.reason}` : 
                        `${record.employee_name}: ${record.days}天 - ${record.reason}`;
                    
                    return {
                        title: title,
                        start: record.start_date,
                        end: moment(record.end_date).add(1, 'days').format('YYYY-MM-DD'),
                        className: className,
                        description: `状态: ${record.status === 'pending' ? '待审批' : 
                                  record.status === 'approved' ? '已批准' : '已拒绝'}`
                    };
                }),
                eventRender: function(event, element) {
                    element.attr('title', event.description);
                }
            });
        }
    </script>
</body>
</html>
