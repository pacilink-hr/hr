<?php
session_start();
require_once 'functions.php';

// 检查登录状态和权限
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != ADMIN_ROLE) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];
$users = getUsers();
$leaves = getLeaves();

// 处理添加员工
if (isset($_POST['add_employee'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $name = trim($_POST['name']);
    $annualLeave = (float)$_POST['annual_leave'];
    $supervisorId = (int)$_POST['supervisor_id'];
    
    if (!empty($username) && !empty($password) && !empty($name) && $annualLeave > 0) {
        // 检查用户名是否已存在
        $existingUser = getUserByUsername($username);
        if (!$existingUser) {
            addUser([
                'username' => $username,
                'password' => $password,
                'name' => $name,
                'role' => EMPLOYEE_ROLE,
                'annual_leave_days' => $annualLeave,
                'supervisor_id' => $supervisorId
            ]);
            header('Location: admin.php?success=员工添加成功');
            exit;
        } else {
            $error = '用户名已存在';
        }
    } else {
        $error = '请填写所有必填字段，且年假天数必须大于0';
    }
}

// 处理更新员工年假
if (isset($_POST['update_leave'])) {
    $userId = (int)$_POST['user_id'];
    $newDays = (float)$_POST['new_annual_leave'];
    
    if ($newDays >= 0) {
        updateUser($userId, ['annual_leave_days' => $newDays]);
        header('Location: admin.php?success=年假天数已更新');
        exit;
    } else {
        $error = '年假天数不能为负数';
    }
}

// 处理修改员工密码
if (isset($_POST['change_employee_password'])) {
    $userId = (int)$_POST['user_id'];
    $newPassword = trim($_POST['new_password']);
    
    if (!empty($newPassword)) {
        updateUser($userId, ['password' => $newPassword]);
        header('Location: admin.php?success=密码已更新');
        exit;
    } else {
        $error = '新密码不能为空';
    }
}

// 处理导出记录
if (isset($_GET['export'])) {
    exportToCSV($leaves);
}

// 处理审批假单
if (isset($_POST['approve_leave']) || isset($_POST['reject_leave'])) {
    $leaveId = (int)$_POST['leave_id'];
    $status = isset($_POST['approve_leave']) ? 'approved' : 'rejected';
    $comment = trim($_POST['comment'] ?? '');
    
    updateApproval($leaveId, $status, $comment);
    header('Location: admin.php?success=假单已' . ($status == 'approved' ? '批准' : '拒绝'));
    exit;
}

// 获取日历数据
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$calendarData = getCalendarData($currentYear, $currentMonth);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员控制面板 - 员工年假记录系统</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f2f5;
        }
        .header {
            background-color: #1a73e8;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            margin: 0;
            font-size: 1.5rem;
        }
        .user-info {
            display: flex;
            align-items: center;
        }
        .user-info span {
            margin-right: 1rem;
        }
        .logout-btn {
            background-color: #ffffff;
            color: #1a73e8;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }
        .container {
            display: flex;
            min-height: calc(100vh - 64px);
        }
        .sidebar {
            width: 220px;
            background-color: #ffffff;
            border-right: 1px solid #dadce0;
            padding: 1.5rem 0;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu li {
            margin-bottom: 0.25rem;
        }
        .sidebar-menu a {
            display: block;
            padding: 0.75rem 1.5rem;
            color: #5f6368;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: #f1f3f4;
            color: #1a73e8;
        }
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }
        .section {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .section h2 {
            margin-top: 0;
            color: #202124;
            border-bottom: 1px solid #dadce0;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
        .success {
            color: #137333;
            background-color: #e6f4ea;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .error {
            color: #d93025;
            background-color: #fce8e6;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1rem;
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
        input, select, textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #dadce0;
            border-radius: 4px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #1a73e8;
            box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.2);
        }
        .btn {
            background-color: #1a73e8;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn:hover {
            background-color: #1765cc;
        }
        .btn-secondary {
            background-color: #f1f3f4;
            color: #202124;
        }
        .btn-secondary:hover {
            background-color: #e8eaed;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }
        .table th, .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #dadce0;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: 500;
            color: #202124;
        }
        .table tr:hover {
            background-color: #f8f9fa;
        }
        .status-pending {
            color: #f59f00;
            background-color: #fff8e6;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .status-approved {
            color: #137333;
            background-color: #e6f4ea;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .status-rejected {
            color: #d93025;
            background-color: #fce8e6;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        .action-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .tabs {
            display: flex;
            border-bottom: 1px solid #dadce0;
            margin-bottom: 1rem;
        }
        .tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            color: #5f6368;
        }
        .tab.active {
            border-bottom-color: #1a73e8;
            color: #1a73e8;
            font-weight: 500;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
        }
        .calendar-header {
            font-weight: bold;
            text-align: center;
            padding: 0.5rem;
            background-color: #f1f3f4;
        }
        .calendar-day {
            min-height: 100px;
            border: 1px solid #dadce0;
            padding: 0.5rem;
            position: relative;
        }
        .calendar-day-number {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .calendar-day.weekend {
            background-color: #f8f9fa;
        }
        .calendar-event {
            font-size: 0.75rem;
            margin-bottom: 0.25rem;
            padding: 0.25rem;
            border-radius: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .calendar-event.approved {
            background-color: #e6f4ea;
            color: #137333;
        }
        .calendar-event.pending {
            background-color: #fff8e6;
            color: #f59f00;
        }
        .calendar-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>员工年假记录系统</h1>
        <div class="user-info">
            <span>管理员: <?php echo $currentUser['name']; ?></span>
            <a href="change_password.php"><button class="logout-btn">修改密码</button></a>
            <a href="login.php?logout=1"><button class="logout-btn">退出登录</button></a>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="#employee-management" class="tab-link active">员工管理</a></li>
                <li><a href="#leave-records" class="tab-link">请假记录</a></li>
                <li><a href="#calendar-view" class="tab-link">日历视图</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <?php if (isset($_GET['success'])): ?>
                <div class="success"><?php echo $_GET['success']; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- 员工管理 -->
            <div id="employee-management" class="tab-content active">
                <div class="section">
                    <h2>添加新员工</h2>
                    <form method="post">
                        <div class="form-group">
                            <label for="username">用户名</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="password">初始密码</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="name">员工姓名</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="annual_leave">年假天数</label>
                            <input type="number" id="annual_leave" name="annual_leave" min="0" step="0.5" required>
                        </div>
                        <div class="form-group">
                            <label for="supervisor_id">直属上司</label>
                            <select id="supervisor_id" name="supervisor_id">
                                <option value="0">无</option>
                                <?php foreach ($users as $user): ?>
                                    <?php if ($user['role'] == ADMIN_ROLE || $user['role'] == EMPLOYEE_ROLE): ?>
                                        <option value="<?php echo $user['id']; ?>"><?php echo $user['name']; ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="add_employee" class="btn">添加员工</button>
                    </form>
                </div>
                
                <div class="section">
                    <h2>员工列表</h2>
                    <table class="table">
                        <tr>
                            <th>ID</th>
                            <th>用户名</th>
                            <th>姓名</th>
                            <th>年假总天数</th>
                            <th>已使用天数</th>
                            <th>剩余天数</th>
                            <th>直属上司</th>
                            <th>操作</th>
                        </tr>
                        <?php foreach ($users as $user): ?>
                            <?php if ($user['role'] == EMPLOYEE_ROLE): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo $user['username']; ?></td>
                                    <td><?php echo $user['name']; ?></td>
                                    <td>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="number" name="new_annual_leave" value="<?php echo $user['annual_leave_days']; ?>" min="0" step="0.5" style="width: 80px;">
                                            <button type="submit" name="update_leave" class="btn action-btn">更新</button>
                                        </form>
                                    </td>
                                    <td><?php echo $user['used_days']; ?></td>
                                    <td><?php echo $user['remaining_days']; ?></td>
                                    <td>
                                        <?php 
                                        $supervisor = getUserById($user['supervisor_id']);
                                        echo $supervisor ? $supervisor['name'] : '无';
                                        ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-secondary action-btn" onclick="showChangePasswordForm(<?php echo $user['id']; ?>, '<?php echo $user['name']; ?>')">修改密码</button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            
            <!-- 请假记录 -->
            <div id="leave-records" class="tab-content">
                <div class="section">
                    <h2>所有请假记录</h2>
                    <div style="margin-bottom: 1rem; text-align: right;">
                        <a href="admin.php?export=1" class="btn">导出CSV</a>
                    </div>
                    <table class="table">
                        <tr>
                            <th>ID</th>
                            <th>员工</th>
                            <th>开始日期</th>
                            <th>结束日期</th>
                            <th>天数</th>
                            <th>原因</th>
                            <th>状态</th>
                            <th>申请时间</th>
                            <th>操作</th>
                        </tr>
                        <?php foreach ($leaves as $leave): ?>
                            <?php $user = getUserById($leave['user_id']); ?>
                            <tr>
                                <td><?php echo $leave['id']; ?></td>
                                <td><?php echo $user['name']; ?></td>
                                <td><?php echo $leave['start_date']; ?><?php echo isset($leave['is_half_day_start']) && $leave['is_half_day_start'] ? ' (半天)' : ''; ?></td>
                                <td><?php echo $leave['end_date']; ?><?php echo isset($leave['is_half_day_end']) && $leave['is_half_day_end'] ? ' (半天)' : ''; ?></td>
                                <td><?php echo $leave['days']; ?></td>
                                <td><?php echo $leave['reason']; ?></td>
                                <td>
                                    <span class="status-<?php echo $leave['status']; ?>">
                                        <?php 
                                        switch ($leave['status']) {
                                            case 'pending': echo '待批准'; break;
                                            case 'approved': echo '已批准'; break;
                                            case 'rejected': echo '已拒绝'; break;
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo $leave['created_at']; ?></td>
                                <td>
                                    <?php if ($leave['status'] == 'pending'): ?>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                            <button type="submit" name="approve_leave" class="btn action-btn">批准</button>
                                        </form>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                            <input type="text" name="comment" placeholder="拒绝原因" style="width: 100px; display: inline;">
                                            <button type="submit" name="reject_leave" class="btn btn-secondary action-btn">拒绝</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            
            <!-- 日历视图 -->
            <div id="calendar-view" class="tab-content">
                <div class="section">
                    <h2>请假日历</h2>
                    <div class="calendar-controls">
                        <h3><?php echo $currentYear; ?>年 <?php echo $currentMonth; ?>月</h3>
                        <div>
                            <a href="admin.php?month=<?php echo ($currentMonth == 1) ? 12 : $currentMonth - 1; ?>&year=<?php echo ($currentMonth == 1) ? $currentYear - 1 : $currentYear; ?>&tab=calendar-view" class="btn btn-secondary">上月</a>
                            <a href="admin.php?month=<?php echo ($currentMonth == 12) ? 1 : $currentMonth + 1; ?>&year=<?php echo ($currentMonth == 12) ? $currentYear + 1 : $currentYear; ?>&tab=calendar-view" class="btn btn-secondary">下月</a>
                        </div>
                    </div>
                    <div class="calendar">
                        <div class="calendar-header">周一</div>
                        <div class="calendar-header">周二</div>
                        <div class="calendar-header">周三</div>
                        <div class="calendar-header">周四</div>
                        <div class="calendar-header">周五</div>
                        <div class="calendar-header">周六</div>
                        <div class="calendar-header">周日</div>
                        
                        <?php 
                        $firstDayOfMonth = new DateTime("$currentYear-$currentMonth-01");
                        $firstDayWeekday = (int)$firstDayOfMonth->format('N'); // 1=周一, 7=周日
                        
                        // 添加上月的占位天数
                        for ($i = 1; $i < $firstDayWeekday; $i++) {
                            echo '<div class="calendar-day"></div>';
                        }
                        
                        // 添加当月的天数
                        foreach ($calendarData as $day) {
                            $isWeekend = $day['weekday'] == 6 || $day['weekday'] == 7;
                            echo '<div class="calendar-day ' . ($isWeekend ? 'weekend' : '') . '">';
                            echo '<div class="calendar-day-number">' . $day['day'] . '</div>';
                            
                            foreach ($day['leaves'] as $leave) {
                                $statusClass = $leave['status'] == 'approved' ? 'approved' : 'pending';
                                $halfDayText = $leave['is_half_day'] ? ' (半天)' : '';
                                echo '<div class="calendar-event ' . $statusClass . '">';
                                echo $leave['user'] . $halfDayText;
                                echo '</div>';
                            }
                            
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 修改员工密码弹窗 -->
    <div id="changePasswordModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background-color: white; padding: 2rem; border-radius: 8px; width: 350px;">
            <h3>修改 <span id="modalEmployeeName"></span> 的密码</h3>
            <form id="changePasswordForm" method="post">
                <input type="hidden" id="modalUserId" name="user_id">
                <div class="form-group">
                    <label for="new_password">新密码</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <button type="submit" name="change_employee_password" class="btn">确认修改</button>
                    <button type="button" class="btn btn-secondary" onclick="hideChangePasswordForm()">取消</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // 标签切换功能
        document.querySelectorAll('.tab-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // 移除所有活跃状态
                document.querySelectorAll('.tab-link').forEach(l => l.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // 添加当前活跃状态
                this.classList.add('active');
                const targetId = this.getAttribute('href').substring(1);
                document.getElementById(targetId).classList.add('active');
            });
        });
        
        // 显示修改密码表单
        function showChangePasswordForm(userId, userName) {
            document.getElementById('modalUserId').value = userId;
            document.getElementById('modalEmployeeName').textContent = userName;
            document.getElementById('changePasswordModal').style.display = 'flex';
        }
        
        // 隐藏修改密码表单
        function hideChangePasswordForm() {
            document.getElementById('changePasswordModal').style.display = 'none';
        }
        
        // 点击模态框外部关闭
        document.getElementById('changePasswordModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideChangePasswordForm();
            }
        });
        
        // 根据URL参数激活对应的标签
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab');
        if (activeTab) {
            document.querySelectorAll('.tab-link').forEach(l => l.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            const tabLink = document.querySelector(`.tab-link[href="#${activeTab}"]`);
            if (tabLink) {
                tabLink.classList.add('active');
                document.getElementById(activeTab).classList.add('active');
            }
        }
    </script>
</body>
</html>
