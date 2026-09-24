<?php
$page_title = 'Manage Users';
require_once __DIR__ . '/includes/header.php';

// Only admins can access
// Handle admin verification
if (isset($_GET['verify']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT full_name, email FROM users WHERE id = $id"));

    if ($user) {
        $verified = mysqli_query($conn, "UPDATE users SET email_verified = 1, verification_token = NULL, status = 'active' WHERE id = $id");
        if ($verified) {
            logActivity($_SESSION['user_id'], 'verify_user', "Verified user: {$user['full_name']}");
            sendUpdateNotification($user['email'], $user['full_name'], 'Account verified', 'Your account has been verified and activated.');
            setFlash('success', "User '{$user['full_name']}' has been verified and activated.");
        } else {
            setFlash('error', 'Unable to verify this user.');
        }
    } else {
        setFlash('error', 'User not found.');
    }

    redirect('users.php');
}

if (!isAdmin()) {
    setFlash('error', 'Access denied. Admin privileges required.');
    redirect('dashboard.php');
}

$message = '';
$error = '';

// Handle add user
// Handle add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    // Check if username/email exists
    $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username' OR email = '$email'");
    
    if (mysqli_num_rows($check) > 0) {
        $error = 'Username or email already exists!';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters!';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        // ✅ AUTO-VERIFY: email_verified = 1 by default
        $sql = "INSERT INTO users (username, password, full_name, email, role, email_verified, status) 
                VALUES ('$username', '$hashed', '$full_name', '$email', '$role', 1, 'active')";
        
        if (mysqli_query($conn, $sql)) {
            $newId = mysqli_insert_id($conn);
            logActivity($_SESSION['user_id'], 'add_user', "Created user: $full_name ($role)");
            sendUpdateNotification($email, $full_name, 'Account created', 'Your payroll system account has been created and is ready to use.');
            setFlash('success', "User '$full_name' created successfully! They can now login.");
            redirect('users.php');
        } else {
            $error = 'Error: ' . mysqli_error($conn);
        }
    }
}
// Handle status toggle
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($id !== $_SESSION['user_id']) { // Can't deactivate self
        $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT full_name, email, status FROM users WHERE id = $id"));
        if ($user && mysqli_query($conn, "UPDATE users SET status = IF(status='active', 'inactive', 'active') WHERE id = $id")) {
            $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
            sendUpdateNotification($user['email'], $user['full_name'], 'Account status updated', "Your account status is now $newStatus.");
            setFlash('success', 'User status updated.');
        } else {
            setFlash('error', 'User not found or status could not be updated.');
        }
    } else {
        setFlash('error', 'You cannot deactivate your own account!');
    }
    redirect('users.php');
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id !== $_SESSION['user_id']) {
        $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT full_name, email FROM users WHERE id = $id"));
        if ($user && mysqli_query($conn, "DELETE FROM users WHERE id = $id")) {
            sendUpdateNotification($user['email'], $user['full_name'], 'Account deleted', 'Your payroll system account has been deleted by an administrator.');
            setFlash('success', 'User deleted.');
        } else {
            setFlash('error', 'User not found or could not be deleted.');
        }
    } else {
        setFlash('error', 'You cannot delete your own account!');
    }
    redirect('users.php');
}

// Get all users
$users = mysqli_query($conn, "SELECT * FROM users ORDER BY created_at DESC");
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-gray-900">Manage Users</h1>
        <p class="text-gray-500 text-sm mt-1">Add or manage admin accounts</p>
    </div>
    <button onclick="document.getElementById('addUserModal').classList.remove('hidden')" 
            class="mt-3 md:mt-0 bg-indigo-600 text-white px-4 py-2 rounded-xl hover:bg-indigo-700 transition flex items-center space-x-2">
        <i class="fas fa-user-plus"></i>
        <span>Add User</span>
    </button>
</div>

<?php if ($error): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
    <?php echo $error; ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Verification</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Login</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php while ($u = mysqli_fetch_assoc($users)): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                <span class="text-indigo-600 font-bold"><?php echo strtoupper(substr($u['full_name'], 0, 1)); ?></span>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($u['full_name']); ?></p>
                                <p class="text-xs text-gray-500">@<?php echo htmlspecialchars($u['username']); ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm"><?php echo htmlspecialchars($u['email']); ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo getRoleBadge($u['role']); ?>">
                            <?php echo ucfirst($u['role']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded-full text-xs font-semibold
                            <?php echo $u['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'; ?>">
                            <?php echo ucfirst($u['status']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <?php if ((int)$u['email_verified'] === 1): ?>
                        <span class="px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                            <i class="fas fa-check mr-1"></i>Verified
                        </span>
                        <?php else: ?>
                        <span class="px-2 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                            Pending
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        <?php echo $u['last_login'] ? date('M d, Y H:i', strtotime($u['last_login'])) : 'Never'; ?>
                    </td>
                    <td class="px-6 py-4">
                        <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                        <div class="flex space-x-2">
                            <?php if ((int)$u['email_verified'] !== 1): ?>
                            <a href="?verify=1&id=<?php echo $u['id']; ?>"
                               class="text-green-600 hover:text-green-700" title="Verify User"
                               onclick="return confirm('Verify this user and activate the account?')">
                                <i class="fas fa-user-check"></i>
                            </a>
                            <?php endif; ?>
                            <a href="?toggle=1&id=<?php echo $u['id']; ?>" 
                               class="text-yellow-600 hover:text-yellow-700" title="Toggle Status">
                                <i class="fas <?php echo $u['status'] === 'active' ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                            </a>
                            <a href="?delete=<?php echo $u['id']; ?>" 
                               onclick="return confirm('Delete this user?')"
                               class="text-red-600 hover:text-red-700" title="Delete">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                        <?php else: ?>
                        <span class="text-xs text-gray-400 italic">You</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-900">Add New User</h3>
            <button onclick="document.getElementById('addUserModal').classList.add('hidden')" 
                    class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                <input type="text" name="username" required 
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                <input type="text" name="full_name" required 
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                <input type="email" name="email" required 
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password * (min 6 chars)</label>
                <input type="password" name="password" required minlength="6"
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                <select name="role" required 
                        class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="admin">Admin (Full Access)</option>
                    <option value="manager">Manager (Can manage payroll)</option>
                    <option value="viewer">Viewer (Read only)</option>
                </select>
            </div>
            <button type="submit" name="add_user" 
                    class="w-full bg-indigo-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-indigo-700 transition">
                <i class="fas fa-user-plus mr-2"></i> Create User
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>