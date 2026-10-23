<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}
include("../conexion.php");
include("../login/registrar_actividad.php");

// 🔥 VALIDAR MÉTODO POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

$folio = $data['folio'] ?? '';
$venta_id = (int) ($data['venta_id'] ?? 0);

if (!$venta_id) {
    echo json_encode(['success' => false, 'message' => 'ID de venta inválido']);
    exit;
}

mysqli_begin_transaction($conn);
try {
    // 🔥 VERIFICAR QUE LA VENTA EXISTA
    $stmt_check = mysqli_prepare($conn, "SELECT * FROM ventas WHERE id = ?");
    mysqli_stmt_bind_param($stmt_check, "i", $venta_id);
    mysqli_stmt_execute($stmt_check);
    $result = mysqli_stmt_get_result($stmt_check);
    $venta = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt_check);

    if (!$venta) {
        throw new Exception('Venta no encontrada');
    }

    // 🔥 OBTENER DETALLE DE LA VENTA
    $stmt_detalle = mysqli_prepare($conn, "SELECT * FROM detalle_venta WHERE venta_id = ?");
    mysqli_stmt_bind_param($stmt_detalle, "i", $venta_id);
    mysqli_stmt_execute($stmt_detalle);
    $detalle = mysqli_stmt_get_result($stmt_detalle);

    // 🔥 REGRESAR PRODUCTOS AL INVENTARIO
    while ($prod = mysqli_fetch_assoc($detalle)) {
        $codigo = $prod['codigo_barra'];
        $cantidad = (float) $prod['cantidad'];
        $precio_compra = (float) $prod['precio_compra'];
        $precio_venta = (float) $prod['precio_venta']; // 🔥 AGREGAR ESTO

        // 🔥 CALCULAR EL TOTAL DE VENTA A REINTEGRAR
        $total_venta_reponer = $cantidad * $precio_venta;
        $ganancia_lote = ($precio_venta - $precio_compra) * $cantidad;

        // ✅ ACTUALIZAR PRODUCTOS (REGRESAR CANTIDAD)
        // $stmt_update = mysqli_prepare($conn, "UPDATE productos SET cantidad = cantidad + ? WHERE codigo_barra = ?");
        $stmt_update = mysqli_prepare($conn, "UPDATE productos SET cantidad = cantidad + ?, total_venta = total_venta + ? WHERE codigo_barra = ?");
        // mysqli_stmt_bind_param($stmt_update, "ds", $cantidad, $codigo);
        mysqli_stmt_bind_param($stmt_update, "dds", $cantidad, $total_venta_reponer, $codigo);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);

        // ✅ CREAR LOTE DE DEVOLUCIÓN
        $stmt_lote = mysqli_prepare($conn, "INSERT INTO lotes (codigo_barra, cantidad, precio_compra, ganancia_lote, proveedor, es_de_compra) 
                                  VALUES (?, ?, ?, ?, 'Devolución Venta', 0)");
        mysqli_stmt_bind_param($stmt_lote, "sddd", $codigo, $cantidad, $precio_compra, $ganancia_lote);
        mysqli_stmt_execute($stmt_lote);
        mysqli_stmt_close($stmt_lote);
    }
    mysqli_stmt_close($stmt_detalle);

    // 🔥 ELIMINAR DETALLE DE VENTA
    $stmt_delete_detalle = mysqli_prepare($conn, "DELETE FROM detalle_venta WHERE venta_id = ?");
    mysqli_stmt_bind_param($stmt_delete_detalle, "i", $venta_id);
    mysqli_stmt_execute($stmt_delete_detalle);
    mysqli_stmt_close($stmt_delete_detalle);

    // 🔥 ELIMINAR VENTA
    $stmt_delete_venta = mysqli_prepare($conn, "DELETE FROM ventas WHERE id = ?");
    mysqli_stmt_bind_param($stmt_delete_venta, "i", $venta_id);
    mysqli_stmt_execute($stmt_delete_venta);
    mysqli_stmt_close($stmt_delete_venta);

    // 🔥 REGISTRAR ACTIVIDAD
    registrar_actividad(
        $conn,
        $_SESSION["user_id"],
        $_SESSION["username"],
        "DELETE",
        "Ticket eliminado: Folio $folio, Total: $" . number_format($venta['total_venta'], 2),
        "ventas",
        $venta_id,
        ['folio' => $folio, 'total' => $venta['total_venta']],
        null
    );

    mysqli_commit($conn);
    echo json_encode(['success' => true, 'message' => 'Ticket eliminado correctamente. Inventario actualizado.']);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>