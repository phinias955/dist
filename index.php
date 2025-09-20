<?php
session_start();
require_once 'includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . getDashboardUrl($_SESSION['user_role']));
} else {
    header('Location: login.php');
}
exit();
?>
