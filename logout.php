<?php
session_start();  // Start the session

// Destroy the session to log the user out
session_unset();    // Unset all session variables
session_destroy();  // Destroy the session

// Redirect the user to the index page (or login page)
header('Location: index.php');
exit;
?>
