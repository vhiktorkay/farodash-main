<?php
require_once '../includes/auth_functions.php';

$auth = new AuthManager();
$auth->logout();

header("Location: login.php");
exit();
?>