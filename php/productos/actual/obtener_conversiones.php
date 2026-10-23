<?php
session_start();
include("../conexion.php");

$producto = $_GET['producto'] ?? '';

if (empty($producto)) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT cu.*, 
        p1.nombre_producto as nombre_padre, p1.imagen as imagen_padre,
        p2.nombre_producto as nombre_hijo, p2.imagen as imagen_hijo
        FROM conversion_unidades cu
        INNER JOIN productos p1 ON cu.producto_padre_codigo = p1.codigo_barra
        INNER JOIN productos p2 ON cu.producto_hijo_codigo = p2.codigo_barra
        WHERE cu.producto_padre_codigo = '$producto' AND cu.activo = 1
        ORDER BY cu.nivel ASC";

$result = mysqli_query($conn, $sql);
$conversiones = [];

while ($row = mysqli_fetch_assoc($result)) {
    $conversiones[] = $row;
}

echo json_encode($conversiones);
?>