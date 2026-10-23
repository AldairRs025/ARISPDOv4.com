<?php
session_start();
include("../conexion.php");
include("../login/registrar_actividad.php");
header('Content-Type: application/json');

if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$user_id = $_SESSION["user_id"] ?? 0;
$username = $_SESSION["username"] ?? 'Desconocido';
$data = json_decode(file_get_contents('php://input'), true);

$fecha_inicio = $data['fecha_inicio'] ?? '';
$fecha_fin = $data['fecha_fin'] ?? '';

if (empty($fecha_inicio) || empty($fecha_fin)) {
    echo json_encode(['success' => false, 'message' => 'Fechas requeridas']);
    exit();
}

// 🔥 CONTAR REGISTROS DE HISTORIAL A ELIMINAR
$historial_query = mysqli_query($conn, "
    SELECT * FROM historial_compras_cabecera
    WHERE DATE(fecha_compra) BETWEEN '$fecha_inicio' AND '$fecha_fin'
    ORDER BY fecha_compra ASC
");

$ids_a_eliminar = [];
$total_historial = 0;
while ($row = mysqli_fetch_assoc($historial_query)) {
    $ids_a_eliminar[] = $row['id'];
    $total_historial += $row['total_compra'];
}

if (count($ids_a_eliminar) === 0) {
    echo json_encode(['success' => false, 'message' => 'No hay historial en ese rango']);
    exit();
}

// 🔥 ELIMINAR SOLO DE TABLAS DE HISTORIAL (NO AFECTA INVENTARIO)
mysqli_begin_transaction($conn);
try {
    // Eliminar detalles del historial
    mysqli_query($conn, "
        DELETE FROM historial_compras_detalle
        WHERE historial_id IN (" . implode(',', $ids_a_eliminar) . ")
    ");

    // Eliminar cabeceras del historial
    mysqli_query($conn, "
        DELETE FROM historial_compras_cabecera
        WHERE DATE(fecha_compra) BETWEEN '$fecha_inicio' AND '$fecha_fin'
    ");

    mysqli_commit($conn);

    // Registrar actividad
    registrar_actividad(
        $conn,
        $user_id,
        $username,
        "DEPURAR",
        "Historial depurado: " . count($ids_a_eliminar) . " tickets",
        "historial_compras_cabecera",
        "multiple",
        ['tickets_eliminados' => count($ids_a_eliminar), 'total_historial' => $total_historial],
        []
    );

    echo json_encode([
        'success' => true,
        'message' => "✅ Historial depurado correctamente\n" .
            "📦 Se eliminaron " . count($ids_a_eliminar) . " tickets del historial\n" .
            "💾 Los tickets reales PERMANECEN en la base de datos\n" .
            "📊 Inventario NO afectado"
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Error al depurar: ' . $e->getMessage()]);
}
?>