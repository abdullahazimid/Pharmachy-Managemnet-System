<?php
require_once "includes/db.php";
require_once "includes/auth.php";
require_login();
require_role("Admin", "Pharmacist");

$page_title = "Inventory";
$error = "";
$msg = "";

if (isset($_GET["msg"]) && $_GET["msg"] === "saved") {
    $msg = "Stock updated.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $medicine_id = (int) ($_POST["medicine_id"] ?? 0);
    $added = (int) ($_POST["quantity_added"] ?? 0);
    $sold = (int) ($_POST["quantity_sold"] ?? 0);
    $user_id = (int) $_SESSION["user_id"];

    if ($medicine_id <= 0 || ($added === 0 && $sold === 0)) {
        $error = "Select a medicine and enter a quantity.";
    } else {
        $conn->begin_transaction();
        $ok = true;

        $stmt = $conn->prepare("SELECT quantity_in_stock, category FROM medicines WHERE medicine_id = ? FOR UPDATE");
        $stmt->bind_param("i", $medicine_id);
        $stmt->execute();
        $med = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$med) {
            $ok = false;
            $error = "Medicine not found.";
        } else {
            $next = (int) $med["quantity_in_stock"] + $added - $sold;
            if ($next < 0) {
                $ok = false;
                $error = "Stock cannot go below zero.";
            } else {
                $up = $conn->prepare("UPDATE medicines SET quantity_in_stock = ? WHERE medicine_id = ?");
                $up->bind_param("ii", $next, $medicine_id);
                $up->execute();
                $up->close();

                $log = $conn->prepare("INSERT INTO stock_inventory (medicine_id, quantity_added, quantity_sold, updated_by) VALUES (?, ?, ?, ?)");
                $log->bind_param("iiii", $medicine_id, $added, $sold, $user_id);
                $log->execute();
                $log->close();

                if ($med["category"] === "Antibiotic") {
                    $status = alert_status_for_stock($next);
                    $ab = $conn->prepare("UPDATE antibiotic_list SET current_stock_level=?, alert_status=? WHERE medicine_id=?");
                    $ab->bind_param("isi", $next, $status, $medicine_id);
                    $ab->execute();
                    $ab->close();
                }
            }
        }

        if ($ok) {
            $conn->commit();
            header("Location: inventory.php?msg=saved");
            exit;
        }
        $conn->rollback();
    }
}

$medicines = $conn->query("SELECT medicine_id, medicine_name, quantity_in_stock FROM medicines ORDER BY medicine_name")->fetch_all(MYSQLI_ASSOC);
$rows = $conn->query(
    "SELECT si.*, m.medicine_name, u.name AS updated_by_name
     FROM stock_inventory si
     JOIN medicines m ON si.medicine_id = m.medicine_id
     LEFT JOIN users u ON si.updated_by = u.user_id
     ORDER BY si.date_updated DESC, si.stock_id DESC"
)->fetch_all(MYSQLI_ASSOC);

require_once "includes/header.php";
?>

<?php if ($msg !== "") { ?><div class="msg-ok"><?php echo h($msg); ?></div><?php } ?>
<?php if ($error !== "") { ?><div class="msg-err"><?php echo h($error); ?></div><?php } ?>

<div class="card">
    <h2>Adjust stock</h2>
    <form method="post">
        <div class="form-row">
            <div class="form-group">
                <label>Medicine</label>
                <select name="medicine_id" required>
                    <option value="">Select</option>
                    <?php foreach ($medicines as $m) { ?>
                    <option value="<?php echo (int) $m["medicine_id"]; ?>">
                        <?php echo h($m["medicine_name"]); ?> (<?php echo (int) $m["quantity_in_stock"]; ?> in stock)
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-group">
                <label>Quantity added</label>
                <input type="number" name="quantity_added" value="0" min="0">
            </div>
            <div class="form-group">
                <label>Quantity sold / removed</label>
                <input type="number" name="quantity_sold" value="0" min="0">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="save-btn">Update stock</button>
        </div>
    </form>
</div>

<input type="text" class="search-box" data-table="data-table" placeholder="Search inventory...">
<table id="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Medicine</th>
            <th>Added</th>
            <th>Sold</th>
            <th>Updated by</th>
            <th>Date</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row) { ?>
        <tr>
            <td><?php echo (int) $row["stock_id"]; ?></td>
            <td><?php echo h($row["medicine_name"]); ?></td>
            <td><?php echo (int) $row["quantity_added"]; ?></td>
            <td><?php echo (int) $row["quantity_sold"]; ?></td>
            <td><?php echo h($row["updated_by_name"] ?? ""); ?></td>
            <td><?php echo h($row["date_updated"]); ?></td>
        </tr>
        <?php } ?>
    </tbody>
</table>

<?php require_once "includes/footer.php"; ?>
