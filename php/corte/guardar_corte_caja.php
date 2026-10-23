<?php
// 🔥 PRIMERA LÍNEA - SIN ESPACIOS ANTES
ob_start();
session_start();
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

// 🔥 VALIDAR SESIÓN
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// 🔥 CONEXIÓN A BD
include("../conexion.php");
include("../login/registrar_actividad.php");

// 🔥 VALIDAR MÉTODO POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // 🔥 DATOS DE SESIÓN
    $usuario_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];

    // 🔥 DATOS DEL FORMULARIO
    $modo = $_POST['modo'] ?? 'crear';
    $caja_inicial = floatval($_POST['caja_inicial'] ?? 0);
    $observaciones = $_POST['observaciones'] ?? '';
    $detalle_billetes = json_decode($_POST['detalle_billetes'] ?? '{}', true);
    $total_ventas_bd = floatval($_POST['total_ventas_bd'] ?? 0);

    // 🔥 CALCULAR TOTAL EFECTIVO CONTADO (suma de billetes y monedas)
    $total_efectivo_contado = 0;
    if (is_array($detalle_billetes)) {
        foreach ($detalle_billetes as $clave => $cantidad) {
            $partes = explode('_', $clave);
            $valor = floatval($partes[0]);
            $total_efectivo_contado += $valor * intval($cantidad);
        }
    }

    // 🔥 CONVERTIR A JSON PARA GUARDAR EN BD
    $detalle_billetes_json = json_encode($detalle_billetes);

    // 🔥 OBTENER ÚLTIMO CORTE DEL USUARIO HOY (para definir periodo)
    $ultimoCorte = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT * FROM cortes_caja
        WHERE usuario_id = $usuario_id
        AND fecha_corte = CURDATE()
        ORDER BY hora_corte DESC
        LIMIT 1
    "));

    // 🔥 DEFINIR FECHA Y HORA DE INICIO DEL PERIODO
    if ($ultimoCorte && $modo !== 'editar') {
        $fecha_inicio_periodo = $ultimoCorte['fecha_corte'];
        $hora_inicio_periodo = $ultimoCorte['hora_corte'];
        $fechaDesde = $ultimoCorte['fecha_corte'] . ' ' . $ultimoCorte['hora_corte'];
    } else {
        $fecha_inicio_periodo = date('Y-m-d');
        $hora_inicio_periodo = '00:00:00';
        $fechaDesde = date('Y-m-d') . ' 00:00:00';
    }

    // 🔥 FECHA HASTA (hora actual)
    $fechaHasta = date('Y-m-d H:i:s');

    // 🔥 OBTENER COMPRAS DEL PERIODO
    $comprasPeriodo = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COALESCE(SUM(total_compra), 0) as total
        FROM compras_cabecera
        WHERE fecha_compra > '$fechaDesde'
        AND fecha_compra <= '$fechaHasta'
    "))['total'];

    // 🔥 CALCULAR SUBTOTAL: (Caja Inicial + Ventas) - Compras
    $subtotal = ($caja_inicial + $total_ventas_bd) - $comprasPeriodo;

    // 🔥 CALCULAR DIFERENCIA: Conteo - Subtotal
    $diferencia = $total_efectivo_contado - $subtotal;

    // 🔥 DETERMINAR ESTADO ***************************************** = 0
    if (abs($diferencia) < 1) {
        $estado = 'cuadrado';
    } elseif ($diferencia < 0) {
        $estado = 'faltante';
    } else {
        $estado = 'sobrante';
    }

    // 🔥 CAMPOS BÁSICOS
    $fecha_corte = date('Y-m-d');
    $hora_corte = date('H:i:s');

    // 🔥 DETECTAR CORTE ESPECIAL (cruza 2 días automáticamente)
    $corte_especial = 0;
    if ($fecha_corte != $fecha_inicio_periodo) {
        // Fecha diferente = cruzó madrugada (ej: inicia 23:00, termina 08:00 del día siguiente)
        $corte_especial = 1;
    }

    // 🔥 INSERTAR EN LA BASE DE DATOS (TU INSERT ORIGINAL)
    $stmt = mysqli_prepare($conn, "
        INSERT INTO cortes_caja
        (fecha_corte, fecha_inicio_periodo, hora_inicio_periodo, caja_inicial, hora_corte,
        usuario_id, username, total_ventas_bd, total_efectivo_contado, diferencia,
        detalle_billetes, total_compras_dia, observaciones, estado, corte_especial)
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param(
        $stmt,
        "sssdsisdddsdssi",
        $fecha_corte,
        $fecha_inicio_periodo,
        $hora_inicio_periodo,
        $caja_inicial,
        $hora_corte,
        $usuario_id,
        $username,
        $total_ventas_bd,
        $total_efectivo_contado,
        $diferencia,
        $detalle_billetes_json,
        $comprasPeriodo,
        $observaciones,
        $estado,
        $corte_especial
    );

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Error al guardar: ' . mysqli_error($conn));
    }

    $corte_id = mysqli_insert_id($conn);

    // 🔥 REGISTRAR ACTIVIDAD
    if (function_exists('registrar_actividad')) {
        registrar_actividad(
            $conn,
            $usuario_id,
            $username,
            $modo === 'editar' ? "UPDATE" : "CREATE",
            "Corte de caja " . ($modo === 'editar' ? 'actualizado' : 'realizado') .
            ": Total $" . number_format($total_efectivo_contado, 2),
            "cortes_caja",
            $corte_id,
            [],
            ['total_efectivo' => $total_efectivo_contado, 'estado' => $estado]
        );
    }

    // 🔥 ENVIAR RESPUESTA JSON
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Corte guardado correctamente',
        'corte_id' => $corte_id
    ]);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
    exit;
}
?>