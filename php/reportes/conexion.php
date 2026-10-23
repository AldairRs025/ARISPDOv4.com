<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "losnietos";
// 🔥 CONFIGURAR ZONA HORARIA (México)
date_default_timezone_set('America/Mexico_City');

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Error de conexión: " . mysqli_connect_error());
}
?>
