<?php
require_once "includes/db.php";
require_once "includes/auth.php";
require_login();
require_role("Admin", "Pharmacist", "Employee");

$id = (int) ($_GET["id"] ?? 0);
$stmt = $conn->prepare(
    "SELECT i.*, u.name AS generated_by_name
     FROM invoices i
     LEFT JOIN users u ON i.generated_by = u.user_id
     WHERE i.invoice_id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$invoice) {
    header("Location: invoices.php");
    exit;
}

$items = json_decode($invoice["medicine_details"], true);
if (!is_array($items)) {
    $items = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo (int) $invoice["invoice_id"]; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body style="display:block; background:#f4f6f9;">
    <div class="receipt">
        <h1>Khan Pharmacy</h1>
        <p style="text-align:center">Invoice #<?php echo (int) $invoice["invoice_id"]; ?></p>
        <p><strong>Customer:</strong> <?php echo h($invoice["customer_name"]); ?></p>
        <p><strong>Date:</strong> <?php echo h($invoice["invoice_date"]); ?></p>
        <p><strong>Payment:</strong> <?php echo h($invoice["payment_method"]); ?></p>
        <p><strong>Served by:</strong> <?php echo h($invoice["generated_by_name"] ?? ""); ?></p>
        <table style="margin-top:16px">
            <thead>
                <tr>
                    <th>Medicine</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item) { ?>
                <tr>
                    <td><?php echo h($item["name"] ?? ""); ?></td>
                    <td><?php echo (int) ($item["qty"] ?? 0); ?></td>
                    <td><?php echo number_format((float) ($item["price"] ?? 0), 2); ?></td>
                    <td><?php echo number_format(((float) ($item["price"] ?? 0)) * ((int) ($item["qty"] ?? 0)), 2); ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        <p style="margin-top:16px"><strong>Discount:</strong> <?php echo number_format((float) $invoice["discount_applied"], 2); ?></p>
        <p><strong>Total:</strong> <?php echo number_format((float) $invoice["total_amount"], 2); ?></p>
        <p class="no-print" style="margin-top:20px">
            <button type="button" class="save-btn" onclick="window.print()">Print</button>
            <a class="cancel-btn" href="invoices.php">Back</a>
        </p>
    </div>
</body>
</html>
