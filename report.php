<?php
$page_title = 'Reports';
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/function.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/includes/header.php';

// Get filters
$month = isset($_GET['month']) ? $_GET['month'] : date('F');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$department = isset($_GET['department']) ? $_GET['department'] : '';

// Build query
$where = "WHERE p.payroll_month = '$month' AND p.payroll_year = $year";
if ($department) {
    $where .= " AND e.department = '$department'";
}

// Get payroll data
$payrolls = mysqli_query($conn, "
    SELECT p.*, e.first_name, e.last_name, e.employee_id, e.department, e.position
    FROM payroll p 
    JOIN employees e ON p.employee_id = e.id 
    $where
    ORDER BY e.first_name
");

// Get summary
$summary = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_employees,
        SUM(p.gross_pay) as total_gross,
        SUM(p.paye) as total_paye,
        SUM(p.nhif) as total_nhif,
        SUM(p.nssf) as total_nssf,
        SUM(p.total_deductions) as total_deductions,
        SUM(p.net_pay) as total_net
    FROM payroll p 
    JOIN employees e ON p.employee_id = e.id 
    $where
");
$summaryData = mysqli_fetch_assoc($summary);

// Get departments for filter
$departments = mysqli_query($conn, "SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department");
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-gray-900">Payroll Reports</h1>
        <p class="text-gray-500 text-sm mt-1">View and analyze payroll data</p>
    </div>
    <div class="flex gap-2 mt-3 md:mt-0">
        <a href="export_excel.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>&department=<?php echo $department; ?>" 
           class="bg-green-600 text-white px-4 py-2 rounded-xl hover:bg-green-700 transition flex items-center space-x-2 text-sm">
            <i class="fas fa-file-excel"></i>
            <span>Export Excel</span>
        </a>
        <a href="export_pdf.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>&department=<?php echo $department; ?>" 
           class="bg-red-600 text-white px-4 py-2 rounded-xl hover:bg-red-700 transition flex items-center space-x-2 text-sm">
            <i class="fas fa-file-pdf"></i>
            <span>Export PDF</span>
        </a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm p-4 mb-6">
    <form method="GET" class="flex flex-col md:flex-row gap-3">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
            <select name="month" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <?php
                $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                foreach ($months as $m) {
                    $selected = ($m === $month) ? 'selected' : '';
                    echo "<option value='$m' $selected>$m</option>";
                }
                ?>
            </select>
        </div>
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
            <select name="year" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                <option value="<?php echo $y; ?>" <?php echo $y === $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
            <select name="department" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Departments</option>
                <?php while ($d = mysqli_fetch_assoc($departments)): ?>
                <option value="<?php echo $d['department']; ?>" <?php echo $d['department'] === $department ? 'selected' : ''; ?>>
                    <?php echo $d['department']; ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition">
                <i class="fas fa-filter mr-2"></i> Filter
            </button>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-indigo-600">
        <p class="text-sm text-gray-500">Employees</p>
        <p class="text-2xl font-black text-gray-900"><?php echo $summaryData['total_employees'] ?? 0; ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-green-600">
        <p class="text-sm text-gray-500">Gross Pay</p>
        <p class="text-xl font-black text-green-600"><?php echo formatMoney($summaryData['total_gross'] ?? 0); ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-red-600">
        <p class="text-sm text-gray-500">Deductions</p>
        <p class="text-xl font-black text-red-600"><?php echo formatMoney($summaryData['total_deductions'] ?? 0); ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-purple-600">
        <p class="text-sm text-gray-500">Net Pay</p>
        <p class="text-xl font-black text-purple-600"><?php echo formatMoney($summaryData['total_net'] ?? 0); ?></p>
    </div>
</div>

<!-- Detailed Table -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="p-4 border-b border-gray-200">
        <h3 class="font-bold text-gray-900">Payroll Details - <?php echo $month . ' ' . $year; ?></h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Basic</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Allowances</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Gross</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">PAYE</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">NHIF</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">NSSF</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Net Pay</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (mysqli_num_rows($payrolls) > 0): ?>
                    <?php while ($p = mysqli_fetch_assoc($payrolls)): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-gray-900"><?php echo $p['first_name'] . ' ' . $p['last_name']; ?></p>
                            <p class="text-xs text-gray-500"><?php echo $p['employee_id']; ?></p>
                        </td>
                        <td class="px-4 py-3 text-sm"><?php echo $p['department']; ?></td>
                        <td class="px-4 py-3 text-right"><?php echo number_format($p['basic_salary'], 0); ?></td>
                        <td class="px-4 py-3 text-right"><?php echo number_format($p['allowances'], 0); ?></td>
                        <td class="px-4 py-3 text-right font-semibold"><?php echo number_format($p['gross_pay'], 0); ?></td>
                        <td class="px-4 py-3 text-right text-red-600"><?php echo number_format($p['paye'], 0); ?></td>
                        <td class="px-4 py-3 text-right text-red-600"><?php echo number_format($p['nhif'], 0); ?></td>
                        <td class="px-4 py-3 text-right text-red-600"><?php echo number_format($p['nssf'], 0); ?></td>
                        <td class="px-4 py-3 text-right font-bold text-green-600"><?php echo number_format($p['net_pay'], 0); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                            No payroll records found for this period
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <?php if (mysqli_num_rows($payrolls) > 0): ?>
            <tfoot class="bg-gray-50 font-bold">
                <tr>
                    <td colspan="4" class="px-4 py-3 text-right">TOTALS:</td>
                    <td class="px-4 py-3 text-right"><?php echo number_format($summaryData['total_gross'] ?? 0, 0); ?></td>
                    <td class="px-4 py-3 text-right"><?php echo number_format($summaryData['total_paye'] ?? 0, 0); ?></td>
                    <td class="px-4 py-3 text-right"><?php echo number_format($summaryData['total_nhif'] ?? 0, 0); ?></td>
                    <td class="px-4 py-3 text-right"><?php echo number_format($summaryData['total_nssf'] ?? 0, 0); ?></td>
                    <td class="px-4 py-3 text-right text-green-600"><?php echo number_format($summaryData['total_net'] ?? 0, 0); ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>