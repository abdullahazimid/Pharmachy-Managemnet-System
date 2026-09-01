<?php
require_once "includes/db.php";
require_once "includes/auth.php";
require_login();
require_role("Admin", "Pharmacist", "Employee");

$page_title = "Sales";
$extra_js = "js/sales.js";
$error = "";
$msg = "";
$user_id = (int) $_SESSION["user_id"];

if (isset($_GET["msg"]) && $_GET["msg"] === "saved") {
    $msg = "Sale completed.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $payment_method = $_POST["payment_method"] ?? "Cash";
    $medicine_ids = $_POST["medicine_id"] ?? [];
    $quantities = $_POST["quantity"] ?? [];

    if (!is_array($medicine_ids) || count($medicine_ids) === 0) {
        $error = "At least one medicine is required.";
    } else {
        $conn->begin_transaction();
        $ok = true;
        $sale_rows = [];

        for ($i = 0; $i < count($medicine_ids); $i++) {
            $mid = (int) $medicine_ids[$i];
            $qty = (int) ($quantities[$i] ?? 0);
            if ($mid <= 0 || $qty <= 0) {
                $ok = false;
                $error = "Invalid item quantity.";
                break;
            }

            $stmt = $conn->prepare(
                "SELECT m.medicine_id, m.medicine_name, m.sale_price, i.current_stock
                 FROM medicines m
                 INNER JOIN inventory i ON m.medicine_id = i.medicine_id
                 WHERE m.medicine_id = ? FOR UPDATE"
            );
            $stmt->bind_param("i", $mid);
            $stmt->execute();
            $med = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$med) {
                $ok = false;
                $error = "Medicine not found.";
                break;
            }
            if ((int) $med["current_stock"] < $qty) {
                $ok = false;
                $error = "Insufficient stock for " . $med["medicine_name"] . " (available: " . $med["current_stock"] . ").";
                break;
            }

            $sale_rows[] = ["med" => $med, "qty" => $qty];
        }

        if ($ok) {
            foreach ($sale_rows as $row) {
                $med = $row["med"];
                $qty = $row["qty"];
                $unit_price = (float) $med["sale_price"];
                $total_amount = $unit_price * $qty;
                $mid = (int) $med["medicine_id"];
                $next_stock = (int) $med["current_stock"] - $qty;

                $st = $conn->prepare(
                    "INSERT INTO sales (medicine_id, quantity_sold, unit_price, total_amount, payment_method, sold_by)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $st->bind_param("iiddsi", $mid, $qty, $unit_price, $total_amount, $payment_method, $user_id);
                $st->execute();
                $st->close();

                $status = alert_status_for_stock($next_stock);
                $up = $conn->prepare("UPDATE inventory SET current_stock = ?, stock_status = ?, updated_by = ? WHERE medicine_id = ?");
                $up->bind_param("isii", $next_stock, $status, $user_id, $mid);
                $up->execute();
                $up->close();
            }
        }

        if ($ok) {
            $conn->commit();
            header("Location: sales.php?msg=saved");
            exit;
        }

        $conn->rollback();
    }
}

$medicines = $conn->query(
    "SELECT m.medicine_id, m.medicine_name, m.category, m.sale_price, COALESCE(i.current_stock, 0) AS current_stock
     FROM medicines m
     LEFT JOIN inventory i ON m.medicine_id = i.medicine_id
     WHERE COALESCE(i.current_stock, 0) > 0
     ORDER BY m.medicine_name"
)->fetch_all(MYSQLI_ASSOC);

$sales = $conn->query(
    "SELECT s.*, m.medicine_name, m.category, u.username AS sold_by_name
     FROM sales s
     JOIN medicines m ON s.medicine_id = m.medicine_id
     LEFT JOIN users u ON s.sold_by = u.user_id
     ORDER BY s.sale_date_time DESC, s.sale_id DESC"
)->fetch_all(MYSQLI_ASSOC);

require_once "includes/header.php";
?>

<?php if ($msg !== "") { ?><div class="msg-ok"><?php echo h($msg); ?></div><?php } ?>
<?php if ($error !== "") { ?><div class="msg-err"><?php echo h($error); ?></div><?php } ?>

<div class="card">
    <h2>New sale</h2>
    <form method="post" id="sale-form">
        <div class="form-row">
            <div class="form-group">
                <label>Payment</label>
                <select name="payment_method">
                    <option value="Cash">Cash</option>
                    <option value="Card">Card</option>
                    <option value="bKash">bKash</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Medicine</label>
                <select id="med-select">
                    <option value="">Select</option>
                    <?php foreach ($medicines as $m) {
                        $category_label = medicine_category_label($m["category"]);
                    ?>
                    <option
                        value="<?php echo (int) $m["medicine_id"]; ?>"
                        data-name="<?php echo h($m["medicine_name"]); ?>"
                        data-category="<?php echo h($category_label); ?>"
                        data-price="<?php echo h($m["sale_price"]); ?>"
                        data-stock="<?php echo (int) $m["current_stock"]; ?>"
                    >
                        <?php echo h($m["medicine_name"]); ?> — <?php echo h($category_label); ?> (<?php echo (int) $m["current_stock"]; ?> left)
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-group">
                <label>Qty</label>
                <input type="number" id="med-qty" value="1" min="1">
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="button" class="add-btn" id="add-item-btn">Add item</button>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Medicine</th>
                    <th>Category</th>
                    <th>Qty</th>
                    <th>Unit price</th>
                    <th>Line total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="cart-body"></tbody>
        </table>
        <p style="margin-top:12px">Total: <strong id="grand-total">0.00</strong></p>
        <div class="form-actions">
            <button type="submit" class="save-btn">Complete sale</button>
        </div>
    </form>
</div>

<input type="text" class="search-box" data-table="data-table" placeholder="Search sales...">
<table id="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Medicine</th>
            <th>Category</th>
            <th>Qty</th>
            <th>Unit price</th>
            <th>Total</th>
            <th>Payment</th>
            <th>Sold by</th>
            <th>Date</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($sales as $row) { ?>
        <tr>
            <td><?php echo (int) $row["sale_id"]; ?></td>
            <td><?php echo h($row["medicine_name"]); ?></td>
            <td><?php echo h(medicine_category_label($row["category"])); ?></td>
            <td><?php echo (int) $row["quantity_sold"]; ?></td>
            <td><?php echo number_format((float) $row["unit_price"], 2); ?></td>
            <td><?php echo number_format((float) $row["total_amount"], 2); ?></td>
            <td><?php echo h($row["payment_method"]); ?></td>
            <td><?php echo h($row["sold_by_name"] ?? ""); ?></td>
            <td><?php echo h($row["sale_date_time"]); ?></td>
        </tr>
        <?php } ?>
    </tbody>
</table>

<?php require_once "includes/footer.php"; ?>
