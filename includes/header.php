<?php
if (!isset($page_title)) {
    $page_title = "Khan Pharmacy";
}
$role = $_SESSION["role"];
$current_page = basename($_SERVER["PHP_SELF"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($page_title); ?> - Khan Pharmacy</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<aside class="sidebar">
    <h2>Khan Pharmacy</h2>
    <ul>
        <li class="<?php echo $current_page === "dashboard.php" ? "active" : ""; ?>">
            <a href="dashboard.php">Dashboard</a>
        </li>
        <?php if ($role === "Admin") { ?>
        <li class="<?php echo $current_page === "users.php" ? "active" : ""; ?>">
            <a href="users.php">Users</a>
        </li>
        <?php } ?>
        <?php if ($role === "Admin" || $role === "Pharmacist") { ?>
        <li class="<?php echo $current_page === "medicines.php" ? "active" : ""; ?>">
            <a href="medicines.php">Medicines</a>
        </li>
        <li class="<?php echo $current_page === "suppliers.php" ? "active" : ""; ?>">
            <a href="suppliers.php">Suppliers</a>
        </li>
        <li class="<?php echo $current_page === "antibiotics.php" ? "active" : ""; ?>">
            <a href="antibiotics.php">Antibiotics</a>
        </li>
        <li class="<?php echo $current_page === "inventory.php" ? "active" : ""; ?>">
            <a href="inventory.php">Inventory</a>
        </li>
        <?php } ?>
        <li class="<?php echo $current_page === "sales.php" ? "active" : ""; ?>">
            <a href="sales.php">Sales</a>
        </li>
        <?php if ($role === "Admin" || $role === "Pharmacist") { ?>
        <li class="<?php echo $current_page === "reports.php" ? "active" : ""; ?>">
            <a href="reports.php">Reports</a>
        </li>
        <?php } ?>
        <?php if ($role === "Admin") { ?>
        <li class="<?php echo $current_page === "salaries.php" ? "active" : ""; ?>">
            <a href="salaries.php">Salaries</a>
        </li>
        <?php } ?>
        <li class="<?php echo $current_page === "invoices.php" || $current_page === "invoice_print.php" ? "active" : ""; ?>">
            <a href="invoices.php">Invoices</a>
        </li>
        <li class="<?php echo $current_page === "customers.php" ? "active" : ""; ?>">
            <a href="customers.php">Customers</a>
        </li>
        <li>
            <a href="logout.php">Logout (<?php echo h($_SESSION["name"]); ?>)</a>
        </li>
    </ul>
</aside>
<main class="main">
    <div class="topbar">
        <h1><?php echo h($page_title); ?></h1>
    </div>
