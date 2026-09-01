<?php
require_once "includes/db.php";
require_once "includes/auth.php";
require_login();

$page_title = "Dashboard";
$role = $_SESSION["role"];

$users = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()["c"];
$medicines = $conn->query("SELECT COUNT(*) AS c FROM medicines")->fetch_assoc()["c"];
$stock = $conn->query("SELECT COALESCE(SUM(current_stock), 0) AS c FROM inventory")->fetch_assoc()["c"];
$sales_total = $conn->query("SELECT COALESCE(SUM(total_amount), 0) AS c FROM sales")->fetch_assoc()["c"];
$purchases_total = $conn->query("SELECT COALESCE(SUM(total_amount), 0) AS c FROM purchases WHERE purchase_status = 'Received'")->fetch_assoc()["c"];
$sales_count = $conn->query("SELECT COUNT(*) AS c FROM sales")->fetch_assoc()["c"];

$profit = 0;
$low_stock = [];
$near_expiry = [];

if ($role === "Admin" || $role === "Pharmacist") {
    $p = $conn->query(
        "SELECT COALESCE(SUM(s.total_amount) - SUM(s.quantity_sold * m.purchase_price), 0) AS profit
         FROM sales s
         JOIN medicines m ON s.medicine_id = m.medicine_id"
    )->fetch_assoc();
    $profit = $p["profit"];

    $limit = LOW_STOCK;
    $stmt = $conn->prepare(
        "SELECT m.medicine_id, m.medicine_name, i.current_stock, m.category, m.expire_date, i.stock_status
         FROM inventory i
         JOIN medicines m ON i.medicine_id = m.medicine_id
         WHERE i.current_stock < ?
         ORDER BY i.current_stock ASC"
    );
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $low_stock = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $days = EXPIRY_DAYS;
    $stmt = $conn->prepare(
        "SELECT m.medicine_id, m.medicine_name, i.current_stock, m.category, m.expire_date
         FROM medicines m
         JOIN inventory i ON m.medicine_id = i.medicine_id
         WHERE m.expire_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
         ORDER BY m.expire_date ASC"
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
        <p><?php echo number_format((float) $sales_total, 2); ?></p>
    </div>
    <div class="card-stat">
        <h3>Total purchases</h3>
        <p><?php echo number_format((float) $purchases_total, 2); ?></p>
    </div>
    <div class="card-stat">
        <h3>Sales count</h3>
        <p><?php echo (int) $sales_count; ?></p>
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
                <th>Status</th>
                <th>Expire</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($low_stock as $row) { ?>
            <tr>
                <td><?php echo (int) $row["medicine_id"]; ?></td>
                <td><?php echo h($row["medicine_name"]); ?></td>
                <td><?php echo (int) $row["current_stock"]; ?></td>
                <td><?php echo h($row["category"]); ?></td>
                <td><?php echo h($row["stock_status"]); ?></td>
                <td><?php echo h($row["expire_date"]); ?></td>
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
                <th>Expire</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($near_expiry as $row) { ?>
            <tr>
                <td><?php echo (int) $row["medicine_id"]; ?></td>
                <td><?php echo h($row["medicine_name"]); ?></td>
                <td><?php echo (int) $row["current_stock"]; ?></td>
                <td><?php echo h($row["category"]); ?></td>
                <td><?php echo h($row["expire_date"]); ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
    <?php } ?>
</div>
<?php } ?>

<?php require_once "includes/footer.php"; ?>
