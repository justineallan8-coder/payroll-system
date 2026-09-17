<?php
$page_title = 'Add Employee';
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/function.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/leave_config.php';
requireAdmin();
require_once __DIR__ . '/includes/header.php';

$message = '';
$error = '';

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
    
    // Check if employee ID exists
    $check = mysqli_query($conn, "SELECT id FROM employees WHERE employee_id = '$employee_id'");
    if (mysqli_num_rows($check) > 0) {
        $error = 'Employee ID already exists!';
    } else {
        $sql = "INSERT INTO employees (employee_id, first_name, last_name, email, phone, position, department, basic_salary, allowances, hire_date) 
                VALUES ('$employee_id', '$first_name', '$last_name', '$email', '$phone', '$position', '$department', $basic_salary, $allowances, '$hire_date')";
        
        if (mysqli_query($conn, $sql)) {
            $newEmployeeId = mysqli_insert_id($conn);
            initializeEmployeeLeaveBalances($newEmployeeId);
            logActivity($_SESSION['user_id'], 'add_employee', "Added employee: $first_name $last_name ($employee_id)");
            sendUpdateNotification($email, "$first_name $last_name", 'Employee record created', 'Your employee record has been created in the payroll system.');
            setFlash('success', 'Employee added successfully!');
            redirect('employees.php');
        } else {
            $error = 'Error: ' . mysqli_error($conn);
        }
    }
}
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-gray-900">Add Employee</h1>
        <p class="text-gray-500 text-sm mt-1">Add a new employee to the system</p>
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
                <input type="text" name="employee_id" required 
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Hire Date *</label>
                <input type="date" name="hire_date" required 
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                <input type="text" name="first_name" required 
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                <input type="text" name="last_name" required 
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" 
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                <input type="text" name="phone" 
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                <input type="text" name="position" 
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                <input type="text" name="department" 
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Basic Salary (TSh) *</label>
                <input type="number" name="basic_salary" required min="0" step="1000"
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Allowances (TSh)</label>
                <input type="number" name="allowances" value="0" min="0" step="1000"
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>
        
        <div class="flex gap-3 pt-4">
            <button type="submit" class="bg-indigo-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-indigo-700 transition">
                <i class="fas fa-save mr-2"></i> Save Employee
            </button>
            <a href="employees.php" class="bg-gray-200 text-gray-700 px-6 py-3 rounded-xl font-bold hover:bg-gray-300 transition">
                Cancel
            </a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>