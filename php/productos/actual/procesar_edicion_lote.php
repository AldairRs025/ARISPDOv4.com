<?php
session_start();
include("../conexion.php");
include("../login/registrar_actividad.php"); // 🔥 Incluir función de registro

// 🔥 VALIDAR QUE EL LOTE SEA EDITABLE
$stmt_check = mysqli_prepare($conn, "SELECT es_de_compra FROM lotes WHERE id = ?");
mysqli_stmt_bind_param($stmt_check, "i", $id_lote);
mysqli_stmt_execute($stmt_check);
$result_check = mysqli_stmt_get_result($stmt_check);
$lote_check = mysqli_fetch_assoc($result_check);

if ($lote_check && $lote_check['es_de_compra'] == 1) {
    echo json_encode([
        'success' => false,
        'message' => '❌ No se puede editar: Este lote proviene de una compra registrada'
    ]);
    exit();
}

// ... continuar con la edición normal ...

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION["user_id"] ?? 0;
    $username = $_SESSION["username"] ?? 'Desconocido';

    $id_lote = (int) ($_POST['id_lote'] ?? 0);
    $codigo_barra = $_POST['codigo_barra'] ?? '';
    $cantidad_anterior = (float) ($_POST['cantidad_anterior'] ?? 0);
    $cantidad_nueva = (float) ($_POST['cantidad'] ?? 0);
    $precio_compra_nuevo = (float) ($_POST['precio_compra'] ?? 0);
    $proveedor = $_POST['proveedor'] ?? null;

    // 🔥 VALIDACIONES
    if ($id_lote <= 0 || empty($codigo_barra)) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        exit();
    }
    if ($cantidad_nueva < 0) {
        echo json_encode(['success' => false, 'message' => 'La cantidad no puede ser negativa']);
        exit();
    }
    if ($precio_compra_nuevo < 0) {
        echo json_encode(['success' => false, 'message' => 'El precio de compra no puede ser negativo']);
        exit();
    }

    // 🔥 OBTENER DATOS ANTIGUOS DEL LOTE (Para audit log)
    $stmt_old = mysqli_prepare($conn, "SELECT * FROM lotes WHERE id = ?");
    mysqli_stmt_bind_param($stmt_old, "i", $id_lote);
    mysqli_stmt_execute($stmt_old);
    $result_old = mysqli_stmt_get_result($stmt_old);
    $lote_antiguo = mysqli_fetch_assoc($result_old);
    $datos_antiguos = $lote_antiguo;

    // Calcular diferencia de cantidad
    $diferencia = $cantidad_nueva - $cantidad_anterior;

    // 🔥 OBTENER PRECIO DE VENTA DEL PRODUCTO
    $stmt = mysqli_prepare($conn, "SELECT precio_venta, cantidad FROM productos WHERE codigo_barra = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Error en consulta: ' . mysqli_error($conn)]);
        exit();
    }
    mysqli_stmt_bind_param($stmt, "s", $codigo_barra);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $producto = mysqli_fetch_assoc($result);
    if (!$producto) {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
        exit();
    }
    $precio_venta = (float) $producto['precio_venta'];
    $cantidad_actual = (float) $producto['cantidad'];

    // 🔥 VALIDAR QUE NO QUEDE STOCK NEGATIVO
    if (($cantidad_actual + $diferencia) < 0) {
        echo json_encode(['success' => false, 'message' => 'No hay suficiente stock para esta operación']);
        exit();
    }

    // 🔥 OBTENER PRECIO COMPRA ANTERIOR DEL LOTE
    $precio_compra_anterior = (float) $lote_antiguo['precio_compra'];

    // 🔥 CALCULAR TOTALES CORRECTAMENTE
    $total_compra_lote_anterior = $cantidad_anterior * $precio_compra_anterior;
    $total_compra_lote_nuevo = $cantidad_nueva * $precio_compra_nuevo;
    $diferencia_total_compra = $total_compra_lote_nuevo - $total_compra_lote_anterior;
    $diferencia_total_venta = $diferencia * $precio_venta;

    // 🔥 CALCULAR GANANCIA DEL LOTE (NUEVO)
    $ganancia_unitaria = $precio_venta - $precio_compra_nuevo;
    $ganancia_lote_nuevo = $ganancia_unitaria * $cantidad_nueva;
    $porcentaje_ganancia_nuevo = $precio_compra_nuevo > 0 ? (($ganancia_unitaria / $precio_compra_nuevo) * 100) : 0;

    // 🔥 USAR TRANSACCIÓN
    mysqli_begin_transaction($conn);
    try {
        // 🔥 Actualizar lote con NUEVOS valores (INCLUYE GANANCIA)
        $stmt2 = mysqli_prepare($conn, "UPDATE lotes SET cantidad = ?, precio_compra = ?, proveedor = ?,
        ganancia_lote = ?, porcentaje_ganancia = ? WHERE id = ?");
        if (!$stmt2) {
            throw new Exception('Error en consulta: ' . mysqli_error($conn));
        }
        mysqli_stmt_bind_param(
            $stmt2,
            "ddsdii",
            $cantidad_nueva,
            $precio_compra_nuevo,
            $proveedor,
            $ganancia_lote_nuevo,
            $porcentaje_ganancia_nuevo,
            $id_lote
        );
        $update_lote_ok = mysqli_stmt_execute($stmt2);
        if (!$update_lote_ok) {
            throw new Exception('Error al actualizar lote: ' . mysqli_stmt_error($stmt2));
        }

        // 🔥 Actualizar producto con la DIFERENCIA calculada
        $stmt3 = mysqli_prepare($conn, "
        UPDATE productos
        SET
        cantidad = GREATEST(0, cantidad + ?),
        total_compra = GREATEST(0, total_compra + ?),
        total_venta = GREATEST(0, total_venta + ?)
        WHERE codigo_barra = ?
        ");
        if (!$stmt3) {
            throw new Exception('Error en consulta: ' . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt3, "ddds", $diferencia, $diferencia_total_compra, $diferencia_total_venta, $codigo_barra);
        $update_producto_ok = mysqli_stmt_execute($stmt3);
        if (!$update_producto_ok) {
            throw new Exception('Error al actualizar producto: ' . mysqli_stmt_error($stmt3));
        }
        mysqli_commit($conn);

        // Obtener valores actualizados
        $stmt4 = mysqli_prepare($conn, "SELECT cantidad, total_compra, total_venta FROM productos WHERE codigo_barra = ?");
        mysqli_stmt_bind_param($stmt4, "s", $codigo_barra);
        mysqli_stmt_execute($stmt4);
        $result4 = mysqli_stmt_get_result($stmt4);
        $producto_actualizado = mysqli_fetch_assoc($result4);

        // 🔥 REGISTRAR ACTIVIDAD DE ACTUALIZACIÓN
        registrar_actividad(
            $conn,
            $user_id,
            $username,
            "UPDATE",
            "Lote actualizado: de $cantidad_anterior a $cantidad_nueva unidades (Producto: $codigo_barra)",
            "lotes",
            $id_lote,
            $datos_antiguos,
            ['cantidad_nueva' => $cantidad_nueva, 'precio_compra_nuevo' => $precio_compra_nuevo, 'proveedor' => $proveedor]
        );

        // ✅ ÉXITO
        echo json_encode([
            'success' => true,
            'message' => 'Lote actualizado correctamente',
            'tipo' => 'actualizado',
            'codigo' => $codigo_barra,
            'cantidad_total' => (float) $producto_actualizado['cantidad'],
            'total_compra' => (float) $producto_actualizado['total_compra'],
            'total_venta' => (float) $producto_actualizado['total_venta']
        ]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'debug' => mysqli_error($conn)
        ]);
    }
    exit();
}
echo json_encode(['success' => false, 'message' => 'Método no permitido']);
?>