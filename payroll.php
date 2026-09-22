<?php
$page_title = 'Process Payroll';
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/function.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/includes/header.php';

$message = '';
$error = '';

// Handle payroll processing
// Handle payroll processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payroll'])) {
    $month = $_POST['month'];
    $year = (int)$_POST['year'];
    $monthNum = date('m', strtotime("$month 1, $year"));
    
    require_once __DIR__ . '/includes/leave_config.php';
    
    // Get all active employees
    $employees = mysqli_query($conn, "SELECT * FROM employees WHERE status = 'active'");
    $count = 0;
    $skipped = 0;
    
    // Working days in month
    $daysInMonth = getWorkingDaysInMonth($year, $monthNum);
    $workingDays = 0;
    foreach ($daysInMonth as $d) {
        if ($d['is_working']) $workingDays++;
    }
    
    while ($emp = mysqli_fetch_assoc($employees)) {
        // Check if payroll already exists
        $check = mysqli_query($conn, "SELECT id FROM payroll WHERE employee_id = {$emp['id']} AND payroll_month = '$month' AND payroll_year = $year");
        if (mysqli_num_rows($check) > 0) {
            $skipped++;
            continue;
        }
        
        // ============================================
        // 1. Get attendance for this month
        // ============================================
        $attendance = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT 
                SUM(regular_hours) as regular_hours,
                SUM(overtime_hours) as overtime_hours,
                COUNT(DISTINCT work_date) as present_days
            FROM work_sessions 
            WHERE user_id = {$emp['id']}
              AND YEAR(work_date) = $year 
              AND MONTH(work_date) = $monthNum
              AND status IN ('completed', 'timeout')
        "));
        
        $regularHours = $attendance['regular_hours'] ?? 0;
        $overtimeHours = $attendance['overtime_hours'] ?? 0;
        $presentDays = $attendance['present_days'] ?? 0;
        
        // ============================================
        // 2. Calculate unpaid leave days in this month
        // ============================================
        $startOfMonth = "$year-$monthNum-01";
        $endOfMonth = date('Y-m-t', strtotime($startOfMonth));
        
        $unpaidResult = mysqli_query($conn, "
            SELECT SUM(lr.total_days) as unpaid_days
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.employee_id = {$emp['id']}
              AND lr.status = 'approved'
              AND lt.is_paid = 0
              AND lr.start_date <= '$endOfMonth'
              AND lr.end_date >= '$startOfMonth'
        ");
        $unpaidDays = mysqli_fetch_assoc($unpaidResult)['unpaid_days'] ?? 0;
        
        // ============================================
        // 3. Calculate paid leave days
        // ============================================
        $paidResult = mysqli_query($conn, "
            SELECT SUM(lr.total_days) as paid_days
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.employee_id = {$emp['id']}
              AND lr.status = 'approved'
              AND lt.is_paid = 1
              AND lr.start_date <= '$endOfMonth'
              AND lr.end_date >= '$startOfMonth'
        ");
        $paidLeaveDays = mysqli_fetch_assoc($paidResult)['paid_days'] ?? 0;
        
        // ============================================
        // 4. Calculate salary components
        // ============================================
        $basicSalary = $emp['basic_salary'];
        $allowances = $emp['allowances'];
        
        // Daily rate (for unpaid leave deduction)
        $dailyRate = $basicSalary / $workingDays;
        
        // Overtime rate (1.5x hourly)
        $hourlyRate = ($basicSalary / $workingDays) / STANDARD_HOURS;
        $overtimePay = $overtimeHours * $hourlyRate * OVERTIME_MULTIPLIER;
        
        // Unpaid leave deduction
        $unpaidDeduction = $unpaidDays * $dailyRate;
        
        // Calculate gross pay
        $grossPay = $basicSalary + $allowances + $overtimePay - $unpaidDeduction;
        
        // ============================================
        // 5. Apply statutory deductions
        // ============================================
        $paye = calculatePAYE($grossPay);
        $nhif = calculateNHIF($grossPay);
        $nssf = calculateNSSF($basicSalary);
        
        $totalDeductions = $paye + $nhif + $nssf;
        $netPay = $grossPay - $totalDeductions;
        
        // ============================================
        // 6. Insert payroll record
        // ============================================
        $sql = "INSERT INTO payroll 
                (employee_id, payroll_month, payroll_year, basic_salary, allowances, 
                 gross_pay, paye, nhif, nssf, total_deductions, net_pay,
                 overtime_hours, overtime_pay, unpaid_leave_days, unpaid_leave_deduction,
                 paid_leave_days, working_days, present_days) 
                VALUES 
                ({$emp['id']}, '$month', $year, $basicSalary, $allowances,
                 $grossPay, $paye, $nhif, $nssf, $totalDeductions, $netPay,
                 $overtimeHours, $overtimePay, $unpaidDays, $unpaidDeduction,
                 $paidLeaveDays, $workingDays, $presentDays)";
        
        if (mysqli_query($conn, $sql)) {
            $count++;
            sendUpdateNotification(
                $emp['email'],
                $emp['first_name'] . ' ' . $emp['last_name'],
                'Payroll updated',
                "Your payroll for $month $year has been processed. Your payslip is now available in the payroll system."
            );
        }
    }
    
    $message = "Payroll processed for $count employees! " . ($skipped > 0 ? "$skipped skipped (already processed)." : "");
    setFlash('success', $message);
    redirect('payroll.php');
}

// Get payroll records
$payrolls = mysqli_query($conn, "
    SELECT p.*, e.first_name, e.last_name, e.employee_id 
    FROM payroll p 
    JOIN employees e ON p.employee_id = e.id 
    ORDER BY p.created_at DESC 
    LIMIT 50
");
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-gray-900">Process Payroll</h1>
        <p class="text-gray-500 text-sm mt-1">Calculate and process employee salaries</p>
    </div>
</div>

<?php if ($message): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
    <i class="fas fa-check-circle mr-2"></i> <?php echo $message; ?>
</div>
<?php endif; ?>

<!-- Process Payroll Form -->
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <h3 class="font-bold text-gray-900 mb-4">Run Payroll</h3>
    <form method="POST" class="flex flex-col md:flex-row gap-4 items-end">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
            <select name="month" required class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <?php
                $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                foreach ($months as $m) {
                    $selected = ($m === date('F')) ? 'selected' : '';
                    echo "<option value='$m' $selected>$m</option>";
                }
                ?>
            </select>
        </div>
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
            <select name="year" required class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <button type="submit" name="process_payroll" 
                class="bg-green-600 text-white px-8 py-2 rounded-lg hover:bg-green-700 transition font-bold">
            <i class="fas fa-calculator mr-2"></i> Process Payroll
        </button>
    </form>
</div>

<!-- Payroll Records -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="p-4 border-b border-gray-200">
        <h3 class="font-bold text-gray-900">Payroll Records</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium