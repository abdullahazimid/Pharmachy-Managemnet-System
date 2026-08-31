<?php
require_once "includes/db.php";
require_once "includes/auth.php";
require_login();
require_role("Admin");

$page_title = "Users";
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
        if ($id === (int) $_SESSION["user_id"]) {
            $error = "You cannot delete your own account.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            header("Location: users.php?msg=deleted");
            exit;
        }
    }

    if ($action === "add" || $action === "edit") {
        $name = trim($_POST["name"] ?? "");
        $role = $_POST["role"] ?? "";
        $username = trim($_POST["username"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";
        $id = (int) ($_POST["id"] ?? 0);
        $roles = ["Admin", "Pharmacist", "Employee"];

        if ($name === "" || $username === "" || $email === "" || !in_array($role, $roles, true)) {
            $error = "Name, role, username and email are required.";
        } elseif ($action === "add" && $password === "") {
            $error = "Password is required for a new user.";
        } else {
            if ($action === "add") {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, role, username, password, email) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $name, $role, $username, $hash, $email);
            } elseif ($password !== "") {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET name=?, role=?, username=?, email=?, password=? WHERE user_id=?");
                $stmt->bind_param("sssssi", $name, $role, $username, $email, $hash, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name=?, role=?, username=?, email=? WHERE user_id=?");
                $stmt->bind_param("ssssi", $name, $role, $username, $email, $id);
            }

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: users.php?msg=saved");
                exit;
            }

            if ($conn->errno === 1062) {
                $error = "Username or email already exists.";
            } else {
                $error = "Could not save user.";
            }
            $stmt->close();
        }
    }
}

if (isset($_GET["edit"])) {
    $eid = (int) $_GET["edit"];
    $stmt = $conn->prepare("SELECT user_id, name, role, username, email FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$rows = $conn->query("SELECT user_id, name, role, username, email, created_at FROM users ORDER BY user_id")->fetch_all(MYSQLI_ASSOC);

require_once "includes/header.php";
?>

<?php if ($msg !== "") { ?><div class="msg-ok"><?php echo h($msg); ?></div><?php } ?>
<?php if ($error !== "") { ?><div class="msg-err"><?php echo h($error); ?></div><?php } ?>

<div class="card">
    <h2><?php echo $edit ? "Edit user" : "Add user"; ?></h2>
    <form method="post">
        <input type="hidden" name="action" value="<?php echo $edit ? "edit" : "add"; ?>">
        <input type="hidden" name="id" value="<?php echo $edit ? (int) $edit["user_id"] : 0; ?>">
        <div class="form-row">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" required value="<?php echo h($edit["name"] ?? ""); ?>">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" required>
                    <?php
                    $selected = $edit["role"] ?? "Employee";
                    foreach (["Admin", "Pharmacist", "Employee"] as $r) {
                        echo '<option value="' . h($r) . '"' . ($selected === $r ? " selected" : "") . ">" . h($r) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required value="<?php echo h($edit["username"] ?? ""); ?>">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required value="<?php echo h($edit["email"] ?? ""); ?>">
            </div>
            <div class="form-group">
                <label>Password <?php echo $edit ? "(leave blank to keep)" : ""; ?></label>
                <input type="password" name="password" <?php echo $edit ? "" : "required"; ?>>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="save-btn">Save</button>
            <?php if ($edit) { ?><a class="cancel-btn" href="users.php">Cancel</a><?php } ?>
        </div>
    </form>
</div>

<input type="text" class="search-box" data-table="data-table" placeholder="Search users...">
<table id="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Role</th>
            <th>Username</th>
            <th>Email</th>
            <th>Created</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row) { ?>
        <tr>
            <td><?php echo (int) $row["user_id"]; ?></td>
            <td><?php echo h($row["name"]); ?></td>
            <td><span class="badge badge-<?php echo strtolower($row["role"]); ?>"><?php echo h($row["role"]); ?></span></td>
            <td><?php echo h($row["username"]); ?></td>
            <td><?php echo h($row["email"]); ?></td>
            <td><?php echo h($row["created_at"]); ?></td>
            <td>
                <a class="edit" href="users.php?edit=<?php echo (int) $row["user_id"]; ?>">Edit</a>
                <form method="post" class="delete-form" style="display:inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int) $row["user_id"]; ?>">
                    <button type="submit" class="delete">Delete</button>
                </form>
            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>

<?php require_once "includes/footer.php"; ?>
