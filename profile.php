<?php
$page_title = 'My Profile';
require_once __DIR__ . '/includes/header.php';

$userId = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $oldEmail = mysqli_real_escape_string($conn, $_SESSION['email']);
    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    
    // Check if email exists
    $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email' AND id != $userId");
    if (mysqli_num_rows($check) > 0) {
        $error = 'Email already in use!';
    } else {
        mysqli_query($conn, "UPDATE users SET full_name = '$full_name', email = '$email' WHERE id = $userId");
        mysqli_query($conn, "UPDATE employees SET email = '$email' WHERE email = '$oldEmail'");
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email'] = $email;
        logActivity($userId, 'profile_update', 'Updated profile information');
        sendUpdateNotification($email, $full_name, 'Profile updated', 'Your profile information was updated successfully.');
        setFlash('success', 'Profile updated successfully!');
        redirect('profile.php');
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    // Get current user
    $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $userId"));
    
    if (!password_verify($current, $user['password'])) {
        $error = 'Current password is incorrect!';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match!';
    } elseif (strlen($new) < 6) {
        $error = 'Password must be at least 6 characters!';
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET password = '$hashed' WHERE id = $userId");
        logActivity($userId, 'password_change', 'Changed password');
        sendUpdateNotification($user['email'], $user['full_name'], 'Password changed', 'Your account password was changed successfully.');
        setFlash('success', 'Password changed successfully!');
        redirect('profile.php');
    }
}

// Handle avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file = $_FILES['avatar'];
        
        if (!in_array($file['type'], $allowed)) {
            $error = 'Only JPG, PNG, GIF, WEBP images allowed!';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $error = 'Image must be less than 2MB!';
        } else {
            $uploadDir = __DIR__ . '/uploads/avatars/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'user_' . $userId . '_' . time() . '.' . $ext;
            
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                // Delete old avatar
                $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT avatar FROM users WHERE id = $userId"))['avatar'];
                if ($old && file_exists($uploadDir . $old)) {
                    unlink($uploadDir . $old);
                }
                
                mysqli_query($conn, "UPDATE users SET avatar = '$filename' WHERE id = $userId");
                $_SESSION['avatar'] = $filename;
                logActivity($userId, 'avatar_upload', 'Uploaded new avatar');
                sendUpdateNotification($user['email'], $user['full_name'], 'Profile photo updated', 'Your profile photo was updated successfully.');
                setFlash('success', 'Avatar updated!');
                redirect('profile.php');
            } else {
                $error = 'Failed to upload file!';
            }
        }
    }
}

// Get user data
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $userId"));

// Get work hours
$todayHours = getTotalWorkHours($userId, 'today');
$weekHours = getTotalWorkHours($userId, 'week');
$monthHours = getTotalWorkHours($userId, 'month');
$totalHours = getTotalWorkHours($userId, 'all');
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-gray-900">My Profile</h1>
        <p class="text-gray-500 text-sm mt-1">Manage your account settings</p>
    </div>
</div>

<?php if ($error): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Profile Card -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-sm p-6 text-center">
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div class="relative inline-block">
                    <?php if ($user['avatar'] && file_exists(__DIR__ . '/uploads/avatars/' . $user['avatar'])): ?>
                    <img src="uploads/avatars/<?php echo $user['avatar']; ?>" class="w-32 h-32 rounded-full object-cover mx-auto border-4 border-indigo-100">
                    <?php else: ?>
                    <div class="w-32 h-32 bg-gradient-to-r from-indigo-100 to-purple-100 rounded-full flex items-center justify-center mx-auto border-4 border-indigo-100">
                        <i class="fas fa-user text-5xl text-indigo-600"></i>
                    </div>
                    <?php endif; ?>
                    <label for="avatar" class="absolute bottom-0 right-0 w-10 h-10 bg-indigo-600 rounded-full flex items-center justify-center text-white cursor-pointer hover:bg-indigo-700">
                        <i class="fas fa-camera"></i>
                        <input type="file" name="avatar" id="avatar" class="hidden" accept="image/*" onchange="this.form.submit()">
                    </label>
                </div>
                <input type="hidden" name="upload_avatar" value="1">
            </form>
            
            <h3 class="font-bold text-gray-900 text-lg mt-4"><?php echo htmlspecialchars($user['full_name']); ?></h3>
            <p class="text-sm text-gray-500">@<?php echo htmlspecialchars($user['username']); ?></p>
            <span class="px-3 py-1 rounded-full text-xs font-semibold mt-2 inline-block <?php echo getRoleBadge($user['role']); ?>">
                <?php echo ucfirst($user['role']); ?>
            </span>
            
            <div class="mt-6 pt-4 border-t border-gray-100">
                <div class="text-xs text-gray-500 mb-1">Member since</div>
                <div class="font-semibold text-gray-900"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></div>
            </div>
        </div>
        
        <!-- Work Hours Summary -->
        <div class="bg-white rounded-xl shadow-sm p-6 mt-6">
            <h4 class="font-bold text-gray-900 mb-4">⏱️ My Work Hours</h4>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Today</span>
                    <span class="font-bold text-indigo-600"><?php echo $todayHours['formatted']; ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">This Week</span>
                    <span class="font-bold text-blue-600"><?php echo $weekHours['formatted']; ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">This Month</span>
                    <span class="font-bold text-green-600"><?php echo $monthHours['formatted']; ?></span>
                </div>
                <div class="flex justify-between items-center border-t pt-3">
                    <span class="text-sm font-semibold text-gray-700">Total</span>
                    <span class="font-bold text-purple-600"><?php echo $totalHours['formatted']; ?></span>
                </div>
            </div>
            <a href="work_hours.php" class="block mt-4 text-center text-sm text-indigo-600 hover:text-indigo-700">
                View Details <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>
    
    <!-- Forms -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Update Profile -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="font-bold text-gray-900 mb-4">📝 Update Profile</h3>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required 
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required 
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled 
                           class="w-full px-4 py-2 rounded-lg border border-gray-200 bg-gray-50 text-gray-500">
                </div>
                <button type="submit" name="update_profile" 
                        class="bg-indigo