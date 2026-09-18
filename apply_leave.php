<?php
$page_title = 'Apply for Leave';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/leave_config.php';

if (isAdmin()) {
    redirect('leave_management.php');
}

$message = '';
$error = '';

// Get employee record for this user
$userEmail = mysqli_real_escape_string($conn, trim($_SESSION['email'] ?? ''));
$empResult = mysqli_query($conn, "SELECT * FROM employees WHERE email = '$userEmail' LIMIT 1");
$employee = mysqli_fetch_assoc($empResult);

if (!$employee) {
        $displayEmail = htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8');
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i> 
                        No employee record is linked to <strong>' . $displayEmail . '</strong>. Ask an administrator to create an employee record or set its email to this address.
          </div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Handle leave application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_leave'])) {
    $leaveTypeId = (int)$_POST['leave_type_id'];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $reason = trim($_POST['reason']);
    
    // Handle document upload
    $documentPath = null;
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/leave_documents/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $ext = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
        $filename = 'leave_' . $employee['id'] . '_' . time() . '.' . $ext;
        
        if (move_uploaded_file($_FILES['document']['tmp_name'], $uploadDir . $filename)) {
            $documentPath = 'uploads/leave_documents/' . $filename;
        }
    }
    
    $result = applyForLeave($employee['id'], $leaveTypeId, $startDate, $endDate, $reason, $documentPath);
    
    if ($result['success']) {
        setFlash('success', $result['message']);
        redirect('my_leaves.php');
    } else {
        $error = $result['message'];
    }
}

$leaveTypes = getLeaveTypes();
$balances = getEmployeeLeaveBalances($employee['id']);
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-gray-900">🏖️ Apply for Leave</h1>
        <p class="text-gray-500 text-sm mt-1">Submit a new leave request</p>
    </div>
    <a href="my_leaves.php" class="mt-3 md:mt-0 bg-gray-200 text-gray-700 px-4 py-2 rounded-xl hover:bg-gray-300 transition">
        <i class="fas fa-arrow-left mr-2"></i> My Leaves
    </a>
</div>

<?php if ($error): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
</div>
<?php endif; ?>

<!-- Leave Balances -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
    <?php foreach (array_slice($balances, 0, 5) as $b): 
        $remaining = $b['allocated_days'] + $b['carried_over'] - $b['used_days'] - $b['pending_days'];
    ?>
    <div class="bg-white rounded-xl shadow-sm p-3 border-l-4" style="border-color: <?php echo $b['color']; ?>">
        <div class="flex items-center space-x-2">
            <i class="fas <?php echo $b['icon']; ?>" style="color: <?php echo $b['color']; ?>"></i>
            <p class="text-xs text-gray-500 truncate"><?php echo $b['type_name']; ?></p>
        </div>
        <p class="text-xl font-black mt-1" style="color: <?php echo $b['color']; ?>"><?php echo $remaining; ?></p>
        <p class="text-xs text-gray-400">of <?php echo $b['allocated_days']; ?> days</p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Application Form -->
<div class="bg-white rounded-xl shadow-sm p-6">
    <h3 class="font-bold text-gray-900 mb-4">📝 Leave Application Form</h3>
    
    <form method="POST" enctype="multipart/form-data" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Leave Type *</label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                    <?php foreach ($leaveTypes as $lt): 
                        $balance = getLeaveBalance($employee['id'], $lt['id']);
                        $remaining = $balance ? ($balance['allocated_days'] + $balance['carried_over'] - $balance['used_days'] - $balance['pending_days']) : 0;
                    ?>
                    <label class="cursor-pointer">
                        <input type="radio" name="leave_type_id" value="<?php echo $lt['id']; ?>" 
                               class="peer hidden" required onchange="updateBalanceInfo(<?php echo $lt['id']; ?>)">
                        <div class="border-2 rounded-lg p-3 hover:bg-gray-50 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 transition">
                            <div class="flex items-center space-x-2">
                                <i class="fas <?php echo $lt['icon']; ?>" style="color: <?php echo $lt['color']; ?>"></i>
                                <span class="font-semibold text-sm"><?php echo $lt['type_name']; ?></span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1"><?php echo $remaining; ?> days available</p>
                            <?php if (!$lt['is_paid']): ?>
                            <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full mt-1 inline-block">Unpaid</span>
                            <?php endif; ?>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Start Date *</label>
                <input type="date" name="start_date" id="start_date" required min="<?php echo date('Y-m-d'); ?>"
                       onchange="calculateDays()"
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">End Date *</label>
                <input type="date" name="end_date" id="end_date" required min="<?php echo date('Y-m-d'); ?>"
                       onchange="calculateDays()"
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            
            <div class="md:col-span-2">
                <div id="daysInfo" class="hidden bg-blue-50 border border-blue-200 rounded-lg p-3">
                    <p class="text-sm text-blue-700">
                        <i class="fas fa-info-circle mr-1"></i>
                        Total working days: <strong id="totalDays">0</strong>
                        <span class="text-xs text-blue-500 ml-2">(weekends & holidays excluded)</span>
                    </p>
                </div>
            </div>
            
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Reason *</label>
                <textarea name="reason" rows="3" required 
                          placeholder="Explain the reason for your leave..."
                          class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
            
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Supporting Document 
                    <span class="text-xs text-gray-400">(Required for Sick, Maternity, Paternity)</span>
                </label>
                <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png"
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <p class="text-xs text-gray-400 mt-1">PDF, JPG, PNG (Max 5MB)</p>
            </div>
        </div>
        
        <div class="flex gap-3 pt-4">
            <button type="submit" name="apply_leave" 
                    class="bg-indigo-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-indigo-700 transition">
                <i class="fas fa-paper-plane mr-2"></i> Submit Request
            </button>
            <a href="my_leaves.php" class="bg-gray-200 text-gray-700 px-6 py-3 rounded-xl font-bold hover:bg-gray-300 transition">
                Cancel
            </a>
        </div>
    </form>
</div>

<!-- Leave Policy Info -->
<div class="bg-blue-50 border border-blue-200 rounded-xl p-6 mt-6">
    <h4 class="font-bold text-blue-900 mb-3">📌 Leave Policy Guidelines</h4>
    <ul class="text-sm text-blue-800 space-y-1">
        <li>• Requests must be submitted at least 7 days in advance (except emergency/sick leave)</li>
        <li>• Sick leave requires a medical certificate</li>
        <li>• Maternity/Paternity leave requires supporting documentation</li>
        <li>• Weekends and public holidays are automatically excluded</li>
        <li>• Unapproved leaves will be treated as unpaid</li>
    </ul>
</div>

<script>
function updateBalanceInfo(typeId) {
    // Optional: Show balance info
}

function calculateDays() {
    const start = document.getElementById('start_date').value;
    const end = document.getElementById('end_date').value;
    
    if (!start || !end) return;
    
    if (new Date(end) < new Date(start)) {
        alert('End date cannot be before start date');
        document.getElementById('end_date').value = '';
        return;
    }
    
    // Simple client-side calculation (excludes weekends only)
    const startDate = new Date(start);
    const endDate = new Date(end);
    let days = 0;
    let current = new Date(startDate);
    
    while (current <= endDate) {
        const day = current.getDay();
        if (day !== 0 && day !== 6) days++;
        current.setDate(current.getDate() + 1);
    }
    
    document.getElementById('totalDays').textContent = days;
    document.getElementById('daysInfo').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>