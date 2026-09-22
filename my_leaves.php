<?php
$page_title = 'My Leaves';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/leave_config.php';

if (isAdmin()) {
    redirect('leave_management.php');
}

// Get employee
$userEmail = mysqli_real_escape_string($conn, trim($_SESSION['email'] ?? ''));
$employee = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM employees WHERE email = '$userEmail' LIMIT 1"));

if (!$employee) {
    $displayEmail = htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8');
    echo '<p class="text-red-500">No employee record is linked to <strong>' . $displayEmail . '</strong>. Ask an administrator to create an employee record or set its email to this address.</p>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Cancel leave
if (isset($_GET['cancel'])) {
    $id = (int)$_GET['cancel'];
    $result = mysqli_query($conn, "SELECT * FROM leave_requests WHERE id = $id AND employee_id = {$employee['id']} AND status = 'pending'");
    $leave = mysqli_fetch_assoc($result);
    
    if ($leave) {
        $year = date('Y', strtotime($leave['start_date']));
        mysqli_query($conn, "UPDATE leave_requests SET status = 'cancelled' WHERE id = $id");
        mysqli_query($conn, "UPDATE leave_balances SET pending_days = pending_days - {$leave['total_days']} 
                             WHERE employee_id = {$employee['id']} 
                             AND leave_type_id = {$leave['leave_type_id']} 
                             AND year = $year");
        sendUpdateNotification(
            $employee['email'],
            $employee['first_name'] . ' ' . $employee['last_name'],
            'Leave request cancelled',
            "Your leave request (#$id) has been cancelled."
        );
        setFlash('success', 'Leave request cancelled.');
    }
    redirect('my_leaves.php');
}

$leaves = getLeaveRequests(['employee_id' => $employee['id']]);
$balances = getEmployeeLeaveBalances($employee['id']);
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-gray-900">📋 My Leaves</h1>
        <p class="text-gray-500 text-sm mt-1">Your leave requests and balances</p>
    </div>
    <a href="apply_leave.php" class="mt-3 md:mt-0 bg-indigo-600 text-white px-4 py-2 rounded-xl hover:bg-indigo-700 transition flex items-center space-x-2">
        <i class="fas fa-plus-circle"></i>
        <span>Apply for Leave</span>
    </a>
</div>

<!-- Leave Balances -->
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <h3 class="font-bold text-gray-900 mb-4">💼 Leave Balances <?php echo date('Y'); ?></h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($balances as $b): 
            $remaining = $b['allocated_days'] + $b['carried_over'] - $b['used_days'] - $b['pending_days'];
            $percent = $b['allocated_days'] > 0 ? (($b['used_days'] + $b['pending_days']) / $b['allocated_days']) * 100 : 0;
        ?>
        <div class="border rounded-lg p-4" style="border-color: <?php echo $b['color']; ?>33;">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center space-x-2">
                    <i class="fas <?php echo $b['icon']; ?>" style="color: <?php echo $b['color']; ?>"></i>
                    <span class="font-semibold text-sm"><?php echo $b['type_name']; ?></span>
                </div>
                <span class="text-xs font-bold" style="color: <?php echo $b['color']; ?>">
                    <?php echo $remaining; ?> left
                </span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                <div class="h-2 rounded-full transition" style="width: <?php echo min(100, $percent); ?>%; background: <?php echo $b['color']; ?>"></div>
            </div>
            <div class="flex justify-between text-xs text-gray-500">
                <span>Used: <?php echo $b['used_days']; ?></span>
                <span>Pending: <?php echo $b['pending_days']; ?></span>
                <span>Total: <?php echo $b['allocated_days']; ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Leave History -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="p-4 border-b border-gray-200">
        <h3 class="font-bold text-gray-900">📜 Leave History</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Start</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">End</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Days</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($leaves)): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No leave requests yet</td></tr>
                <?php else: ?>
                    <?php foreach ($leaves as $l): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center space-x-2">
                                <i class="fas <?php echo $l['icon']; ?>" style="color: <?php echo $l['color']; ?>"></i>
                                <span class="font-semibold text-sm"><?php echo $l['type_name']; ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm"><?php echo date('M d, Y', strtotime($l['start_date'])); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo date('M d, Y', strtotime($l['end_date'])); ?></td>
                        <td class="px-4 py-3 font-bold"><?php echo $l['total_days']; ?></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold
                                <?php echo $l['status'] === 'approved' ? 'bg-green-100 text-green-700' : 
                                          ($l['status'] === 'pending' ? 'bg-yellow-100 text-yellow-700' : 
                                          ($l['status'] === 'rejected' ? 'bg-red-100 text-red-700' : 
                                          'bg-gray-100 text-gray-700')); ?>">
                                <?php echo ucfirst($l['status']); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($l['status'] === 'pending'): ?>
                            <a href="?cancel=<?php echo $l['id']; ?>" 
                               onclick="return confirm('Cancel this request?')"
                               class="text-red-600 hover:text-red-700 text-sm">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <?php endif; ?>
                            <?php if ($l['document_path']): ?>
                            <a href="<?php echo $l['document_path']; ?>" target="_blank" class="text-blue-600 hover:text-blue-700 ml-2">
                                <i class="fas fa-file"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>