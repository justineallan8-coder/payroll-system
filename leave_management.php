<?php
$page_title = 'Leave Management';
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/function.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/leave_config.php';

if (!isAdmin()) {
    setFlash('error', 'Admin access required');
    redirect('dashboard.php');
}

$message = '';

// Handle approve/reject
if (isset($_GET['approve'])) {
    $result = approveLeave((int)$_GET['approve'], $_SESSION['user_id']);
    setFlash($result['success'] ? 'success' : 'error', $result['message']);
    redirect('leave_management.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_id'])) {
    $reason = trim($_POST['reason'] ?? '');
    $result = rejectLeave((int)$_POST['reject_id'], $_SESSION['user_id'], $reason ?: 'No reason provided');
    setFlash($result['success'] ? 'success' : 'error', $result['message']);
    redirect('leave_management.php');
}

// Support rejection links from older cached versions of this page.
if (isset($_GET['reject'])) {
    $reason = trim($_GET['reason'] ?? '');
    $result = rejectLeave((int)$_GET['reject'], $_SESSION['user_id'], $reason ?: 'No reason provided');
    setFlash($result['success'] ? 'success' : 'error', $result['message']);
    redirect('leave_management.php');
}

require_once __DIR__ . '/includes/header.php';

// Filters
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'pending';
$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

$leaves = getLeaveRequests(['status' => $filterStatus, 'year' => $filterYear]);
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-gray-900">🏖️ Leave Management</h1>
        <p class="text-gray-500 text-sm mt-1">Review and manage employee leave requests</p>
    </div>
</div>

<!-- Stats -->
<?php
$pendingCount = getPendingLeaveCount();
$approvedThisYear = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM leave_requests WHERE status = 'approved' AND YEAR(start_date) = $filterYear"))['c'];
$rejectedThisYear = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM leave_requests WHERE status = 'rejected' AND YEAR(start_date) = $filterYear"))['c'];
?>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-yellow-600">
        <p class="text-xs text-gray-500">Pending</p>
        <p class="text-2xl font-black text-yellow-600"><?php echo $pendingCount; ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-600">
        <p class="text-xs text-gray-500">Approved (<?php echo $filterYear; ?>)</p>
        <p class="text-2xl font-black text-green-600"><?php echo $approvedThisYear; ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-600">
        <p class="text-xs text-gray-500">Rejected (<?php echo $filterYear; ?>)</p>
        <p class="text-2xl font-black text-red-600"><?php echo $rejectedThisYear; ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-indigo-600">
        <p class="text-xs text-gray-500">Total Employees</p>
        <p class="text-2xl font-black text-indigo-600"><?php echo mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM employees WHERE status = 'active'"))['c']; ?></p>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm p-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-3">
        <select name="status" class="px-4 py-2 rounded-lg border border-gray-300">
            <option value="">All Status</option>
            <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="approved" <?php echo $filterStatus === 'approved' ? 'selected' : ''; ?>>Approved</option>
            <option value="rejected" <?php echo $filterStatus === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
        </select>
        <select name="year" class="px-4 py-2 rounded-lg border border-gray-300">
            <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
            <option value="<?php echo $y; ?>" <?php echo $filterYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
        <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">
            <i class="fas fa-filter mr-2"></i> Filter
        </button>
    </form>
</div>

<!-- Leave Requests -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Leave Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Days</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($leaves)): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No leave requests found</td></tr>
                <?php else: ?>
                    <?php foreach ($leaves as $l): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($l['first_name'] . ' ' . $l['last_name']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo $l['emp_code']; ?></p>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center space-x-2">
                                <i class="fas <?php echo $l['icon']; ?>" style="color: <?php echo $l['color']; ?>"></i>
                                <span class="text-sm font-semibold"><?php echo $l['type_name']; ?></span>
                                <?php if (!$l['is_paid']): ?>
                                <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">Unpaid</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($l['reason']): ?>
                            <p class="text-xs text-gray-500 mt-1 truncate max-w-xs"><?php echo htmlspecialchars($l['reason']); ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <?php echo date('M d', strtotime($l['start_date'])); ?> - <?php echo date('M d, Y', strtotime($l['end_date'])); ?>
                        </td>
                        <td class="px-4 py-3 font-bold"><?php echo $l['total_days']; ?> days</td>
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
                            <div class="flex space-x-2">
                                <?php if ($l['status'] === 'pending'): ?>
                                <a href="?approve=<?php echo $l['id']; ?>" 
                                   onclick="return confirm('Approve this leave request?')"
                                   class="bg-green-600 text-white px-3 py-1 rounded-lg text-xs hover:bg-green-700">
                                    <i class="fas fa-check"></i> Approve
                                </a>
                                <button onclick="rejectLeave(<?php echo $l['id']; ?>)" 
                                        class="bg-red-600 text-white px-3 py-1 rounded-lg text-xs hover:bg-red-700">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                                <?php endif; ?>
                                <?php if ($l['document_path']): ?>
                                <a href="<?php echo $l['document_path']; ?>" target="_blank" 
                                   class="text-blue-600 hover:text-blue-700" title="View document">
                                    <i class="fas fa-file"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Reject Leave Request</h3>
        <form method="POST" action="leave_management.php" id="rejectForm">
            <input type="hidden" name="reject_id" id="rejectId">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Reason for rejection</label>
                <textarea name="reason" rows="3" required
                          class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
            <div class="flex gap-2 mt-4">
                <button type="submit" class="flex-1 bg-red-600 text-white py-2 rounded-lg hover:bg-red-700">
                    Reject
                </button>
                <button type="button" onclick="document.getElementById('rejectModal').classList.add('hidden')"
                        class="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function rejectLeave(id) {
    document.getElementById('rejectId').value = id;
    document.getElementById('rejectModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>