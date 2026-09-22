<?php
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/function.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/activity.php';

// Redirect if logged in
if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? 'dashboard.php' : 'work_hours.php'));
    exit;
}

$error = '';
$success = '';

// Check for remember me cookie
if (isset($_COOKIE['remember_token']) && !isLoggedIn()) {
    // Auto-login logic (simplified - in production use secure token)
    // ...
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (loginUser($username, $password)) {
        setFlash('success', 'Welcome back, ' . $_SESSION['full_name'] . '!');
        header('Location: ' . (isAdmin() ? 'dashboard.php' : 'work_hours.php'));
        exit;
    }

    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Payroll System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="includes/theme.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
    </style>
</head>
<body class="flex items-center justify-center p-4 auth-page">

<div class="w-full max-w-md">
    <div class="text-center mb-8">
        <div class="w-20 h-20 bg-white rounded-2xl flex items-center justify-center mx-auto shadow-xl mb-4 auth-logo">
            <i class="fas fa-money-bill-wave text-4xl text-indigo-600"></i>
        </div>
        <h1 class="text-3xl font-black text-white auth-title">Payroll System</h1>
        <p class="text-indigo-100 mt-1 auth-subtitle">Allan Shop</p>
    </div>
    
    <div class="bg-white rounded-2xl shadow-2xl p-8 auth-card">
        <h2 class="text-2xl font-bold text-gray-900 mb-1">Welcome Back</h2>
        <p class="text-gray-500 text-sm mb-6">Please login to your account</p>
        
        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center space-x-2">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['verified'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center space-x-2">
            <i class="fas fa-check-circle"></i>
            <span>Email verified! You can now login.</span>
        </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                <div class="relative">
                    <i class="fas fa-user absolute left-3 top-3 text-gray-400"></i>
                    <input type="text" name="username" required autofocus
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           placeholder="Enter your username"
                           class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <div class="relative">
                    <i class="fas fa-lock absolute left-3 top-3 text-gray-400"></i>
                    <input type="password" name="password" id="password" required
                           placeholder="Enter your password"
                           class="w-full pl-10 pr-12 py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <button type="button" onclick="togglePassword()" 
                            class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            
            <div class="flex items-center justify-between">
                <label class="flex items-center space-x-2 text-sm text-gray-600">
                    <input type="checkbox" name="remember" class="rounded border-gray-300 text-indigo-600">
                    <span>Remember me</span>
                </label>
                <a href="forgot_password.php" class="text-sm text-indigo-600 hover:text-indigo-700">Forgot password?</a>
            </div>
            
            <button type="submit" 
                    class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold hover:bg-indigo-700 transition shadow-lg auth-submit">
                <i class="fas fa-sign-in-alt mr-2"></i> Login
            </button>
        </form>
        
        <div class="mt-6 pt-4 border-t border-gray-100 text-center">
            <p class="text-sm text-gray-600">
                Don't have an account? 
                <a href="register.php" class="text-indigo-600 hover:text-indigo-700 font-semibold">Register</a>
            </p>
        </div>
    </div>
    
    <p class="text-center text-indigo-100 text-xs mt-6">
        © <?php echo date('Y'); ?> Allan Shop Payroll System.
    </p>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    input.type = input.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
}
</script>

</body>
</html>