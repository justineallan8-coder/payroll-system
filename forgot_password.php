<?php
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/function.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    
    $result = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email' AND status = 'active'");
    $user = mysqli_fetch_assoc($result);
    
    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        mysqli_query($conn, "UPDATE users SET reset_token = '$token', reset_expires = '$expires' WHERE id = {$user['id']}");
        
        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=$token";
        
        // In production, send this via email
        $message = "Password reset link: <a href='$resetLink' class='text-indigo-600 underline'>Click here to reset</a><br>
                    <small class='text-gray-500'>This link expires in 1 hour.</small>";
        
        logActivity($user['id'], 'forgot_password', 'Requested password reset');
    } else {
        $error = 'No account found with that email.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - Payroll System</title>
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
                <i class="fas fa-key text-2xl text-indigo-600"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900">Forgot Password?</h2>
            <p class="text-gray-500 text-sm mt-1">Enter your email to reset your password</p>
        </div>
        
        <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm">
            <i class="fas fa-check-circle mr-2"></i> <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
            <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                <div class="relative">
                    <i class="fas fa-envelope absolute left-3 top-3 text-gray-400"></i>
                    <input type="email" name="email" required 
                           placeholder="your@email.com"
                           class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
            <button type="submit" 
                    class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold hover:bg-indigo-700 transition">
                <i class="fas fa-paper-plane mr-2"></i> Send Reset Link
            </button>
        </form>
        
        <div class="mt-6 text-center">
            <a href="index.php" class="text-sm text-indigo-600 hover:text-indigo-700">
                <i class="fas fa-arrow-left mr-1"></i> Back to Login
            </a>
        </div>
    </div>
</div>

</body>
</html>