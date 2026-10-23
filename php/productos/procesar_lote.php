<?php
session_start();
include("../conexion.php");
include("../login/registrar_actividad.php"); // 🔥 Incluir función de registro

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION["user_id"] ?? 0;
    $username = $_SESSION["username"] ?? 'Desconocido';

    $codigo = $_POST['codigo_barra'] ?? '';
    $cantidad = (float) ($_POST['cantidad'] ?? 0);
    $precio_compra = (float) ($_POST['precio_compra'] ?? 0);
    $proveedor = $_POST['proveedor'] ?? null;

    // 🔥 VALIDAR CANTIDAD POSITIVA
    if ($cantidad <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'La cantidad debe ser mayor a 0']);
        exit();
    }

    // Validar que el producto exista
    $escaped_codigo = mysqli_real_escape_string($conn, $codigo);
    $existe = mysqli_query($conn, "SELECT precio_venta, cantidad, total_compra FROM productos WHERE codigo_barra = '$escaped_codigo'");
    if (mysqli_num_rows($existe) === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
        exit();
    }
    $producto = mysqli_fetch_assoc($existe);
    $precio_venta = (float) $producto['precio_venta'];
    $cantidad_actual = (float) $producto['cantidad'];
    $total_compra_actual = (float) $producto['total_compra'];

    // 🔥 CALCULAR NUEVA CANTIDAD TOTAL
    $cantidad_nueva_total = $cantidad_actual + $cantidad;

    // 🔥 CALCULAR TOTALES CORRECTAMENTE
    $total_compra_nuevo = $total_compra_actual + ($cantidad * $precio_compra);
    $total_venta_nuevo = $cantidad_nueva_total * $precio_venta;

    // 🔥 CALCULAR GANANCIA DEL LOTE (NUEVO)
    $ganancia_unitaria = $precio_venta - $precio_compra;
    $ganancia_lote = $ganancia_unitaria * $cantidad;
    $porcentaje_ganancia = $precio_compra > 0 ? (($ganancia_unitaria / $precio_compra) * 100) : 0;

    // 🔥 INSERTAR LOTE CON GANANCIA (MODIFICADO - 6 CAMPOS)
    // 🔥 INSERTAR LOTE CON GANANCIA (MODIFICADO - 7 CAMPOS)
    $sql_insert = "INSERT INTO lotes (codigo_barra, cantidad, precio_compra, proveedor, ganancia_lote, porcentaje_ganancia, es_de_compra)
                    VALUES (?, ?, ?, ?, ?, ?, 0)";  // 🔥 0 = Manual (editable/eliminable)
    $stmt = mysqli_prepare($conn, $sql_insert);
    mysqli_stmt_bind_param($stmt, "sddsdd", $codigo, $cantidad, $precio_compra, $proveedor, $ganancia_lote, $porcentaje_ganancia);
    $insert_ok = mysqli_stmt_execute($stmt);
    if (!$insert_ok) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al insertar lote']);
        exit();
    }

    // 🔥 OBTENER ID DEL LOTE CREADO
    $id_lote_nuevo = mysqli_insert_id($conn);

    // 🔥 USAR TRANSACCIÓN PARA SEGURIDAD
    mysqli_begin_transaction($conn);
    try {
        // Actualizar productos con los valores CALCULADOS
        $sql_update = "
        UPDATE productos
        SET
        cantidad = ?,
        total_compra = ?,
        total_venta = ?
        WHERE codigo_barra = ?
        ";
        $stmt2 = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt2, "ddds", $cantidad_nueva_total, $total_compra_nuevo, $total_venta_nuevo, $codigo);
        $update_ok = mysqli_stmt_execute($stmt2);
        if (!$update_ok) {
            throw new Exception('Error al actualizar producto');
        }
        mysqli_commit($conn);

        // 🔥 OBTENER VALORES TOTALES ACTUALIZADOS DEL PRODUCTO
        $stmt3 = mysqli_prepare($conn, "SELECT cantidad, total_compra, total_venta FROM productos WHERE codigo_barra = ?");
        mysqli_stmt_bind_param($stmt3, "s", $codigo);
        mysqli_stmt_execute($stmt3);
        $result3 = mysqli_stmt_get_result($stmt3);
        $producto_actualizado = mysqli_fetch_assoc($result3);

        // 🔥 REGISTRAR ACTIVIDAD
        registrar_actividad(
            $conn,
            $user_id,
            $username,
            "CREATE",
            "Lote agregado: $cantidad unidades al producto $codigo",
            "lotes",
            $id_lote_nuevo,
            [],
            ['codigo_barra' => $codigo, 'cantidad' => $cantidad, 'precio_compra' => $precio_compra, 'proveedor' => $proveedor]
        );

        // ✅ Éxito - Retornar valores TOTALES
        echo json_encode([
            'success' => true,
            'message' => 'Lote agregado correctamente',
            'tipo' => 'agregado',
            'codigo' => $codigo,
            'cantidad_agregada' => $cantidad,
            'cantidad_total' => (float) $producto_actualizado['cantidad'],
            'total_compra' => (float) $producto_actualizado['total_compra'],
            'total_venta' => (float) $producto_actualizado['total_venta']
        ]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}
?>