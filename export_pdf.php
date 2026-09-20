<?php
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/function.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

$month = isset($_GET['month']) ? $_GET['month'] : date('F');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$department = isset($_GET['department']) ? $_GET['department'] : '';

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

// For PDF, we use print-friendly HTML with JS to trigger print dialog
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payroll Report - <?php echo $month . ' ' . $year; ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        h2 { text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #4f46e5; color: white; padding: 8px; text-align: left; }
        td { padding: 6px 8px; border-bottom: 1px solid #ddd; }
        .text-right { text-align: right; }
        .total-row { background: #f3f4f6; font-weight: bold; }
        .header { text-align: center; margin-bottom: 20px; }
        @media print {
            .no-print { display: none; }
            body { font-size: 10px; }
        }
    </style>
</head>
<body>

<div class="header">
    <h2>ALLAN SHOP</h2>
    <p>Dar es Salaam, Tanzania | Tel: +255 754 590 950</p>
    <h3>PAYROLL REPORT - <?php echo strtoupper($month . ' ' . $year); ?></h3>
    <?php if ($department): ?>
    <p>Department: <?php echo $department; ?></p>
    <?php endif; ?>
</div>

<table>
    <thead>
        <tr>
            <th>Employee ID</th>
            <th>Name</th>
            <th>Department</th>
            <th class="text-right">Basic</th>
            <th class="text-right">Allowances</th>
            <th class="text-right">Gross</th>
            <th class="text-right">PAYE</th>
            <th class="text-right">NHIF</th>
            <th class="text-right">NSSF</th>
            <th class="text-right">Net Pay</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $totalGross = 0; $totalDeductions = 0; $totalNet = 0;
        while ($p = mysqli_fetch_assoc($payrolls)): 
            $totalGross += $p['gross_pay'];
            $totalDeductions += $p['total_deductions'];
            $totalNet += $p['net_pay'];
        ?>
        <tr>
            <td><?php echo $p['employee_id']; ?></td>
            <td><?php echo $p['first_name'] . ' ' . $p['last_name']; ?></td>
            <td><?php echo $p['department']; ?></td>
            <td class="text-right"><?php echo number_format($p['basic_salary'], 0); ?></td>
            <td class="text-right"><?php echo number_format($p['allowances'], 0); ?></td>
            <td class="text-right"><?php echo number_format($p['gross_pay'], 0); ?></td>
            <td class="text-right"><?php echo number_format($p['paye'], 0); ?></td>
            <td class="text-right"><?php echo number_format($p['nhif'], 0); ?></td>
            <td class="text-right"><?php echo number_format($p['nssf'], 0); ?></td>
            <td class="text-right"><?php echo number_format($p['net_pay'], 0); ?></td>
        </tr>
        <?php endwhile; ?>
        <tr class="total-row">
            <td colspan="5" class="text-right">TOTALS:</td>
            <td class="text-right"><?php echo number_format($totalGross, 0); ?></td>
            <td colspan="2"></td>
            <td class="text-right"><?php echo number_format($totalDeductions, 0); ?></td>
            <td class="text-right"><?php echo number_format($totalNet, 0); ?></td>
        </tr>
    </tbody>
</table>

<div style="margin-top: 30px; text-align: center; font-size: 10px; color: #666;">
    Generated on <?php echo date('F d, Y H:i:s'); ?> | Allan Shop Payroll System
</div>

<div class="no-print" style="text-align:center; margin-top:30px;">
    <button onclick="window.print()" style="padding:12px 24px; background:#4f46e5; color:white; border:none; border-radius:8px; cursor:pointer; font-size:14px;">
        🖨️ Print / Save as PDF
    </button>
    <a href="report.php" style="padding:12px 24px; background:#e5e7eb; color:#333; border:none; border-radius:8px; text-decoration:none; font-size:14px; margin-left:10px;">
        ← Back
    </a>
</div>

<script>
    // Auto-trigger print dialog
    // window.onload = function() { window.print(); }
</script>

</body>
</html>