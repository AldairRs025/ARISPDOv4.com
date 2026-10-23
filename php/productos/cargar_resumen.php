<?php
include("../conexion.php");

$resumenSQL = "
SELECT
COUNT(codigo_barra) AS total_productos,
SUM(cantidad) AS total_unidades,
SUM(total_compra) AS inversion_total,
SUM(total_venta) AS venta_total,
SUM(total_venta - total_compra) AS ganancia_total,
SUM(CASE WHEN cantidad <= stock_minimo THEN 1 ELSE 0 END) AS stock_bajo
FROM productos
";

$resumen = mysqli_fetch_assoc(mysqli_query($conn, $resumenSQL));

header('Content-Type: application/json');
echo json_encode($resumen);
?>