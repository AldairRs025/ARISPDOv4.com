<?php
header('Content-Type: application/json');
include("../conexion.php");

$codigo = $_GET['codigo'] ?? '';

if (empty($codigo)) {
    echo json_encode(['existe' => false]);
    exit;
}

$codigo = mysqli_real_escape_string($conn, $codigo);
$result = mysqli_query($conn, "SELECT codigo_barra, nombre_producto, cantidad, precio_venta, imagen, estado FROM productos WHERE codigo_barra = '$codigo'");

if (mysqli_num_rows($result) > 0) {
    $prod = mysqli_fetch_assoc($result);
    echo json_encode([
        'existe' => true,
        'codigo' => $prod['codigo_barra'],
        'nombre' => $prod['nombre_producto'],
        'cantidad' => $prod['cantidad'],
        'precio_venta' => $prod['precio_venta'],
        'imagen' => $prod['imagen'],
        'estado' => $prod['estado']
    ]);
} else {
    echo json_encode(['existe' => false]);
}
?>