<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}
include("../conexion.php");

$codigo = $_GET['codigo'] ?? '';

if (empty($codigo)) {
    echo json_encode(['stock' => 0, 'error' => 'Código requerido']);
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT cantidad FROM productos WHERE codigo_barra = ?");
mysqli_stmt_bind_param($stmt, "s", $codigo);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$producto = mysqli_fetch_assoc($result);

header('Content-Type: application/json');
echo json_encode([
    'stock' => $producto['cantidad'] ?? 0,
    'codigo' => $codigo
]);
?>