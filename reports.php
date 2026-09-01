<?php
require_once "includes/db.php";
require_once "includes/auth.php";
require_login();
require_role("Admin", "Pharmacist");

$page_title = "Reports";
$type = strtolower($_GET["type"] ?? "daily") === "monthly" ? "Monthly" : "Daily";

if ($type === "Daily") {
    $sales_rows = $conn->query(
        "SELECT DATE(s.sale_date_time) AS report_date,
                SUM(s.total_amount) AS total_sales_amount,
                SUM(s.quantity_sold * m.purchase_price) AS total_purchase_cost,
                SUM(s.total_amount) - SUM(s.quantity_sold * m.purchase_price) AS profit_loss
         FROM sales s
         JOIN medicines m ON s.medicine_id = m.medicine_id
         GROUP BY DATE(s.sale_date_time)
         ORDER BY report_date DESC
         LIMIT 60"
    )->fetch_all(MYSQLI_ASSOC);

    $purchase_rows = $conn->query(
        "SELECT DATE(purchase_date) AS report_date,
                SUM(total_amount) AS total_purchase_amount
         FROM purchases
         WHERE purchase_status = 'Received'
         GROUP BY DATE(purchase_date)
         ORDER BY report_date DESC
         LIMIT 60"
    )->fetch_all(MYSQLI_ASSOC);
} else {
    $sales_rows = $conn->query(
        "SELECT DATE_FORMAT(s.sale_date_time, '%Y-%m-01') AS report_date,
                SUM(s.total_amount) AS total_sales_amount,
                SUM(s.quantity_sold * m.purchase_price) AS total_purchase_cost,
                SUM(s.total_amount) - SUM(s.quantity_sold * m.purchase_price) AS profit_loss
         FROM sales s
         JOIN medicines m ON s.medicine_id = m.medicine_id
         GROUP BY DATE_FORMAT(s.sale_date_time, '%Y-%m')
         ORDER BY report_date DESC
         LIMIT 24"
    )->fetch_all(MYSQLI_ASSOC);

    $purchase_rows = $conn->query(
        "SELECT DATE_FORMAT(purchase_date, '%Y-%m-01') AS report_date,
                SUM(total_amount) AS total_purchase_amount
         FROM purchases
         WHERE purchase_status = 'Received'
         GROUP BY DATE_FORMAT(purchase_date, '%Y-%m')
         ORDER BY report_date DESC
         LIMIT 24"
    )->fetch_all(MYSQLI_ASSOC);
}

require_once "includes/header.php";
?>

<div class="tabs">
    <a href="reports.php?type=daily" class="<?php echo $type === "Daily" ? "active" : ""; ?>">Daily</a>
    <a href="reports.php?type=monthly" class="<?php echo $type === "Monthly" ? "active" : ""; ?>">Monthly</a>
</div>

<div class="card">
    <h2>Sales report (<?php echo h($type); ?>)</h2>
    <table id="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Sales</th>
                <th>COGS</th>
                <th>Profit / Loss</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sales_rows as $row) { ?>
            <tr>
                <td><?php echo h($row["report_date"]); ?></td>
                <td><?php echo number_format((float) $row["total_sales_amount"], 2); ?></td>
                <td><?php echo number_format((float) $row["total_purchase_cost"], 2); ?></td>
                <td><?php echo number_format((float) $row["profit_loss"], 2); ?></td>
            </tr>
            <?php } ?>
            <?php if (count($sales_rows) === 0) { ?>
            <tr><td colspan="4">No sales yet.</td></tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Purchase report (<?php echo h($type); ?>)</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Total purchases</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($purchase_rows as $row) { ?>
            <tr>
                <td><?php echo h($row["report_date"]); ?></td>
                <td><?php echo number_format((float) $row["total_purchase_amount"], 2); ?></td>
            </tr>
            <?php } ?>
            <?php if (count($purchase_rows) === 0) { ?>
            <tr><td colspan="2">No purchases yet.</td></tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php require_once "includes/footer.php"; ?>
