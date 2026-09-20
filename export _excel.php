<?php
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/function.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

$month = isset($_GET['month']) ? $_GET['month'] : date('F');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$department = isset($_GET['department']) ? trim($_GET['department']) : '';

$month = mysqli_real_escape_string($conn, $month);
$department = mysqli_real_escape_string($conn, $department);

$where = "WHERE p.payroll_month = '$month' AND p.payroll_year = $year";
if ($department) {
    $where .= " AND e.department = '$department'";
}

$payrolls = mysqli_query($conn, "
    SELECT p.*, e.first_name, e.last_name, e.employee_id, e.department, e.position
    FROM payroll p 
    JOIN employees e ON p.employee_id = e.id 
    $where
    ORDER BY e.first_name
");

if (!$payrolls) {
    http_response_code(500);
    exit('Unable to export payroll: ' . htmlspecialchars(mysqli_error($conn), ENT_QUOTES, 'UTF-8'));
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="payroll_' . $month . '_' . $year . '.xls"');

// Output HTML table
echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
echo '<head><meta charset="UTF-8"></head>';
echo '<body>';
echo '<h2>Payroll Report - ' . htmlspecialchars($month, ENT_QUOTES, 'UTF-8') . ' ' . $year . '</h2>';
echo '<table border="1">';
echo '<tr style="background:#4f46e5;color:white;font-weight:bold;">';
echo '<th>Employee ID</th><th>Name</th><th>Department</th><th>Position</th>';
echo '<th>Basic Salary</th><th>Allowances</th><th>Gross Pay</th>';
echo '<th>PAYE</th><th>NHIF</th><th>NSSF</th><th>Deductions</th><th>Net Pay</th>';
echo '</tr>';

$totalGross = 0; $totalDeductions = 0; $totalNet = 0;

while ($p = mysqli_fetch_assoc($payrolls)) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($p['employee_id'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars($p['first_name'] . ' ' . $p['last_name'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars($p['department'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars($p['position'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td align="right">' . number_format($p['basic_salary'], 2) . '</td>';
    echo '<td align="right">' . number_format($p['allowances'], 2) . '</td>';
    echo '<td align="right">' . number_format($p['gross_pay'], 2) . '</td>';
    echo '<td align="right">' . number_format($p['paye'], 2) . '</td>';
    echo '<td align="right">' . number_format($p['nhif'], 2) . '</td>';
    echo '<td align="right">' . number_format($p['nssf'], 2) . '</td>';
    echo '<td align="right">' . number_format($p['total_deductions'], 2) . '</td>';
    echo '<td align="right">' . number_format($p['net_pay'], 2) . '</td>';
    echo '</tr>';
    
    $totalGross += $p['gross_pay'];
    $totalDeductions += $p['total_deductions'];
    $totalNet += $p['net_pay'];
}

echo '<tr style="background:#f3f4f6;font-weight:bold;">';
echo '<td colspan="6" align="right">TOTALS:</td>';
echo '<td align="right">' . number_format($totalGross, 2) . '</td>';
echo '<td colspan="3"></td>';
echo '<td align="right">' . number_format($totalDeductions, 2) . '</td>';
echo '<td align="right">' . number_format($totalNet, 2) . '</td>';
echo '</tr>';

echo '</table>';
echo '</body></html>';
?>