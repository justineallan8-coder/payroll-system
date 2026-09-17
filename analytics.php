<?php
$page_title = 'Analytics & Charts';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/leave_config.php';

if (!isAdmin()) {
    setFlash('error', 'Admin access required');
    redirect('dashboard.php');
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Get data
$attendanceStats = getAttendanceStats($year);
$deptAttendance = getDepartmentAttendance($year);
$leaveDistribution = getLeaveDistribution($year);
$monthlyTrend = getMonthlyAttendanceTrend($year);
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-gray-900">📊 Analytics & Charts</h1>
        <p class="text-gray-500 text-sm mt-1">Attendance and leave analytics for <?php echo $year; ?></p>
    </div>
    <form method="GET" class="mt-3 md:mt-0">
        <select name="year" onchange="this.form.submit()" 
                class="px-4 py-2 rounded-lg border border-gray-300">
            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl shadow-sm p-4">
        <p class="text-xs opacity-90">Total Working Hours</p>
        <p class="text-2xl font-black"><?php echo number_format($attendanceStats['total_regular'] ?? 0, 0); ?>h</p>
    </div>
    <div class="bg-gradient-to-r from-orange-500 to-orange-600 text-white rounded-xl shadow-sm p-4">
        <p class="text-xs opacity-90">Total Overtime</p>
        <p class="text-2xl font-black"><?php echo number_format($attendanceStats['total_overtime'] ?? 0, 0); ?>h</p>
    </div>
    <div class="bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl shadow-sm p-4">
        <p class="text-xs opacity-90">Late Arrivals</p>
        <p class="text-2xl font-black"><?php echo floor(($attendanceStats['total_late'] ?? 0) / 60); ?>h <?php echo ($attendanceStats['total_late'] ?? 0) % 60; ?>m</p>
    </div>
    <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-xl shadow-sm p-4">
        <p class="text-xs opacity-90">Days Worked</p>
        <p class="text-2xl font-black"><?php echo $attendanceStats['days_present'] ?? 0; ?></p>
    </div>
</div>

<!-- Charts Row 1 -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Monthly Attendance Trend -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="font-bold text-gray-900 mb-4">📈 Monthly Attendance Trend</h3>
        <div class="h-72">
            <canvas id="monthlyTrendChart"></canvas>
        </div>
    </div>
    
    <!-- Leave Distribution -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="font-bold text-gray-900 mb-4">🥧 Leave Distribution</h3>
        <div class="h-72">
            <canvas id="leaveDistributionChart"></canvas>
        </div>
    </div>
</div>

<!-- Charts Row 2 -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Department Attendance -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="font-bold text-gray-900 mb-4">🏢 Department Hours Comparison</h3>
        <div class="h-72">
            <canvas id="deptChart"></canvas>
        </div>
    </div>
    
    <!-- Overtime vs Regular -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="font-bold text-gray-900 mb-4">⏰ Regular vs Overtime (Monthly)</h3>
        <div class="h-72">
            <canvas id="overtimeChart"></canvas>
        </div>
    </div>
</div>

<!-- Department Details Table -->
<div class="bg-white rounded-xl shadow-sm p-6">
    <h3 class="font-bold text-gray-900 mb-4">📋 Department Details</h3>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employees</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Hours</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Overtime</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Late</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($deptAttendance as $d): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-semibold"><?php echo htmlspecialchars($d['department']); ?></td>
                    <td class="px-4 py-3"><?php echo $d['employee_count']; ?></td>
                    <td class="px-4 py-3 font-bold text-green-600"><?php echo number_format($d['total_hours'] ?? 0, 0); ?>h</td>
                    <td class="px-4 py-3 font-bold text-orange-600"><?php echo number_format($d['total_overtime'] ?? 0, 0); ?>h</td>
                    <td class="px-4 py-3 text-red-600"><?php echo floor(($d['total_late'] ?? 0) / 60); ?>h <?php echo ($d['total_late'] ?? 0) % 60; ?>m</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Monthly Trend Chart
const monthlyData = <?php echo json_encode($monthlyTrend); ?>;
new Chart(document.getElementById('monthlyTrendChart'), {
    type: 'line',
    data: {
        labels: monthlyData.map(d => d.month_name),
        datasets: [
            {
                label: 'Regular Hours',
                data: monthlyData.map(d => d.total_regular),
                borderColor: 'rgb(16, 185, 129)',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                fill: true,
                tension: 0.4,
                borderWidth: 3
            },
            {
                label: 'Overtime Hours',
                data: monthlyData.map(d => d.total_overtime),
                borderColor: 'rgb(249, 115, 22)',
                backgroundColor: 'rgba(249, 115, 22, 0.1)',
                fill: true,
                tension: 0.4,
                borderWidth: 3
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => v + 'h' } } }
    }
});

// Leave Distribution Chart
const leaveData = <?php echo json_encode($leaveDistribution); ?>;
new Chart(document.getElementById('leaveDistributionChart'), {
    type: 'doughnut',
    data: {
        labels: leaveData.map(d => d.type_name),
        datasets: [{
            data: leaveData.map(d => d.total_days || 0),
            backgroundColor: leaveData.map(d => d.color),
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'right' },
            tooltip: {
                callbacks: {
                    label: ctx => ctx.label + ': ' + ctx.parsed + ' days'
                }
            }
        }
    }
});

// Department Chart
const deptData = <?php echo json_encode($deptAttendance); ?>;
new Chart(document.getElementById('deptChart'), {
    type: 'bar',
    data: {
        labels: deptData.map(d => d.department || 'Unassigned'),
        datasets: [{
            label: 'Total Hours',
            data: deptData.map(d => d.total_hours || 0),
            backgroundColor: 'rgba(99, 102, 241, 0.7)',
            borderColor: 'rgb(99, 102, 241)',
            borderWidth: 2,
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => v + 'h' } } }
    }
});

// Overtime Chart
new Chart(document.getElementById('overtimeChart'), {
    type: 'bar',
    data: {
        labels: monthlyData.map(d => d.month_name),
        datasets: [
            {
                label: 'Regular',
                data: monthlyData.map(d => d.total_regular),
                backgroundColor: 'rgba(16, 185, 129, 0.7)',
                borderRadius: 4
            },
            {
                label: 'Overtime',
                data: monthlyData.map(d => d.total_overtime),
                backgroundColor: 'rgba(249, 115, 22, 0.7)',
                borderRadius: 4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } },
        scales: {
            x: { stacked: true },
            y: { stacked: true, beginAtZero: true, ticks: { callback: v => v + 'h' } }
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>