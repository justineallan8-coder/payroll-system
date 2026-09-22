<?php
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/function.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$userId = (int)$_SESSION['user_id'];
$notificationId = isset($_POST['id']) ? (int)$_POST['id'] : (int)($_GET['id'] ?? 0);

if ($notificationId > 0) {
    mysqli_query($conn, "
        UPDATE notifications
        SET is_read = 1, read_at = NOW()
        WHERE id = $notificationId AND user_id = $userId
    ");
}

if (isset($_POST['all']) || isset($_GET['all'])) {
    mysqli_query($conn, "
        UPDATE notifications
        SET is_read = 1, read_at = NOW()
        WHERE user_id = $userId AND is_read = 0
    ");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'unread' => getUnreadNotificationCount($userId)]);
    exit;
}

redirect('my_leaves.php');
?>