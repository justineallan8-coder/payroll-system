<?php
$page_title = 'Payslip';
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/function.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$query = "SELECT p.*, e.*, p.id as payroll_id 
          FROM payroll p 
          JOIN employees e ON p.employee_id = e.id 
          WHERE p.id = $id";
$result = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    echo "<p class='text-red-500'>Payslip not found</p>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
?>

<div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm p-8">
        <!-- Header -->
        <div class="text-center border-b pb-6 mb-6">
            <h1 class="text-2xl font-black text-gray-900">ALLAN SHOP</h1>
            <p class="text-gray-500">Dar es Salaam, Tanzania</p>
            <p class="text-gray-500">Tel: +255 754 590 950</p>
            <h2 class="text-xl font-bold text-indigo-600 mt-4">PAYSLIP</h2>
            <p class="text-gray-500"><?php echo $data['payroll_month'] . ' ' . $data['payroll_year']; ?></p>
        </div>
        
        <!-- Employee Info -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div>
                <p class="text-sm text-gray-500">Employee Name</p>
                <p class="font-bold"><?php echo $data['first_name'] . ' ' . $data['last_name']; ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Employee ID</p>
                <p class="font-bold"><?php echo $data['employee_id']; ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Position</p>
                <p class="font-bold"><?php echo $data['position']; ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Department</p>
                <p class="font-bold"><?php echo $data['department']; ?></p>
            </div>
        </div>
        
        <!-- Earnings & Deductions -->
        <div class="grid grid-cols-2 gap-6 mb-6">
            <div>
                <h3 class="font-bold text-green-600 border-b pb-2 mb-2">EARNINGS</h3>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span>Basic Salary</span>
                        <span class="font-semibold"><?php echo formatMoney($data['basic_salary']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Allowances</span>
                        <span class="font-semibold"><?php echo formatMoney($data['allowances']); ?></span>
                    </div>
                    <div class="flex justify-between border-t pt-2 font-bold">
                        <span>Gross Pay</span>
                        <span><?php echo formatMoney($data['gross_pay']); ?></span>
                    </div>
                </div>
            </div>
            
            <div>
                <h3 class="font-bold text-red-600 border-b pb-2 mb-2">DEDUCTIONS</h3>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span>PAYE</span>
                        <span class="font-semibold"><?php echo formatMoney($data['paye']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>NHIF</span>
                        <span class="font-semibold"><?php echo formatMoney($data['nhif']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>NSSF</span>
                        <span class="font-semibold"><?php echo formatMoney($data['nssf']); ?></span>
                    </div>
                    <div class="flex justify-between border-t pt-2 font-bold">
                        <span>Total Deductions</span>
                        <span><?php echo formatMoney($data['total_deductions']); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <!-- Attendance & Leave Summary -->
<?php if ($data['overtime_hours'] > 0 || $data['unpaid_leave_days'] > 0 || $data['paid_leave_days'] > 0): ?>
<div class="grid grid-cols-2 gap-6 mb-6">
    <div>
        <h3 class="font-bold text-blue-600 border-b pb-2 mb-2">📅 ATTENDANCE</h3>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span>Working Days</span>
                <span class="font-semibold"><?php echo $data['working_days'] ?? 0; ?></span>
            </div>
            <div class="flex justify-between">
                <span>Days Present</span>
                <span class="font-semibold text-green-600"><?php echo $data['present_days'] ?? 0; ?></span>
            </div>
            <div class="flex justify-between">
                <span>Paid Leave Days</span>
                <span class="font-semibold text-purple-600"><?php echo $data['paid_leave_days'] ?? 0; ?></span>
            </div>
            <div class="flex justify-between">
                <span>Unpaid Leave Days</span>
                <span class="font-semibold text-red-600"><?php echo $data['unpaid_leave_days'] ?? 0; ?></span>
            </div>
        </div>
    </div>
    <div>
        <h3 class="font-bold text-orange-600 border-b pb-2 mb-2">⏰ OVERTIME</h3>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span>Overtime Hours</span>
                <span class="font-semibold"><?php echo number_format($data['overtime_hours'] ?? 0, 2); ?>h</span>
            </div>
            <div class="flex justify-between">
                <span>Overtime Rate</span>
                <span class="font-semibold">1.5x</span>
            </div>
            <div class="flex justify-between border-t pt-2 font-bold">
                <span>Overtime Pay</span>
                <span class="text-orange-600"><?php echo formatMoney($data['overtime_pay'] ?? 0); ?></span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Unpaid Leave Deduction -->
<?php if (($data['unpaid_leave_deduction'] ?? 0) > 0): ?>
<div class="bg-red-50 rounded-lg p-4 mb-6">
    <div class="flex justify-between">
        <span class="font-semibold text-red-700">Unpaid Leave Deduction</span>
        <span class="font-bold text-red-700">-<?php echo formatMoney($data['unpaid_leave_deduction']); ?></span>
    </div>
</div>
<?php endif; ?>
        
        <!-- Net Pay -->
        <div class="bg-indigo-50 rounded-xl p-6 text-center">
            <p class="text-gray-600">NET PAY</p>
            <p class="text-3xl font-black text-indigo-600"><?php echo formatMoney($data['net_pay']); ?></p>
        </div>
        
        <!-- Footer -->
        <div class="mt-6 pt-4 border-t text-center text-sm text-gray-500">
            <p>Generated on <?php echo date('F d, Y'); ?></p>
            <p>This is a computer-generated payslip</p>
        </div>
    </div>
    
    <!-- Print Button -->
    <div class="mt-4 text-center no-print">
        <button onclick="window.print()" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition">
            <i class="fas fa-print mr-2"></i> Print Payslip
        </button>
        <a href="payroll.php" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 transition ml-2">
            Back
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>