<?php
session_start();
include("../conexion.php");
include("../login/registrar_actividad.php");

// 🔥 VALIDAR SESIÓN
if (!isset($_SESSION["user_id"])) {
    echo json_encode(['success' => false, 'message' => '❌ Sesión no válida']);
    exit();
}

$user_id = $_SESSION["user_id"];
$username = $_SESSION["username"] ?? 'Desconocido';

// 🔥 OBTENER ID DEL LOTE (ESTO FALTABA)
$id_lote = isset($_POST['id_lote']) ? (int) $_POST['id_lote'] : 0;

if ($id_lote <= 0) {
    echo json_encode(['success' => false, 'message' => '❌ ID de lote inválido']);
    exit();
}

// 🔥 VALIDAR QUE EL LOTE EXISTA
$stmt_check = mysqli_prepare($conn, "SELECT es_de_compra, codigo_barra, cantidad, precio_compra, ganancia_lote, porcentaje_ganancia FROM lotes WHERE id = ?");
mysqli_stmt_bind_param($stmt_check, "i", $id_lote);
mysqli_stmt_execute($stmt_check);
$result_check = mysqli_stmt_get_result($stmt_check);
$lote_check = mysqli_fetch_assoc($result_check);

if (!$lote_check) {
    echo json_encode(['success' => false, 'message' => '❌ Lote no encontrado']);
    exit();
}

// 🔥 VALIDAR QUE NO SEA DE COMPRA REGISTRADA
if ($lote_check['es_de_compra'] == 1) {
    echo json_encode([
        'success' => false,
        'message' => '❌ No se puede eliminar: Este lote proviene de una compra registrada'
    ]);
    exit();
}

// 🔥 INICIAR TRANSACCIÓN
mysqli_begin_transaction($conn);

try {
    $codigo_barra = $lote_check['codigo_barra'];
    $cantidad_lote = (float) $lote_check['cantidad'];
    $precio_compra_lote = (float) $lote_check['precio_compra'];

    // 🔥 GUARDAR DATOS ANTIGUOS PARA AUDITORÍA
    $datos_antiguos = [
        'id' => $id_lote,
        'codigo_barra' => $codigo_barra,
        'cantidad' => $cantidad_lote,
        'precio_compra' => $precio_compra_lote,
        'ganancia_lote' => $lote_check['ganancia_lote'],
        'porcentaje_ganancia' => $lote_check['porcentaje_ganancia']
    ];

    // 🔥 OBTENER PRECIO DE VENTA DEL PRODUCTO
    $stmt2 = mysqli_prepare($conn, "SELECT precio_venta, cantidad FROM productos WHERE codigo_barra = ?");
    mysqli_stmt_bind_param($stmt2, "s", $codigo_barra);
    mysqli_stmt_execute($stmt2);
    $result2 = mysqli_stmt_get_result($stmt2);
    $producto = mysqli_fetch_assoc($result2);

    if (!$producto) {
        throw new Exception('Producto no encontrado');
    }

    $precio_venta = (float) $producto['precio_venta'];
    $cantidad_actual = (float) $producto['cantidad'];

    // 🔥 VALIDAR QUE NO QUEDE NEGATIVO
    if (($cantidad_actual - $cantidad_lote) < 0) {
        throw new Exception('❌ No se puede eliminar: quedaría stock negativo');
    }

    // 🔥 CALCULAR TOTALES A RESTAR
    $total_compra_restar = $cantidad_lote * $precio_compra_lote;
    $total_venta_restar = $cantidad_lote * $precio_venta;

    // 🔥 ELIMINAR LOTE
    $stmt3 = mysqli_prepare($conn, "DELETE FROM lotes WHERE id = ?");
    mysqli_stmt_bind_param($stmt3, "i", $id_lote);
    $delete_ok = mysqli_stmt_execute($stmt3);

    if (!$delete_ok) {
        throw new Exception('Error al eliminar lote');
    }

    // 🔥 ACTUALIZAR PRODUCTO
    $stmt4 = mysqli_prepare($conn, "
        UPDATE productos
        SET
            cantidad = GREATEST(0, cantidad - ?),
            total_compra = GREATEST(0, total_compra - ?),
            total_venta = GREATEST(0, total_venta - ?)
        WHERE codigo_barra = ?
    ");
    mysqli_stmt_bind_param($stmt4, "ddds", $cantidad_lote, $total_compra_restar, $total_venta_restar, $codigo_barra);
    $update_ok = mysqli_stmt_execute($stmt4);

    if (!$update_ok) {
        throw new Exception('Error al actualizar producto');
    }

    mysqli_commit($conn);

    // 🔥 OBTENER VALORES ACTUALIZADOS
    $stmt5 = mysqli_prepare($conn, "SELECT cantidad, total_compra, total_venta FROM productos WHERE codigo_barra = ?");
    mysqli_stmt_bind_param($stmt5, "s", $codigo_barra);
    mysqli_stmt_execute($stmt5);
    $result5 = mysqli_stmt_get_result($stmt5);
    $producto_actualizado = mysqli_fetch_assoc($result5);

    // 🔥 REGISTRAR ACTIVIDAD
    registrar_actividad(
        $conn,
        $user_id,
        $username,
        "DELETE",
        "Lote eliminado: $cantidad_lote unidades del producto $codigo_barra",
        "lotes",
        $id_lote,
        $datos_antiguos,
        []
    );

    echo json_encode([
        'success' => true,
        'message' => '✅ Lote eliminado correctamente',
        'tipo' => 'eliminado',
        'codigo' => $codigo_barra,
        'cantidad_eliminada' => $cantidad_lote,
        'cantidad_total' => (float) $producto_actualizado['cantidad'],
        'total_compra' => (float) $producto_actualizado['total_compra'],
        'total_venta' => (float) $producto_actualizado['total_venta']
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit();
?>