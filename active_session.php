<?php
$page_title = 'Active Sessions';
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/function.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/includes/header.php';

// Cleanup stale sessions first
cleanupStaleSessions();

// Handle force logout (admin only)
if (isset($_GET['force_logout']) && isAdmin()) {
    $userId = (int)$_GET['force_logout'];
    forceLogout($userId);
    setFlash('success', 'User session terminated successfully.');
    redirect('active_sessions.php');
}

// Get active sessions
$activeSessions = getActiveSessions();

// Get all users with last login
$allUsers = getAllUsersLastLogin();

// Stats
$totalOnline = count($activeSessions);
$totalUsers = count($allUsers);
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-gray-900">🟢 Active Sessions</h1>
        <p class="text-gray-500 text-sm mt-1">Real-time view of who's currently logged in</p>
    </div>
    <div class="mt-3 md:mt-0 flex items-center space-x-3">
        <div class="flex items-center space-x-2 bg-green-50 px-4 py-2 rounded-xl border border-green-200">
            <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
            <span class="font-bold text-green-700"><?php echo $totalOnline; ?> Online</span>
        </div>
        <button onclick="location.reload()" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-xl hover:bg-gray-300 transition">
            <i class="fas fa-sync-alt"></i>
        </button>
    </div>
</div>

<!-- Active Sessions -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
    <div class="p-4 border-b border-gray-100 flex items-center space-x-2">
        <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
        <h3 class="font-bold text-gray-900">Currently Active (<?php echo $totalOnline; ?>)</h3>
    </div>
    
    <?php if ($totalOnline > 0): ?>
    <div class="divide-y divide-gray-100">
        <?php foreach ($activeSessions as $session): ?>
        <div class="p-4 hover:bg-gray-50 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center space-x-4">
                <?php if ($session['avatar'] && file_exists(__DIR__ . '/uploads/avatars/' . $session['avatar'])): ?>
                <img src="uploads/avatars/<?php echo $session['avatar']; ?>" class="w-12 h-12 rounded-full object-cover border-2 border-green-300">
                <?php else: ?>
                <div class="w-12 h-12 bg-gradient-to-r from-indigo-100 to-purple-100 rounded-full flex items-center justify-center border-2 border-green-300">
                    <span class="text-indigo-600 font-bold"><?php echo strtoupper(substr($session['full_name'], 0, 1)); ?></span>
                </div>
                <?php endif; ?>
                
                <div>
                    <div class="flex items-center space-x-2">
                        <p class="font-bold text-gray-900"><?php echo htmlspecialchars($session['full_name']); ?></p>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?php echo getRoleBadge($session['role']); ?>">
                            <?php echo ucfirst($session['role']); ?>
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?php echo $session['work_status'] === 'meeting' ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700'; ?>">
                            <i class="fas <?php echo $session['work_status'] === 'meeting' ? 'fa-users' : 'fa-circle'; ?> mr-1"></i><?php echo ucfirst($session['work_status'] ?? 'working'); ?>
                        </span>
                    </div>
                    <p class="text-sm text-gray-500">@<?php echo htmlspecialchars($session['username']); ?> • <?php echo htmlspecialchars($session['email']); ?></p>
                    <p class="text-xs text-gray-400 mt-1">
                        <i class="fas fa-map-marker-alt mr-1"></i> IP: <?php echo $session['ip_address']; ?>
                    </p>
                </div>
            </div>
            
            <div class="flex items-center space-x-4">
                <div class="text-right">
                    <p class="text-xs text-gray-500">Logged in at</p>
                    <p class="font-semibold text-gray-900"><?php echo date('H:i:s', strtotime($session['login_time'])); ?></p>
                    <p class="text-xs text-gray-400"><?php echo date('M d, Y', strtotime($session['login_time'])); ?></p>
                </div>
                
                <div class="text-right min-w-[100px]">
                    <p class="text-xs text-gray-500">Duration</p>
                    <?php 
                    $mins = $session['minutes_active'];
                    $hours = floor($mins / 60);
                    $remainder = $mins % 60;
                    ?>
                    <p class="text-xl font-black text-green-600"><?php echo $hours . 'h ' . $remainder . 'm'; ?></p>
                </div>
                
                <?php if (isAdmin() && $session['user_id'] != $_SESSION['user_id']): ?>
                <a href="?force_logout=<?php echo $session['user_id']; ?>" 
                   onclick="return confirm('Force logout <?php echo htmlspecialchars($session['full_name']); ?>?')"
                   class="bg-red-100 text-red-700 px-3 py-2 rounded-lg hover:bg-red-200 transition text-sm">
                    <i class="fas fa-sign-out-alt mr-1"></i> Logout
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="p-8 text-center">
        <i class="fas fa-user-slash text-4xl text-gray-300 mb-3"></i>
        <p class="text-gray-500">No active sessions right now</p>
    </div>
    <?php endif; ?>
</div>

<!-- All Users - Last Login Info -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="p-4 border-b border-gray-100">
        <h3 class="font-bold text-gray-900">📋 All Users — Last Login Info</h3>
        <p class="text-sm text-gray-500">Previous session history for every user</p>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Login</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Logout</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Sessions</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Hours</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($allUsers as $u): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center space-x-3">
                            <?php if ($u['avatar'] && file_exists(__DIR__ . '/uploads/avatars/' . $u['avatar'])): ?>
                            <img src="uploads/avatars/<?php echo $u['avatar']; ?>" class="w-10 h-10 rounded-full object-cover">
                            <?php else: ?>
                            <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                <span class="text-indigo-600 font-bold text-sm"><?php echo strtoupper(substr($u['full_name'], 0, 1)); ?></span>
                            </div>
                            <?php endif; ?>
                            <div>
                                <p class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($u['full_name']); ?></p>
                                <p class="text-xs text-gray-500">@<?php echo htmlspecialchars($u['username']); ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <?php if ($u['is_online']): ?>
                        <span class="px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 flex items-center space-x-1 w-fit">
                            <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                            <span>Online</span>
                        </span>
                        <?php else: ?>
                        <span class="px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">Offline</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?php if ($u['last_login']): ?>
                        <p class="font-medium"><?php echo date('M d, Y', strtotime($u['last_login'])); ?></p>
                        <p class="text-xs text-gray-500"><?php echo date('H:i:s', strtotime($u['last_login'])); ?></p>
                        <?php else: ?>
                        <span class="text-gray-400">Never</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?php if ($u['last_logout']): ?>
                        <p class="font-medium"><?php echo date('M d, Y', strtotime($u['last_logout'])); ?></p>
                        <p class="text-xs text-gray-500"><?php echo date('H:i:s', strtotime($u['last_logout'])); ?></p>
                        <?php else: ?>
                        <span class="text-gray-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm font-semibold"><?php echo $u['total_sessions']; ?></td>
                    <td class="px-4 py-3 text-sm font-bold text-indigo-600">
                        <?php 
                        $mins = $u['total_minutes'] ?? 0;
                        echo floor($mins / 60) . 'h ' . ($mins % 60) . 'm';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Auto-refresh every 30 seconds -->
<script>
setTimeout(() => location.reload(), 30000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>