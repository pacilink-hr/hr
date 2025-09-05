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

// 验证请求参数
if (!isset($_POST['start_date']) || !isset($_POST['end_date']) || !isset($_POST['format'])) {
    die("无效的请求参数");
}

$start_date = $_POST['start_date'];
$end_date = $_POST['end_date'];
$format = $_POST['format'];
$employee_id = isset($_POST['employee_id']) && $_POST['employee_id'] ? $_POST['employee_id'] : null;

// 根据角色构建查询条件
if ($role == 'employee') {
    // 员工只能导出自己的记录
    $params = [$user_id, $start_date, $end_date];
    $sql = "SELECT lr.*, u.name FROM leave_records lr
           JOIN users u ON lr.user_id = u.id
           WHERE lr.user_id = ? AND lr.start_date BETWEEN ? AND ?
           ORDER BY lr.start_date DESC";
} elseif ($role == 'manager') {
    // 经理可以导出下属的记录
    if ($employee_id) {
        // 检查是否为自己的下属
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND manager_id = ?");
        $check_stmt->bind_param("ii", $employee_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows != 1) {
            die("无权访问该员工的记录");
        }
        $check_stmt->close();
        
        $params = [$employee_id, $start_date, $end_date];
        $sql = "SELECT lr.*, u.name FROM leave_records lr
               JOIN users u ON lr.user_id = u.id
               WHERE lr.user_id = ? AND lr.start_date BETWEEN ? AND ?
               ORDER BY lr.start_date DESC";
    } else {
        $params = [$user_id, $start_date, $end_date];
        $sql = "SELECT lr.*, u.name FROM leave_records lr
               JOIN users u ON lr.user_id = u.id
               WHERE u.manager_id = ? AND lr.start_date BETWEEN ? AND ?
               ORDER BY lr.start_date DESC";
    }
} else {
    // 管理员可以导出所有记录
    if ($employee_id) {
        $params = [$employee_id, $start_date, $end_date];
        $sql = "SELECT lr.*, u.name FROM leave_records lr
               JOIN users u ON lr.user_id = u.id
               WHERE lr.user_id = ? AND lr.start_date BETWEEN ? AND ?
               ORDER BY lr.start_date DESC";
    } else {
        $params = [$start_date, $end_date];
        $sql = "SELECT lr.*, u.name FROM leave_records lr
               JOIN users u ON lr.user_id = u.id
               WHERE lr.start_date BETWEEN ? AND ?
               ORDER BY lr.start_date DESC";
    }
}

// 执行查询
$stmt = $conn->prepare($sql);
if (count($params) == 2) {
    $stmt->bind_param("ss", $params[0], $params[1]);
} elseif (count($params) == 3) {
    $stmt->bind_param("iss", $params[0], $params[1], $params[2]);
}
$stmt->execute();
$result = $stmt->get_result();

$records = [];
while ($row = $result->fetch_assoc()) {
    // 转换状态为中文
    switch ($row['status']) {
        case 'pending':
            $row['status_text'] = '待审批';
            break;
        case 'approved':
            $row['status_text'] = '已批准';
            break;
        case 'rejected':
            $row['status_text'] = '已拒绝';
            break;
    }
    $records[] = $row;
}

$stmt->close();
$conn->close();

// 导出为Excel
if ($format == 'excel') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=请假记录_" . date('Ymd') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // Excel内容
    echo "员工姓名\t开始日期\t结束日期\t请假天数\t请假原因\t状态\t申请时间\t审批时间\n";
    foreach ($records as $record) {
        echo "{$record['name']}\t{$record['start_date']}\t{$record['end_date']}\t{$record['days']}\t{$record['reason']}\t{$record['status_text']}\t{$record['apply_time']}\t{$record['approve_time'] ?? ''}\n";
    }
}
// 导出为PDF
elseif ($format == 'pdf') {
    // 这里使用简单的文本PDF格式，实际应用中应使用TCPDF或FPDF库
    header("Content-Type: application/pdf");
    header("Content-Disposition: attachment; filename=请假记录_" . date('Ymd') . ".pdf");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // PDF文件头
    echo "%PDF-1.0\n";
    echo "1 0 obj\n<< /Type /Catalog /Outlines 2 0 R /Pages 3 0 R >>\nendobj\n";
    echo "2 0 obj\n<< /Type /Outlines /Count 0 >>\nendobj\n";
    echo "3 0 obj\n<< /Type /Pages /Kids [4 0 R] /Count 1 >>\nendobj\n";
    echo "4 0 obj\n<< /Type /Page /Parent 3 0 R /MediaBox [0 0 612 792] /Contents 5 0 R /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n";
    echo "6 0 obj\n<< /Type /Font /Subtype /Type1 /Name /F1 /BaseFont /Helvetica /Encoding /MacRomanEncoding >>\nendobj\n";
    
    // 内容流
    $content = "BT /F1 12 Tf 50 700 Td (请假记录 - 日期范围: $start_date 至 $end_date) Tj ET\n";
    $y = 670;
    
    $content .= "BT /F1 10 Tf 50 $y Td (员工姓名) Tj 150 $y Td (开始日期) Tj 250 $y Td (结束日期) Tj 350 $y Td (天数) Tj 400 $y Td (状态) Tj ET\n";
    $y -= 20;
    
    foreach ($records as $record) {
        $content .= "BT /F1 10 Tf 50 $y Td (" . $record['name'] . ") Tj 150 $y Td (" . $record['start_date'] . ") Tj 250 $y Td (" . $record['end_date'] . ") Tj 350 $y Td (" . $record['days'] . ") Tj 400 $y Td (" . $record['status_text'] . ") Tj ET\n";
        $y -= 20;
        
        // 每页最多显示30条记录
        if ($y < 50) {
            break;
        }
    }
    
    $length = strlen($content);
    echo "5 0 obj\n<< /Length $length >>\nstream\n$content\nendstream\nendobj\n";
    
    // PDF文件尾
    echo "xref\n0 7\n0000000000 65535 f \n0000000009 00000 n \n0000000074 00000 n \n0000000120 00000 n \n0000000179 00000 n \n";
    echo sprintf("%010d 00000 n \n", 210 + $length);
    echo "0000000457 00000 n \ntrailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n";
    echo sprintf("%d\n", 495 + $length);
    echo "%%EOF\n";
}
?>
