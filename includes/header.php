<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/function.php';
require_once __DIR__ . '/activity.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/tracker.php';
require_once __DIR__ . '/leave_config.php';

// =============================================
// SESSION TIMEOUT CHECK (30 minutes)
// =============================================
$timeout_duration = 1800; // 30 minutes in seconds

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    // Session expired
    endWorkSession($_SESSION['user_id']);
    session_unset();
    session_destroy();
    session_start();
    setFlash('warning', 'Your session has expired. Please login again.');
    header('Location: index.php');
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();
updateLastActivity();

// Require login
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Payroll System'; ?> - Allan Shop</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar-link { transition: all 0.2s ease; }
        .sidebar-link:hover { background: #f3f4f6; }
        .sidebar-link.active { background: #eef2ff; color: #4f46e5; }
        .badge-pulse { animation: pulse 2s infinite; }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="bg-gray-50">

<nav class="bg-white shadow-sm border-b border-gray-200 fixed top-0 left-0 right-0 z-50 h-16 no-print">
    <div class="flex items-center justify-between h-full px-4 md:px-6">
        <div class="flex items-center space-x-4">
            <button id="sidebarToggle" class="lg:hidden text-gray-600 hover:text-indigo-600">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <a href="<?php echo isAdmin() ? 'dashboard.php' : 'work_hours.php'; ?>" class="flex items-center space-x-2">
                <div class="w-8 h-8 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-white text-sm"></i>
                </div>
                <span class="font-bold text-xl text-gray-900 hidden sm:block">Payroll System</span>
            </a>
        </div>
        <div class="flex items-center space-x-4">
            <div class="hidden md:flex items-center space-x-2 bg-green-50 px-3 py-1.5 rounded-lg border border-green-200">
                <i class="fas fa-clock text-green-600"></i>
                <span class="text-sm font-semibold text-green-700" id="sessionTimer">0h 0m</span>
            </div>
            <?php if (!isAdmin()):
                $notificationEmail = mysqli_real_escape_string($conn, trim($_SESSION['email'] ?? ''));
                $notificationEmployee = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM employees WHERE email = '$notificationEmail' LIMIT 1"));
                $leaveNotifications = [];
                if ($notificationEmployee) {
                    $notificationEmployeeId = (int)$notificationEmployee['id'];
                    $notificationResult = mysqli_query($conn, "
                        SELECT lr.id, lr.status, lr.start_date, lr.end_date, lt.type_name
                        FROM leave_requests lr
                        JOIN leave_types lt ON lr.leave_type_id = lt.id
                        WHERE lr.employee_id = $notificationEmployeeId
                          AND lr.status IN ('approved', 'rejected')
                        ORDER BY lr.approved_at DESC, lr.created_at DESC
                        LIMIT 5
                    ");
                    if ($notificationResult) {
                        while ($notification = mysqli_fetch_assoc($notificationResult)) {
                            $leaveNotifications[] = $notification;
                        }
                    }
                }
                $notificationCount = count($leaveNotifications);
            ?>
            <div class="relative">
                <button id="notificationBtn" type="button" aria-label="Leave notifications"
                        class="relative text-gray-600 hover:text-indigo-600 hover:bg-gray-50 rounded-xl px-3 py-2 transition">
                    <i class="fas fa-bell text-lg"></i>
                    <?php if ($notificationCount > 0): ?>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold min-w-5 h-5 px-1 rounded-full flex items-center justify-center">
                        <?php echo $notificationCount; ?>
                    </span>
                    <?php endif; ?>
                </button>
                <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-50">
                    <div class="p-3 border-b border-gray-100 flex items-center justify-between">
                        <p class="font-semibold text-gray-900">Leave notifications</p>
                        <span class="text-xs text-gray-500"><?php echo $notificationCount; ?> update<?php echo $notificationCount === 1 ? '' : 's'; ?></span>
                    </div>
                    <?php if ($notificationCount === 0): ?>
                    <p class="p-4 text-sm text-gray-500">No leave decisions yet.</p>
                    <?php else: ?>
                        <?php foreach ($leaveNotifications as $notification): ?>
                        <a href="my_leaves.php" class="block p-3 border-b border-gray-100 hover:bg-gray-50">
                            <div class="flex items-start space-x-3">
                                <i class="fas <?php echo $notification['status'] === 'approved' ? 'fa-check-circle text-green-600' : 'fa-times-circle text-red-600'; ?> mt-0.5"></i>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">
                                        Leave <?php echo htmlspecialchars($notification['status']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($notification['type_name']); ?> request, <?php echo date('M d, Y', strtotime($notification['start_date'])); ?>
                                    </p>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <a href="my_leaves.php" class="block p-3 text-center text-sm font-semibold text-indigo-600 hover:bg-indigo-50">View My Leaves</a>
                </div>
            </div>
            <?php endif; ?>
            <div class="relative">
                <button id="userMenuBtn" class="flex items-center space-x-2 hover:bg-gray-50 rounded-xl px-3 py-2 transition">
                    <?php $avatar = $_SESSION['avatar'] ?? null; ?>
                    <?php if ($avatar && file_exists(__DIR__ . '/../uploads/avatars/' . $avatar)): ?>
                    <img src="uploads/avatars/<?php echo htmlspecialchars($avatar); ?>" class="w-8 h-8 rounded-full object-cover" alt="Profile">
                    <?php else: ?>
                    <div class="w-8 h-8 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-full flex items-center justify-center text-white text-sm font-bold">
                        <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?>
                    </div>
                    <?php endif; ?>
                    <span class="hidden md:block text-sm font-medium text-gray-700"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></span>
                    <i class="fas fa-chevron-down text-xs text-gray-400 hidden md:block"></i>
                </button>
                <div id="userDropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-50">
                    <div class="p-3 border-b border-gray-100">
                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></p>
                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></p>
                    </div>
                    <a href="profile.php" class="block px-4 py-3 text-sm text-gray-700 hover:bg-gray-50">My Profile</a>
                    <a href="logout.php" class="block px-4 py-3 text-sm text-red-600 hover:bg-red-50">Logout</a>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Sidebar -->
<aside id="sidebar" class="fixed left-0 top-16 bottom-0 w-64 bg-white border-r border-gray-200 overflow-y-auto z-40 transition-transform duration-300 -translate-x-full lg:translate-x-0 no-print">
    <div class="p-4">
        <nav class="space-y-1">
            <a href="attendance.php" class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-600 <?php echo basename($_SERVER['PHP_SELF']) === 'attendance.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt w-5"></i>
                <span>Attendance</span>
            </a>
            <?php if (!isAdmin()): ?>
            <a href="apply_leave.php" class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-600 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['apply_leave.php', 'my_leaves.php']) ? 'active' : ''; ?>">
                <i class="fas fa-umbrella-beach w-5"></i>
                <span>My Leaves</span>
            </a>
            <?php endif; ?>
            <?php if (isAdmin()): ?>
            <a href="active_session.php" class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-600 <?php echo basename($_SERVER['PHP_SELF']) === 'active_session.php' ? 'active' : ''; ?>">
                <i class="fas fa-circle text-green-500 text-xs w-5"></i>
                <span>Active Sessions</span>
                <?php
                $onlineCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM work_sessions WHERE status = 'active'"))['c'];
                if ($onlineCount > 0):
                ?>
                <span class="ml-auto bg-green-500 text-white text-xs px-2 py-0.5 rounded-full"><?php echo $onlineCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="leave_management.php" class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-600 <?php echo basename($_SERVER['PHP_SELF']) === 'leave_management.php' ? 'active' : ''; ?>">
                <i class="fas fa-tasks w-5"></i>
                <span>Leave Management</span>
                <?php $pending = getPendingLeaveCount(); if ($pending > 0): ?>
                <span class="ml-auto bg-yellow-500 text-white text-xs px-2 py-0.5 rounded-full badge-pulse"><?php echo $pending; ?></span>
                <?php endif; ?>
            </a>
            <a href="analytics.php" class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-600 <?php echo basename($_SERVER['PHP_SELF']) === 'analytics.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line w-5"></i>
                <span>Analytics</span>
            </a>
            <a href="dashboard.php" class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-600 <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-th-large w-5"></i>
                <span>Dashboard</span>
            </a>
            <a href="employees.php" class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-600 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['employees.php', 'add employee.php', 'edit employee.php']) ? 'active' : ''; ?>">
                <i class="fas fa-users w-5"></i>
                <span>Employees</span>
            </a>
            <?php if (isAdmin()): ?>
            <a href="payroll.php" class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-600 <?php echo basename($_SERVER['PHP_SELF']) === 'payroll.php' ? 'active' : ''; ?>">
                <i class="fas fa-money-check-alt w-5"></i>
                <span>Process Payroll</span>
            </a>
            <?php endif; ?>
            <a href="report.php" class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-600 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['report.php', 'reports.php']) ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar w-5"></i>
                <span>Reports</span>
            </a>
            <?php endif; ?>
            <a href="work_hours.php" class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-600 <?php echo basename($_SERVER['PHP_SELF']) === 'work_hours.php' ? 'active' : ''; ?>">
                <i class="fas fa-clock w-5"></i>
                <span>Work Hours</span>
            </a>
            <a href="activity_log.php" class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-600 <?php echo basename($_SERVER['PHP_SELF']) === 'activity_log.php' ? 'active' : ''; ?>">
                <i class="fas fa-history w-5"></i>
                <span>My Activity</span>
            </a>
            <a href="holidays.php" class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-600 <?php echo basename($_SERVER['PHP_SELF']) === 'holidays.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt w-5"></i>
                <span>Holiday Calendar</span>
            </a>
            
            <?php if (isAdmin()): ?>
            <hr class="my-2 border-gray-200">
            <a href="users.php" class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-600 <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users-cog w-5"></i>
                <span>Manage Users</span>
            </a>
            <?php endif; ?>
        </nav>
        
        <hr class="my-4 border-gray-200">
        
        <a href="logout.php" class="sidebar-link flex items-center space-x-3 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50">
            <i class="fas fa-sign-out-alt w-5"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<!-- Main Content -->
<main class="lg:ml-64 mt-16 p-4 md:p-6 min-h-screen">

<?php
$flash = getFlash();
if ($flash):
?>
<div class="mb-4 px-4 py-3 rounded-lg flex items-center space-x-2
    <?php echo $flash['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 
              ($flash['type'] === 'error' ? 'bg-red-100 border border-red-400 text-red-700' : 
              'bg-yellow-100 border border-yellow-400 text-yellow-700'); ?>">
    <i class="fas <?php echo $flash['type'] === 'success' ? 'fa-check-circle' : 
                           ($flash['type'] === 'error' ? 'fa-exclamation-circle' : 'fa-exclamation-triangle'); ?>"></i>
    <span><?php echo $flash['message']; ?></span>
</div>
<?php endif; ?>