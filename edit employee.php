<?php
$page_title = 'Edit Employee';
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/function.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

// Get employee data
$result = mysqli_query($conn, "SELECT * FROM employees WHERE id = $id");
$employee = mysqli_fetch_assoc($result);

if (!$employee) {
    setFlash('error', 'Employee not found!');
    redirect('employees.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = mysqli_real_escape_string($conn, $_POST['employee_id']);
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $position = mysqli_real_escape_string($conn, $_POST['position']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $basic_salary = (float)$_POST['basic_salary'];
    $allowances = (float)$_POST['allowances'];
    $hire_date = $_POST['hire_date'];
    $status = $_POST['status'];
    
    // Check if employee ID exists (excluding current)
    $check = mysqli_query($conn, "SELECT id FROM employees WHERE employee_id = '$employee_id' AND id != $id");
    if (mysqli_num_rows($check) > 0) {
        $error = 'Employee ID already exists!';
    } else {
        $sql = "UPDATE employees SET 
                employee_id = '$employee_id',
                first_name = '$first_name',
                last_name = '$last_name',
                email = '$email',
                phone = '$phone',
                position = '$position',
                department = '$department',
                basic_salary = $basic_salary,
                allowances = $allowances,
                hire_date = '$hire_date',
                status = '$status'
                WHERE id = $id";
        
        if (mysqli_query($conn, $sql)) {
            logActivity($_SESSION['user_id'], 'edit_employee', "Updated employee: $first_name $last_name ($employee_id)");
            sendUpdateNotification($email, "$first_name $last_name", 'Employee record updated', 'Your employee information was updated by an administrator.');
            setFlash('success', 'Employee updated successfully!');
            redirect('employees.php');
        } else {
            $error = 'Error: ' . mysqli_error($conn);
        }
    }
}
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-gray-900">Edit Employee</h1>
        <p class="text-gray-500 text-sm mt-1">Update employee information</p>
    </div>
    <a href="employees.php" class="mt-3 md:mt-0 bg-gray-200 text-gray-700 px-4 py-2 rounded-xl hover:bg-gray-300 transition">
        <i class="fas fa-arrow-left mr-2"></i> Back
    </a>
</div>

<?php if ($error): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
    <?php echo $error; ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm p-6">
    <form method="POST" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Employee ID *</label>
                <input type="text" name="employee_id" value="<?php echo htmlspecialchars($employee['employee_id']); ?>" required 
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Hire Date *</label>
                <input type="date" name="hire_date" value="<?php echo $employee['hire_date']; ?>" required 
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                <input type="text" name="first_name" value="<?php echo htmlspecialchars($employee['first_name']); ?>" required 
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                <input type="text" name="last_name" value="<?php echo htmlspecialchars($employee['last_name']); ?>" required 
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($employee['email']); ?>" 
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($employee['phone']); ?>" 
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                <input type="text" name="position" value="<?php echo htmlspecialchars($employee['position']); ?>" 
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                <input type="text" name="department" value="<?php echo htmlspecialchars($employee['department']); ?>" 
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Basic Salary (TSh) *</label>
                <input type="number" name="basic_salary" value="<?php echo $employee['basic_salary']; ?>" required min="0" step="1000"
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Allowances (TSh)</label>
                <input type="number" name="allowances" value="<?php echo $employee['allowances']; ?>" min="0" step="1000"
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="active" <?php echo $employee['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $employee['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
        </div>
        
        <div class="flex gap-3 pt-4">
            <button type="submit" class="bg-indigo-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-indigo-700 transition">
                <i class="fas fa-save mr-2"></i> Update Employee
            </button>
            <a href="employees.php" class="bg-gray-200 text-gray-700 px-6 py-3 rounded-xl font-bold hover:bg-gray-300 transition">
                Cancel
            </a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>