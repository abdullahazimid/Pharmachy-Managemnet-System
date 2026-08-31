<?php
require_once "includes/db.php";
require_once "includes/auth.php";
require_login();
require_role("Admin", "Pharmacist", "Employee");

$page_title = "Sales";
$extra_js = "js/sales.js";
$error = "";
$user_id = (int) $_SESSION["user_id"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $customer_name = trim($_POST["customer_name"] ?? "");
    $customer_contact = trim($_POST["customer_contact"] ?? "");
    $payment_method = $_POST["payment_method"] ?? "Cash";
    $discount_pct = (float) ($_POST["discount_percentage"] ?? 0);
    $medicine_ids = $_POST["medicine_id"] ?? [];
    $quantities = $_POST["quantity"] ?? [];

    if ($customer_name === "" || !is_array($medicine_ids) || count($medicine_ids) === 0) {
        $error = "Customer name and at least one medicine are required.";
    } else {
        $conn->begin_transaction();
        $ok = true;

        $customer_id = 0;
        if ($customer_contact !== "") {
            $find = $conn->prepare("SELECT customer_id FROM customers WHERE contact_no = ? LIMIT 1");
            $find->bind_param("s", $customer_contact);
            $find->execute();
            $found = $find->get_result()->fetch_assoc();
            $find->close();
            if ($found) {
                $customer_id = (int) $found["customer_id"];
            }
        }

        if ($customer_id === 0) {
            $contact = $customer_contact !== "" ? $customer_contact : "N/A";
            $empty = "";
            $ins = $conn->prepare("INSERT INTO customers (customer_name, contact_no, purchase_history) VALUES (?, ?, ?)");
            $ins->bind_param("sss", $customer_name, $contact, $empty);
            if (!$ins->execute()) {
                $ok = false;
                $error = "Could not save customer.";
            } else {
                $customer_id = $ins->insert_id;
            }
            $ins->close();
        }

        $details = [];
        $sale_rows = [];
        $subtotal = 0;

        if ($ok) {
            for ($i = 0; $i < count($medicine_ids); $i++) {
                $mid = (int) $medicine_ids[$i];
                $qty = (int) ($quantities[$i] ?? 0);
                if ($mid <= 0 || $qty <= 0) {
                    $ok = false;
                    $error = "Invalid item quantity.";
                    break;
                }

                $stmt = $conn->prepare("SELECT medicine_id, medicine_name, category, unit_price, quantity_in_stock FROM medicines WHERE medicine_id = ? FOR UPDATE");
                $stmt->bind_param("i", $mid);
                $stmt->execute();
                $med = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$med) {
                    $ok = false;
                    $error = "Medicine not found.";
                    break;
                }
                if ((int) $med["quantity_in_stock"] < $qty) {
                    $ok = false;
                    $error = "Insufficient stock for " . $med["medicine_name"] . " (available: " . $med["quantity_in_stock"] . ").";
                    break;
                }

                if ($med["category"] === "Antibiotic") {
                    $ab = $conn->prepare("SELECT allowed_range_limit FROM antibiotic_list WHERE medicine_id = ?");
                    $ab->bind_param("i", $mid);
                    $ab->execute();
                    $limit_row = $ab->get_result()->fetch_assoc();
                    $ab->close();
                    if ($limit_row && $qty > (int) $limit_row["allowed_range_limit"]) {
                        $ok = false;
                        $error = $med["medicine_name"] . " antibiotic limit is " . $limit_row["allowed_range_limit"] . " per sale.";
                        break;
                    }
                }

                $line = (float) $med["unit_price"] * $qty;
                $subtotal += $line;
                $details[] = [
                    "medicine_id" => (int) $med["medicine_id"],
                    "name" => $med["medicine_name"],
                    "qty" => $qty,
                    "price" => (float) $med["unit_price"],
                ];
                $sale_rows[] = ["med" => $med, "qty" => $qty, "line" => $line];
            }
        }

        $discount_amount = ($subtotal * $discount_pct) / 100;
        $total_amount = $subtotal - $discount_amount;
        $invoice_id = 0;

        if ($ok) {
            $details_json = json_encode($details);
            $inv = $conn->prepare(
                "INSERT INTO invoices (customer_id, customer_name, medicine_details, total_amount, discount_applied, payment_method, generated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $inv->bind_param("issddsi", $customer_id, $customer_name, $details_json, $total_amount, $discount_amount, $payment_method, $user_id);
            if (!$inv->execute()) {
                $ok = false;
                $error = "Could not create invoice.";
            } else {
                $invoice_id = $inv->insert_id;
            }
            $inv->close();
        }

        if ($ok) {
            foreach ($sale_rows as $row) {
                $med = $row["med"];
                $qty = $row["qty"];
                $line_total = $row["line"] * (1 - $discount_pct / 100);
                $next_stock = (int) $med["quantity_in_stock"] - $qty;
                $mid = (int) $med["medicine_id"];

                $up = $conn->prepare("UPDATE medicines SET quantity_in_stock = ? WHERE medicine_id = ?");
                $up->bind_param("ii", $next_stock, $mid);
                $up->execute();
                $up->close();

                $st = $conn->prepare(
                    "INSERT INTO sales_transactions (medicine_id, quantity_sold, total_price, discount_percentage, payment_method, sold_by, invoice_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $st->bind_param("iiddsii", $mid, $qty, $line_total, $discount_pct, $payment_method, $user_id, $invoice_id);
                $st->execute();
                $st->close();

                $zero = 0;
                $log = $conn->prepare("INSERT INTO stock_inventory (medicine_id, quantity_added, quantity_sold, updated_by) VALUES (?, ?, ?, ?)");
                $log->bind_param("iiii", $mid, $zero, $qty, $user_id);
                $log->execute();
                $log->close();

                if ($med["category"] === "Antibiotic") {
                    $status = alert_status_for_stock($next_stock);
                    $ab = $conn->prepare("UPDATE antibiotic_list SET current_stock_level=?, alert_status=? WHERE medicine_id=?");
                    $ab->bind_param("isi", $next_stock, $status, $mid);
                    $ab->execute();
                    $ab->close();
                }
            }

            $history = [];
            foreach ($details as $d) {
                $history[] = $d["name"] . " (" . $d["qty"] . " units)";
            }
            $history_text = implode(", ", $history);
            $hist = $conn->prepare(
                "UPDATE customers SET customer_name = ?, purchase_history = CONCAT(IFNULL(purchase_history, ''), IF(IFNULL(purchase_history, '') = '', '', '; '), ?) WHERE customer_id = ?"
            );
            $hist->bind_param("ssi", $customer_name, $history_text, $customer_id);
            $hist->execute();
            $hist->close();
        }

        if ($ok) {
            $conn->commit();
            header("Location: invoice_print.php?id=" . $invoice_id);
            exit;
        }

        $conn->rollback();
    }
}

$medicines = $conn->query(
    "SELECT medicine_id, medicine_name, unit_price, quantity_in_stock
     FROM medicines
     WHERE quantity_in_stock > 0
     ORDER BY medicine_name"
)->fetch_all(MYSQLI_ASSOC);

$sales = $conn->query(
    "SELECT st.*, m.medicine_name, u.name AS sold_by_name
     FROM sales_transactions st
     JOIN medicines m ON st.medicine_id = m.medicine_id
     LEFT JOIN users u ON st.sold_by = u.user_id
     ORDER BY st.sale_date DESC, st.sales_id DESC"
)->fetch_all(MYSQLI_ASSOC);

require_once "includes/header.php";
?>

<?php if ($error !== "") { ?><div class="msg-err"><?php echo h($error); ?></div><?php } ?>

<div class="card">
    <h2>New sale</h2>
    <form method="post" id="sale-form">
        <div class="form-row">
            <div class="form-group">
                <label>Customer name</label>
                <input type="text" name="customer_name" required>
            </div>
            <div class="form-group">
                <label>Contact</label>
                <input type="text" name="customer_contact">
            </div>
            <div class="form-group">
                <label>Payment</label>
                <select name="payment_method">
                    <option value="Cash">Cash</option>
                    <option value="Card">Card</option>
                    <option value="bKash">bKash</option>
                </select>
            </div>
            <div class="form-group">
                <label>Discount %</label>
                <input type="number" step="0.01" name="discount_percentage" id="discount" value="0" min="0">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Medicine</label>
                <select id="med-select">
                    <option value="">Select</option>
                    <?php foreach ($medicines as $m) { ?>
                    <option
                        value="<?php echo (int) $m["medicine_id"]; ?>"
                        data-name="<?php echo h($m["medicine_name"]); ?>"
                        data-price="<?php echo h($m["unit_price"]); ?>"
                        data-stock="<?php echo (int) $m["quantity_in_stock"]; ?>"
                    >
                        <?php echo h($m["medicine_name"]); ?> (<?php echo (int) $m["quantity_in_stock"]; ?> left)
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
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Line total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="cart-body"></tbody>
        </table>
        <p style="margin-top:12px">Subtotal: <strong id="sub-total">0.00</strong> &nbsp; Discount: <strong id="disc-amount">0.00</strong> &nbsp; Total: <strong id="grand-total">0.00</strong></p>
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
            <th>Qty</th>
            <th>Total</th>
            <th>Discount %</th>
            <th>Payment</th>
            <th>Sold by</th>
            <th>Invoice</th>
            <th>Date</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($sales as $row) { ?>
        <tr>
            <td><?php echo (int) $row["sales_id"]; ?></td>
            <td><?php echo h($row["medicine_name"]); ?></td>
            <td><?php echo (int) $row["quantity_sold"]; ?></td>
            <td><?php echo number_format((float) $row["total_price"], 2); ?></td>
            <td><?php echo number_format((float) $row["discount_percentage"], 2); ?></td>
            <td><?php echo h($row["payment_method"]); ?></td>
            <td><?php echo h($row["sold_by_name"] ?? ""); ?></td>
            <td>
                <?php if (!empty($row["invoice_id"])) { ?>
                <a class="view" href="invoice_print.php?id=<?php echo (int) $row["invoice_id"]; ?>">#<?php echo (int) $row["invoice_id"]; ?></a>
                <?php } ?>
            </td>
            <td><?php echo h($row["sale_date"]); ?></td>
        </tr>
        <?php } ?>
    </tbody>
</table>

<?php require_once "includes/footer.php"; ?>
