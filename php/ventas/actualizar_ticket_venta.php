<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}
include("../conexion.php");
include("../login/registrar_actividad.php");

$data = json_decode(file_get_contents('php://input'), true);
$folio = $data['folio'] ?? '';
$venta_id = (int) ($data['venta_id'] ?? 0);
$productos = $data['productos'] ?? [];

if (!$venta_id || empty($productos)) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

mysqli_begin_transaction($conn);
try {
    // 🔥 OBTENER VENTA ACTUAL
    $venta_actual = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM ventas WHERE id = $venta_id"));
    if (!$venta_actual) {
        throw new Exception('Venta no encontrada');
    }

    // 🔥 OBTENER DETALLE ACTUAL PARA CALCULAR DIFERENCIAS DE STOCK
    $detalle_actual_query = mysqli_query($conn, "SELECT codigo_barra, cantidad FROM detalle_venta WHERE venta_id = $venta_id");
    $detalle_actual = [];
    while ($row = mysqli_fetch_assoc($detalle_actual_query)) {
        $detalle_actual[$row['codigo_barra']] = (float) $row['cantidad'];
    }

    $total_venta = 0;
    $total_compra = 0;
    $productos_nuevos = [];

    foreach ($productos as $prod) {
        $id_detalle = (int) $prod['id_detalle'];
        $codigo = $prod['codigo'] ?? '';
        $cantidad = (float) $prod['cantidad'];
        $precio_venta = (float) $prod['precio'];
        $subtotal = $cantidad * $precio_venta;

        // 🔥 OBTENER PRECIO DE COMPRA
        $precio_compra_query = mysqli_query($conn, "SELECT precio_compra FROM productos WHERE codigo_barra = '$codigo'");
        $precio_compra = mysqli_fetch_assoc($precio_compra_query)['precio_compra'] ?? 0;

        // 🔥 VALIDAR STOCK DISPONIBLE
        $stock_query = mysqli_query($conn, "SELECT cantidad FROM productos WHERE codigo_barra = '$codigo'");
        $stock_disponible = mysqli_fetch_assoc($stock_query)['cantidad'] ?? 0;

        if ($cantidad > $stock_disponible) {
            throw new Exception("Stock insuficiente para producto $codigo. Disponible: $stock_disponible");
        }

        if ($id_detalle > 0) {
            // 🔥 ACTUALIZAR DETALLE EXISTENTE
            mysqli_query($conn, "UPDATE detalle_venta 
                                SET cantidad = $cantidad, precio_venta = $precio_venta, subtotal = $subtotal 
                                WHERE id = $id_detalle");
        } else {
            // 🔥 INSERTAR NUEVO PRODUCTO AL TICKET
            mysqli_query($conn, "INSERT INTO detalle_venta (venta_id, codigo_barra, cantidad, precio_venta, precio_compra, subtotal) 
                                VALUES ($venta_id, '$codigo', $cantidad, $precio_venta, $precio_compra, $subtotal)");
            $productos_nuevos[] = $codigo;
        }

        $total_venta += $subtotal;
        $total_compra += $precio_compra * $cantidad;
    }

    // 🔥 ELIMINAR PRODUCTOS QUE YA NO ESTÁN EN EL TICKET
    $productos_en_ticket = array_column($productos, 'codigo');
    foreach ($detalle_actual as $codigo => $cantidad_antigua) {
        if (!in_array($codigo, $productos_en_ticket)) {
            mysqli_query($conn, "DELETE FROM detalle_venta WHERE venta_id = $venta_id AND codigo_barra = '$codigo'");
        }
    }

    $ganancia = $total_venta - $total_compra;

    // 🔥 ACTUALIZAR VENTA
    mysqli_query($conn, "UPDATE ventas 
                        SET total_venta = $total_venta, total_compra = $total_compra, ganancia = $ganancia 
                        WHERE id = $venta_id");

    // 🔥 ACTUALIZAR STOCK DE PRODUCTOS
    foreach ($productos as $prod) {
        $codigo = $prod['codigo'] ?? '';
        $cantidad_nueva = (float) $prod['cantidad'];
        $cantidad_antigua = $detalle_actual[$codigo] ?? 0;
        $diferencia = $cantidad_nueva - $cantidad_antigua;

        if ($diferencia != 0) {
            // 🔥 CALCULAR DIFERENCIA DE TOTAL VENTA
            $precio_venta = (float) $prod['precio'];
            $diferencia_total_venta = $diferencia * $precio_venta;

            // 🔥 SI DIFERENCIA POSITIVA = RESTAR STOCK Y SUMAR TOTAL VENTA (SE VENDIÓ MÁS)
            // 🔥 SI DIFERENCIA NEGATIVA = SUMAR STOCK Y RESTAR TOTAL VENTA (SE DEVOLVIÓ)
            mysqli_query($conn, "UPDATE productos 
                        SET cantidad = GREATEST(0, cantidad - $diferencia),
                            total_venta = total_venta - $diferencia_total_venta
                        WHERE codigo_barra = '$codigo'");
        }
    }

    // 🔥 REGISTRAR ACTIVIDAD
    registrar_actividad(
        $conn,
        $_SESSION["user_id"],
        $_SESSION["username"],
        "UPDATE",
        "Ticket actualizado: Folio $folio, Total anterior: $" . number_format($venta_actual['total_venta'], 2) . ", Nuevo total: $" . number_format($total_venta, 2),
        "ventas",
        $venta_id,
        ['total_anterior' => $venta_actual['total_venta']],
        ['nuevo_total' => $total_venta, 'productos_actualizados' => count($productos)]
    );

    mysqli_commit($conn);
    echo json_encode(['success' => true, 'message' => 'Ticket actualizado correctamente. Inventario actualizado.']);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>