<?php
require_once "includes/db.php";
require_once "includes/auth.php";

if (!empty($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {
        $error = "Username and password required.";
    } else {
        $stmt = $conn->prepare("SELECT user_id, role, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user["password"])) {
            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["role"] = $user["role"];
            $_SESSION["username"] = $user["username"];
            header("Location: dashboard.php");
            exit;
        }

        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Khan Pharmacy</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page">
    <div class="login-card">
        <h1>Khan Pharmacy</h1>
        <p>Sign in to continue</p>
        <?php if ($error !== "") { ?>
            <div class="msg-err"><?php echo h($error); ?></div>
        <?php } ?>
        <form method="post">
            <label>Username</label>
            <input type="text" name="username" value="<?php echo h($_POST["username"] ?? ""); ?>" required>
            <label>Password</label>
            <input type="password" name="password" required>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
