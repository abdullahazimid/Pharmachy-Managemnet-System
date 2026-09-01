<?php
require_once "includes/db.php";
require_once "includes/auth.php";
require_login();
require_role("Admin", "Pharmacist");

$page_title = "Antibiotics";
$error = "";
$msg = "";

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
        $stmt = $conn->prepare("DELETE FROM antibiotic_list WHERE antibiotic_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: antibiotics.php?msg=deleted");
        exit;
    }

    if ($action === "add") {
        $medicine_id = (int) ($_POST["medicine_id"] ?? 0);

        if ($medicine_id <= 0) {
            $error = "Medicine is required.";
        } else {
            $stmt = $conn->prepare("SELECT quantity_in_stock, category FROM medicines WHERE medicine_id = ?");
            $stmt->bind_param("i", $medicine_id);
            $stmt->execute();
            $med = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$med) {
                $error = "Medicine not found.";
            } elseif ($med["category"] !== "Antibiotic") {
                $error = "Medicine must be Antibiotic category.";
            } else {
                $dup = $conn->prepare("SELECT antibiotic_id FROM antibiotic_list WHERE medicine_id = ?");
                $dup->bind_param("i", $medicine_id);
                $dup->execute();
                $already = $dup->get_result()->fetch_assoc();
                $dup->close();

                if ($already) {
                    $error = "This medicine is already in the antibiotic list.";
                } else {
                    $qty = (int) $med["quantity_in_stock"];
                    $status = alert_status_for_stock($qty);
                    $limit = 0;
                    $ins = $conn->prepare("INSERT INTO antibiotic_list (medicine_id, allowed_range_limit, current_stock_level, alert_status) VALUES (?, ?, ?, ?)");
                    $ins->bind_param("iiis", $medicine_id, $limit, $qty, $status);
                    $ins->execute();
                    $ins->close();
                    header("Location: antibiotics.php?msg=saved");
                    exit;
                }
            }
        }
    }
}

$options = $conn->query(
    "SELECT m.medicine_id, m.medicine_name
     FROM medicines m
     LEFT JOIN antibiotic_list a ON a.medicine_id = m.medicine_id
     WHERE m.category = 'Antibiotic' AND a.antibiotic_id IS NULL
     ORDER BY m.medicine_name"
)->fetch_all(MYSQLI_ASSOC);

$rows = $conn->query(
    "SELECT a.antibiotic_id, a.medicine_id, a.current_stock_level, a.alert_status,
            m.medicine_name, m.company_name,
            COALESCE(s.total_sold, 0) AS total_sold,
            COALESCE(s.total_sell_amount, 0) AS total_sell_amount
     FROM antibiotic_list a
     JOIN medicines m ON a.medicine_id = m.medicine_id
     LEFT JOIN (
         SELECT medicine_id,
                SUM(quantity_sold) AS total_sold,
                SUM(total_price) AS total_sell_amount
         FROM sales_transactions
         GROUP BY medicine_id
     ) s ON s.medicine_id = a.medicine_id
     ORDER BY a.antibiotic_id"
)->fetch_all(MYSQLI_ASSOC);

require_once "includes/header.php";
?>

<?php if ($msg !== "") { ?><div class="msg-ok"><?php echo h($msg); ?></div><?php } ?>
<?php if ($error !== "") { ?><div class="msg-err"><?php echo h($error); ?></div><?php } ?>

<div class="card">
    <h2>Add antibiotic</h2>
    <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group">
                <label>Medicine</label>
                <select name="medicine_id" required>
                    <option value="">Select</option>
                    <?php foreach ($options as $opt) { ?>
                    <option value="<?php echo (int) $opt["medicine_id"]; ?>"><?php echo h($opt["medicine_name"]); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="save-btn">Save</button>
        </div>
    </form>
</div>

<input type="text" class="search-box" data-table="data-table" placeholder="Search antibiotics...">
<table id="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Medicine</th>
            <th>Company</th>
            <th>Stock</th>
            <th>Total sell</th>
            <th>Alert</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row) { ?>
        <tr>
            <td><?php echo (int) $row["antibiotic_id"]; ?></td>
            <td><?php echo h($row["medicine_name"]); ?></td>
            <td><?php echo h($row["company_name"]); ?></td>
            <td><?php echo (int) $row["current_stock_level"]; ?></td>
            <td><?php echo (int) $row["total_sold"]; ?> (<?php echo number_format((float) $row["total_sell_amount"], 2); ?>)</td>
            <td><span class="badge badge-<?php echo strtolower($row["alert_status"]); ?>"><?php echo h($row["alert_status"]); ?></span></td>
            <td>
                <form method="post" class="delete-form" style="display:inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int) $row["antibiotic_id"]; ?>">
                    <button type="submit" class="delete">Delete</button>
                </form>
            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>

<?php require_once "includes/footer.php"; ?>
