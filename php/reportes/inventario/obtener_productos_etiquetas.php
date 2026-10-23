<?php
session_start();
include("../../conexion.php"); // Ajusta la ruta si es necesario

$modo = $_GET['modo'];
$codigo = $_GET['codigo'] ?? '';

// Consulta principal
$query = "SELECT 
    p.codigo_barra, 
    p.nombre_producto, 
    p.categoria, 
    p.precio_venta, 
    p.cantidad,
    (
        -- SUBCONSULTA: Trae el precio_compra de la última entrada (Compra o Conversión)
        SELECT l.precio_compra 
        FROM lotes l 
        WHERE l.codigo_barra = p.codigo_barra 
        AND (l.es_de_compra = 1 OR l.proveedor = 'CONVERSIÓN')
        ORDER BY l.id DESC -- Ordenamos por ID descendente para obtener el último
        LIMIT 1
    ) AS ultimo_precio_compra
FROM productos p 
WHERE p.estado = 'activo'";

if ($modo == 'individual' && !empty($codigo)) {
    $codigo = mysqli_real_escape_string($conn, $codigo);
    $query .= " AND p.codigo_barra LIKE '%$codigo%'";
}

$query .= " ORDER BY p.nombre_producto ASC";

$result = mysqli_query($conn, $query);
$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    // Si no tiene precio de compra registrado, lo ponemos en 0
    if ($row['ultimo_precio_compra'] === null) {
        $row['ultimo_precio_compra'] = 0.00;
    }
    $data[] = $row;
}

header('Content-Type: application/json');
echo json_encode($data);
?>