<?php
session_start();
require_once 'functions.php';

// 检查登录状态和权限
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != EMPLOYEE_ROLE) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];
$userLeaves = getLeaves($currentUser['id']);

// 获取直属上司信息
$supervisor = getUserById($currentUser['supervisor_id']);

// 处理请假申请
if (isset($_POST['apply_leave'])) {
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $reason = trim($_POST['reason']);
    $isHalfDayStart = isset($_POST['is_half_day_start']);
    $isHalfDayEnd = isset($_POST['is_half_day_end']);
    
    // 验证日期
    if (new DateTime($startDate) > new DateTime($endDate)) {
        $error = '开始日期不能晚于结束日期';
    } elseif (!isValidLeaveDate($endDate)) {
        $error = '请假日期不能超过未来1年';
    } elseif (empty($reason)) {
        $error = '请填写请假原因';
    } else {
        // 计算请假天数
        $leaveDays = calculateLeaveDays($startDate, $endDate, $isHalfDayStart, $isHalfDayEnd);
        
        // 检查剩余年假是否足够
        if ($leaveDays > $currentUser['remaining_days']) {
            $error = '剩余年假不足，请检查申请的天数';
        } else {
            // 添加请假记录
            addLeave([
                'user_id' => $currentUser['id'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason' => $reason,
                'is_half_day_start' => $isHalfDayStart,
                'is_half_day_end' => $isHalfDayEnd,
                'supervisor_id' => $currentUser['supervisor_id']
            ]);
            
            header('Location: employee.php?success=请假申请已提交，等待批准');
            exit;
        }
    }
}

// 处理导出记录
if (isset($_GET['export'])) {
    exportToCSV($userLeaves, 'my_leave_records.csv');
}

// 获取日历数据
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$calendarData = getCalendarData($currentYear, $currentMonth, $currentUser['id']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>员工控制面板 - 年假记录系统</title>
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
        .leave-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .summary-card {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        .summary-card h3 {
            margin: 0 0 0.5rem 0;
            color: #5f6368;
            font-size: 1rem;
        }
        .summary-card .value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #1a73e8;
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
        input[type="checkbox"] {
            width: auto;
            margin-right: 0.5rem;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
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
        .supervisor-info {
            background-color: #e8f0fe;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>员工年假记录系统</h1>
        <div class="user-info">
            <span>员工: <?php echo $currentUser['name']; ?></span>
            <a href="change_password.php"><button class="logout-btn">修改密码</button></a>
            <a href="login.php?logout=1"><button class="logout-btn">退出登录</button></a>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="#leave-summary" class="tab-link active">年假概览</a></li>
                <li><a href="#apply-leave" class="tab-link">申请年假</a></li>
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
            
            <!-- 年假概览 -->
            <div id="leave-summary" class="tab-content active">
                <div class="section">
                    <h2>年假概览</h2>
                    
                    <?php if ($supervisor): ?>
                        <div class="supervisor-info">
                            <strong>直属上司:</strong> <?php echo $supervisor['name']; ?> (请假需其批准)
                        </div>
                    <?php endif; ?>
                    
                    <div class="leave-summary">
                        <div class="summary-card">
                            <h3>年假总天数</h3>
                            <div class="value"><?php echo $currentUser['annual_leave_days']; ?> 天</div>
                        </div>
                        <div class="summary-card">
                            <h3>已使用天数</h3>
                            <div class="value"><?php echo $currentUser['used_days']; ?> 天</div>
                        </div>
                        <div class="summary-card">
                            <h3>剩余天数</h3>
                            <div class="value"><?php echo $currentUser['remaining_days']; ?> 天</div>
                        </div>
                    </div>
                    
                    <p>注：星期六按半天工作日计算，支持申请半天年假。</p>
                </div>
            </div>
            
            <!-- 申请年假 -->
            <div id="apply-leave" class="tab-content">
                <div class="section">
                    <h2>申请年假</h2>
                    <form method="post">
                        <div class="form-group">
                            <label for="start_date">开始日期</label>
                            <input type="date" id="start_date" name="start_date" required>
                        </div>
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="is_half_day_start" name="is_half_day_start">
                                <label for="is_half_day_start" style="display: inline;">开始日期为半天假</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="end_date">结束日期</label>
                            <input type="date" id="end_date" name="end_date" required>
                        </div>
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="is_half_day_end" name="is_half_day_end">
                                <label for="is_half_day_end" style="display: inline;">结束日期为半天假</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="reason">请假原因</label>
                            <textarea id="reason" name="reason" rows="3" required></textarea>
                        </div>
                        <button type="submit" name="apply_leave" class="btn">提交申请</button>
                    </form>
                    <p style="margin-top: 1rem; color: #5f6368; font-size: 0.9rem;">
                        注：请假日期不能超过未来1年，可申请当前日期之前的假期。
                    </p>
                </div>
            </div>
            
            <!-- 请假记录 -->
            <div id="leave-records" class="tab-content">
                <div class="section">
                    <h2>我的请假记录</h2>
                    <div style="margin-bottom: 1rem; text-align: right;">
                        <a href="employee.php?export=1" class="btn">导出CSV</a>
                    </div>
                    <table class="table">
                        <tr>
                            <th>ID</th>
                            <th>开始日期</th>
                            <th>结束日期</th>
                            <th>天数</th>
                            <th>原因</th>
                            <th>状态</th>
                            <th>申请时间</th>
                        </tr>
                        <?php if (empty($userLeaves)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 1rem;">暂无请假记录</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($userLeaves as $leave): ?>
                                <tr>
                                    <td><?php echo $leave['id']; ?></td>
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
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            
            <!-- 日历视图 -->
            <div id="calendar-view" class="tab-content">
                <div class="section">
                    <h2>我的请假日历</h2>
                    <div class="calendar-controls">
                        <h3><?php echo $currentYear; ?>年 <?php echo $currentMonth; ?>月</h3>
                        <div>
                            <a href="employee.php?month=<?php echo ($currentMonth == 1) ? 12 : $currentMonth - 1; ?>&year=<?php echo ($currentMonth == 1) ? $currentYear - 1 : $currentYear; ?>&tab=calendar-view" class="btn btn-secondary">上月</a>
                            <a href="employee.php?month=<?php echo ($currentMonth == 12) ? 1 : $currentMonth + 1; ?>&year=<?php echo ($currentMonth == 12) ? $currentYear + 1 : $currentYear; ?>&tab=calendar-view" class="btn btn-secondary">下月</a>
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
                                echo ($statusClass == 'approved' ? '已批准' : '待批准') . $halfDayText;
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
        
        // 设置默认日期为今天
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            
            if (startDateInput && !startDateInput.value) {
                startDateInput.value = today;
            }
            
            if (endDateInput && !endDateInput.value) {
                endDateInput.value = today;
            }
        });
    </script>
</body>
</html>
