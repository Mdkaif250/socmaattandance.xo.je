<?php
// Always start the session first
session_start();

// Unset all of the session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect the user to the login page
header("location: login.php");
exit;
?>
