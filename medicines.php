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
        $category = $_POST["category"] ?? "General";
        $unit_price = (float) ($_POST["unit_price"] ?? 0);
        $purchase_price = (float) ($_POST["purchase_price"] ?? 0);
        $quantity_in_stock = (int) ($_POST["quantity_in_stock"] ?? 0);
        $expiry_date = $_POST["expiry_date"] ?? "";
        $manufacture_date = $_POST["manufacture_date"] ?? "";
        $supplier_id = $_POST["supplier_id"] !== "" ? (int) $_POST["supplier_id"] : 0;
        $id = (int) ($_POST["id"] ?? 0);
        $user_id = (int) $_SESSION["user_id"];

        if ($medicine_name === "" || $company_name === "" || $unit_price <= 0 || $expiry_date === "" || $manufacture_date === "") {
            $error = "Name, company, price and dates are required.";
        } elseif ($category !== "Antibiotic" && $category !== "General") {
            $error = "Invalid category.";
        } else {
            if ($action === "add") {
                $stmt = $conn->prepare(
                    "INSERT INTO medicines (medicine_name, company_name, category, unit_price, purchase_price, quantity_in_stock, expiry_date, manufacture_date, supplier_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0))"
                );
                $stmt->bind_param(
                    "sssddissi",
                    $medicine_name,
                    $company_name,
                    $category,
                    $unit_price,
                    $purchase_price,
                    $quantity_in_stock,
                    $expiry_date,
                    $manufacture_date,
                    $supplier_id
                );
                $stmt->execute();
                $new_id = $stmt->insert_id;
                $stmt->close();

                if ($quantity_in_stock > 0) {
                    $log = $conn->prepare("INSERT INTO stock_inventory (medicine_id, quantity_added, quantity_sold, updated_by) VALUES (?, ?, 0, ?)");
                    $log->bind_param("iii", $new_id, $quantity_in_stock, $user_id);
                    $log->execute();
                    $log->close();
                }

                if ($category === "Antibiotic") {
                    $status = alert_status_for_stock($quantity_in_stock);
                    $limit = 10;
                    $ab = $conn->prepare("INSERT INTO antibiotic_list (medicine_id, allowed_range_limit, current_stock_level, alert_status) VALUES (?, ?, ?, ?)");
                    $ab->bind_param("iiis", $new_id, $limit, $quantity_in_stock, $status);
                    $ab->execute();
                    $ab->close();
                }
            } else {
                $stmt = $conn->prepare(
                    "UPDATE medicines SET medicine_name=?, company_name=?, category=?, unit_price=?, purchase_price=?,
                     quantity_in_stock=?, expiry_date=?, manufacture_date=?, supplier_id=NULLIF(?, 0) WHERE medicine_id=?"
                );
                $stmt->bind_param(
                    "sssddissii",
                    $medicine_name,
                    $company_name,
                    $category,
                    $unit_price,
                    $purchase_price,
                    $quantity_in_stock,
                    $expiry_date,
                    $manufacture_date,
                    $supplier_id,
                    $id
                );
                $stmt->execute();
                $stmt->close();

                if ($category === "Antibiotic") {
                    $status = alert_status_for_stock($quantity_in_stock);
                    $check = $conn->prepare("SELECT antibiotic_id FROM antibiotic_list WHERE medicine_id = ?");
                    $check->bind_param("i", $id);
                    $check->execute();
                    $exists = $check->get_result()->fetch_assoc();
                    $check->close();

                    if ($exists) {
                        $up = $conn->prepare("UPDATE antibiotic_list SET current_stock_level=?, alert_status=? WHERE medicine_id=?");
                        $up->bind_param("isi", $quantity_in_stock, $status, $id);
                        $up->execute();
                        $up->close();
                    } else {
                        $limit = 10;
                        $ab = $conn->prepare("INSERT INTO antibiotic_list (medicine_id, allowed_range_limit, current_stock_level, alert_status) VALUES (?, ?, ?, ?)");
                        $ab->bind_param("iiis", $id, $limit, $quantity_in_stock, $status);
                        $ab->execute();
                        $ab->close();
                    }
                }
            }

            header("Location: medicines.php?msg=saved");
            exit;
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
    "SELECT m.*, s.supplier_name
     FROM medicines m
     LEFT JOIN suppliers s ON m.supplier_id = s.supplier_id
     ORDER BY m.medicine_id"
)->fetch_all(MYSQLI_ASSOC);

require_once "includes/header.php";
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
                    <option value="General" <?php echo ($edit["category"] ?? "") === "General" ? "selected" : ""; ?>>General</option>
                    <option value="Antibiotic" <?php echo ($edit["category"] ?? "") === "Antibiotic" ? "selected" : ""; ?>>Antibiotic</option>
                </select>
            </div>
            <div class="form-group">
                <label>Supplier</label>
                <select name="supplier_id">
                    <option value="">None</option>
                    <?php foreach ($suppliers as $s) { ?>
                    <option value="<?php echo (int) $s["supplier_id"]; ?>" <?php echo (isset($edit["supplier_id"]) && (int) $edit["supplier_id"] === (int) $s["supplier_id"]) ? "selected" : ""; ?>>
                        <?php echo h($s["supplier_name"]); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Unit price</label>
                <input type="number" step="0.01" name="unit_price" required value="<?php echo h($edit["unit_price"] ?? ""); ?>">
            </div>
            <div class="form-group">
                <label>Purchase price</label>
                <input type="number" step="0.01" name="purchase_price" value="<?php echo h($edit["purchase_price"] ?? "0"); ?>">
            </div>
            <div class="form-group">
                <label>Stock</label>
                <input type="number" name="quantity_in_stock" value="<?php echo h($edit["quantity_in_stock"] ?? "0"); ?>">
            </div>
            <div class="form-group">
                <label>Manufacture date</label>
                <input type="date" name="manufacture_date" required value="<?php echo h($edit["manufacture_date"] ?? ""); ?>">
            </div>
            <div class="form-group">
                <label>Expiry date</label>
                <input type="date" name="expiry_date" required value="<?php echo h($edit["expiry_date"] ?? ""); ?>">
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
            <th>Unit price</th>
            <th>Stock</th>
            <th>Expiry</th>
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
                <span class="badge badge-<?php echo strtolower($row["category"]); ?>"><?php echo h($row["category"]); ?></span>
            </td>
            <td><?php echo number_format((float) $row["unit_price"], 2); ?></td>
            <td><?php echo (int) $row["quantity_in_stock"]; ?></td>
            <td><?php echo h($row["expiry_date"]); ?></td>
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

<?php require_once "includes/footer.php"; ?>
