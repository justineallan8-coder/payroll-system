<?php
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/function.php';
require_once __DIR__ . '/includes/mailer.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';
$message = '';
$type = 'error';

if (empty($token)) {
    $message = 'Invalid verification link.';
} else {
    $token = mysqli_real_escape_string($conn, $token);
    $result = mysqli_query($conn, "SELECT * FROM users WHERE verification_token = '$token'");
    $user = mysqli_fetch_assoc($result);
    
    if ($user) {
        mysqli_query($conn, "UPDATE users SET email_verified = 1, verification_token = NULL WHERE id = {$user['id']}");
        $message = 'Email verified successfully! You can now login.';
        $type = 'success';
        logActivity($user['id'], 'email_verified', 'Email verified');
        sendUpdateNotification($user['email'], $user['full_name'], 'Email verified', 'Your email address was verified successfully.');
    } else {
        $message = 'Invalid or already used verification link.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Verification - Payroll System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="includes/theme.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
    </style>
</head>
<body class="flex items-center justify-center p-4 auth-page">

<div class="w-full max-w-md">
    <div class="bg-white rounded-2xl shadow-2xl p-8 text-center">
        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4
            <?php echo $type === 'success' ? 'bg-green-100' : 'bg-red-100'; ?>">
            <i class="fas <?php echo $type === 'success' ? 'fa-check-circle text-green-600' : 'fa-times-circle text-red-600'; ?> text-4xl"></i>
        </div>
        
        <h2 class="text-2xl font-bold text-gray-900 mb-2">
            <?php echo $type === 'success' ? 'Email Verified!' : 'Verification Failed'; ?>
        </h2>
        <p class="text-gray-500 mb-6"><?php echo $message; ?></p>
        
        <a href="index.php" class="block w-full bg-indigo-600 text-white py-3 rounded-xl font-bold hover:bg-indigo-700 transition">
            <i class="fas fa-sign-in-alt mr-2"></i> Go to Login
        </a>
    </div>
</div>

</body>
</html>