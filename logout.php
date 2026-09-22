<?php
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/function.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tracker.php';

$timeout = isset($_GET['timeout']) ? true : false;
$status = $timeout ? 'timeout' : 'completed';

if (isset($_SESSION['user_id'])) {
    endWorkSession($_SESSION['user_id']);
}

logoutUser();

session_start();
if ($timeout) {
    setFlash('warning', 'You have been logged out due to inactivity.');
} else {
    setFlash('success', 'You have been logged out successfully.');
}

header('Location: index.php');
exit;
?>