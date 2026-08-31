<?php
require_once "includes/db.php";
require_once "includes/auth.php";
require_login();
require_role("Admin", "Pharmacist");

$page_title = "Reports";
$type = strtolower($_GET["type"] ?? "daily") === "monthly" ? "Monthly" : "Daily";

if ($type === "Daily") {
    $rows = $conn->query(
        "SELECT DATE(st.sale_date) AS report_date,
                SUM(st.total_price) AS total_sales_amount,
                SUM(st.quantity_sold * m.purchase_price) AS total_purchase_amount,
                SUM(st.total_price) - SUM(st.quantity_sold * m.purchase_price) AS profit_loss
         FROM sales_transactions st
         JOIN medicines m ON st.medicine_id = m.medicine_id
         GROUP BY DATE(st.sale_date)
         ORDER BY report_date DESC
         LIMIT 60"
    )->fetch_all(MYSQLI_ASSOC);
} else {
    $rows = $conn->query(
        "SELECT DATE_FORMAT(st.sale_date, '%Y-%m-01') AS report_date,
                SUM(st.total_price) AS total_sales_amount,
                SUM(st.quantity_sold * m.purchase_price) AS total_purchase_amount,
                SUM(st.total_price) - SUM(st.quantity_sold * m.purchase_price) AS profit_loss
         FROM sales_transactions st
         JOIN medicines m ON st.medicine_id = m.medicine_id
         GROUP BY DATE_FORMAT(st.sale_date, '%Y-%m')
         ORDER BY report_date DESC
         LIMIT 24"
    )->fetch_all(MYSQLI_ASSOC);
}

require_once "includes/header.php";
?>

<div class="tabs" style="margin-bottom:16px">
    <a href="reports.php?type=daily" class="<?php echo $type === "Daily" ? "active" : ""; ?>">Daily</a>
    <a href="reports.php?type=monthly" class="<?php echo $type === "Monthly" ? "active" : ""; ?>">Monthly</a>
</div>

<table id="data-table">
    <thead>
        <tr>
            <th>Type</th>
            <th>Date</th>
            <th>Sales</th>
            <th>Purchase cost</th>
            <th>Profit / Loss</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row) { ?>
        <tr>
            <td><?php echo h($type); ?></td>
            <td><?php echo h($row["report_date"]); ?></td>
            <td><?php echo number_format((float) $row["total_sales_amount"], 2); ?></td>
            <td><?php echo number_format((float) $row["total_purchase_amount"], 2); ?></td>
            <td><?php echo number_format((float) $row["profit_loss"], 2); ?></td>
        </tr>
        <?php } ?>
        <?php if (count($rows) === 0) { ?>
        <tr><td colspan="5">No sales yet.</td></tr>
        <?php } ?>
    </tbody>
</table>

<?php require_once "includes/footer.php"; ?>
