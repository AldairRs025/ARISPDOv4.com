<?php
// 🔥 SIN ESPACIOS ANTES DEL <?php
ob_start();
session_start();
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_SESSION["user_id"])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

include("../conexion.php");

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

// 🔥 OBTENER EL CORTE SOLICITADO
$stmt = mysqli_prepare($conn, "
    SELECT c.*, u.username as usuario
    FROM cortes_caja c
    LEFT JOIN usuarios u ON c.usuario_id = u.id
    WHERE c.id = ?
");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$corte = mysqli_fetch_assoc($result);

if (!$corte) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Corte no encontrado']);
    exit;
}

// 🔥 DECODIFICAR DETALLE_BILLETES
$detalle_billetes = json_decode($corte['detalle_billetes'], true) ?? [];

// 🔥 OBTENER TODOS LOS CORTES DEL MISMO DÍA
$cortesDia = mysqli_query($conn, "
SELECT * FROM cortes_caja
WHERE fecha_corte = '{$corte['fecha_corte']}'
AND usuario_id = {$corte['usuario_id']}
ORDER BY hora_corte ASC
");

$cortesDelDia = [];
$ventasTotalesDia = 0;
$efectivoTotalDia = 0;
$comprasTotalesDia = 0;
$totalCortesNormales = 0;

while ($c = mysqli_fetch_assoc($cortesDia)) {
    $cortesDelDia[] = $c;

    // 🔥 SOLO SUMAR CORTES NORMALES (NO especiales)
    if (empty($c['corte_especial']) || $c['corte_especial'] == 0) {
        $ventasTotalesDia += floatval($c['total_ventas_bd']);
        $efectivoTotalDia += floatval($c['total_efectivo_contado']);
        $comprasTotalesDia += floatval($c['total_compras_dia']);
        $totalCortesNormales++;
    }
}

// 🔥 DEFINIR FECHA Y HORA DE INICIO DEL PERIODO
$fechaInicio = $corte['fecha_inicio_periodo'] ?? $corte['fecha_corte'];
$horaInicio = $corte['hora_inicio_periodo'] ?? '00:00:00';
$fechaCorte = $corte['fecha_corte'];
$horaCorte = $corte['hora_corte'];
$fechaDesde = $fechaInicio . ' ' . $horaInicio;
$fechaHasta = $fechaCorte . ' ' . $horaCorte;

// 🔥 OBTENER COMPRAS DEL PERIODO
$comprasDia = mysqli_query($conn, "
    SELECT p.nombre as proveedor, c.total_compra, c.fecha_compra
    FROM compras_cabecera c
    JOIN proveedores p ON c.proveedor_id = p.id
    WHERE DATE(c.fecha_compra) = '$fechaCorte'
    AND c.fecha_compra > '$fechaDesde'
    AND c.fecha_compra <= '$fechaHasta'
    ORDER BY c.fecha_compra ASC
");

$listaCompras = [];
while ($row = mysqli_fetch_assoc($comprasDia)) {
    $listaCompras[] = [
        'proveedor' => $row['proveedor'],
        'total' => $row['total_compra'],
        'fecha_compra' => $row['fecha_compra']
    ];
}

// 🔥 CALCULAR TOTAL DE COMPRAS DEL PERIODO
$comprasPeriodo = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(total_compra), 0) as total
    FROM compras_cabecera
    WHERE DATE(fecha_compra) = '$fechaCorte'
    AND fecha_compra > '$fechaDesde'
    AND fecha_compra <= '$fechaHasta'
"))['total'];

// 🔥 CALCULAR SUBTOTAL: (Caja Inicial + Ventas) - Compras
$cajaInicial = floatval($corte['caja_inicial'] ?? 0);
$ventasDelCorte = floatval($corte['total_ventas_bd']);
$subtotal = ($cajaInicial + $ventasDelCorte) - $comprasPeriodo;
$comprasDelCorte = floatval($corte['total_compras_dia']);

// 🔥 CALCULAR DIFERENCIA: Conteo - Subtotal
$diferencia_real = floatval($corte['total_efectivo_contado']) - $subtotal;

// 🔥 CALCULAR EFECTIVO ESPERADO: (Billetes + Monedas + Compras) - Caja Inicial
// $efectivo_esperado = (floatval($corte['total_efectivo_contado']) + $comprasPeriodo) - $cajaInicial;
$efectivo_esperado = ($cajaInicial + $ventasDelCorte) - $comprasDelCorte;

// 🔥 ENVIAR JSON
ob_end_clean();
echo json_encode([
    'success' => true,
    'corte_actual' => [
        'id' => $corte['id'],
        'caja_inicial' => number_format($corte['caja_inicial'] ?? 0, 2),
        'corte_especial' => !empty($corte['corte_especial']) ? 1 : 0,  // ✅ AGREGAR ESTO
        'fecha' => date('d/m/Y', strtotime($corte['fecha_corte'])),
        'hora' => date('h:i A', strtotime($corte['hora_corte'])),
        'hora_completa' => $corte['hora_corte'],
        'hora_inicio' => date('h:i A', strtotime($horaInicio)),
        'usuario' => $corte['usuario'] ?? 'N/A',
        'estado' => $corte['estado'],
        'total_ventas_bd' => number_format($ventasDelCorte, 2),
        'total_efectivo' => number_format($corte['total_efectivo_contado'], 2),
        'total_compras_dia' => number_format($comprasPeriodo, 2),
        'subtotal' => number_format($subtotal, 2),
        'efectivo_esperado' => number_format($efectivo_esperado, 2),
        'diferencia' => number_format($diferencia_real, 2),
        'observaciones' => $corte['observaciones'] ?? '',
        'detalle_billetes' => $detalle_billetes
    ],
    'compras_dia' => $listaCompras,
    'cortes_del_dia' => $cortesDelDia,
    'resumen_dia' => [
        'total_cortes' => $totalCortesNormales,
        'total_cortes_incluyendo_especiales' => count($cortesDelDia),
        'ventas_totales' => number_format($ventasTotalesDia, 2),
        'efectivo_total' => number_format($efectivoTotalDia, 2),
        'compras_totales' => number_format($comprasTotalesDia, 2),
        'diferencia_total' => number_format($efectivoTotalDia - ($ventasTotalesDia - $comprasTotalesDia), 2)
    ]
]);
exit;
?>