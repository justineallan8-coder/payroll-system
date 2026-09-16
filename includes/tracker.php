<?php
// =============================================
// WORK HOURS & SESSION TRACKER
// =============================================

require_once __DIR__ . '/work_config.php';

/**
 * Start a new work session when user logs in
 */
function startWorkSession($userId) {
    global $conn;
    
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');
    
    // End any existing active sessions
    mysqli_query($conn, "UPDATE work_sessions SET 
        logout_time = NOW(), 
        status = 'completed',
        duration_minutes = TIMESTAMPDIFF(MINUTE, login_time, NOW())
        WHERE user_id = $userId AND status = 'active'");
    
    // Check if it's a working day
    $holiday = isHoliday($today);
    $weekend = isWeekend($today);
    
    $is_holiday = $holiday ? 1 : 0;
    $is_weekend = $weekend ? 1 : 0;
    
    // Create new session
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $agent = mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
    
    $sql = "INSERT INTO work_sessions 
            (user_id, login_time, work_date, ip_address, user_agent, status, is_holiday, is_weekend) 
            VALUES ($userId, NOW(), '$today', '$ip', '$agent', 'active', $is_holiday, $is_weekend)";
    
    mysqli_query($conn, $sql);
    $_SESSION['session_id'] = mysqli_insert_id($conn);
    
    // Update user status
    mysqli_query($conn, "UPDATE users SET 
        is_online = 1, 
        last_login = NOW(),
        last_activity = NOW()
        WHERE id = $userId");
    
    logActivity($userId, 'login', 'User logged in from ' . $ip);
}

/**
 * End work session on logout
 */
function endWorkSession($userId, $status = 'completed') {
    global $conn;
    
    // Get active session
    $result = mysqli_query($conn, "SELECT * FROM work_sessions 
        WHERE user_id = $userId AND status = 'active' 
        ORDER BY login_time DESC LIMIT 1");
    $session = mysqli_fetch_assoc($result);
    
    if ($session) {
        $calc = calculateSessionHours($session['login_time'], date('Y-m-d H:i:s'));
        
        // Update with calculated hours
        mysqli_query($conn, "UPDATE work_sessions SET 
            logout_time = NOW(), 
            status = '$status',
            duration_minutes = TIMESTAMPDIFF(MINUTE, login_time, NOW()),
            regular_hours = {$calc['regular_hours']},
            overtime_hours = {$calc['overtime_hours']},
            late_minutes = {$calc['late_minutes']},
            early_leave_minutes = {$calc['early_leave_minutes']}
            WHERE id = {$session['id']}");
    }
    
    // Update user status
    mysqli_query($conn, "UPDATE users SET 
        is_online = 0,
        last_logout = NOW()
        WHERE id = $userId");
    
    logActivity($userId, 'logout', 'User logged out');
}

// Session and work-hour helper functions
function getActiveSessions() {
    global $conn;
    
    $result = mysqli_query($conn, "
        SELECT ws.*, u.full_name, u.username, u.email, u.role, u.avatar,
               TIMESTAMPDIFF(MINUTE, ws.login_time, NOW()) as minutes_active
        FROM work_sessions ws
        JOIN users u ON ws.user_id = u.id
        WHERE ws.status = 'active'
        ORDER BY ws.login_time DESC
    ");
    
    $sessions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $sessions[] = $row;
    }
    return $sessions;
}

/**
 * Get session history for a user
 */
function getUserSessionHistory($userId, $limit = 30) {
    global $conn;
    
    $result = mysqli_query($conn, "
        SELECT * FROM work_sessions 
        WHERE user_id = $userId 
        ORDER BY login_time DESC 
        LIMIT $limit
    ");
    
    $sessions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $sessions[] = $row;
    }
    return $sessions;
}

/**
 * Get last login info for a user
 */
function getLastLogin($userId) {
    global $conn;
    
    $result = mysqli_query($conn, "
        SELECT * FROM work_sessions 
        WHERE user_id = $userId AND status != 'active'
        ORDER BY login_time DESC 
        LIMIT 1
    ");
    
    return mysqli_fetch_assoc($result);
}

/**
 * Get last login for all users (for admin view)
 */
function getAllUsersLastLogin() {
    global $conn;
    
    $result = mysqli_query($conn, "
        SELECT u.id, u.full_name, u.username, u.email, u.role, u.avatar, u.is_online,
               u.last_login, u.last_logout,
               (SELECT COUNT(*) FROM work_sessions WHERE user_id = u.id) as total_sessions,
               (SELECT SUM(duration_minutes) FROM work_sessions WHERE user_id = u.id) as total_minutes
        FROM users u
        WHERE u.status = 'active'
        ORDER BY u.is_online DESC, u.last_login DESC
    ");
    
    $users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    return $users;
}

/**
 * Get total work hours for a user
 */
function getTotalWorkHours($userId, $period = 'all') {
    global $conn;
    
    $where = "user_id = $userId AND status IN ('completed', 'timeout')";
    
    switch ($period) {
        case 'today':
            $where .= " AND DATE(login_time) = CURDATE()";
            break;
        case 'yesterday':
            $where .= " AND DATE(login_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'week':
            $where .= " AND YEARWEEK(login_time) = YEARWEEK(CURDATE())";
            break;
        case 'month':
            $where .= " AND MONTH(login_time) = MONTH(CURDATE()) AND YEAR(login_time) = YEAR(CURDATE())";
            break;
    }
    
    $result = mysqli_query($conn, "SELECT SUM(duration_minutes) as total FROM work_sessions WHERE $where");
    $row = mysqli_fetch_assoc($result);
    $minutes = $row['total'] ?? 0;
    
    return [
        'minutes' => $minutes,
        'hours' => floor($minutes / 60),
        'formatted' => floor($minutes / 60) . 'h ' . ($minutes % 60) . 'm'
    ];
}

/**
 * Get current session duration
 */
function getCurrentSessionDuration() {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) return '0h 0m';
    
    $userId = $_SESSION['user_id'];
    $result = mysqli_query($conn, "SELECT TIMESTAMPDIFF(MINUTE, login_time, NOW()) as minutes 
        FROM work_sessions 
        WHERE user_id = $userId AND status = 'active' 
        ORDER BY login_time DESC LIMIT 1");
    
    $row = mysqli_fetch_assoc($result);
    $minutes = $row['minutes'] ?? 0;
    
    return floor($minutes / 60) . 'h ' . ($minutes % 60) . 'm';
}

/**
 * Update last activity timestamp
 */
function updateLastActivity() {
    global $conn;
    
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        mysqli_query($conn, "UPDATE users SET last_activity = NOW() WHERE id = $userId");
    }
}

/**
 * Cleanup stale sessions (sessions older than timeout)
 */
function cleanupStaleSessions() {
    global $conn;
    
    // Mark sessions older than 30 min without logout as timeout
    mysqli_query($conn, "UPDATE work_sessions SET 
        logout_time = DATE_ADD(login_time, INTERVAL 30 MINUTE),
        status = 'timeout',
        duration_minutes = 30
        WHERE status = 'active' 
          AND login_time < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    
    // Update users offline
    mysqli_query($conn, "UPDATE users SET is_online = 0 
        WHERE is_online = 1 
        AND id NOT IN (SELECT DISTINCT user_id FROM work_sessions WHERE status = 'active')");
}
?>