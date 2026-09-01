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
    $adjustment = (int) ($_POST["adjustment"] ?? 0);
    $user_id = (int) $_SESSION["user_id"];

    if ($medicine_id <= 0 || $adjustment === 0) {
        $error = "Select a medicine and enter an adjustment (+ or -).";
    } else {
        $conn->begin_transaction();
        $ok = true;

        $stmt = $conn->prepare("SELECT current_stock FROM inventory WHERE medicine_id = ? FOR UPDATE");
        $stmt->bind_param("i", $medicine_id);
        $stmt->execute();
        $inv = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$inv) {
            $ok = false;
            $error = "Inventory record not found.";
        } else {
            $next = (int) $inv["current_stock"] + $adjustment;
            if ($next < 0) {
                $ok = false;
                $error = "Stock cannot go below zero.";
            } else {
                $status = alert_status_for_stock($next);
                $up = $conn->prepare("UPDATE inventory SET current_stock = ?, stock_status = ?, updated_by = ? WHERE medicine_id = ?");
                $up->bind_param("isii", $next, $status, $user_id, $medicine_id);
                $up->execute();
                $up->close();
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

$rows = $conn->query(
    "SELECT i.*, m.medicine_name, m.category, m.expire_date, u.username AS updated_by_name
     FROM inventory i
     JOIN medicines m ON i.medicine_id = m.medicine_id
     LEFT JOIN users u ON i.updated_by = u.user_id
     ORDER BY m.medicine_name"
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
                    <?php foreach ($rows as $m) { ?>
                    <option value="<?php echo (int) $m["medicine_id"]; ?>">
                        <?php echo h($m["medicine_name"]); ?> — <?php echo h(medicine_category_label($m["category"])); ?> (<?php echo (int) $m["current_stock"]; ?> in stock)
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-group">
                <label>Adjustment (+ add / - remove)</label>
                <input type="number" name="adjustment" required placeholder="e.g. 10 or -5">
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
            <th>Category</th>
            <th>Current stock</th>
            <th>Status</th>
            <th>Expire date</th>
            <th>Updated by</th>
            <th>Last updated</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row) { ?>
        <tr>
            <td><?php echo (int) $row["inventory_id"]; ?></td>
            <td><?php echo h($row["medicine_name"]); ?></td>
            <td><?php echo h(medicine_category_label($row["category"])); ?></td>
            <td><?php echo (int) $row["current_stock"]; ?></td>
            <td><span class="badge badge-<?php echo strtolower($row["stock_status"]); ?>"><?php echo h($row["stock_status"]); ?></span></td>
            <td><?php echo h($row["expire_date"]); ?></td>
            <td><?php echo h($row["updated_by_name"] ?? ""); ?></td>
            <td><?php echo h($row["updated_at"]); ?></td>
        </tr>
        <?php } ?>
    </tbody>
</table>

<?php require_once "includes/footer.php"; ?>
