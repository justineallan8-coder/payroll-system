<?php
session_start();
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/function.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $employee = mysqli_fetch_assoc(mysqli_query($conn, "SELECT first_name, last_name, email, employee_id FROM employees WHERE id = $id"));
    // Check if employee has payroll records
    $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM payroll WHERE employee_id = $id");
    $payrollCount = mysqli_fetch_assoc($check)['count'];
    
    if ($payrollCount > 0) {
        // Soft delete - just mark as inactive
        $sql = "UPDATE employees SET status = 'inactive' WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            logActivity($_SESSION['user_id'], 'delete_employee', "Deactivated employee: " . ($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
            sendUpdateNotification($employee['email'], $employee['first_name'] . ' ' . $employee['last_name'], 'Employee account deactivated', 'Your employee account has been marked inactive by an administrator.');
            setFlash('warning', 'Employee has payroll records. Status changed to inactive instead of deleting.');
        } else {
            setFlash('error', 'Error: ' . mysqli_error($conn));
        }
    } else {
        // Hard delete - no payroll records
        $sql = "DELETE FROM employees WHERE id = $id";
        if (mysqli_query($conn, $sql)) {
            logActivity($_SESSION['user_id'], 'delete_employee', "Deleted employee: " . ($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
            sendUpdateNotification($employee['email'], $employee['first_name'] . ' ' . $employee['last_name'], 'Employee record deleted', 'Your employee record has been deleted from the payroll system.');
            setFlash('success', 'Employee deleted successfully!');
        } else {
            setFlash('error', 'Error: ' . mysqli_error($conn));
        }
    }
} else {
    setFlash('error', 'Invalid employee ID!');
}

redirect('employees.php');
?>