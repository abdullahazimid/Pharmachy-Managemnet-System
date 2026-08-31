<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define("LOW_STOCK", 20);
define("EXPIRY_DAYS", 30);

function h($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, "UTF-8");
}

function require_login()
{
    if (empty($_SESSION["user_id"])) {
        header("Location: login.php");
        exit;
    }
}

function require_role()
{
    $allowed = func_get_args();
    if (!in_array($_SESSION["role"], $allowed, true)) {
        header("Location: dashboard.php");
        exit;
    }
}

function alert_status_for_stock($qty)
{
    $qty = (int) $qty;
    if ($qty <= 10) {
        return "Critical";
    }
    if ($qty < LOW_STOCK) {
        return "Warning";
    }
    return "Normal";
}
