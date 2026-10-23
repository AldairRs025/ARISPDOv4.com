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

    if (empty($folio) || $compra_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        exit();
    }

    // 🔥 OBTENER DATOS DEL TICKET ANTES DE ELIMINAR
    $ticket_query = mysqli_query($conn, "
        SELECT c.*, p.nombre as proveedor_nombre 
        FROM compras_cabecera c 
        LEFT JOIN proveedores p ON c.proveedor_id = p.id 
        WHERE c.id = $compra_id AND c.folio = '" . mysqli_real_escape_string($conn, $folio) . "'
    ");

    $ticket = mysqli_fetch_assoc($ticket_query);

    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => 'Ticket no encontrado']);
        exit();
    }

    // 🔥 OBTENER DETALLES DEL TICKET
    $detalles_query = mysqli_query($conn, "SELECT * FROM compras WHERE compra_id = $compra_id");
    $detalles = [];
    while ($row = mysqli_fetch_assoc($detalles_query)) {
        $detalles[] = $row;
    }

    // 🔥 INICIAR TRANSACCIÓN
    mysqli_begin_transaction($conn);

    try {
        // 1️⃣ ACTUALIZAR PRODUCTOS (RESTAR STOCK Y TOTALES)
        foreach ($detalles as $detalle) {
            $codigo = $detalle['codigo_barra'];
            $cantidad = $detalle['cantidad'];
            $precio_compra = $detalle['precio_compra'];
            $total = $detalle['total'];

            // Obtener precio de venta del producto
            $prod_query = mysqli_query($conn, "SELECT precio_venta FROM productos WHERE codigo_barra = '$codigo'");
            $producto = mysqli_fetch_assoc($prod_query);
            $precio_venta = $producto['precio_venta'];

            // Calcular totales a restar
            $total_venta_restar = $cantidad * $precio_venta;

            // Actualizar producto (restar cantidades y totales)
            mysqli_query($conn, "
                UPDATE productos 
                SET 
                    cantidad = GREATEST(0, cantidad - $cantidad),
                    total_compra = GREATEST(0, total_compra - $total),
                    total_venta = GREATEST(0, total_venta - $total_venta_restar)
                WHERE codigo_barra = '$codigo'
            ");

            // 2️⃣ ELIMINAR LOTES ASOCIADOS A ESTA COMPRA
            // 2️⃣ ELIMINAR LOTES ASOCIADOS A ESTA COMPRA (USANDO compra_id)
            mysqli_query($conn, "
                DELETE FROM lotes
                WHERE compra_id = $compra_id
                ");
        }

        // 3️⃣ ELIMINAR DETALLES DE COMPRA
        mysqli_query($conn, "DELETE FROM compras WHERE compra_id = $compra_id");

        // 4️⃣ ELIMINAR CABECERA DE COMPRA
        mysqli_query($conn, "DELETE FROM compras_cabecera WHERE id = $compra_id");

        // 5️⃣ ELIMINAR DE TABLAS DE HISTORIAL
        // Obtener el ID del historial usando el folio
        $historial_query = mysqli_query($conn, "SELECT id FROM historial_compras_cabecera WHERE folio = '" . mysqli_real_escape_string($conn, $folio) . "'");
        $historial_data = mysqli_fetch_assoc($historial_query);
        if ($historial_data) {
            $historial_id = $historial_data['id'];
            // Eliminar detalles del historial
            mysqli_query($conn, "DELETE FROM historial_compras_detalle WHERE historial_id = $historial_id");
            // Eliminar cabecera del historial
            mysqli_query($conn, "DELETE FROM historial_compras_cabecera WHERE folio = '" . mysqli_real_escape_string($conn, $folio) . "'");
        }

        mysqli_commit($conn);

        // 🔥 REGISTRAR ACTIVIDAD
        registrar_actividad(
            $conn,
            $user_id,
            $username,
            "DELETE",
            "Ticket eliminado: Folio $folio, Total: $" . $ticket['total_compra'],
            "compras_cabecera",
            $compra_id,
            [
                'folio' => $ticket['folio'],
                'proveedor' => $ticket['proveedor_nombre'],
                'total' => $ticket['total_compra'],
                'productos_eliminados' => count($detalles)
            ],
            []
        );

        echo json_encode([
            'success' => true,
            'message' => "✅ Ticket $folio eliminado correctamente\n📦 Se eliminaron " . count($detalles) . " productos\n💰 Total revertido: $" . number_format($ticket['total_compra'], 2)
        ]);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
    }

    exit();
}

echo json_encode(['success' => false, 'message' => 'Método no permitido']);
?>