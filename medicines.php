<?php
require_once "includes/db.php";
require_once "includes/auth.php";
require_login();
require_role("Admin", "Pharmacist");

$page_title = "Medicines";
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
        $stmt = $conn->prepare("DELETE FROM medicines WHERE medicine_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: medicines.php?msg=deleted");
        exit;
    }

    if ($action === "add" || $action === "edit") {
        $medicine_name = trim($_POST["medicine_name"] ?? "");
        $company_name = trim($_POST["company_name"] ?? "");
        $category = trim($_POST["category"] ?? "Medicine");
        $sale_price = (float) ($_POST["sale_price"] ?? 0);
        $purchase_price = (float) ($_POST["purchase_price"] ?? 0);
        $expire_date = $_POST["expire_date"] ?? "";
        $manufacture_date = $_POST["manufacture_date"] ?? "";
        $batch_number = trim($_POST["batch_number"] ?? "");
        $supplier_id = $_POST["supplier_id"] !== "" ? (int) $_POST["supplier_id"] : 0;
        $quantity = (int) ($_POST["quantity"] ?? 0);
        $id = (int) ($_POST["id"] ?? 0);
        $user_id = (int) $_SESSION["user_id"];
        $categories = ["Medicine", "Antibiotics"];

        if ($medicine_name === "" || $company_name === "" || $sale_price <= 0 || $expire_date === "" || $manufacture_date === "") {
            $error = "Name, company, sale price and dates are required.";
        } elseif (!in_array($category, $categories, true)) {
            $error = "Category must be Medicine or Antibiotics.";
        } elseif ($action === "add" && $quantity < 0) {
            $error = "Quantity cannot be negative.";
        } elseif ($action === "add" && $quantity > 0 && $supplier_id <= 0) {
            $error = "Select a supplier for the initial stock purchase.";
        } elseif ($action === "add" && $quantity > 0 && $purchase_price <= 0) {
            $error = "Purchase price is required when adding initial stock.";
        } else {
            if ($action === "add") {
                $conn->begin_transaction();
                $ok = true;

                $stmt = $conn->prepare(
                    "INSERT INTO medicines (medicine_name, company_name, category, purchase_price, expire_date, manufacture_date, supplier_id, sale_price, batch_number)
                     VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, 0), ?, ?)"
                );
                $stmt->bind_param(
                    "sssdssids",
                    $medicine_name,
                    $company_name,
                    $category,
                    $purchase_price,
                    $expire_date,
                    $manufacture_date,
                    $supplier_id,
                    $sale_price,
                    $batch_number
                );
                if (!$stmt->execute()) {
                    $ok = false;
                    $error = "Could not save medicine.";
                }
                $new_id = (int) $stmt->insert_id;
                $stmt->close();

                if ($ok && $new_id <= 0) {
                    $ok = false;
                    $error = "Could not save medicine.";
                }

                if ($ok) {
                    $status = alert_status_for_stock($quantity);
                    $inv = $conn->prepare("INSERT INTO inventory (medicine_id, current_stock, stock_status, updated_by) VALUES (?, ?, ?, ?)");
                    $inv->bind_param("iisi", $new_id, $quantity, $status, $user_id);
                    if (!$inv->execute()) {
                        $ok = false;
                        $error = "Could not save inventory.";
                    }
                    $inv->close();
                }

                // Initial purchase is a history record only — do not add quantity to inventory again.
                if ($ok && $quantity > 0) {
                    $total_amount = $quantity * $purchase_price;
                    $purchase_status = "Received";
                    $purchase_date = date("Y-m-d");
                    $pur = $conn->prepare(
                        "INSERT INTO purchases (supplier_id, purchase_date, medicine_id, quantity, purchase_price, total_amount, purchase_status)
                         VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );
                    $pur->bind_param(
                        "isiidds",
                        $supplier_id,
                        $purchase_date,
                        $new_id,
                        $quantity,
                        $purchase_price,
                        $total_amount,
                        $purchase_status
                    );
                    if (!$pur->execute()) {
                        $ok = false;
                        $error = "Could not save initial purchase.";
                    }
                    $pur->close();
                }

                if ($ok) {
                    $conn->commit();
                    header("Location: medicines.php?msg=saved");
                    exit;
                }
                $conn->rollback();
            } else {
                $stmt = $conn->prepare(
                    "UPDATE medicines SET medicine_name=?, company_name=?, category=?, purchase_price=?,
                     expire_date=?, manufacture_date=?, supplier_id=NULLIF(?, 0), sale_price=?, batch_number=? WHERE medicine_id=?"
                );
                $stmt->bind_param(
                    "sssdssidsi",
                    $medicine_name,
                    $company_name,
                    $category,
                    $purchase_price,
                    $expire_date,
                    $manufacture_date,
                    $supplier_id,
                    $sale_price,
                    $batch_number,
                    $id
                );
                $stmt->execute();
                $stmt->close();
                header("Location: medicines.php?msg=saved");
                exit;
            }
        }
    }
}

if (isset($_GET["edit"])) {
    $eid = (int) $_GET["edit"];
    $stmt = $conn->prepare("SELECT * FROM medicines WHERE medicine_id = ?");
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$suppliers = $conn->query("SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name")->fetch_all(MYSQLI_ASSOC);
$rows = $conn->query(
    "SELECT m.*, s.supplier_name, COALESCE(i.current_stock, 0) AS current_stock, i.stock_status
     FROM medicines m
     LEFT JOIN suppliers s ON m.supplier_id = s.supplier_id
     LEFT JOIN inventory i ON m.medicine_id = i.medicine_id
     ORDER BY m.medicine_id"
)->fetch_all(MYSQLI_ASSOC);

require_once "includes/header.php";

$edit_category = medicine_category_label($edit["category"] ?? "Medicine");
?>

<?php if ($msg !== "") { ?><div class="msg-ok"><?php echo h($msg); ?></div><?php } ?>
<?php if ($error !== "") { ?><div class="msg-err"><?php echo h($error); ?></div><?php } ?>

<div class="card">
    <h2><?php echo $edit ? "Edit medicine" : "Add medicine"; ?></h2>
    <form method="post">
        <input type="hidden" name="action" value="<?php echo $edit ? "edit" : "add"; ?>">
        <input type="hidden" name="id" value="<?php echo $edit ? (int) $edit["medicine_id"] : 0; ?>">
        <div class="form-row">
            <div class="form-group">
                <label>Medicine name</label>
                <input type="text" name="medicine_name" required value="<?php echo h($edit["medicine_name"] ?? ""); ?>">
            </div>
            <div class="form-group">
                <label>Company</label>
                <input type="text" name="company_name" required value="<?php echo h($edit["company_name"] ?? ""); ?>">
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category">
                    <option value="Medicine" <?php echo $edit_category === "Medicine" ? "selected" : ""; ?>>Medicine</option>
                    <option value="Antibiotics" <?php echo $edit_category === "Antibiotics" ? "selected" : ""; ?>>Antibiotics</option>
                </select>
            </div>
            <?php if (!$edit) { ?>
            <div class="form-group">
                <label>Quantity</label>
                <input type="number" name="quantity" min="0" required value="0">
            </div>
            <?php } ?>
            <div class="form-group">
                <label>Batch number</label>
                <input type="text" name="batch_number" value="<?php echo h($edit["batch_number"] ?? ""); ?>">
            </div>
            <div class="form-group">
                <label>Supplier</label>
                <div class="input-with-btn">
                    <select name="supplier_id" id="supplier-select">
                        <option value="">None</option>
                        <?php foreach ($suppliers as $s) { ?>
                        <option value="<?php echo (int) $s["supplier_id"]; ?>" <?php echo (isset($edit["supplier_id"]) && (int) $edit["supplier_id"] === (int) $s["supplier_id"]) ? "selected" : ""; ?>>
                            <?php echo h($s["supplier_name"]); ?>
                        </option>
                        <?php } ?>
                    </select>
                    <button type="button" class="add-btn" id="open-supplier-modal">+ Add Supplier</button>
                </div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Sale price</label>
                <input type="number" step="0.01" name="sale_price" required value="<?php echo h($edit["sale_price"] ?? ""); ?>">
            </div>
            <div class="form-group">
                <label>Purchase price</label>
                <input type="number" step="0.01" name="purchase_price" value="<?php echo h($edit["purchase_price"] ?? "0"); ?>">
            </div>
            <div class="form-group">
                <label>Manufacture date</label>
                <input type="date" name="manufacture_date" required value="<?php echo h($edit["manufacture_date"] ?? ""); ?>">
            </div>
            <div class="form-group">
                <label>Expire date</label>
                <input type="date" name="expire_date" required value="<?php echo h($edit["expire_date"] ?? ""); ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="save-btn">Save</button>
            <?php if ($edit) { ?><a class="cancel-btn" href="medicines.php">Cancel</a><?php } ?>
        </div>
    </form>
</div>

<input type="text" class="search-box" data-table="data-table" placeholder="Search medicines...">
<table id="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Company</th>
            <th>Category</th>
            <th>Batch</th>
            <th>Sale price</th>
            <th>Stock</th>
            <th>Status</th>
            <th>Expire</th>
            <th>Supplier</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row) { ?>
        <tr>
            <td><?php echo (int) $row["medicine_id"]; ?></td>
            <td><?php echo h($row["medicine_name"]); ?></td>
            <td><?php echo h($row["company_name"]); ?></td>
            <td>
                <span class="badge badge-<?php echo h(medicine_badge_class($row["category"])); ?>"><?php echo h(medicine_category_label($row["category"])); ?></span>
            </td>
            <td><?php echo h($row["batch_number"]); ?></td>
            <td><?php echo number_format((float) $row["sale_price"], 2); ?></td>
            <td><?php echo (int) $row["current_stock"]; ?></td>
            <td><?php echo h($row["stock_status"] ?? "Normal"); ?></td>
            <td><?php echo h($row["expire_date"]); ?></td>
            <td><?php echo h($row["supplier_name"] ?? ""); ?></td>
            <td>
                <a class="edit" href="medicines.php?edit=<?php echo (int) $row["medicine_id"]; ?>">Edit</a>
                <form method="post" class="delete-form" style="display:inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int) $row["medicine_id"]; ?>">
                    <button type="submit" class="delete">Delete</button>
                </form>
            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>

<div id="supplier-modal" class="modal" aria-hidden="true">
    <div class="modal-card">
        <h2>Add supplier</h2>
        <p id="supplier-modal-error" class="msg-err" hidden></p>
        <div class="form-group">
            <label>Supplier name</label>
            <input type="text" id="modal-supplier-name">
        </div>
        <div class="form-group">
            <label>Company name</label>
            <input type="text" id="modal-company-name">
        </div>
        <div class="form-group">
            <label>Contact number</label>
            <input type="text" id="modal-contact-number">
        </div>
        <div class="form-actions">
            <button type="button" class="save-btn" id="save-supplier-btn">Save Supplier</button>
            <button type="button" class="cancel-btn" id="cancel-supplier-btn">Cancel</button>
        </div>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>
