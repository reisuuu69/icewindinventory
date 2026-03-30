<?php
/**
 * Icewind HVAC Inventory System - Logout
 */
session_start();
session_destroy();
header('Location: login.php');
exit();
?>