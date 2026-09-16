<?php
// =============================================
// ACTIVITY LOGGING
// =============================================

/**
 * Log user activity
 * Supports both signatures:
 *   logActivity('login', 'message')
 *   logActivity($userId, 'login', 'message')
 */
function logActivity($arg1, $arg2 = '', $arg3 = '') {
    global $conn;

    if (is_numeric($arg1) && $arg2 !== '') {
        $user_id = (int)$arg1;
        $action = $arg2;
        $description = $arg3;
    } else {
        $user_id = $_SESSION['user_id'] ?? null;
        $action = $arg1;
        $description = $arg2;
    }

    $username = $_SESSION['username'] ?? 'Guest';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    $action = mysqli_real_escape_string($conn, $action);
    $description = mysqli_real_escape_string($conn, $description);
    $username = mysqli_real_escape_string($conn, $username);
    $ip = mysqli_real_escape_string($conn, $ip);
    $ua = mysqli_real_escape_string($conn, $ua);

    $user_id_sql = $user_id !== null ? (int)$user_id : 'NULL';

    $sql = "INSERT INTO activity_log (user_id, username, action, description, ip_address, user_agent) 
            VALUES ($user_id_sql, '$username', '$action', '$description', '$ip', '$ua')";

    mysqli_query($conn, $sql);
}

/**
 * Get activities with pagination
 */
function getActivities($limit = 50, $offset = 0, $userId = null) {
    global $conn;
    
    $where = $userId ? "WHERE user_id = " . (int)$userId : "";
    
    $sql = "SELECT * FROM activity_log $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    return mysqli_query($conn, $sql);
}

/**
 * Count total activities
 */
function countActivities($userId = null) {
    global $conn;
    $where = $userId ? "WHERE user_id = " . (int)$userId : "";
    $result = mysqli_query($conn, "SELECT COUNT(*) as c FROM activity_log $where");
    return mysqli_fetch_assoc($result)['c'];
}
?>