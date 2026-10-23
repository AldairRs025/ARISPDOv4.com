<?php
session_start();
include("../conexion.php");
include("../login/registrar_actividad.php");

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION["user_id"] ?? 0;
    $username = $_SESSION["username"] ?? 'Desconocido';

    $data = json_decode(file_get_contents('php://input'), true);

    $folio = $data['folio'] ?? '';
    $compra_id = (int) ($data['compra_id'] ?? 0);
    $productos = $data['productos'] ?? [];

    // 🔥 VALIDAR DATOS
    if (empty($folio) || $compra_id <= 0 || empty($productos)) {
        echo json_encode([
            'success' => false,
            'message' => 'Datos inválidos: Folio=' . $folio . ', ID=' . $compra_id . ', Productos=' . count($productos)
        ]);
        exit();
    }

    // 🔥 CORRECCIÓN PRINCIPAL: Buscar en compras_cabecera (NO en historial)
    $stmt_ticket = mysqli_prepare($conn, "
        SELECT * FROM compras_cabecera 
        WHERE id = ? AND folio = ?
    ");
    mysqli_stmt_bind_param($stmt_ticket, "is", $compra_id, $folio);
    mysqli_stmt_execute($stmt_ticket);
    $result_ticket = mysqli_stmt_get_result($stmt_ticket);
    $ticket = mysqli_fetch_assoc($result_ticket);

    if (!$ticket) {
        echo json_encode([
            'success' => false,
            'message' => 'Ticket no encontrado en compras_cabecera. Folio: ' . $folio . ', ID: ' . $compra_id
        ]);
        exit();
    }

    $total_anterior = $ticket['total_compra'];

    // 🔥 INICIAR TRANSACCIÓN
    mysqli_begin_transaction($conn);

    try {
        $nuevo_total = 0;
        $productos_actualizados = 0;

        foreach ($productos as $prod) {
            $id_detalle = (int) $prod['id_detalle'];
            $codigo = $prod['codigo'];
            $cantidad = (float) $prod['cantidad'];
            $precio = (float) $prod['precio'];
            $subtotal = $cantidad * $precio;

            $nuevo_total += $subtotal;

            // 🔥 OBTENER DATOS ANTERIORES DEL DETALLE (CON PREPARED STATEMENT)
            $stmt_detalle = mysqli_prepare($conn, "SELECT * FROM compras WHERE id = ?");
            mysqli_stmt_bind_param($stmt_detalle, "i", $id_detalle);
            mysqli_stmt_execute($stmt_detalle);
            $result_detalle = mysqli_stmt_get_result($stmt_detalle);
            $detalle_anterior = mysqli_fetch_assoc($result_detalle);

            if (!$detalle_anterior) {
                throw new Exception('Detalle de compra no encontrado: ' . $id_detalle);
            }

            $cantidad_anterior = (float) $detalle_anterior['cantidad'];
            $precio_anterior = (float) $detalle_anterior['precio_compra'];

            // 🔥 ACTUALIZAR DETALLE DE COMPRA (CON PREPARED STATEMENT)
            $stmt_update = mysqli_prepare($conn, "
                UPDATE compras
                SET cantidad = ?,
                    precio_compra = ?,
                    total = ?
                WHERE id = ?
            ");
            mysqli_stmt_bind_param($stmt_update, "dddi", $cantidad, $precio, $subtotal, $id_detalle);
            mysqli_stmt_execute($stmt_update);

            // 🔥 ACTUALIZAR LOTE (si existe)
            $stmt_lote = mysqli_prepare($conn, "
                SELECT id, precio_compra as precio_compra_anterior FROM lotes
                WHERE codigo_barra = ?
                AND compra_id = ?
                LIMIT 1
            ");
            mysqli_stmt_bind_param($stmt_lote, "si", $codigo, $compra_id);
            mysqli_stmt_execute($stmt_lote);
            $result_lote = mysqli_stmt_get_result($stmt_lote);
            $lote_info = mysqli_fetch_assoc($result_lote);

            if ($lote_info) {
                // 🔥 CALCULAR NUEVAS GANANCIAS
                $stmt_venta = mysqli_prepare($conn, "SELECT precio_venta FROM productos WHERE codigo_barra = ?");
                mysqli_stmt_bind_param($stmt_venta, "s", $codigo);
                mysqli_stmt_execute($stmt_venta);
                $prod_venta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_venta));
                $precio_venta = $prod_venta['precio_venta'];

                // Calcular ganancia unitaria y total
                $ganancia_unitaria = $precio_venta - $precio;
                $ganancia_lote_nueva = $ganancia_unitaria * $cantidad;
                $porcentaje_ganancia_nuevo = $precio > 0 ? (($ganancia_unitaria / $precio) * 100) : 0;

                // Actualizar lote con ganancias recalculadas
                $stmt_update_lote = mysqli_prepare($conn, "
                    UPDATE lotes
                    SET cantidad = ?,
                        precio_compra = ?,
                        ganancia_lote = ?,
                        porcentaje_ganancia = ?
                    WHERE id = ?
                ");
                mysqli_stmt_bind_param(
                    $stmt_update_lote,
                    "ddddi",
                    $cantidad,
                    $precio,
                    $ganancia_lote_nueva,
                    $porcentaje_ganancia_nuevo,
                    $lote_info['id']
                );
                mysqli_stmt_execute($stmt_update_lote);
            }

            // 🔥 ACTUALIZAR PRODUCTO (inventario)
            $stmt_producto = mysqli_prepare($conn, "SELECT precio_venta FROM productos WHERE codigo_barra = ?");
            mysqli_stmt_bind_param($stmt_producto, "s", $codigo);
            mysqli_stmt_execute($stmt_producto);
            $producto = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_producto));
            $precio_venta = $producto['precio_venta'];

            $diferencia_cantidad = $cantidad - $cantidad_anterior;
            $diferencia_total_compra = $subtotal - $detalle_anterior['total'];
            $diferencia_total_venta = $diferencia_cantidad * $precio_venta;

            $stmt_update_prod = mysqli_prepare($conn, "
                UPDATE productos
                SET
                    cantidad = GREATEST(0, cantidad + ?),
                    total_compra = GREATEST(0, total_compra + ?),
                    total_venta = GREATEST(0, total_venta + ?)
                WHERE codigo_barra = ?
            ");
            mysqli_stmt_bind_param($stmt_update_prod, "ddds", $diferencia_cantidad, $diferencia_total_compra, $diferencia_total_venta, $codigo);
            mysqli_stmt_execute($stmt_update_prod);

            $productos_actualizados++;
        }

        // 🔥 ACTUALIZAR TOTAL DE CABECERA (CON PREPARED STATEMENT)
        $stmt_cabecera = mysqli_prepare($conn, "UPDATE compras_cabecera SET total_compra = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_cabecera, "di", $nuevo_total, $compra_id);
        mysqli_stmt_execute($stmt_cabecera);

        mysqli_commit($conn);

        // 🔥 REGISTRAR ACTIVIDAD
        registrar_actividad(
            $conn,
            $user_id,
            $username,
            "UPDATE",
            "Ticket actualizado: Folio $folio, Total anterior: $$total_anterior, Nuevo total: $$nuevo_total",
            "compras_cabecera",
            $compra_id,
            ['total_anterior' => $total_anterior],
            ['nuevo_total' => $nuevo_total, 'productos_actualizados' => $productos_actualizados]
        );

        echo json_encode([
            'success' => true,
            'message' => "✅ Ticket actualizado correctamente\n📦 Se actualizaron $productos_actualizados productos\n💰 Nuevo total: $" . number_format($nuevo_total, 2)
        ]);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode([
            'success' => false,
            'message' => 'Error al actualizar: ' . $e->getMessage()
        ]);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Método no permitido']);
?>