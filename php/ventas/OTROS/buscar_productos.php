<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

include("../conexion.php");

$term = $_GET['term'] ?? '';
$limit = (int) ($_GET['limit'] ?? 20);

// 🔥 BUSCAR POR NOMBRE O CÓDIGO DE BARRAS (SOLO ACTIVOS)
$stmt = mysqli_prepare($conn, "
    SELECT codigo_barra, nombre_producto, precio_venta, cantidad, imagen, marca, categoria
    FROM productos 
    WHERE estado = 'activo' 
    AND cantidad > 0
    AND (nombre_producto LIKE ? OR codigo_barra LIKE ?)
    ORDER BY nombre_producto ASC
    LIMIT ?
");

$searchTerm = "%$term%";
mysqli_stmt_bind_param($stmt, "ssi", $searchTerm, $searchTerm, $limit);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$productos = [];
while ($row = mysqli_fetch_assoc($result)) {
    $productos[] = [
        'codigo_barra' => $row['codigo_barra'],
        'nombre_producto' => $row['nombre_producto'],
        'precio_venta' => (float) $row['precio_venta'],
        'cantidad' => (int) $row['cantidad'],
        'imagen' => $row['imagen'],  // ✅ CAMPO IMAGEN
        'marca' => $row['marca'],
        'categoria' => $row['categoria']
    ];
}

header('Content-Type: application/json');
echo json_encode($productos);
?>