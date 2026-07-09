<?php
require_once 'config.php';
requireAuth();
$role = $_SESSION['role'];
redirect(APP_URL . "/$role/dashboard.php");
