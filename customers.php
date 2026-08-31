<?php
require_once "includes/db.php";
require_once "includes/auth.php";
require_login();
require_role("Admin", "Pharmacist", "Employee");

$page_title = "Customers";
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
        $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: customers.php?msg=deleted");
        exit;
    }

    if ($action === "add" || $action === "edit") {
        $customer_name = trim($_POST["customer_name"] ?? "");
        $contact_no = trim($_POST["contact_no"] ?? "");
        $purchase_history = trim($_POST["purchase_history"] ?? "");
        $id = (int) ($_POST["id"] ?? 0);

        if ($customer_name === "" || $contact_no === "") {
            $error = "Name and contact are required.";
        } elseif ($action === "add") {
            $stmt = $conn->prepare("INSERT INTO customers (customer_name, contact_no, purchase_history) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $customer_name, $contact_no, $purchase_history);
            $stmt->execute();
            $stmt->close();
            header("Location: customers.php?msg=saved");
            exit;
        } else {
            $stmt = $conn->prepare("UPDATE customers SET customer_name=?, contact_no=?, purchase_history=? WHERE customer_id=?");
            $stmt->bind_param("sssi", $customer_name, $contact_no, $purchase_history, $id);
            $stmt->execute();
            $stmt->close();
            header("Location: customers.php?msg=saved");
            exit;
        }
    }
}

if (isset($_GET["edit"])) {
    $eid = (int) $_GET["edit"];
    $stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$rows = $conn->query("SELECT * FROM customers ORDER BY customer_id DESC")->fetch_all(MYSQLI_ASSOC);

require_once "includes/header.php";
?>

<?php if ($msg !== "") { ?><div class="msg-ok"><?php echo h($msg); ?></div><?php } ?>
<?php if ($error !== "") { ?><div class="msg-err"><?php echo h($error); ?></div><?php } ?>

<div class="card">
    <h2><?php echo $edit ? "Edit customer" : "Add customer"; ?></h2>
    <form method="post">
        <input type="hidden" name="action" value="<?php echo $edit ? "edit" : "add"; ?>">
        <input type="hidden" name="id" value="<?php echo $edit ? (int) $edit["customer_id"] : 0; ?>">
        <div class="form-row">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="customer_name" required value="<?php echo h($edit["customer_name"] ?? ""); ?>">
            </div>
            <div class="form-group">
                <label>Contact</label>
                <input type="text" name="contact_no" required value="<?php echo h($edit["contact_no"] ?? ""); ?>">
            </div>
            <div class="form-group">
                <label>Purchase history</label>
                <input type="text" name="purchase_history" value="<?php echo h($edit["purchase_history"] ?? ""); ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="save-btn">Save</button>
            <?php if ($edit) { ?><a class="cancel-btn" href="customers.php">Cancel</a><?php } ?>
        </div>
    </form>
</div>

<input type="text" class="search-box" data-table="data-table" placeholder="Search customers...">
<table id="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Contact</th>
            <th>Purchase history</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row) { ?>
        <tr>
            <td><?php echo (int) $row["customer_id"]; ?></td>
            <td><?php echo h($row["customer_name"]); ?></td>
            <td><?php echo h($row["contact_no"]); ?></td>
            <td><?php echo h($row["purchase_history"]); ?></td>
            <td>
                <a class="edit" href="customers.php?edit=<?php echo (int) $row["customer_id"]; ?>">Edit</a>
                <form method="post" class="delete-form" style="display:inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int) $row["customer_id"]; ?>">
                    <button type="submit" class="delete">Delete</button>
                </form>
            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>

<?php require_once "includes/footer.php"; ?>
