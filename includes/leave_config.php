<?php
// =============================================
// LEAVE MANAGEMENT FUNCTIONS
// =============================================

/**
 * Create the default leave balances for a new employee.
 */
function initializeEmployeeLeaveBalances($employeeId, $year = null) {
    global $conn;
    $year = (int)($year ?? date('Y'));
    $employeeId = (int)$employeeId;

    $defaults = [
        'ANNUAL' => 28,
        'SICK' => 14,
        'MATERNITY' => 84,
        'PATERNITY' => 7,
        'EMERGENCY' => 5,
        'COMPASSIONATE' => 5,
        'STUDY' => 10,
        'UNPAID' => 30,
        'MARRIAGE' => 5,
        'HAJJ' => 30
    ];

    foreach ($defaults as $typeCode => $allocatedDays) {
        $typeCode = mysqli_real_escape_string($conn, $typeCode);
        $type = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM leave_types WHERE type_code = '$typeCode' LIMIT 1"));
        if (!$type) continue;

        mysqli_query($conn, "INSERT IGNORE INTO leave_balances
            (employee_id, leave_type_id, year, allocated_days, carried_over, used_days, pending_days)
            VALUES ($employeeId, {$type['id']}, $year, $allocatedDays, 0, 0, 0)");
    }
}

/**
 * Get all leave types
 */
function getLeaveTypes() {
    global $conn;
    $result = mysqli_query($conn, "SELECT * FROM leave_types ORDER BY type_name");
    $types = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $types[] = $row;
    }
    return $types;
}

/**
 * Get leave type by ID
 */
function getLeaveType($id) {
    global $conn;
    $id = (int)$id;
    $result = mysqli_query($conn, "SELECT * FROM leave_types WHERE id = $id");
    return mysqli_fetch_assoc($result);
}

/**
 * Get employee leave balance for a specific type and year
 */
function getLeaveBalance($employeeId, $leaveTypeId, $year = null) {
    global $conn;
    $year = $year ?? date('Y');
    
    $result = mysqli_query($conn, "
        SELECT * FROM leave_balances 
        WHERE employee_id = $employeeId 
          AND leave_type_id = $leaveTypeId 
          AND year = $year
    ");
    return mysqli_fetch_assoc($result);
}

/**
 * Get all leave balances for an employee
 */
function getEmployeeLeaveBalances($employeeId, $year = null) {
    global $conn;
    $year = $year ?? date('Y');
    
    $result = mysqli_query($conn, "
        SELECT lb.*, lt.type_name, lt.type_code, lt.color, lt.icon, lt.is_paid
        FROM leave_balances lb
        JOIN leave_types lt ON lb.leave_type_id = lt.id
        WHERE lb.employee_id = $employeeId AND lb.year = $year
        ORDER BY lt.type_name
    ");
    
    $balances = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $balances[] = $row;
    }
    return $balances;
}

/**
 * Apply for leave
 */
function applyForLeave($employeeId, $leaveTypeId, $startDate, $endDate, $reason, $documentPath = null) {
    global $conn;
    
    // Calculate total days (excluding weekends and holidays)
    $totalDays = calculateWorkingDays($startDate, $endDate);
    
    if ($totalDays <= 0) {
        return ['success' => false, 'message' => 'Invalid date range — no working days'];
    }
    
    // Check leave balance
    $year = date('Y', strtotime($startDate));
    $balance = getLeaveBalance($employeeId, $leaveTypeId, $year);
    
    if (!$balance) {
        return ['success' => false, 'message' => 'No leave balance found'];
    }
    
    $remaining = $balance['allocated_days'] + $balance['carried_over'] - $balance['used_days'] - $balance['pending_days'];
    
    if ($totalDays > $remaining) {
        return ['success' => false, 'message' => "Insufficient balance. You have $remaining days remaining."];
    }
    
    // Insert leave request
    $startDate = mysqli_real_escape_string($conn, $startDate);
    $endDate = mysqli_real_escape_string($conn, $endDate);
    $reason = mysqli_real_escape_string($conn, $reason);
    $documentPath = $documentPath ? "'" . mysqli_real_escape_string($conn, $documentPath) . "'" : 'NULL';
    
    $sql = "INSERT INTO leave_requests 
            (employee_id, leave_type_id, start_date, end_date, total_days, reason, document_path) 
            VALUES ($employeeId, $leaveTypeId, '$startDate', '$endDate', $totalDays, '$reason', $documentPath)";
    
    if (mysqli_query($conn, $sql)) {
        $leaveId = mysqli_insert_id($conn);
        
        // Update pending balance
        mysqli_query($conn, "
            UPDATE leave_balances 
            SET pending_days = pending_days + $totalDays 
            WHERE employee_id = $employeeId 
              AND leave_type_id = $leaveTypeId 
              AND year = $year
        ");

        $employee = mysqli_fetch_assoc(mysqli_query($conn, "SELECT first_name, last_name, email FROM employees WHERE id = $employeeId"));
        if ($employee) {
            sendUpdateNotification(
                $employee['email'],
                $employee['first_name'] . ' ' . $employee['last_name'],
                'Leave request submitted',
                "Your leave request (#$leaveId) has been submitted and is awaiting review."
            );
            notifyAdministrators(
                'New leave request',
                "{$employee['first_name']} {$employee['last_name']} submitted leave request #$leaveId for review."
            );
        }
        
        return ['success' => true, 'message' => 'Leave request submitted!', 'id' => $leaveId];
    }
    
    return ['success' => false, 'message' => mysqli_error($conn)];
}

/**
 * Approve leave request
 */
function approveLeave($leaveId, $approverId) {
    global $conn;
    
    $result = mysqli_query($conn, "SELECT * FROM leave_requests WHERE id = $leaveId");
    $leave = mysqli_fetch_assoc($result);
    
    if (!$leave || $leave['status'] !== 'pending') {
        return ['success' => false, 'message' => 'Invalid leave request'];
    }
    
    $year = date('Y', strtotime($leave['start_date']));
    
    // Update leave request
    mysqli_query($conn, "
        UPDATE leave_requests 
        SET status = 'approved', approved_by = $approverId, approved_at = NOW() 
        WHERE id = $leaveId
    ");
    
    // Move from pending to used
    mysqli_query($conn, "
        UPDATE leave_balances 
        SET pending_days = pending_days - {$leave['total_days']},
            used_days = used_days + {$leave['total_days']}
        WHERE employee_id = {$leave['employee_id']} 
          AND leave_type_id = {$leave['leave_type_id']} 
          AND year = $year
    ");
    
    logActivity($approverId, 'approve_leave', "Approved leave #$leaveId for employee {$leave['employee_id']}");

    $employee = mysqli_fetch_assoc(mysqli_query($conn, "SELECT first_name, last_name, email FROM employees WHERE id = {$leave['employee_id']}"));
    if ($employee) {
        sendUpdateNotification(
            $employee['email'],
            $employee['first_name'] . ' ' . $employee['last_name'],
            'Leave request approved',
            "Your leave request (#$leaveId) has been approved."
        );
    }
    
    return ['success' => true, 'message' => 'Leave approved'];
}

/**
 * Reject leave request
 */
function rejectLeave($leaveId, $approverId, $reason = '') {
    global $conn;
    
    $result = mysqli_query($conn, "SELECT * FROM leave_requests WHERE id = $leaveId");
    $leave = mysqli_fetch_assoc($result);
    
    if (!$leave || $leave['status'] !== 'pending') {
        return ['success' => false, 'message' => 'Invalid leave request'];
    }
    
    $year = date('Y', strtotime($leave['start_date']));
    $reason = mysqli_real_escape_string($conn, $reason);
    
    mysqli_query($conn, "
        UPDATE leave_requests 
        SET status = 'rejected', approved_by = $approverId, approved_at = NOW(), rejection_reason = '$reason' 
        WHERE id = $leaveId
    ");
    
    // Remove from pending
    mysqli_query($conn, "
        UPDATE leave_balances 
        SET pending_days = pending_days - {$leave['total_days']}
        WHERE employee_id = {$leave['employee_id']} 
          AND leave_type_id = {$leave['leave_type_id']} 
          AND year = $year
    ");
    
    logActivity($approverId, 'reject_leave', "Rejected leave #$leaveId");

    $employee = mysqli_fetch_assoc(mysqli_query($conn, "SELECT first_name, last_name, email FROM employees WHERE id = {$leave['employee_id']}"));
    if ($employee) {
        sendUpdateNotification(
            $employee['email'],
            $employee['first_name'] . ' ' . $employee['last_name'],
            'Leave request rejected',
            "Your leave request (#$leaveId) has been rejected. Reason: " . ($reason ?: 'No reason provided')
        );
    }
    
    return ['success' => true, 'message' => 'Leave rejected'];
}

/**
 * Get leave requests (with filters)
 */
function getLeaveRequests($filters = []) {
    global $conn;
    
    $where = [];
    
    if (!empty($filters['employee_id'])) {
        $where[] = "lr.employee_id = " . (int)$filters['employee_id'];
    }
    
    if (!empty($filters['status'])) {
        $status = mysqli_real_escape_string($conn, $filters['status']);
        $where[] = "lr.status = '$status'";
    }
    
    if (!empty($filters['year'])) {
        $where[] = "YEAR(lr.start_date) = " . (int)$filters['year'];
    }
    
    if (!empty($filters['month'])) {
        $where[] = "MONTH(lr.start_date) = " . (int)$filters['month'];
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $result = mysqli_query($conn, "
        SELECT lr.*, 
               e.first_name, e.last_name, e.employee_id as emp_code,
               lt.type_name, lt.type_code, lt.color, lt.icon, lt.is_paid,
               u.full_name as approver_name
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        LEFT JOIN users u ON lr.approved_by = u.id
        $whereClause
        ORDER BY lr.created_at DESC
    ");
    
    $requests = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $requests[] = $row;
    }
    return $requests;
}

/**
 * Get leave requests for a specific date range (used in attendance)
 */
function getLeavesInRange($employeeId, $startDate, $endDate) {
    global $conn;
    
    $startDate = mysqli_real_escape_string($conn, $startDate);
    $endDate = mysqli_real_escape_string($conn, $endDate);
    
    $result = mysqli_query($conn, "
        SELECT lr.*, lt.type_name, lt.type_code, lt.color, lt.icon
        FROM leave_requests lr
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        WHERE lr.employee_id = $employeeId
          AND lr.status = 'approved'
          AND lr.start_date <= '$endDate'
          AND lr.end_date >= '$startDate'
        ORDER BY lr.start_date
    ");
    
    $leaves = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $leaves[] = $row;
    }
    return $leaves;
}

/**
 * Check if employee is on leave on a specific date
 */
function getLeaveOnDate($employeeId, $date) {
    global $conn;
    
    $date = mysqli_real_escape_string($conn, $date);
    
    $result = mysqli_query($conn, "
        SELECT lr.*, lt.type_name, lt.type_code, lt.color, lt.icon, lt.is_paid
        FROM leave_requests lr
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        WHERE lr.employee_id = $employeeId
          AND lr.status = 'approved'
          AND '$date' BETWEEN lr.start_date AND lr.end_date
        LIMIT 1
    ");
    
    return mysqli_fetch_assoc($result);
}

/**
 * Calculate working days between two dates (excludes weekends & holidays)
 */
function calculateWorkingDays($startDate, $endDate) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $end->modify('+1 day');
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);
    
    $workingDays = 0;
    foreach ($period as $date) {
        $dateStr = $date->format('Y-m-d');
        if (isWorkingDay($dateStr)) {
            $workingDays++;
        }
    }
    
    return $workingDays;
}

/**
 * Get pending leave count (for admin badge)
 */
function getPendingLeaveCount() {
    global $conn;
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'");
    return mysqli_fetch_assoc($result)['count'];
}

/**
 * Get attendance statistics for charts
 */
function getAttendanceStats($year = null, $month = null) {
    global $conn;
    $year = $year ?? date('Y');
    $month = $month ?? date('m');
    
    $result = mysqli_query($conn, "
        SELECT 
            COUNT(DISTINCT ws.work_date) as days_present,
            SUM(ws.regular_hours) as total_regular,
            SUM(ws.overtime_hours) as total_overtime,
            SUM(ws.late_minutes) as total_late,
            SUM(CASE WHEN ws.overtime_hours > 0 THEN 1 ELSE 0 END) as days_with_overtime,
            SUM(CASE WHEN ws.late_minutes > 15 THEN 1 ELSE 0 END) as days_late
        FROM work_sessions ws
        WHERE YEAR(ws.work_date) = $year 
          AND MONTH(ws.work_date) = $month
          AND ws.status IN ('completed', 'timeout')
    ");
    
    return mysqli_fetch_assoc($result);
}

/**
 * Get department-wise attendance
 */
function getDepartmentAttendance($year = null, $month = null) {
    global $conn;
    $year = $year ?? date('Y');
    $month = $month ?? date('m');
    
    $result = mysqli_query($conn, "
        SELECT 
            e.department,
            COUNT(DISTINCT ws.work_date) as total_days,
            SUM(ws.regular_hours) as total_hours,
            SUM(ws.overtime_hours) as total_overtime,
            SUM(ws.late_minutes) as total_late,
            COUNT(DISTINCT e.id) as employee_count
        FROM employees e
        LEFT JOIN work_sessions ws ON e.id = ws.user_id 
            AND YEAR(ws.work_date) = $year 
            AND MONTH(ws.work_date) = $month
        WHERE e.status = 'active' AND e.department IS NOT NULL
        GROUP BY e.department
        ORDER BY total_hours DESC
    ");
    
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    return $data;
}

/**
 * Get leave distribution by type
 */
function getLeaveDistribution($year = null) {
    global $conn;
    $year = $year ?? date('Y');
    
    $result = mysqli_query($conn, "
        SELECT 
            lt.type_name,
            lt.color,
            lt.icon,
            COUNT(lr.id) as request_count,
            SUM(lr.total_days) as total_days
        FROM leave_types lt
        LEFT JOIN leave_requests lr ON lt.id = lr.leave_type_id 
            AND YEAR(lr.start_date) = $year 
            AND lr.status = 'approved'
        GROUP BY lt.id
        ORDER BY total_days DESC
    ");
    
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    return $data;
}

/**
 * Get monthly attendance trend (last 12 months)
 */
function getMonthlyAttendanceTrend($year = null) {
    global $conn;
    $year = $year ?? date('Y');
    
    $result = mysqli_query($conn, "
        SELECT 
            MONTH(work_date) as month_num,
            MONTHNAME(work_date) as month_name,
            SUM(regular_hours) as total_regular,
            SUM(overtime_hours) as total_overtime,
            COUNT(DISTINCT work_date) as days_worked
        FROM work_sessions 
        WHERE YEAR(work_date) = $year
          AND status IN ('completed', 'timeout')
        GROUP BY MONTH(work_date)
        ORDER BY month_num
    ");
    
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    return $data;
}
?>