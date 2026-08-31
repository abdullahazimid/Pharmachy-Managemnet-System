<?php
require_once "includes/db.php";
require_once "includes/auth.php";
require_login();
require_role("Admin");

$page_title = "Salaries";
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
        $stmt = $conn->prepare("DELETE FROM employee_salaries WHERE salary_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: salaries.php?msg=deleted");
        exit;
    }

    if ($action === "add" || $action === "edit") {
        $employee_id = (int) ($_POST["employee_id"] ?? 0);
        $month = trim($_POST["month"] ?? "");
        $basic = (float) ($_POST["basic_salary"] ?? 0);
        $bonus = (float) ($_POST["sales_linked_bonus"] ?? 0);
        $payment_date = $_POST["payment_date"] ?? "";
        $id = (int) ($_POST["id"] ?? 0);
        $total = $basic + $bonus;

        if ($employee_id <= 0 || $month === "" || $payment_date === "") {
            $error = "Employee, month and payment date are required.";
        } elseif ($action === "add") {
            $stmt = $conn->prepare(
                "INSERT INTO employee_salaries (employee_id, month, basic_salary, sales_linked_bonus, total_salary, payment_date)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("isddds", $employee_id, $month, $basic, $bonus, $total, $payment_date);
            $stmt->execute();
            $stmt->close();
            header("Location: salaries.php?msg=saved");
            exit;
        } else {
            $stmt = $conn->prepare(
                "UPDATE employee_salaries SET employee_id=?, month=?, basic_salary=?, sales_linked_bonus=?, total_salary=?, payment_date=?
                 WHERE salary_id=?"
            );
            $stmt->bind_param("isdddsi", $employee_id, $month, $basic, $bonus, $total, $payment_date, $id);
            $stmt->execute();
            $stmt->close();
            header("Location: salaries.php?msg=saved");
            exit;
        }
    }
}

if (isset($_GET["edit"])) {
    $eid = (int) $_GET["edit"];
    $stmt = $conn->prepare("SELECT * FROM employee_salaries WHERE salary_id = ?");
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$employees = $conn->query("SELECT user_id, name, role FROM users ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$rows = $conn->query(
    "SELECT es.*, u.name AS employee_name, u.role
     FROM employee_salaries es
     JOIN users u ON es.employee_id = u.user_id
     ORDER BY es.payment_date DESC, es.salary_id DESC"
)->fetch_all(MYSQLI_ASSOC);

require_once "includes/header.php";
?>

<?php if ($msg !== "") { ?><div class="msg-ok"><?php echo h($msg); ?></div><?php } ?>
<?php if ($error !== "") { ?><div class="msg-err"><?php echo h($error); ?></div><?php } ?>

<div class="card">
    <h2><?php echo $edit ? "Edit salary" : "Add salary"; ?></h2>
    <form method="post">
        <input type="hidden" name="action" value="<?php echo $edit ? "edit" : "add"; ?>">
        <input type="hidden" name="id" value="<?php echo $edit ? (int) $edit["salary_id"] : 0; ?>">
        <div class="form-row">
            <div class="form-group">
                <label>Employee</label>
                <select name="employee_id" required>
                    <option value="">Select</option>
                    <?php foreach ($employees as $e) { ?>
                    <option value="<?php echo (int) $e["user_id"]; ?>" <?php echo (isset($edit["employee_id"]) && (int) $edit["employee_id"] === (int) $e["user_id"]) ? "selected" : ""; ?>>
                        <?php echo h($e["name"] . " (" . $e["role"] . ")"); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-group">
                <label>Month</label>
                <input type="text" name="month" required placeholder="August 2026" value="<?php echo h($edit["month"] ?? ""); ?>">
            </div>
            <div class="form-group">
                <label>Basic salary</label>
                <input type="number" step="0.01" name="basic_salary" required value="<?php echo h($edit["basic_salary"] ?? ""); ?>">
            </div>
            <div class="form-group">
                <label>Bonus</label>
                <input type="number" step="0.01" name="sales_linked_bonus" value="<?php echo h($edit["sales_linked_bonus"] ?? "0"); ?>">
            </div>
            <div class="form-group">
                <label>Payment date</label>
                <input type="date" name="payment_date" required value="<?php echo h($edit["payment_date"] ?? ""); ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="save-btn">Save</button>
            <?php if ($edit) { ?><a class="cancel-btn" href="salaries.php">Cancel</a><?php } ?>
        </div>
    </form>
</div>

<input type="text" class="search-box" data-table="data-table" placeholder="Search salaries...">
<table id="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Employee</th>
            <th>Role</th>
            <th>Month</th>
            <th>Basic</th>
            <th>Bonus</th>
            <th>Total</th>
            <th>Payment date</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row) { ?>
        <tr>
            <td><?php echo (int) $row["salary_id"]; ?></td>
            <td><?php echo h($row["employee_name"]); ?></td>
            <td><?php echo h($row["role"]); ?></td>
            <td><?php echo h($row["month"]); ?></td>
            <td><?php echo number_format((float) $row["basic_salary"], 2); ?></td>
            <td><?php echo number_format((float) $row["sales_linked_bonus"], 2); ?></td>
            <td><?php echo number_format((float) $row["total_salary"], 2); ?></td>
            <td><?php echo h($row["payment_date"]); ?></td>
            <td>
                <a class="edit" href="salaries.php?edit=<?php echo (int) $row["salary_id"]; ?>">Edit</a>
                <form method="post" class="delete-form" style="display:inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int) $row["salary_id"]; ?>">
                    <button type="submit" class="delete">Delete</button>
                </form>
            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>

<?php require_once "includes/footer.php"; ?>
