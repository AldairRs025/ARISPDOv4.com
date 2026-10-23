<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

include("../conexion.php");

$data = json_decode(file_get_contents('php://input'), true);
$codigo = $data['codigo_barra'] ?? '';
$cantidad = floatval($data['cantidad'] ?? 0);
$idDetalle = $data['id_detalle'] ?? '';

if (empty($codigo) || $cantidad <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

try {
    mysqli_begin_transaction($conn);

    // 🔥 OBTENER PRECIOS PARA CALCULAR GANANCIA
    $stmt_precios = mysqli_prepare($conn, "SELECT precio_venta, precio_compra FROM productos WHERE codigo_barra = ?");
    mysqli_stmt_bind_param($stmt_precios, "s", $codigo);
    mysqli_stmt_execute($stmt_precios);
    $result_precios = mysqli_stmt_get_result($stmt_precios);
    $producto = mysqli_fetch_assoc($result_precios);
    $precio_venta = (float) $producto['precio_venta'];
    $precio_compra = (float) $producto['precio_compra'];

    // 🔥 CALCULAR GANANCIA DEL LOTE
    $ganancia_unitaria = $precio_venta - $precio_compra;
    $ganancia_lote = $ganancia_unitaria * $cantidad;
    $porcentaje_ganancia = $precio_compra > 0 ? (($ganancia_unitaria / $precio_compra) * 100) : 0;

    // 1. Actualizar stock del producto (DEVOLVER al inventario)
    $stmt = mysqli_prepare($conn, "UPDATE productos 
        SET cantidad = cantidad + ?, 
            total_venta = total_venta + (? * precio_venta)
        WHERE codigo_barra = ?");
    mysqli_stmt_bind_param($stmt, "dds", $cantidad, $cantidad, $codigo);
    mysqli_stmt_execute($stmt);

    // 🔥 CREAR LOTE DE DEVOLUCIÓN
    $stmt_lote = mysqli_prepare($conn, "INSERT INTO lotes 
        (codigo_barra, cantidad, precio_compra, ganancia_lote, porcentaje_ganancia, proveedor, es_de_compra) 
        VALUES (?, ?, ?, ?, ?, 'Devolución Venta', 0)");
    mysqli_stmt_bind_param($stmt_lote, "sdddd", $codigo, $cantidad, $precio_compra, $ganancia_lote, $porcentaje_ganancia);
    mysqli_stmt_execute($stmt_lote);

    // 🔥 ELIMINAR DEL DETALLE DE VENTA (si no es 'nuevo')
    if ($idDetalle !== 'nuevo' && !empty($idDetalle)) {
        $stmt = mysqli_prepare($conn, "DELETE FROM detalle_venta WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $idDetalle);
        mysqli_stmt_execute($stmt);
    }

    mysqli_commit($conn);

    echo json_encode(['success' => true, 'message' => 'Stock regresado correctamente']);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>