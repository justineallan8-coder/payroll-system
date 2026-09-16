<?php
// =============================================
// AUTHENTICATION FUNCTIONS
// =============================================

if (!function_exists('logActivity')) {
    require_once __DIR__ . '/activity.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define constants
if (!defined('SITE_NAME')) define('SITE_NAME', 'Allan Shop Payroll');
if (!defined('SITE_EMAIL')) define('SITE_EMAIL', 'noreply@allanshop.com');
if (!defined('BASE_URL')) define('BASE_URL', 'http://localhost/payroll_system/');

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Restore user details for sessions created before all session fields were saved.
 */
function hydrateSessionUser() {
    global $conn;

    if (!isLoggedIn()) return null;

    $userId = (int)$_SESSION['user_id'];
    $result = mysqli_query($conn, "SELECT id, username, full_name, email, role, avatar, status FROM users WHERE id = $userId LIMIT 1");
    $user = $result ? mysqli_fetch_assoc($result) : null;

    if (!$user) return null;

    foreach (['username', 'full_name', 'email', 'role', 'avatar'] as $field) {
        $_SESSION[$field] = $user[$field];
    }

    return $user;
}

/**
 * Get the current user's role and restore it for older sessions.
 */
function getCurrentUserRole() {
    if (!isLoggedIn()) return null;

    if (!isset($_SESSION['role'])) {
        $user = hydrateSessionUser();
        if (!$user) return null;
    }

    return $_SESSION['role'];
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return getCurrentUserRole() === 'admin';
}

/**
 * Check if user has permission
 */
function hasPermission($permission) {
    if (!isLoggedIn()) return false;
    $role = getCurrentUserRole();
    
    $permissions = [
        'admin' => ['all'],
        'manager' => ['view', 'report'],
        'viewer' => ['view', 'report']
    ];
    
    return in_array('all', $permissions[$role] ?? []) || in_array($permission, $permissions[$role] ?? []);
}

/**
 * Require login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        setFlash('error', 'Please login to access this page.');
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }

    if (!hydrateSessionUser()) {
        session_unset();
        session_destroy();
        session_start();
        setFlash('error', 'Your account could not be found. Please log in again.');
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
    
    // Update last activity
    global $conn;
    $id = (int)$_SESSION['user_id'];
    mysqli_query($conn, "UPDATE users SET last_activity = NOW() WHERE id = $id");
}

/**
 * Require admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        setFlash('error', 'Access denied. Admin privileges required.');
        header('Location: ' . BASE_URL . 'work_hours.php');
        exit;
    }
}

/**
 * Get current user
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    global $conn;
    $id = (int)$_SESSION['user_id'];
    $result = mysqli_query($conn, "SELECT * FROM users WHERE id = $id");
    return mysqli_fetch_assoc($result);
}

/**
 * Login user (with work hours tracking)
 */
function loginUser($username, $password) {
    global $conn;
    require_once __DIR__ . '/tracker.php';
    
    $username = mysqli_real_escape_string($conn, $username);
    $result = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username' AND status = 'active'");
    $user = mysqli_fetch_assoc($result);
    
    if ($user && password_verify($password, $user['password'])) {
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['avatar'] = $user['avatar'];
        
        // Start work session (tracks attendance)
        startWorkSession($user['id']);
        
        return true;
    }
    
    return false;
}

/**
 * Logout user (with work hours tracking)
 */
function logoutUser() {
    global $conn;
    
    if (isLoggedIn()) {
        $user_id = (int)$_SESSION['user_id'];
        
        // Calculate session duration
        $session_start = $_SESSION['login_time'] ?? time();
        $duration = time() - $session_start;
        
        // Update work session
        mysqli_query($conn, "UPDATE work_sessions SET 
            logout_time = NOW(),
            status = 'completed'
            WHERE user_id = $user_id AND status = 'active'
            ORDER BY id DESC LIMIT 1");
        
        // Update total work seconds
        mysqli_query($conn, "UPDATE users SET 
            total_work_seconds = total_work_seconds + $duration,
            is_online = 0,
            current_session_start = NULL
            WHERE id = $user_id");
        
        logActivity('logout', "User logged out. Session: " . formatDuration($duration));
    }
    
    session_unset();
    session_destroy();
}

/**
 * Format seconds into readable duration
 */
function formatDuration($seconds) {
    if ($seconds < 60) return $seconds . 's';
    
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    
    $parts = [];
    if ($hours > 0) $parts[] = $hours . 'h';
    if ($minutes > 0) $parts[] = $minutes . 'm';
    if ($secs > 0 && $hours == 0) $parts[] = $secs . 's';
    
    return implode(' ', $parts);
}

/**
 * Get role badge
 */
function getRoleBadge($role) {
    switch ($role) {
        case 'admin':   return 'bg-purple-100 text-purple-700';
        case 'manager': return 'bg-blue-100 text-blue-700';
        case 'viewer':  return 'bg-gray-100 text-gray-700';
        default:        return 'bg-gray-100 text-gray-700';
    }
}

/**
 * Get avatar URL
 */
function getAvatarUrl($avatar, $fullName = '') {
    if ($avatar && file_exists(__DIR__ . '/../assets/avatars/' . $avatar)) {
        return BASE_URL . 'assets/avatars/' . $avatar;
    }
    // Default: UI Avatars
    $name = urlencode($fullName ?: 'User');
    return "https://ui-avatars.com/api/?name=$name&background=4f46e5&color=fff&size=128";
}
?>