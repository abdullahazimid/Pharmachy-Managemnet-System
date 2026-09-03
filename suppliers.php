<?php
require_once "includes/db.php";
require_once "includes/auth.php";
require_login();
require_role("Admin", "Pharmacist");

$page_title = "Suppliers";
$error = "";
$msg = "";
$edit = null;

if (isset($_GET["msg"]) && $_GET["msg"] === "saved") {
    $msg = "Saved.";
}
if (isset($_GET["msg"]) && $_GET["msg"] === "deleted") {
    $msg = "Deleted.";
}

$is_ajax = !empty($_POST["ajax"]);

function supplier_json_response($data)
{
    header("Content-Type: application/json");
    echo json_encode($data);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "delete") {
        $id = (int) ($_POST["id"] ?? 0);
        $stmt = $conn->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: suppliers.php?msg=deleted");
        exit;
    }

    if ($action === "add" || $action === "edit") {
        $supplier_name = trim($_POST["supplier_name"] ?? "");
        $contact_number = trim($_POST["contact_number"] ?? "");
        $company_name = trim($_POST["company_name"] ?? "");
        $id = (int) ($_POST["id"] ?? 0);

        if ($supplier_name === "" || $contact_number === "" || $company_name === "") {
            $error = "All fields are required.";
            if ($is_ajax) {
                supplier_json_response(["ok" => false, "error" => $error]);
            }
        } elseif ($action === "add") {
            $stmt = $conn->prepare("INSERT INTO suppliers (supplier_name, contact_number, company_name) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $supplier_name, $contact_number, $company_name);
            if (!$stmt->execute()) {
                $stmt->close();
                $error = "Could not save supplier.";
                if ($is_ajax) {
                    supplier_json_response(["ok" => false, "error" => $error]);
                }
            } else {
                $new_id = (int) $stmt->insert_id;
                $stmt->close();
                if ($is_ajax) {
                    supplier_json_response([
                        "ok" => true,
                        "supplier_id" => $new_id,
                        "supplier_name" => $supplier_name,
                    ]);
                }
                header("Location: suppliers.php?msg=saved");
                exit;
            }
        } else {
            $stmt = $conn->prepare("UPDATE suppliers SET supplier_name=?, contact_number=?, company_name=? WHERE supplier_id=?");
            $stmt->bind_param("sssi", $supplier_name, $contact_number, $company_name, $id);
            $stmt->execute();
            $stmt->close();
            header("Location: suppliers.php?msg=saved");
            exit;
        }
    }
}

if (isset($_GET["edit"])) {
    $eid = (int) $_GET["edit"];
    $stmt = $conn->prepare("SELECT * FROM suppliers WHERE supplier_id = ?");
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$rows = $conn->query("SELECT * FROM suppliers ORDER BY supplier_id")->fetch_all(MYSQLI_ASSOC);

require_once "includes/header.php";
?>

<?php if ($msg !== "") { ?><div class="msg-ok"><?php echo h($msg); ?></div><?php } ?>
<?php if ($error !== "") { ?><div class="msg-err"><?php echo h($error); ?></div><?php } ?>

<div class="card">
    <h2><?php echo $edit ? "Edit supplier" : "Add supplier"; ?></h2>
    <form method="post">
        <input type="hidden" name="action" value="<?php echo $edit ? "edit" : "add"; ?>">
        <input type="hidden" name="id" value="<?php echo $edit ? (int) $edit["supplier_id"] : 0; ?>">
        <div class="form-row">
            <div class="form-group">
                <label>Supplier name</label>
                <input type="text" name="supplier_name" required value="<?php echo h($edit["supplier_name"] ?? ""); ?>">
            </div>
            <div class="form-group">
                <label>Company</label>
                <input type="text" name="company_name" required value="<?php echo h($edit["company_name"] ?? ""); ?>">
            </div>
            <div class="form-group">
                <label>Contact number</label>
                <input type="text" name="contact_number" required value="<?php echo h($edit["contact_number"] ?? ""); ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="save-btn">Save</button>
            <?php if ($edit) { ?><a class="cancel-btn" href="suppliers.php">Cancel</a><?php } ?>
        </div>
    </form>
</div>

<input type="text" class="search-box" data-table="data-table" placeholder="Search suppliers...">
<table id="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Supplier</th>
            <th>Company</th>
            <th>Contact</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row) { ?>
        <tr>
            <td><?php echo (int) $row["supplier_id"]; ?></td>
            <td><?php echo h($row["supplier_name"]); ?></td>
            <td><?php echo h($row["company_name"]); ?></td>
            <td><?php echo h($row["contact_number"]); ?></td>
            <td>
                <a class="edit" href="suppliers.php?edit=<?php echo (int) $row["supplier_id"]; ?>">Edit</a>
                <form method="post" class="delete-form" style="display:inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int) $row["supplier_id"]; ?>">
                    <button type="submit" class="delete">Delete</button>
                </form>
            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>

<?php require_once "includes/footer.php"; ?>
