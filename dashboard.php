<?php
require_once "includes/db.php";
require_once "includes/auth.php";
require_login();

$page_title = "Dashboard";
$role = $_SESSION["role"];

$users = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()["c"];
$medicines = $conn->query("SELECT COUNT(*) AS c FROM medicines")->fetch_assoc()["c"];
$stock = $conn->query("SELECT COALESCE(SUM(quantity_in_stock), 0) AS c FROM medicines")->fetch_assoc()["c"];
$sales = $conn->query("SELECT COALESCE(SUM(total_price), 0) AS c FROM sales_transactions")->fetch_assoc()["c"];
$invoices = $conn->query("SELECT COUNT(*) AS c FROM invoices")->fetch_assoc()["c"];
$customers = $conn->query("SELECT COUNT(*) AS c FROM customers")->fetch_assoc()["c"];

$profit = 0;
$low_stock = [];
$near_expiry = [];

if ($role === "Admin" || $role === "Pharmacist") {
    $p = $conn->query(
        "SELECT COALESCE(SUM(st.total_price) - SUM(st.quantity_sold * m.purchase_price), 0) AS profit
         FROM sales_transactions st
         JOIN medicines m ON st.medicine_id = m.medicine_id"
    )->fetch_assoc();
    $profit = $p["profit"];

    $days = EXPIRY_DAYS;
    $limit = LOW_STOCK;
    $stmt = $conn->prepare(
        "SELECT medicine_id, medicine_name, quantity_in_stock, category, expiry_date
         FROM medicines
         WHERE quantity_in_stock < ?
         ORDER BY quantity_in_stock ASC"
    );
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $low_stock = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT medicine_id, medicine_name, quantity_in_stock, category, expiry_date
         FROM medicines
         WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
         ORDER BY expiry_date ASC"
    );
    $stmt->bind_param("i", $days);
    $stmt->execute();
    $near_expiry = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

require_once "includes/header.php";
?>

<div class="cards">
    <div class="card-stat">
        <h3>Users</h3>
        <p><?php echo (int) $users; ?></p>
    </div>
    <div class="card-stat">
        <h3>Medicines</h3>
        <p><?php echo (int) $medicines; ?></p>
    </div>
    <div class="card-stat">
        <h3>Stock units</h3>
        <p><?php echo (int) $stock; ?></p>
    </div>
    <div class="card-stat">
        <h3>Total sales</h3>
        <p><?php echo number_format((float) $sales, 2); ?></p>
    </div>
    <div class="card-stat">
        <h3>Invoices</h3>
        <p><?php echo (int) $invoices; ?></p>
    </div>
    <div class="card-stat">
        <h3>Customers</h3>
        <p><?php echo (int) $customers; ?></p>
    </div>
    <?php if ($role === "Admin" || $role === "Pharmacist") { ?>
    <div class="card-stat">
        <h3>Profit / Loss</h3>
        <p><?php echo number_format((float) $profit, 2); ?></p>
    </div>
    <?php } ?>
</div>

<?php if ($role === "Admin" || $role === "Pharmacist") { ?>
<div class="card">
    <h2>Low stock (below <?php echo LOW_STOCK; ?>)</h2>
    <?php if (count($low_stock) === 0) { ?>
        <p>No low stock items.</p>
    <?php } else { ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Medicine</th>
                <th>Stock</th>
                <th>Category</th>
                <th>Expiry</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($low_stock as $row) { ?>
            <tr>
                <td><?php echo (int) $row["medicine_id"]; ?></td>
                <td><?php echo h($row["medicine_name"]); ?></td>
                <td><?php echo (int) $row["quantity_in_stock"]; ?></td>
                <td><?php echo h($row["category"]); ?></td>
                <td><?php echo h($row["expiry_date"]); ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
    <?php } ?>
</div>

<div class="card">
    <h2>Near expiry (next <?php echo EXPIRY_DAYS; ?> days)</h2>
    <?php if (count($near_expiry) === 0) { ?>
        <p>No near expiry items.</p>
    <?php } else { ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Medicine</th>
                <th>Stock</th>
                <th>Category</th>
                <th>Expiry</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($near_expiry as $row) { ?>
            <tr>
                <td><?php echo (int) $row["medicine_id"]; ?></td>
                <td><?php echo h($row["medicine_name"]); ?></td>
                <td><?php echo (int) $row["quantity_in_stock"]; ?></td>
                <td><?php echo h($row["category"]); ?></td>
                <td><?php echo h($row["expiry_date"]); ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
    <?php } ?>
</div>
<?php } ?>

<?php require_once "includes/footer.php"; ?>
