<?php
$page_title = 'Activity Log';
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/function.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

// Admins can filter all users; other users are limited to their own activity.
$isAdminView = isAdmin() && isset($_GET['user_id']);
$viewUserId = $isAdminView ? (int)$_GET['user_id'] : null;

$where = $isAdminView ? "WHERE a.user_id = $viewUserId" : (isAdmin() ? '' : "WHERE a.user_id = " . (int)$_SESSION['user_id']);

// Filter by action
$actionFilter = isset($_GET['action']) ? $_GET['action'] : '';
if ($actionFilter) {
    $where .= " AND a.action = '$actionFilter'";
}

$logs = mysqli_query($conn, "
    SELECT a.*, u.full_name, u.username 
    FROM activity_log a 
    LEFT JOIN users u ON a.user_id = u.id 
    $where 
    ORDER BY a.created_at DESC 
    LIMIT 200
");

// Get distinct actions
$actions = mysqli_query($conn, "SELECT DISTINCT action FROM activity_log ORDER BY action");

// Get all users for admin
$allUsers = isAdmin() ? mysqli_query($conn, "SELECT id, full_name FROM users ORDER BY full_name") : null;
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-gray-900">Activity Log</h1>
        <p class="text-gray-500 text-sm mt-1">Track user actions in the system</p>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm p-4 mb-6">
    <form method="GET" class="flex flex-col md:flex-row gap-3">
        <?php if (isAdmin()): ?>
        <select name="user_id" class="flex-1 px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All Users</option>
            <?php while ($u = mysqli_fetch_assoc($allUsers)): ?>
            <option value="<?php echo $u['id']; ?>" <?php echo $viewUserId == $u['id'] && $isAdminView ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($u['full_name']); ?>
            </option>
            <?php endwhile; ?>
        </select>
        <?php endif; ?>
        
        <select name="action" class="flex-1 px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All Actions</option>
            <?php while ($a = mysqli_fetch_assoc($actions)): ?>
            <option value="<?php echo $a['action']; ?>" <?php echo $a['action'] === $actionFilter ? 'selected' : ''; ?>>
                <?php echo ucfirst(str_replace('_', ' ', $a['action'])); ?>
            </option>
            <?php endwhile; ?>
        </select>
        
        <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition">
            <i class="fas fa-filter mr-2"></i> Filter
        </button>
    </form>
</div>

<!-- Logs Table -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (mysqli_num_rows($logs) > 0): ?>
                    <?php while ($log = mysqli_fetch_assoc($logs)): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm whitespace-nowrap">
                            <?php echo date('M d, H:i:s', strtotime($log['created_at'])); ?>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($log['full_name'] ?? 'Unknown'); ?></p>
                            <p class="text-xs text-gray-500">@<?php echo htmlspecialchars($log['username'] ?? ''); ?></p>
                        </td>
                        <td class="px-4 py-3">
                            <?php
                            $actionColors = [
                                'login' => 'bg-green-100 text-green-700',
                                'logout' => 'bg-gray-100 text-gray-700',
                                'profile_update' => 'bg-blue-100 text-blue-700',
                                'password_change' => 'bg-red-100 text-red-700',
                                'avatar_upload' => 'bg-purple-100 text-purple-700',
                                'add_employee' => 'bg-indigo-100 text-indigo-700',
                                'edit_employee' => 'bg-yellow-100 text-yellow-700',
                                'delete_employee' => 'bg-red-100 text-red-700',
                                'process_payroll' => 'bg-green-100 text-green-700',
                            ];
                            $color = $actionColors[$log['action']] ?? 'bg-gray-100 text-gray-700';
                            ?>
                            <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $color; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?php echo htmlspecialchars($log['description']); ?></td>
                        <td class="px-4 py-3 text-xs text-gray-500"><?php echo $log['ip_address']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">No activity records found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>