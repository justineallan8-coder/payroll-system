<?php
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/function.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tracker.php';

requireLogin();

$allowedStatuses = ['working', 'meeting', 'offline'];
$newStatus = $_POST['status'] ?? $_GET['status'] ?? '';
$newStatus = in_array($newStatus, $allowedStatuses, true) ? $newStatus : 'working';
$userId = (int)$_SESSION['user_id'];

if ($newStatus === 'offline') {
    endWorkSession($userId);
} else {
    $active = mysqli_query($conn, "SELECT id FROM work_sessions WHERE user_id = $userId AND status = 'active' LIMIT 1");
    if (!$active || mysqli_num_rows($active) === 0) {
        startWorkSession($userId);
    }
    mysqli_query($conn, "UPDATE users SET work_status = '$newStatus', is_online = 1, last_activity = NOW() WHERE id = $userId");
    logActivity($userId, 'status_update', 'Changed work status to ' . $newStatus);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'status' => $newStatus]);
    exit;
}

redirect($_SERVER['HTTP_REFERER'] ?? 'work_hours.php');
?>