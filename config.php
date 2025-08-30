<?php
// 数据存储路径 - 公司网络文件夹
define('DATA_PATH', '\\\\192.168.1.202\\home\\AL Record\\');

// 确保数据目录存在
if (!is_dir(DATA_PATH)) {
    mkdir(DATA_PATH, 0777, true);
}

// 数据文件路径
define('USERS_FILE', DATA_PATH . 'users.json');
define('LEAVES_FILE', DATA_PATH . 'leaves.json');
define('APPROVALS_FILE', DATA_PATH . 'approvals.json');

// 系统常量
define('MAX_FUTURE_DATE', '+1 year'); // 最大可申请未来假期
define('ADMIN_ROLE', 'admin');
define('EMPLOYEE_ROLE', 'employee');

// 初始化数据文件（如果不存在）
function initializeDataFiles() {
    // 初始化用户文件
    if (!file_exists(USERS_FILE)) {
        $admin = [
            'id' => 1,
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'name' => '系统管理员',
            'role' => ADMIN_ROLE,
            'annual_leave_days' => 0,
            'used_days' => 0,
            'remaining_days' => 0,
            'supervisor_id' => 0
        ];
        file_put_contents(USERS_FILE, json_encode([$admin]));
    }
    
    // 初始化请假记录文件
    if (!file_exists(LEAVES_FILE)) {
        file_put_contents(LEAVES_FILE, json_encode([]));
    }
    
    // 初始化审批记录文件
    if (!file_exists(APPROVALS_FILE)) {
        file_put_contents(APPROVALS_FILE, json_encode([]));
    }
}

// 调用初始化函数
initializeDataFiles();
?>
