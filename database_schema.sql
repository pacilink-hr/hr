-- 创建用户表（管理员/员工/经理）
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL, -- 实际应用中存储哈希值
    role ENUM('admin', 'manager', 'employee') NOT NULL,
    name VARCHAR(100) NOT NULL,
    department VARCHAR(100),
    manager_id INT NULL, -- 直属上司ID（关联users表）
    total_annual_leave DECIMAL(5,1) DEFAULT 15, -- 总年假天数
    used_annual_leave DECIMAL(5,1) DEFAULT 0, -- 已用年假
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 创建请假记录表
CREATE TABLE leave_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days DECIMAL(5,1) NOT NULL, -- 请假天数
    reason TEXT NOT NULL,
    type ENUM('annual', 'sick', 'personal') NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approver_id INT NULL, -- 审批人ID（关联users表）
    apply_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approve_time TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 插入默认管理员账号
INSERT INTO users (username, password, role, name, total_annual_leave)
VALUES ('admin', 'admin123', 'admin', '系统管理员', 0);
