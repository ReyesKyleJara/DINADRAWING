<?php
require_once 'db_connection.php';  // use the same file your login/register uses

if ($conn) {
    echo "Connected to database successfully!";
} else {
    echo "Failed to connect to database.";
}
?>