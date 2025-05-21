<?php

$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'ricette_db';

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}
?>