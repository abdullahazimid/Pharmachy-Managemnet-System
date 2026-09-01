<?php
require_once "includes/auth.php";
require_login();
header("Location: dashboard.php");
exit;
