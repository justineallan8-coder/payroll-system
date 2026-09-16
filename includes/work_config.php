<?php
// =============================================
// WORK HOURS CONFIGURATION
// =============================================

// Work hours (24-hour format)
define('WORK_START_HOUR', 8);      // 08:00 AM
define('WORK_END_HOUR', 16);       // 04:00 PM
define('STANDARD_HOURS', 8);       // 8 hours per day
define('GRACE_MINUTES', 15);       // 15-minute grace period for late
define('OVERTIME_MULTIPLIER', 1.5); // Overtime rate

// Weekend days (0 = Sunday, 6 = Saturday)
define('WEEKEND_DAYS', [0, 6]);

// =============================================
// CHECK IF A DATE IS A HOLIDAY
// =============================================
function isHoliday($date) {
    global $conn;
    
    $date = mysqli_real_escape_string($conn, $date);
    $result = mysqli_query($conn, "SELECT * FROM holidays WHERE holiday_date = '$date'");
    
    return mysqli_num_rows($result) > 0 ? mysqli_fetch_assoc($result) : false;
}

// =============================================
// CHECK IF A DATE IS A WEEKEND
// =============================================
function isWeekend($date) {
    $dayOfWeek = date('w', strtotime($date));
    return in_array((int)$dayOfWeek, WEEKEND_DAYS);
}

// =============================================
// CHECK IF A DATE IS A WORKING DAY
// =============================================
function isWorkingDay($date) {
    return !isWeekend($date) && !isHoliday($date);
}

// =============================================
// GET HOLIDAY NAME FOR A DATE
// =============================================
function getHolidayName($date) {
    $holiday = isHoliday($date);
    return $holiday ? $holiday['holiday_name'] : null;
}

// =============================================
// CALCULATE WORK HOURS FOR A SESSION
// =============================================
function calculateSessionHours($login_time, $logout_time) {
    $login = strtotime($login_time);
    $logout = strtotime($logout_time);
    $work_date = date('Y-m-d', $login);
    
    // Get work day boundaries
    $work_start = strtotime($work_date . ' ' . sprintf('%02d:00:00', WORK_START_HOUR));
    $work_end = strtotime($work_date . ' ' . sprintf('%02d:00:00', WORK_END_HOUR));
    
    // Check if holiday or weekend
    $holiday = isHoliday($work_date);
    $weekend = isWeekend($work_date);
    
    // If holiday or weekend, all hours are overtime (if any)
    if ($holiday || $weekend) {
        $total_minutes = floor(($logout - $login) / 60);
        return [
            'regular_hours' => 0,
            'overtime_hours' => round($total_minutes / 60, 2),
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'total_minutes' => $total_minutes,
            'is_holiday' => $holiday ? 1 : 0,
            'is_weekend' => $weekend ? 1 : 0,
            'holiday_name' => $holiday ? $holiday['holiday_name'] : null
        ];
    }
    
    // Calculate late arrival (after grace period)
    $grace_end = $work_start + (GRACE_MINUTES * 60);
    $late_minutes = 0;
    if ($login > $grace_end) {
        $late_minutes = floor(($login - $work_start) / 60);
    }
    
    // Calculate early leave
    $early_leave_minutes = 0;
    if ($logout < $work_end) {
        $early_leave_minutes = floor(($work_end - $logout) / 60);
    }
    
    // Calculate working duration (only between 08:00 and 16:00)
    $effective_start = max($login, $work_start);
    $effective_end = min($logout, $work_end);
    
    $regular_minutes = 0;
    if ($effective_end > $effective_start) {
        $regular_minutes = floor(($effective_end - $effective_start) / 60);
    }
    
    // Overtime = time worked AFTER 16:00
    $overtime_minutes = 0;
    if ($logout > $work_end) {
        $overtime_minutes = floor(($logout - $work_end) / 60);
    }
    
    // If worked before 08:00, count as overtime
    if ($login < $work_start) {
        $overtime_minutes += floor(($work_start - $login) / 60);
    }
    
    return [
        'regular_hours' => round($regular_minutes / 60, 2),
        'overtime_hours' => round($overtime_minutes / 60, 2),
        'late_minutes' => $late_minutes,
        'early_leave_minutes' => $early_leave_minutes,
        'total_minutes' => $regular_minutes + $overtime_minutes,
        'is_holiday' => 0,
        'is_weekend' => 0,
        'holiday_name' => null
    ];
}

// =============================================
// GET WORKING DAYS IN A MONTH
// =============================================
function getWorkingDaysInMonth($year, $month) {
    global $conn;
    
    $days = [];
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $dayOfWeek = date('w', strtotime($date));
        
        $isWeekend = in_array((int)$dayOfWeek, WEEKEND_DAYS);
        $holiday = isHoliday($date);
        
        $days[] = [
            'date' => $date,
            'day' => $day,
            'day_name' => date('l', strtotime($date)),
            'is_weekend' => $isWeekend,
            'is_holiday' => (bool)$holiday,
            'holiday_name' => $holiday ? $holiday['holiday_name'] : null,
            'is_working' => !$isWeekend && !$holiday
        ];
    }
    
    return $days;
}

// =============================================
// GET MONTHLY SUMMARY FOR A USER
// =============================================
function getMonthlySummary($userId, $year, $month) {
    global $conn;
    
    $result = mysqli_query($conn, "
        SELECT 
            COUNT(DISTINCT work_date) as days_worked,
            SUM(regular_hours) as total_regular,
            SUM(overtime_hours) as total_overtime,
            SUM(late_minutes) as total_late,
            SUM(early_leave_minutes) as total_early
        FROM work_sessions 
        WHERE user_id = $userId 
          AND YEAR(work_date) = $year 
          AND MONTH(work_date) = $month
          AND status IN ('completed', 'timeout')
    ");
    
    return mysqli_fetch_assoc($result);
}

// =============================================
// GET TODAY'S SESSION FOR USER
// =============================================
function getTodaySession($userId) {
    global $conn;
    
    $today = date('Y-m-d');
    $result = mysqli_query($conn, "
        SELECT * FROM work_sessions 
        WHERE user_id = $userId AND work_date = '$today' 
        ORDER BY login_time DESC LIMIT 1
    ");
    
    return mysqli_fetch_assoc($result);
}
?>