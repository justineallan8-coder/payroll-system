<?php
$page_title = 'Dashboard';
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/function.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/includes/header.php';

// Get statistics
$totalEmployees = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM employees WHERE status = 'active'"))['count'];

$totalPayroll = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(net_pay) as total FROM payroll WHERE status = 'paid'"))['total'] ?? 0;

$pendingPayroll = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM payroll WHERE status = 'pending'"))['count'];

$monthlyPayroll = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(net_pay) as total FROM payroll WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())"))['total'] ?? 0;

$totalDeductions = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_deductions) as total FROM payroll"))['total'] ?? 0;

// Recent payroll
$recentPayroll = mysqli_query($conn, "
    SELECT p.*, e.first_name, e.last_name, e.employee_id 
    FROM payroll p 
    JOIN employees e ON p.employee_id = e.id 
    ORDER BY p.created_at DESC 
    LIMIT 5
");

// Monthly payroll trend (last 6 months)
$trendData = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('F', strtotime("-$i months"));
    $year = date('Y', strtotime("-$i months"));
    $result = mysqli_query($conn, "SELECT SUM(net_pay) as total FROM payroll WHERE payroll_month = '$month' AND payroll_year = $year");
    $row = mysqli_fetch_assoc($result);
    $trendData[] = [
        'month' => date('M', strtotime("-$i months")),
        'total' => (float)($row['total'] ?? 0)
    ];
}
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-gray-900">Dashboard</h1>
        <p class="text-gray-500 text-sm mt-1">
            Welcome back, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>!
        </p>
    </div>
    <?php if (isAdmin()): ?>
    <div class="flex flex-wrap gap-3 mt-3 md:mt-0">
        <a href="add employee.php" class="bg-indigo-600 text-white px-4 py-2 rounded-xl hover:bg-indigo-700 transition flex items-center space-x-2 text-sm">
            <i class="fas fa-plus-circle"></i>
            <span>Add Employee</span>
        </a>
        <a href="payroll.php" class="bg-green-600 text-white px-4 py-2 rounded-xl hover:bg-green-700 transition flex items-center space-x-2 text-sm">
            <i class="fas fa-money-check-alt"></i>
            <span>Process Payroll</span>
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-indigo-600">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Total Employees</p>
                <p class="text-2xl font-black text-gray-900"><?php echo $totalEmployees; ?></p>
            </div>
            <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-users text-indigo-600 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-green-600">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Total Paid</p>
                <p class="text-xl font-black text-green-600"><?php echo formatMoney($totalPayroll); ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-check-circle text-green-600 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-yellow-600">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Pending Payments</p>
                <p class="text-2xl font-black text-yellow-600"><?php echo $pendingPayroll; ?></p>
            </div>
            <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-clock text-yellow-600 text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-red-600">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Total Deductions</p>
                <p class="text-xl font-black text-red-600"><?php echo formatMoney($totalDeductions); ?></p>
            </div>
            <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-minus-circle text-red-600 text-xl"></i>
            </div>
        </div>
    </div>
</div>
<!-- Active Sessions Widget -->
<?php
$activeSessions = getActiveSessions();
$activeCount = count($activeSessions);
?>
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <div class="flex justify-between items-center mb-4">
        <div class="flex items-center space-x-2">
            <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
            <h3 class="font-bold text-gray-900">Active Sessions (<?php echo $activeCount; ?>)</h3>
        </div>
        <a href="active_sessions.php" class="text-indigo-600 hover:text-indigo-700 text-sm font-medium">
            View All <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>
    
    <?php if ($activeCount > 0): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        <?php foreach (array_slice($activeSessions, 0, 6) as $session): ?>
        <div class="flex items-center space-x-3 p-3 bg-green-50 rounded-lg border border-green-100">
            <?php if ($session['avatar'] && file_exists(__DIR__ . '/uploads/avatars/' . $session['avatar'])): ?>
            <img src="uploads/avatars/<?php echo $session['avatar']; ?>" class="w-10 h-10 rounded-full object-cover border-2 border-green-300">
            <?php else: ?>
            <div class="w-10 h-10 bg-green-200 rounded-full flex items-center justify-center border-2 border-green-300">
                <span class="text-green-700 font-bold text-sm"><?php echo strtoupper(substr($session['full_name'], 0, 1)); ?></span>
            </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-gray-900 text-sm truncate"><?php echo htmlspecialchars($session['full_name']); ?></p>
                <p class="text-xs text-gray-500">
                    <?php 
                    $mins = $session['minutes_active'];
                    echo floor($mins / 60) . 'h ' . ($mins % 60) . 'm';
                    ?> • <?php echo date('H:i', strtotime($session['login_time'])); ?>
                </p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="text-gray-500 text-sm">No active users at the moment</p>
    <?php endif; ?>
</div>

<!-- Second Row Stats -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border border-gray-100">
        <p class="text-xl font-black text-purple-600"><?php echo formatMoney($monthlyPayroll); ?></p>
        <p class="text-xs text-gray-500">This Month</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border border-gray-100">
        <p class="text-xl font-black text-blue-600"><?php echo mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM payroll"))['c']; ?></p>
        <p class="text-xs text-gray-500">Total Payroll Runs</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border border-gray-100">
        <p class="text-xl font-black text-orange-600"><?php echo mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE status = 'active'"))['c']; ?></p>
        <p class="text-xs text-gray-500">Active Admins</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border border-gray-100">
        <p class="text-xl font-black text-teal-600"><?php echo date('Y'); ?></p>
        <p class="text-xs text-gray-500">Current Year</p>
    </div>
</div>

<!-- Chart + Recent Payroll -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Payroll Trend -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-6">
        <h3 class="font-bold text-gray-900 mb-4">📈 Payroll Trend (Last 6 Months)</h3>
        <div class="h-64">
            <canvas id="payrollChart"></canvas>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="font-bold text-gray-900 mb-4">⚡ Quick Actions</h3>
        <div class="space-y-3">
            <?php if (isAdmin()): ?>
            <a href="add employee.php" class="flex items-center space-x-3 p-3 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-xl transition">
                <div class="w-10 h-10 bg-indigo-600 rounded-lg flex items-center justify-center text-white">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div>
                    <p class="font-semibold text-sm">Add Employee</p>
                    <p class="text-xs text-gray-500">Register new employee</p>
                </div>
            </a>
            
            <a href="payroll.php" class="flex items-center space-x-3 p-3 bg-green-50 hover:bg-green-100 text-green-700 rounded-xl transition">
                <div class="w-10 h-10 bg-green-600 rounded-lg flex items-center justify-center text-white">
                    <i class="fas fa-calculator"></i>
                </div>
                <div>
                    <p class="font-semibold text-sm">Run Payroll</p>
                    <p class="text-xs text-gray-500">Process monthly payroll</p>
                </div>
            </a>
            <?php endif; ?>
            
            <a href="report.php" class="flex items-center space-x-3 p-3 bg-purple-50 hover:bg-purple-100 text-purple-700 rounded-xl transition">
                <div class="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center text-white">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div>
                    <p class="font-semibold text-sm">View Reports</p>
                    <p class="text-xs text-gray-500">Analyze payroll data</p>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- Recent Payroll -->
<div class="bg-white rounded-xl shadow-sm p-6">
    <div class="flex justify-between items-center mb-4">
        <h3 class="font-bold text-gray-900">🕐 Recent Payroll</h3>
        <a href="payroll.php" class="text-indigo-600 hover:text-indigo-700 text-sm font-medium">
            View All <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>
    
    <?php if (mysqli_num_rows($recentPayroll) > 0): ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Net Pay</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php while ($row = mysqli_fetch_assoc($recentPayroll)): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <p class="font-semibold text-gray-900"><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></p>
                        <p class="text-xs text-gray-500"><?php echo $row['employee_id']; ?></p>
                    </td>
                    <td class="px-4 py-3 text-sm"><?php echo $row['payroll_month'] . ' ' . $row['payroll_year']; ?></td>
                    <td class="px-4 py-3 font-bold text-gray-900"><?php echo formatMoney($row['net_pay']); ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 rounded-full text-xs font-semibold
                            <?php echo $row['status'] === 'paid' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                            <?php echo ucfirst($row['status']); ?>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <a href="payslip.php?id=<?php echo $row['id']; ?>" class="text-indigo-600 hover:text-indigo-700">
                            <i class="fas fa-file-invoice"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p class="text-gray-500 text-center py-8">No payroll records yet</p>
    <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('payrollChart').getContext('2d');
    
    const labels = <?php echo json_encode(array_column($trendData, 'month')); ?>;
    const values = <?php echo json_encode(array_column($trendData, 'total')); ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Net Pay',
                data: values,
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                borderColor: 'rgba(99, 102, 241, 1)',
                borderWidth: 3,
                fill: true