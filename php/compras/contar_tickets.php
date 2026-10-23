<?php
session_start();
include("../conexion.php");
header('Content-Type: application/json');

if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

if (empty($fecha_inicio) || empty($fecha_fin)) {
    echo json_encode(['success' => false, 'message' => 'Fechas requeridas']);
    exit();
}

// Contar tickets y total
$query = mysqli_query($conn, "
    SELECT COUNT(*) as cantidad, SUM(total_compra) as total
    FROM compras_cabecera
    WHERE DATE(fecha_compra) BETWEEN '$fecha_inicio' AND '$fecha_fin'
");

$resultado = mysqli_fetch_assoc($query);

echo json_encode([
    'success' => true,
    'cantidad' => $resultado['cantidad'] ?? 0,
    'total' => $resultado['total'] ?? 0
]);
?>