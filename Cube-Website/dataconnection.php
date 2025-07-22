<?php

$host = 'localhost';
$dbname = 'Cube';
$username = 'root';
$password = '';

$conn = mysqli_connect("localhost", "root", "", "Cube");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

?>