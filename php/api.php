<?php
header('Content-Type: application/json');
require_once 'db.php';

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {

        // 1. Users
        case 'get_users':
            $stmt = $pdo->query("SELECT user_id, name, role, username, email, password_reset_token, created_at FROM users ORDER BY user_id ASC");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
            break;

        // 2. Medicines
        case 'get_medicines':
            $stmt = $pdo->query("SELECT m.*, s.supplier_name FROM medicines m LEFT JOIN suppliers s ON m.supplier_id = s.supplier_id ORDER BY m.medicine_id ASC");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
            break;

        // 3. Suppliers
        case 'get_suppliers':
            $stmt = $pdo->query("SELECT * FROM suppliers ORDER BY supplier_id ASC");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
            break;

        // 4. Antibiotics
        case 'get_antibiotics':
            $stmt = $pdo->query("SELECT a.*, m.medicine_name, m.company_name, m.unit_price FROM antibiotic_list a JOIN medicines m ON a.medicine_id = m.medicine_id ORDER BY a.antibiotic_id ASC");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
            break;

        // 5. Stock / Inventory
        case 'get_inventory':
            $stmt = $pdo->query("SELECT si.*, m.medicine_name, u.name AS updated_by_name FROM stock_inventory si JOIN medicines m ON si.medicine_id = m.medicine_id LEFT JOIN users u ON si.updated_by = u.user_id ORDER BY si.stock_id DESC");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
            break;

        // 6. Sales / Purchase Transaction
        case 'get_sales':
            $stmt = $pdo->query("SELECT st.*, m.medicine_name, u.name AS sold_by_name FROM sales_transactions st JOIN medicines m ON st.medicine_id = m.medicine_id LEFT JOIN users u ON st.sold_by = u.user_id ORDER BY st.sales_id DESC");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
            break;

        // 7. Sales Reports
        case 'get_reports':
            $stmt = $pdo->query("SELECT * FROM sales_reports ORDER BY report_id DESC");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
            break;

        case 'add_report':
            $report_type = $_POST['report_type'] ?? 'Daily';
            $total_sales_amount = floatval($_POST['total_sales_amount'] ?? 0);
            $total_purchase_amount = floatval($_POST['total_purchase_amount'] ?? 0);
            $profit_loss = isset($_POST['profit_loss']) ? floatval($_POST['profit_loss']) : ($total_sales_amount - $total_purchase_amount);
            $report_date = $_POST['report_date'] ?? date('Y-m-d');

            $stmt = $pdo->prepare("INSERT INTO sales_reports (report_type, total_sales_amount, total_purchase_amount, profit_loss, report_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$report_type, $total_sales_amount, $total_purchase_amount, $profit_loss, $report_date]);
            echo json_encode(['status' => 'success', 'message' => 'Sales report created successfully.', 'report_id' => $pdo->lastInsertId()]);
            break;

        case 'update_report':
            $report_id = intval($_POST['report_id'] ?? 0);
            $report_type = $_POST['report_type'] ?? 'Daily';
            $total_sales_amount = floatval($_POST['total_sales_amount'] ?? 0);
            $total_purchase_amount = floatval($_POST['total_purchase_amount'] ?? 0);
            $profit_loss = isset($_POST['profit_loss']) ? floatval($_POST['profit_loss']) : ($total_sales_amount - $total_purchase_amount);
            $report_date = $_POST['report_date'] ?? date('Y-m-d');

            $stmt = $pdo->prepare("UPDATE sales_reports SET report_type = ?, total_sales_amount = ?, total_purchase_amount = ?, profit_loss = ?, report_date = ? WHERE report_id = ?");
            $stmt->execute([$report_type, $total_sales_amount, $total_purchase_amount, $profit_loss, $report_date, $report_id]);
            echo json_encode(['status' => 'success', 'message' => 'Sales report updated successfully.']);
            break;

        case 'delete_report':
            $report_id = intval($_REQUEST['report_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM sales_reports WHERE report_id = ?");
            $stmt->execute([$report_id]);
            echo json_encode(['status' => 'success', 'message' => 'Sales report deleted successfully.']);
            break;

        // 8. Employee Salaries
        case 'get_salaries':
            $stmt = $pdo->query("SELECT es.*, u.name AS employee_name, u.role FROM employee_salaries es JOIN users u ON es.employee_id = u.user_id ORDER BY es.salary_id DESC");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
            break;

        // 9. Customers
        case 'get_customers':
            $stmt = $pdo->query("SELECT * FROM customers ORDER BY customer_id ASC");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
            break;

        // 10. Invoices
        case 'get_invoices':
            $stmt = $pdo->query("SELECT i.*, u.name AS generated_by_name FROM invoices i LEFT JOIN users u ON i.generated_by = u.user_id ORDER BY i.invoice_id DESC");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
            break;

        default:
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid or missing action parameter.'
            ]);
            break;
    }
} catch (\PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
