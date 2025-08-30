<?php
require_once 'config.php';

// 用户相关函数
function getUsers() {
    $data = file_get_contents(USERS_FILE);
    return json_decode($data, true) ?: [];
}

function getUserById($id) {
    $users = getUsers();
    foreach ($users as $user) {
        if ($user['id'] == $id) {
            return $user;
        }
    }
    return null;
}

function getUserByUsername($username) {
    $users = getUsers();
    foreach ($users as $user) {
        if ($user['username'] == $username) {
            return $user;
        }
    }
    return null;
}

function addUser($userData) {
    $users = getUsers();
    $userData['id'] = count($users) + 1;
    $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
    $userData['used_days'] = 0;
    $userData['remaining_days'] = $userData['annual_leave_days'];
    $users[] = $userData;
    
    file_put_contents(USERS_FILE, json_encode($users));
    return $userData['id'];
}

function updateUser($userId, $userData) {
    $users = getUsers();
    foreach ($users as &$user) {
        if ($user['id'] == $userId) {
            // 如果更新密码，需要重新加密
            if (isset($userData['password']) && !empty($userData['password'])) {
                $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
            } else {
                unset($userData['password']); // 不更新密码
            }
            
            // 如果更新年假天数，重新计算剩余天数
            if (isset($userData['annual_leave_days']) && $userData['annual_leave_days'] != $user['annual_leave_days']) {
                $difference = $userData['annual_leave_days'] - $user['annual_leave_days'];
                $userData['remaining_days'] = $user['remaining_days'] + $difference;
            }
            
            $user = array_merge($user, $userData);
            break;
        }
    }
    
    file_put_contents(USERS_FILE, json_encode($users));
    return true;
}

function verifyLogin($username, $password) {
    $user = getUserByUsername($username);
    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    return false;
}

// 请假记录相关函数
function getLeaves($userId = null) {
    $leaves = json_decode(file_get_contents(LEAVES_FILE), true) ?: [];
    
    // 如果指定了用户ID，只返回该用户的请假记录
    if ($userId !== null) {
        $userLeaves = [];
        foreach ($leaves as $leave) {
            if ($leave['user_id'] == $userId) {
                $userLeaves[] = $leave;
            }
        }
        return $userLeaves;
    }
    
    return $leaves;
}

function getLeaveById($leaveId) {
    $leaves = getLeaves();
    foreach ($leaves as $leave) {
        if ($leave['id'] == $leaveId) {
            return $leave;
        }
    }
    return null;
}

function calculateLeaveDays($startDate, $endDate, $isHalfDayStart = false, $isHalfDayEnd = false) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    
    // 如果是同一天
    if ($start->format('Y-m-d') == $end->format('Y-m-d')) {
        return ($isHalfDayStart || $isHalfDayEnd) ? 0.5 : 1;
    }
    
    $days = 0;
    $current = clone $start;
    
    while ($current <= $end) {
        $dayOfWeek = $current->format('N'); // 1=周一, 7=周日
        
        // 跳过周日
        if ($dayOfWeek == 7) {
            $current->modify('+1 day');
            continue;
        }
        
        // 星期六算半天
        if ($dayOfWeek == 6) {
            $days += 0.5;
        } else {
            // 处理第一天和最后一天可能的半天情况
            if ($current == $start && $isHalfDayStart) {
                $days += 0.5;
            } elseif ($current == $end && $isHalfDayEnd) {
                $days += 0.5;
            } else {
                $days += 1;
            }
        }
        
        $current->modify('+1 day');
    }
    
    return $days;
}

function addLeave($leaveData) {
    $leaves = getLeaves();
    $leaveData['id'] = count($leaves) + 1;
    $leaveData['status'] = 'pending'; // 初始状态为待批准
    $leaveData['created_at'] = date('Y-m-d H:i:s');
    
    // 计算请假天数
    $leaveData['days'] = calculateLeaveDays(
        $leaveData['start_date'], 
        $leaveData['end_date'],
        isset($leaveData['is_half_day_start']) ? $leaveData['is_half_day_start'] : false,
        isset($leaveData['is_half_day_end']) ? $leaveData['is_half_day_end'] : false
    );
    
    $leaves[] = $leaveData;
    file_put_contents(LEAVES_FILE, json_encode($leaves));
    
    // 创建对应的审批记录
    addApproval([
        'leave_id' => $leaveData['id'],
        'user_id' => $leaveData['user_id'],
        'supervisor_id' => $leaveData['supervisor_id'],
        'status' => 'pending',
        'approved_at' => null,
        'comment' => ''
    ]);
    
    return $leaveData['id'];
}

function updateLeaveStatus($leaveId, $status) {
    $leaves = getLeaves();
    foreach ($leaves as &$leave) {
        if ($leave['id'] == $leaveId) {
            $leave['status'] = $status;
            
            // 如果批准，更新用户的年假使用情况
            if ($status == 'approved') {
                $user = getUserById($leave['user_id']);
                $newUsedDays = $user['used_days'] + $leave['days'];
                $newRemainingDays = $user['annual_leave_days'] - $newUsedDays;
                
                updateUser($leave['user_id'], [
                    'used_days' => $newUsedDays,
                    'remaining_days' => $newRemainingDays
                ]);
            }
            
            break;
        }
    }
    
    file_put_contents(LEAVES_FILE, json_encode($leaves));
    return true;
}

// 审批相关函数
function getApprovals($supervisorId = null) {
    $approvals = json_decode(file_get_contents(APPROVALS_FILE), true) ?: [];
    
    // 如果指定了主管ID，只返回该主管需要审批的记录
    if ($supervisorId !== null) {
        $supervisorApprovals = [];
        foreach ($approvals as $approval) {
            if ($approval['supervisor_id'] == $supervisorId) {
                $supervisorApprovals[] = $approval;
            }
        }
        return $supervisorApprovals;
    }
    
    return $approvals;
}

function getApprovalByLeaveId($leaveId) {
    $approvals = getApprovals();
    foreach ($approvals as $approval) {
        if ($approval['leave_id'] == $leaveId) {
            return $approval;
        }
    }
    return null;
}

function addApproval($approvalData) {
    $approvals = getApprovals();
    $approvals[] = $approvalData;
    file_put_contents(APPROVALS_FILE, json_encode($approvals));
    return true;
}

function updateApproval($leaveId, $status, $comment = '') {
    $approvals = getApprovals();
    foreach ($approvals as &$approval) {
        if ($approval['leave_id'] == $leaveId) {
            $approval['status'] = $status;
            $approval['approved_at'] = date('Y-m-d H:i:s');
            $approval['comment'] = $comment;
            break;
        }
    }
    
    file_put_contents(APPROVALS_FILE, json_encode($approvals));
    // 同时更新请假记录状态
    updateLeaveStatus($leaveId, $status);
    return true;
}

// 日期验证函数
function isValidLeaveDate($date) {
    $today = new DateTime();
    $maxDate = new DateTime(MAX_FUTURE_DATE);
    $checkDate = new DateTime($date);
    
    return $checkDate <= $maxDate;
}

// 导出功能函数
function exportToCSV($leaves, $filename = 'leave_records.csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // 写入表头
    fputcsv($output, ['ID', '员工', '开始日期', '结束日期', '天数', '原因', '状态', '申请时间']);
    
    // 写入数据
    foreach ($leaves as $leave) {
        $user = getUserById($leave['user_id']);
        fputcsv($output, [
            $leave['id'],
            $user['name'],
            $leave['start_date'],
            $leave['end_date'],
            $leave['days'],
            $leave['reason'],
            $leave['status'],
            $leave['created_at']
        ]);
    }
    
    fclose($output);
    exit;
}

// 日历视图数据生成
function getCalendarData($year, $month, $userId = null) {
    $leaves = $userId ? getLeaves($userId) : getLeaves();
    
    $calendar = [];
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = "$year-$month-$day";
        $leaveEntries = [];
        
        foreach ($leaves as $leave) {
            $start = new DateTime($leave['start_date']);
            $end = new DateTime($leave['end_date']);
            $current = new DateTime($date);
            
            if ($current >= $start && $current <= $end) {
                $user = getUserById($leave['user_id']);
                $leaveEntries[] = [
                    'user' => $user['name'],
                    'status' => $leave['status'],
                    'is_half_day' => ($current == $start && isset($leave['is_half_day_start']) && $leave['is_half_day_start']) || 
                                    ($current == $end && isset($leave['is_half_day_end']) && $leave['is_half_day_end'])
                ];
            }
        }
        
        $calendar[] = [
            'date' => $date,
            'day' => $day,
            'weekday' => date('N', strtotime($date)),
            'leaves' => $leaveEntries
        ];
    }
    
    return $calendar;
}
?>
