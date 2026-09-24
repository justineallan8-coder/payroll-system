<?php
$page_title = 'Work Hours';
require_once __DIR__ . '/includes/header.php';

$userId = $_SESSION['user_id'];
$isAdminView = isAdmin() && isset($_GET['user_id']);
$viewUserId = $isAdminView ? (int)$_GET['user_id'] : $userId;

// Get user info
$userInfo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $viewUserId"));

// Get sessions
$sessions = mysqli_query($conn, "
    SELECT * FROM work_sessions 
    WHERE user_id = $viewUserId 
    ORDER BY login_time DESC 
    LIMIT 50
");

// Summary
$summary = getTotalWorkHours($viewUserId, 'all');
$todaySummary = getTotalWorkHours($viewUserId, 'today');
$weekSummary = getTotalWorkHours($viewUserId, 'week');
$monthSummary = getTotalWorkHours($viewUserId, 'month');

// Get all users for admin dropdown
$allUsers = isAdmin() ? mysqli_query($conn, "SELECT id, full_name, username FROM users WHERE status = 'active' ORDER BY full_name") : null;
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-gray-900">Work Hours</h1>
        <p class="text-gray-500 text-sm mt-1">
            <?php if ($isAdminView): ?>
            Viewing: <strong><?php echo htmlspecialchars($userInfo['full_name']); ?></strong>
            <?php else: ?>
            Your work hours and session history
            <?php endif; ?>
        </p>
    </div>
    
    <?php if (isAdmin()): ?>
    <div class="mt-3 md:mt-0">
        <form method="GET" class="flex gap-2">
            <select name="user_id" onchange="this.form.submit()" 
                    class="px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">My Hours</option>
                <?php while ($u = mysqli_fetch_assoc($allUsers)): ?>
                <option value="<?php echo $u['id']; ?>" <?php echo $viewUserId == $u['id'] && $isAdminView ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($u['full_name']); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-indigo-600">
        <p class="text-xs text-gray-500">Today</p>
        <p class="text-xl font-black text-indigo-600"><?php echo $todaySummary['formatted']; ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-blue-600">
        <p class="text-xs text-gray-500">This Week</p>
        <p class="text-xl font-black text-blue-600"><?php echo $weekSummary['formatted']; ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-green-600">
        <p class="text-xs text-gray-500">This Month</p>
        <p class="text-xl font-black text-green-600"><?php echo $monthSummary['formatted']; ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-purple-600">
        <p class="text-xs text-gray-500">All Time</p>
        <p class="text-xl font-black text-purple-600"><?php echo $summary['formatted']; ?></p>
    </div>
</div>

<!-- Current Session -->
<?php
$currentSession = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM work_sessions WHERE user_id = $viewUserId AND status = 'active' ORDER BY login_time DESC LIMIT 1"));
if ($currentSession):
?>
<div class="bg-green-50 border border-green-300 rounded-xl p-4 mb-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
            <div>
                <p class="font-bold text-green-700">Currently Active</p>
                <p class="text-sm text-green-600">Logged in at <?php echo date('H:i', strtotime($currentSession['login_time'])); ?></p>
            </div>
        </div>
        <div class="text-right">
            <p class="text-xs text-green-600">Session Duration</p>
            <p class="text-xl font-black text-green-700">
                <?php 
                $mins = floor((time() - strtotime($currentSession['login_time'])) / 60);
                echo floor($mins / 60) . 'h ' . ($mins % 60) . 'm';
                ?>
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Sessions Table -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="p-4 border-b border-gray-200">
        <h3 class="font-bold text-gray-900">Session History (Last 50)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Login</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Logout</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (mysqli_num_rows($sessions) > 0): ?>
                    <?php while ($s = mysqli_fetch_assoc($sessions)): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm"><?php echo date('M d, Y', strtotime($s['login_time'])); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo date('H:i:s', strtotime($s['login_time'])); ?></td>
                        <td class="px-4 py-3 text-sm">
                            <?php echo $s['logout_time'] ? date('H:i:s', strtotime($s['logout_time'])) : '-'; ?>
                        </td>
                        <td class="px-4 py-3 font-semibold">
                            <?php 
                            if ($s['status'] === 'active') {
                                $mins = floor((time() - strtotime($s['login_time'])) / 60);
                                echo '<span class="text-green-600">' . floor($mins / 60) . 'h ' . ($mins % 60) . 'm (active)</span>';
                            } else {
                                $end = $s['logout_time'] ? strtotime($s['logout_time']) : strtotime($s['login_time']);
                                $mins = floor(($end - strtotime($s['login_time'])) / 60);
                                echo floor($mins / 60) . 'h ' . ($mins % 60) . 'm';
                            }
                            ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500"><?php echo $s['ip_address']; ?></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold
                                <?php echo $s['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'; ?>">
                                <?php echo ucfirst($s['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">No work sessions recorded yet</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>