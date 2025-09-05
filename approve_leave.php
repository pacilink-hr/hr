<?php
session_start();
// 检查用户是否登录且为经理或管理员
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'manager' && $_SESSION['role'] != 'admin')) {
    http_response_code(403);
    echo "无权限执行此操作";
    exit();
}

include 'db_connect.php';
$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['leave_id']) && isset($_POST['status'])) {
    $leave_id = $_POST['leave_id'];
    $status = $_POST['status'];
    
    // 验证状态是否有效
    if ($status != 'approved' && $status != 'rejected') {
        http_response_code(400);
        echo "无效的状态";
        exit();
    }
    
    // 检查请假记录是否存在且属于该经理的下属
    if ($_SESSION['role'] == 'manager') {
        $stmt = $conn->prepare("SELECT lr.id, lr.user_id, lr.days FROM leave_records lr
                              JOIN users u ON lr.user_id = u.id
                              WHERE lr.id = ? AND u.manager_id = ? AND lr.status = 'pending'");
        $stmt->bind_param("ii", $leave_id, $user_id);
    } else {
        // 管理员可以审批所有
        $stmt = $conn->prepare("SELECT id, user_id, days FROM leave_records 
                              WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $leave_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows != 1) {
        http_response_code(404);
        echo "请假记录不存在或已处理";
        $stmt->close();
        $conn->close();
        exit();
    }
    
    $leave_record = $result->fetch_assoc();
    $stmt->close();
    
    // 更新请假记录状态
    $stmt = $conn->prepare("UPDATE leave_records SET status = ?, approver_id = ?, approve_time = NOW() 
                          WHERE id = ?");
    $stmt->bind_param("sii", $status, $user_id, $leave_id);
    
    if ($stmt->execute()) {
        // 如果批准，更新员工已使用年假天数
        if ($status == 'approved') {
            $stmt = $conn->prepare("UPDATE users SET used_annual_leave = used_annual_leave + ? 
                                  WHERE id = ?");
            $stmt->bind_param("di", $leave_record['days'], $leave_record['user_id']);
            $stmt->execute();
            $stmt->close();
        }
        
        echo "操作成功";
    } else {
        http_response_code(500);
        echo "操作失败: " . $conn->error;
    }
    
    $stmt->close();
} else {
    http_response_code(400);
    echo "无效的请求";
}

$conn->close();
?>
