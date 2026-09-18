<?php
$page_title = 'Attendance & Work Hours';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/work_config.php';

// Get selected month/year
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$viewUserId = (isAdmin() && isset($_GET['user_id'])) ? (int)$_GET['user_id'] : $_SESSION['user_id'];

$userInfo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $viewUserId"));

// Get working days in month
$days = getWorkingDaysInMonth($year, $month);

// Get sessions for this month
$sessions = mysqli_query($conn, "
    SELECT * FROM work_sessions 
    WHERE user_id = $viewUserId 
      AND YEAR(work_date) = $year 
      AND MONTH(work_date) = $month
      AND status IN ('completed', 'timeout')
    ORDER BY work_date
");

$sessionsByDate = [];
while ($s = mysqli_fetch_assoc($sessions)) {
    $sessionsByDate[$s['work_date']] = $s;
}

// Monthly summary
$summary = getMonthlySummary($viewUserId, $year, $month);
$totalRegular = $summary['total_regular'] ?? 0;
$totalOvertime = $summary['total_overtime'] ?? 0;
$totalLate = $summary['total_late'] ?? 0;
$totalEarly = $summary['total_early'] ?? 0;

// Expected hours for this month (working days * 8)
$workingDaysCount = 0;
foreach ($days as $d) {
    if ($d['is_working']) $workingDaysCount++;
}
$expectedHours = $workingDaysCount * STANDARD_HOURS;

// Admin user list
$allUsers = isAdmin() ? mysqli_query($conn, "SELECT id, full_name FROM users WHERE status = 'active' ORDER BY full_name") : null;
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-gray-900">📅 Attendance & Work Hours</h1>
        <p class="text-gray-500 text-sm mt-1">
            <?php if (isAdmin() && $viewUserId !== $_SESSION['user_id']): ?>
            Viewing: <strong><?php echo htmlspecialchars($userInfo['full_name']); ?></strong>
            <?php else: ?>
            Your work hours and attendance
            <?php endif; ?>
        </p>
    </div>
    
    <?php if (isAdmin()): ?>
    <form method="GET" class="mt-3 md:mt-0">
        <input type="hidden" name="month" value="<?php echo $month; ?>">
        <input type="hidden" name="year" value="<?php echo $year; ?>">
        <select name="user_id" onchange="this.form.submit()" 
                class="px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="<?php echo $_SESSION['user_id']; ?>">My Attendance</option>
            <?php while ($u = mysqli_fetch_assoc($allUsers)): ?>
            <option value="<?php echo $u['id']; ?>" <?php echo $viewUserId == $u['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($u['full_name']); ?>
            </option>
            <?php endwhile; ?>
        </select>
    </form>
    <?php endif; ?>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-600">
        <p class="text-xs text-gray-500">Regular Hours</p>
        <p class="text-2xl font-black text-green-600"><?php echo $totalRegular; ?>h</p>
        <p class="text-xs text-gray-400">Expected: <?php echo $expectedHours; ?>h</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-orange-600">
        <p class="text-xs text-gray-500">Overtime</p>
        <p class="text-2xl font-black text-orange-600"><?php echo $totalOvertime; ?>h</p>
        <p class="text-xs text-gray-400">1.5x rate</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-600">
        <p class="text-xs text-gray-500">Late Arrivals</p>
        <p class="text-2xl font-black text-red-600"><?php echo floor($totalLate / 60); ?>h <?php echo $totalLate % 60; ?>m</p>
        <p class="text-xs text-gray-400"><?php echo $totalLate; ?> minutes total</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-purple-600">
        <p class="text-xs text-gray-500">Working Days</p>
        <p class="text-2xl font-black text-purple-600"><?php echo $workingDaysCount; ?></p>
        <p class="text-xs text-gray-400">Holidays excluded</p>
    </div>
</div>

<!-- Month Navigation -->
<div class="bg-white rounded-xl shadow-sm p-4 mb-6 flex flex-wrap items-center justify-between gap-3">
    <a href="?month=<?php echo $month == 1 ? 12 : $month - 1; ?>&year=<?php echo $month == 1 ? $year - 1 : $year; ?>&user_id=<?php echo $viewUserId; ?>" 
       class="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
        <i class="fas fa-chevron-left"></i> Previous
    </a>
    
    <form method="GET" class="flex gap-2 items-center">
        <input type="hidden" name="user_id" value="<?php echo $viewUserId; ?>">
        <select name="month" class="px-3 py-2 rounded-lg border border-gray-300">
            <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
            </option>
            <?php endfor; ?>
        </select>
        <select name="year" class="px-3 py-2 rounded-lg border border-gray-300">
            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
            <i class="fas fa-search"></i>
        </button>
    </form>
    
    <a href="?month=<?php echo $month == 12 ? 1 : $month + 1; ?>&year=<?php echo $month == 12 ? $year + 1 : $year; ?>&user_id=<?php echo $viewUserId; ?>" 
       class="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
        Next <i class="fas fa-chevron-right"></i>
    </a>
</div>

<!-- Calendar -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
    <div class="p-4 bg-indigo-600 text-white">
        <h2 class="font-bold text-lg"><?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></h2>
    </div>
    
    <div class="p-4">
        <!-- Day headers -->
        <div class="grid grid-cols-7 gap-2 mb-2">
            <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName): ?>
            <div class="text-center text-xs font-bold text-gray-500 py-2"><?php echo $dayName; ?></div>
            <?php endforeach; ?>
        </div>
        
        <!-- Calendar days -->
        <div class="grid grid-cols-7 gap-2">
            <?php
            $firstDayOfMonth = strtotime(sprintf('%04d-%02d-01', $year, $month));
            $startDay = date('w', $firstDayOfMonth);
            
            // Empty cells for days before month starts
            for ($i = 0; $i < $startDay; $i++) {
                echo '<div class="aspect-square"></div>';
            }
            
            // Days of month
            foreach ($days as $day):
                $session = $sessionsByDate[$day['date']] ?? null;
                $today = ($day['date'] === date('Y-m-d'));
                
                // Determine cell styling
                if ($day['is_holiday']) {
                    $cellClass = 'bg-red-50 border-red-200';
                    $textClass = 'text-red-700';
                } elseif ($day['is_weekend']) {
                    $cellClass = 'bg-gray-100 border-gray-200';
                    $textClass = 'text-gray-400';
                } elseif ($session) {
                    if ($session['overtime_hours'] > 0) {
                        $cellClass = 'bg-orange-50 border-orange-300';
                        $textClass = 'text-orange-700';
                    } elseif ($session['late_minutes'] > 15) {
                        $cellClass = 'bg-yellow-50 border-yellow-300';
                        $textClass = 'text-yellow-700';
                    } else {
                        $cellClass = 'bg-green-50 border-green-300';
                        $textClass = 'text-green-700';
                    }
                } else {
                    $cellClass = 'bg-white border-gray-200';
                    $textClass = 'text-gray-700';
                }
                
                if ($today) {
                    $cellClass .= ' ring-2 ring-indigo-500';
                }
            ?>
            <div class="aspect-square border-2 rounded-lg p-2 <?php echo $cellClass; ?> relative hover:shadow-md transition">
                <div class="flex flex-col h-full">
                    <div class="flex justify-between items-start">
                        <span class="font-bold <?php echo $textClass; ?>"><?php echo $day['day']; ?></span>
                        <?php if ($day['is_holiday']): ?>
                        <i class="fas fa-umbrella-beach text-red-500 text-xs" title="<?php echo $day['holiday_name']; ?>"></i>
                        <?php elseif ($session && $session['overtime_hours'] > 0): ?>
                        <i class="fas fa-fire text-orange-500 text-xs" title="Overtime"></i>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($session): ?>
                    <div class="mt-auto text-xs">
                        <div class="font-semibold text-gray-900"><?php echo number_format($session['regular_hours'], 1); ?>h</div>
                        <?php if ($session['overtime_hours'] > 0): ?>
                        <div class="text-orange-600 font-bold">+<?php echo number_format($session['overtime_hours'], 1); ?>h OT</div>
                        <?php endif; ?>
                    </div>
                    <?php elseif ($day['is_holiday']): ?>
                    <div class="mt-auto text-xs text-red-600 font-semibold truncate" title="<?php echo $day['holiday_name']; ?>">
                        <?php echo $day['holiday_name']; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Legend -->
    <div class="px-4 pb-4 flex flex-wrap gap-4 text-xs">
        <div class="flex items-center space-x-2">
            <div class="w-4 h-4 bg-green-50 border-2 border-green-300 rounded"></div>
            <span>Present</span>
        </div>
        <div class="flex items-center space-x-2">
            <div class="w-4 h-4 bg-yellow-50 border-2 border-yellow-300 rounded"></div>
            <span>Late Arrival</span>
        </div>
        <div class="flex items-center space-x-2">
            <div class="w-4 h-4 bg-orange-50 border-2 border-orange-300 rounded"></div>
            <span>Overtime</span>
        </div>
        <div class="flex items-center space-x-2">
            <div class="w-4 h-4 bg-red-50 border-2 border-red-200 rounded"></div>
            <span>Holiday</span>
        </div>
        <div class="flex items-center space-x-2">
            <div class="w-4 h-4 bg-gray-100 border-2 border-gray-200 rounded"></div>
            <span>Weekend</span>
        </div>
    </div>
</div>

<!-- Daily Log Table -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="p-4 border-b border-gray-200">
        <h3 class="font-bold text-gray-900">📋 Daily Attendance Log</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Day</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Login</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Logout</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Regular</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Overtime</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Late</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($days as $day): 
                    if (!$day['is_working']) continue;
                    $session = $sessionsByDate[$day['date']] ?? null;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium"><?php echo $day['date']; ?></td>
                    <td class="px-4 py-3 text-sm"><?php echo $day['day_name']; ?></td>
                    <?php if ($session): ?>
                    <td class="px-4 py-3 text-sm"><?php echo date('H:i', strtotime($session['login_time'])); ?></td>
                    <td class="px-4 py-3 text-sm">
                        <?php echo $session['logout_time'] ? date('H:i', strtotime($session['logout_time'])) : '<span class="text-green-600 font-bold">Active</span>'; ?>
                    </td>
                    <td class="px-4 py-3 font-bold text-green-600"><?php echo number_format($session['regular_hours'], 2); ?>h</td>
                    <td class="px-4 py-3 font-bold text-orange-600">
                        <?php echo $session['overtime_hours'] > 0 ? '+' . number_format($session['overtime_hours'], 2) . 'h' : '—'; ?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?php if ($session['late_minutes'] > 0): ?>
                        <span class="text-red-600 font-bold"><?php echo $session['late_minutes']; ?>m</span>
                        <?php else: ?>
                        <span class="text-green-600">On time</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <?php if ($session['overtime_hours'] > 0): ?>
                        <span class="px-2 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-700">Overtime</span>
                        <?php elseif ($session['late_minutes'] > 15): ?>
                        <span class="px-2 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">Late</span>
                        <?php else: ?>
                        <span class="px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">Present</span>
                        <?php endif; ?>
                    </td>
                    <?php else: ?>
                    <td colspan="6" class="px-4 py-3 text-sm text-gray-400 italic">No attendance record</td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Holidays List -->
<div class="bg-white rounded-xl shadow-sm mt-6 p-6">
    <h3 class="font-bold text-gray-900 mb-4">🎉 Holidays in <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></h3>
    <?php
    $monthHolidays = mysqli_query($conn, "SELECT * FROM holidays 
        WHERE MONTH(holiday_date) = $month AND YEAR(holiday_date) = $year 
        ORDER BY holiday_date");
    
    if (mysqli_num_rows($monthHolidays) > 0):
    ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        <?php while ($h = mysqli_fetch_assoc($monthHolidays)): ?>
        <div class="flex items-center space-x-3 p-3 bg-red-50 rounded-lg border border-red-200">
            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-umbrella-beach text-red-600"></i>
            </div>
            <div>
                <p class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($h['holiday_name']); ?></p>
                <p class="text-xs text-gray-500"><?php echo date('D, M d, Y', strtotime($h['holiday_date'])); ?></p>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php else: ?>
    <p class="text-gray-500 text-sm">No holidays this month.</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>