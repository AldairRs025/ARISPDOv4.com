<?php
session_start();
// ✅ NO REQUERIR SESIÓN PARA PERMITIR VENTA RÁPIDA
// if (!isset($_SESSION["user_id"])) {
//     http_response_code(401);
//     echo json_encode(['precio_compra' => 0]);
//     exit;
// }

include("../conexion.php");

$codigo = $_GET['codigo'] ?? '';

if (empty($codigo)) {
    echo json_encode(['precio_compra' => 0]);
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT precio_compra FROM productos WHERE codigo_barra = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $codigo);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode(['precio_compra' => (float) $row['precio_compra']]);
    } else {
        echo json_encode(['precio_compra' => 0]);
    }
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['precio_compra' => 0]);
}
?>