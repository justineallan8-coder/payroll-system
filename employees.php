<?php
$page_title = 'Employees';
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/function.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/includes/header.php';

$employees = mysqli_query($conn, "SELECT * FROM employees ORDER BY created_at DESC");
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-gray-900">Employees</h1>
        <p class="text-gray-500 text-sm mt-1">Manage your employees</p>
    </div>
    <?php if (isAdmin()): ?>
    <a href="add employee.php" class="mt-3 md:mt-0 bg-indigo-600 text-white px-4 py-2 rounded-xl hover:bg-indigo-700 transition flex items-center space-x-2">
        <i class="fas fa-plus-circle"></i>
        <span>Add Employee</span>
    </a>
    <?php endif; ?>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Position</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Basic Salary</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php while ($emp = mysqli_fetch_assoc($employees)): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-medium"><?php echo $emp['employee_id']; ?></td>
                    <td class="px-6 py-4">
                        <p class="font-semibold text-gray-900"><?php echo $emp['first_name'] . ' ' . $emp['last_name']; ?></p>
                        <p class="text-xs text-gray-500"><?php echo $emp['email']; ?></p>
                    </td>
                    <td class="px-6 py-4 text-sm"><?php echo $emp['position']; ?></td>
                    <td class="px-6 py-4 text-sm"><?php echo $emp['department']; ?></td>
                    <td class="px-6 py-4 font-bold"><?php echo formatMoney($emp['basic_salary']); ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded-full text-xs font-semibold
                            <?php echo $emp['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'; ?>">
                            <?php echo ucfirst($emp['status']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <?php if (isAdmin()): ?>
                        <div class="flex space-x-2">
                            <a href="edit employee.php?id=<?php echo $emp['id']; ?>" class="text-blue-600 hover:text-blue-700">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="delete employee.php?id=<?php echo $emp['id']; ?>" 
                               onclick="return confirm('Delete this employee?')"
                               class="text-red-600 hover:text-red-700">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                        <?php else: ?>
                        <span class="text-xs text-gray-400">View only</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>