<?php
require_once "includes/auth.php";

if (!empty($_SESSION["user_id"])) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit;
