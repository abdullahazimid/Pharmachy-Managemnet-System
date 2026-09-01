<?php
require_once "includes/db.php";
require_once "includes/auth.php";
require_login();
require_role("Admin", "Pharmacist");

$page_title = "Purchases";
$error = "";
$msg = "";
$edit = null;

if (isset($_GET["msg"]) && $_GET["msg"] === "saved") {
    $msg = "Saved.";
}
if (isset($_GET["msg"]) && $_GET["msg"] === "deleted") {
    $msg = "Deleted.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "delete") {
        $id = (int) ($_POST["id"] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM purchases WHERE purchase_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $purchase = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($purchase && $purchase["purchase_status"] === "Received") {
            $conn->begin_transaction();
            $mid = (int) $purchase["medicine_id"];
            $qty = (int) $purchase["quantity"];
            $user_id = (int) $_SESSION["user_id"];

            $inv = $conn->prepare("SELECT current_stock FROM inventory WHERE medicine_id = ? FOR UPDATE");
            $inv->bind_param("i", $mid);
            $inv->execute();
            $stock_row = $inv->get_result()->fetch_assoc();
            $inv->close();

            $next = (int) $stock_row["current_stock"] - $qty;
            if ($next < 0) {
                $conn->rollback();
                $error = "Cannot delete: stock would go below zero.";
            } else {
                $status = alert_status_for_stock($next);
                $up = $conn->prepare("UPDATE inventory SET current_stock = ?, stock_status = ?, updated_by = ? WHERE medicine_id = ?");
                $up->bind_param("isii", $next, $status, $user_id, $mid);
                $up->execute();
                $up->close();

                $del = $conn->prepare("DELETE FROM purchases WHERE purchase_id = ?");
                $del->bind_param("i", $id);
                $del->execute();
                $del->close();

                $conn->commit();
                header("Location: purchases.php?msg=deleted");
                exit;
            }
        } else {
            $del = $conn->prepare("DELETE FROM purchases WHERE purchase_id = ?");
            $del->bind_param("i", $id);
            $del->execute();
            $del->close();
            header("Location: purchases.php?msg=deleted");
            exit;
        }
    }

    if ($action === "add" || $action === "edit") {
        $supplier_id = (int) ($_POST["supplier_id"] ?? 0);
        $purchase_date = $_POST["purchase_date"] ?? "";
        $medicine_id = (int) ($_POST["medicine_id"] ?? 0);
        $quantity = (int) ($_POST["quantity"] ?? 0);
        $purchase_price = (float) ($_POST["purchase_price"] ?? 0);
        $purchase_status = $_POST["purchase_status"] ?? "Received";
        $id = (int) ($_POST["id"] ?? 0);
        $user_id = (int) $_SESSION["user_id"];
        $total_amount = $quantity * $purchase_price;
        $statuses = ["Pending", "Received", "Cancelled"];

        if ($supplier_id <= 0 || $medicine_id <= 0 || $quantity <= 0 || $purchase_price <= 0 || $purchase_date === "" || !in_array($purchase_status, $statuses, true)) {
            $error = "All fields are required with valid values.";
        } else {
            $conn->begin_transaction();
            $ok = true;

            if ($action === "add") {
                $stmt = $conn->prepare(
                    "INSERT INTO purchases (supplier_id, purchase_date, medicine_id, quantity, purchase_price, total_amount, purchase_status)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param("isiidds", $supplier_id, $purchase_date, $medicine_id, $quantity, $purchase_price, $total_amount, $purchase_status);
                $stmt->execute();
                $stmt->close();

                if ($purchase_status === "Received") {
                    $inv = $conn->prepare("SELECT current_stock FROM inventory WHERE medicine_id = ? FOR UPDATE");
                    $inv->bind_param("i", $medicine_id);
                    $inv->execute();
                    $stock_row = $inv->get_result()->fetch_assoc();
                    $inv->close();

                    $next = (int) $stock_row["current_stock"] + $quantity;
                    $status = alert_status_for_stock($next);
                    $up = $conn->prepare("UPDATE inventory SET current_stock = ?, stock_status = ?, updated_by = ? WHERE medicine_id = ?");
                    $up->bind_param("isii", $next, $status, $user_id, $medicine_id);
                    $up->execute();
                    $up->close();
                }

                $mp = $conn->prepare("UPDATE medicines SET purchase_price = ? WHERE medicine_id = ?");
                $mp->bind_param("di", $purchase_price, $medicine_id);
                $mp->execute();
                $mp->close();
            } else {
                $old = $conn->prepare("SELECT * FROM purchases WHERE purchase_id = ?");
                $old->bind_param("i", $id);
                $old->execute();
                $prev = $old->get_result()->fetch_assoc();
                $old->close();

                if (!$prev) {
                    $ok = false;
                    $error = "Purchase not found.";
                } else {
                    if ($prev["purchase_status"] === "Received") {
                        $inv = $conn->prepare("SELECT current_stock FROM inventory WHERE medicine_id = ? FOR UPDATE");
                        $inv->bind_param("i", $prev["medicine_id"]);
                        $inv->execute();
                        $stock_row = $inv->get_result()->fetch_assoc();
                        $inv->close();

                        $next = (int) $stock_row["current_stock"] - (int) $prev["quantity"];
                        if ($next < 0) {
                            $ok = false;
                            $error = "Cannot update: stock would go below zero.";
                        } else {
                            $status = alert_status_for_stock($next);
                            $up = $conn->prepare("UPDATE inventory SET current_stock = ?, stock_status = ?, updated_by = ? WHERE medicine_id = ?");
                            $up->bind_param("isii", $next, $status, $user_id, $prev["medicine_id"]);
                            $up->execute();
                            $up->close();
                        }
                    }

                    if ($ok) {
                        $stmt = $conn->prepare(
                            "UPDATE purchases SET supplier_id=?, purchase_date=?, medicine_id=?, quantity=?, purchase_price=?, total_amount=?, purchase_status=? WHERE purchase_id=?"
                        );
                        $stmt->bind_param("isiiddsi", $supplier_id, $purchase_date, $medicine_id, $quantity, $purchase_price, $total_amount, $purchase_status, $id);
                        $stmt->execute();
                        $stmt->close();

                        if ($purchase_status === "Received") {
                            $inv = $conn->prepare("SELECT current_stock FROM inventory WHERE medicine_id = ? FOR UPDATE");
                            $inv->bind_param("i", $medicine_id);
                            $inv->execute();
                            $stock_row = $inv->get_result()->fetch_assoc();
                            $inv->close();

                            $next = (int) $stock_row["current_stock"] + $quantity;
                            $status = alert_status_for_stock($next);
                            $up = $conn->prepare("UPDATE inventory SET current_stock = ?, stock_status = ?, updated_by = ? WHERE medicine_id = ?");
                            $up->bind_param("isii", $next, $status, $user_id, $medicine_id);
                            $up->execute();
                            $up->close();
                        }

                        $mp = $conn->prepare("UPDATE medicines SET purchase_price = ? WHERE medicine_id = ?");
                        $mp->bind_param("di", $purchase_price, $medicine_id);
                        $mp->execute();
                        $mp->close();
                    }
                }
            }

            if ($ok) {
                $conn->commit();
                header("Location: purchases.php?msg=saved");
                exit;
            }
            $conn->rollback();
        }
    }
}

if (isset($_GET["edit"])) {
    $eid = (int) $_GET["edit"];
    $stmt = $conn->prepare("SELECT * FROM purchases WHERE purchase_id = ?");
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$suppliers = $conn->query("SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name")->fetch_all(MYSQLI_ASSOC);
$medicines = $conn->query("SELECT medicine_id, medicine_name, purchase_price FROM medicines ORDER BY medicine_name")->fetch_all(MYSQLI_ASSOC);
$rows = $conn->query(
    "SELECT p.*, s.supplier_name, m.medicine_name
     FROM purchases p
     JOIN suppliers s ON p.supplier_id = s.supplier_id
     JOIN medicines m ON p.medicine_id = m.medicine_id
     ORDER BY p.purchase_date DESC, p.purchase_id DESC"
)->fetch_all(MYSQLI_ASSOC);

require_once "includes/header.php";
?>

<?php if ($msg !== "") { ?><div class="msg-ok"><?php echo h($msg); ?></div><?php } ?>
<?php if ($error !== "") { ?><div class="msg-err"><?php echo h($error); ?></div><?php } ?>

<div class="card">
    <h2><?php echo $edit ? "Edit purchase" : "New purchase"; ?></h2>
    <form method="post">
        <input type="hidden" name="action" value="<?php echo $edit ? "edit" : "add"; ?>">
        <input type="hidden" name="id" value="<?php echo $edit ? (int) $edit["purchase_id"] : 0; ?>">
        <div class="form-row">
            <div class="form-group">
                <label>Supplier</label>
                <select name="supplier_id" required>
                    <option value="">Select</option>
                    <?php foreach ($suppliers as $s) { ?>
                    <option value="<?php echo (int) $s["supplier_id"]; ?>" <?php echo (isset($edit["supplier_id"]) && (int) $edit["supplier_id"] === (int) $s["supplier_id"]) ? "selected" : ""; ?>>
                        <?php echo h($s["supplier_name"]); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-group">
                <label>Medicine</label>
                <select name="medicine_id" required>
                    <option value="">Select</option>
                    <?php foreach ($medicines as $m) { ?>
                    <option value="<?php echo (int) $m["medicine_id"]; ?>" data-price="<?php echo h($m["purchase_price"]); ?>" <?php echo (isset($edit["medicine_id"]) && (int) $edit["medicine_id"] === (int) $m["medicine_id"]) ? "selected" : ""; ?>>
                        <?php echo h($m["medicine_name"]); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-group">
                <label>Purchase date</label>
                <input type="date" name="purchase_date" required value="<?php echo h($edit["purchase_date"] ?? date("Y-m-d")); ?>">
            </div>
            <div class="form-group">
                <label>Quantity</label>
                <input type="number" name="quantity" required min="1" value="<?php echo h($edit["quantity"] ?? ""); ?>">
            </div>
            <div class="form-group">
                <label>Purchase price (per unit)</label>
                <input type="number" step="0.01" name="purchase_price" required value="<?php echo h($edit["purchase_price"] ?? ""); ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="purchase_status">
                    <?php foreach (["Pending", "Received", "Cancelled"] as $st) { ?>
                    <option value="<?php echo $st; ?>" <?php echo ($edit["purchase_status"] ?? "Received") === $st ? "selected" : ""; ?>><?php echo $st; ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="save-btn">Save</button>
            <?php if ($edit) { ?><a class="cancel-btn" href="purchases.php">Cancel</a><?php } ?>
        </div>
    </form>
</div>

<input type="text" class="search-box" data-table="data-table" placeholder="Search purchases...">
<table id="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Date</th>
            <th>Supplier</th>
            <th>Medicine</th>
            <th>Qty</th>
            <th>Unit price</th>
            <th>Total</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row) { ?>
        <tr>
            <td><?php echo (int) $row["purchase_id"]; ?></td>
            <td><?php echo h($row["purchase_date"]); ?></td>
            <td><?php echo h($row["supplier_name"]); ?></td>
            <td><?php echo h($row["medicine_name"]); ?></td>
            <td><?php echo (int) $row["quantity"]; ?></td>
            <td><?php echo number_format((float) $row["purchase_price"], 2); ?></td>
            <td><?php echo number_format((float) $row["total_amount"], 2); ?></td>
            <td><span class="badge badge-<?php echo strtolower($row["purchase_status"]); ?>"><?php echo h($row["purchase_status"]); ?></span></td>
            <td>
                <a class="edit" href="purchases.php?edit=<?php echo (int) $row["purchase_id"]; ?>">Edit</a>
                <form method="post" class="delete-form" style="display:inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int) $row["purchase_id"]; ?>">
                    <button type="submit" class="delete">Delete</button>
                </form>
            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>

<?php require_once "includes/footer.php"; ?>
