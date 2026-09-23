<?php
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/function.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/auth.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';
$error = '';
$success = '';

if (empty($token)) {
    $error = 'Invalid reset link.';
} else {
    $token = mysqli_real_escape_string($conn, $token);
    $result = mysqli_query($conn, "SELECT * FROM users WHERE reset_token = '$token' AND reset_expires > NOW()");
    $user = mysqli_fetch_assoc($result);
    
    if (!$user) {
        $error = 'Invalid or expired reset link.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];
    
    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET password = '$hashed', reset_token = NULL, reset_expires = NULL WHERE id = {$user['id']}");
        logActivity($user['id'], 'password_reset', 'Password reset successfully');
        sendUpdateNotification($user['email'], $user['full_name'], 'Password reset', 'Your account password was reset successfully.');
        $success = 'Password reset successful! You can now login.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - Payroll System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="includes/theme.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
    </style>
</head>
<body class="flex items-center justify-center p-4 auth-page">

<div class="w-full max-w-md">
    <div class="bg-white rounded-2xl shadow-2xl p-8">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-lock text-2xl text-indigo-600"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900">Reset Password</h2>
            <p class="text-gray-500 text-sm mt-1">Enter your new password</p>
        </div>
        
        <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm">
            <i class="fas fa-check-circle mr-2"></i> <?php echo $success; ?>
        </div>
        <a href="index.php" class="block w-full bg-indigo-600 text-white py-3 rounded-xl font-bold hover:bg-indigo-700 transition text-center">
            <i class="fas fa-sign-in-alt mr-2"></i> Go to Login
        </a>
        <?php elseif ($error && !$user): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
            <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
        </div>
        <a href="forgot_password.php" class="block w-full bg-gray-200 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-300 transition text-center">
            Try Again
        </a>
        <?php else: ?>
        
        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                <input type="password" name="password" required minlength="6"
                       class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                <input type="password" name="confirm" required minlength="6"
                       class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <button type="submit" 
                    class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold hover:bg-indigo-700 transition">
                <i class="fas fa-save mr-2"></i> Reset Password
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>