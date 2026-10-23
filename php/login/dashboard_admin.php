<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../../index.html");
    exit;
}
if ($_SESSION["rol"] !== "admin") {
    header("Location: dashboard_empleado.php");
    exit;
}

// 🔥 CONEXIÓN A BASE DE DATOS
include("../conexion.php");

// 🔥 OBTENER DATOS DEL RESUMEN DE PRODUCTOS (INVENTARIO)
$resumenInventarioSQL = "
SELECT
    COUNT(codigo_barra) AS total_productos,
    SUM(cantidad) AS total_unidades,
    SUM(CASE WHEN cantidad <= stock_minimo THEN 1 ELSE 0 END) AS stock_bajo
FROM productos
";
$resumenInventario = mysqli_fetch_assoc(mysqli_query($conn, $resumenInventarioSQL));

// 🔥 OBTENER VENTAS DEL DÍA (tabla ventas)
$fechaHoy = date('Y-m-d');
$resumenVentasSQL = "
SELECT
    COUNT(folio) AS total_ventas,
    SUM(total_venta) AS venta_total,
    SUM(total_compra) AS compra_total,
    SUM(ganancia) AS ganancia_total
FROM ventas
WHERE DATE(fecha_venta) = '$fechaHoy'
AND estado = 'completada'
";
$resumenVentas = mysqli_fetch_assoc(mysqli_query($conn, $resumenVentasSQL));

// 🔥 VENTAS DEL MES ACTUAL
$inicioMes = date('Y-m-01');
$ventasMesSQL = "
SELECT
    SUM(total_venta) AS venta_mes,
    SUM(ganancia) AS ganancia_mes
FROM ventas
WHERE DATE(fecha_venta) >= '$inicioMes'
AND DATE(fecha_venta) <= '$fechaHoy'
AND estado = 'completada'
";
$ventasMes = mysqli_fetch_assoc(mysqli_query($conn, $ventasMesSQL));

// 🔥 VENTAS DEL AÑO ACTUAL (Enero a Diciembre)
$inicioAnio = date('Y-01-01');
$finAnio = date('Y-12-31');
$ventasAnioSQL = "
SELECT
    SUM(total_venta) AS venta_anio,
    SUM(ganancia) AS ganancia_anio
FROM ventas
WHERE DATE(fecha_venta) >= '$inicioAnio'
AND DATE(fecha_venta) <= '$finAnio'
AND estado = 'completada'
";
$ventasAnio = mysqli_fetch_assoc(mysqli_query($conn, $ventasAnioSQL));

// 🔥 VENTAS DEL ÚLTIMO AÑO (Últimos 365 días)
$inicioUltimoAnio = date('Y-m-d', strtotime('-365 days'));
$ventasUltimoAnioSQL = "
SELECT
    SUM(total_venta) AS venta_ultimo_anio,
    SUM(ganancia) AS ganancia_ultimo_anio
FROM ventas
WHERE DATE(fecha_venta) >= '$inicioUltimoAnio'
AND DATE(fecha_venta) <= '$fechaHoy'
AND estado = 'completada'
";
$ventasUltimoAnio = mysqli_fetch_assoc(mysqli_query($conn, $ventasUltimoAnioSQL));

// 🔥 COMBINAR RESULTADOS
$resumen = [
    'total_productos' => $resumenInventario['total_productos'] ?? 0,
    'total_unidades' => $resumenInventario['total_unidades'] ?? 0,
    'stock_bajo' => $resumenInventario['stock_bajo'] ?? 0,
    'venta_total' => $resumenVentas['venta_total'] ?? 0,  // Del día
    'ganancia_total' => $resumenVentas['ganancia_total'] ?? 0,  // Del día
    'total_ventas' => $resumenVentas['total_ventas'] ?? 0,  // Del día
    'compra_total' => $resumenVentas['compra_total'] ?? 0,  // Del día
    'venta_mes' => $ventasMes['venta_mes'] ?? 0,  // 🔥 NUEVO
    'ganancia_mes' => $ventasMes['ganancia_mes'] ?? 0,  // 🔥 NUEVO
    'venta_anio' => $ventasAnio['venta_anio'] ?? 0,  // 🔥 NUEVO
    'ganancia_anio' => $ventasAnio['ganancia_anio'] ?? 0,  // 🔥 NUEVO
    'venta_ultimo_anio' => $ventasUltimoAnio['venta_ultimo_anio'] ?? 0,  // 🔥 NUEVO
    'ganancia_ultimo_anio' => $ventasUltimoAnio['ganancia_ultimo_anio'] ?? 0  // 🔥 NUEVO
];

// 🔥 OBTENER ACTIVIDAD RECIENTE
$actividadSQL = "
SELECT 
    nombre_producto,
    fecha_ingreso,
    cantidad,
    total_venta,
    estado
FROM productos 
ORDER BY fecha_ingreso DESC 
LIMIT 10
";
$actividad = mysqli_query($conn, $actividadSQL);

// 🔥 NUEVO: OBTENER ALERTAS DE LOTES VENCIDOS
$lotesVencidos = [];
$vencidosCount = 0;
$lotesProximos = [];
$proximosCount = 0;

// 🔥 NUEVO: OBTENER PRODUCTOS CON STOCK BAJO
$stockBajoSQL = "
SELECT codigo_barra, nombre_producto, cantidad, stock_minimo
FROM productos
WHERE cantidad <= stock_minimo
ORDER BY cantidad ASC
LIMIT 5
";
$stockBajo = mysqli_query($conn, $stockBajoSQL);
$stockBajoCount = mysqli_num_rows($stockBajo);

// 🔥 CALCULAR PORCENTAJES DE CRECIMIENTO
$crecimientoVentas = 10;
$crecimientoAnual = 20;
$crecimientoIngresos = 5;

// 🔥 OBTENER DATOS PARA GRÁFICA - VENTAS REALES POR PERÍODO
$periodo = $_GET['periodo'] ?? 'mes'; // día, semana, mes, cuatrimestre, anual

// 🔥 FUNCIONES AUXILIARES PARA OBTENER FECHAS SEGÚN PERÍODO
function obtenerFechasPeriodo($periodo)
{
    switch ($periodo) {
        case 'dia':
            return ['inicio' => date('Y-m-d', strtotime('-7 days')), 'fin' => date('Y-m-d'), 'formato' => '%Y-%m-%d'];
        case 'semana':
            // ✅ CORREGIDO: Usar WEEK() con Mode 0 (Domingo como inicio)
            return ['inicio' => date('Y-m-d', strtotime('-8 weeks')), 'fin' => date('Y-m-d'), 'formato' => 'semana'];
        case 'mes':
            return ['inicio' => date('Y-m-d', strtotime('-12 months')), 'fin' => date('Y-m-d'), 'formato' => '%Y-%m'];
        case 'cuatrimestre':
            return ['inicio' => date('Y-m-d', strtotime('-3 years')), 'fin' => date('Y-m-d'), 'formato' => '%Y-Q'];
        case 'anual':
        default:
            return ['inicio' => date('Y-m-d', strtotime('-5 years')), 'fin' => date('Y-m-d'), 'formato' => '%Y'];
    }
}

$fechas = obtenerFechasPeriodo($periodo);

// 🔥 CONSULTA 1: VENTAS (tabla ventas)
// 🔥 CONSULTA 1: VENTAS (tabla ventas)
if ($periodo == 'semana') {
    // ✅ SEMANAS CON DOMINGO COMO INICIO (Mode 0)
    $ventasSQL = "
    SELECT
        CONCAT(YEAR(fecha_venta), '-', WEEK(fecha_venta, 0)) as periodo,
        CONCAT('Sem ', WEEK(fecha_venta, 0)) as label,
        SUM(total_venta) as ventas_totales,
        SUM(ganancia) as ganancias_totales
    FROM ventas
    WHERE estado = 'completada'
    AND DATE(fecha_venta) BETWEEN '{$fechas['inicio']}' AND '{$fechas['fin']}'
    GROUP BY YEAR(fecha_venta), WEEK(fecha_venta, 0)
    ORDER BY periodo ASC
    ";
} else {
    $ventasSQL = "
    SELECT
        DATE_FORMAT(fecha_venta, '{$fechas['formato']}') as periodo,
        DATE_FORMAT(fecha_venta, '{$fechas['formato']}') as label,
        SUM(total_venta) as ventas_totales,
        SUM(ganancia) as ganancias_totales
    FROM ventas
    WHERE estado = 'completada'
    AND DATE(fecha_venta) BETWEEN '{$fechas['inicio']}' AND '{$fechas['fin']}'
    GROUP BY DATE_FORMAT(fecha_venta, '{$fechas['formato']}')
    ORDER BY periodo ASC
    ";
}
$ventasQuery = mysqli_query($conn, $ventasSQL);
$ventasData = [];
while ($row = mysqli_fetch_assoc($ventasQuery)) {
    $ventasData[$row['periodo']] = [
        'ventas' => $row['ventas_totales'] ?? 0,
        'ganancias' => $row['ganancias_totales'] ?? 0
    ];
}

// 🔥 CONSULTA 2: COMPRAS (tabla compras) - ¡ESTO FALTABA!
// 🔥 CONSULTA 2: COMPRAS (tabla compras)
if ($periodo == 'semana') {
    // ✅ SEMANAS CON DOMINGO COMO INICIO (Mode 0)
    $comprasSQL = "
    SELECT
        CONCAT(YEAR(fecha_compra), '-', WEEK(fecha_compra, 0)) as periodo,
        CONCAT('Sem ', WEEK(fecha_compra, 0)) as label,
        SUM(total) as compras_totales
    FROM compras
    WHERE DATE(fecha_compra) BETWEEN '{$fechas['inicio']}' AND '{$fechas['fin']}'
    GROUP BY YEAR(fecha_compra), WEEK(fecha_compra, 0)
    ORDER BY periodo ASC
    ";
} else {
    $comprasSQL = "
    SELECT
        DATE_FORMAT(fecha_compra, '{$fechas['formato']}') as periodo,
        SUM(total) as compras_totales
    FROM compras
    WHERE DATE(fecha_compra) BETWEEN '{$fechas['inicio']}' AND '{$fechas['fin']}'
    GROUP BY DATE_FORMAT(fecha_compra, '{$fechas['formato']}')
    ORDER BY periodo ASC
    ";
}
$comprasQuery = mysqli_query($conn, $comprasSQL);
$comprasData = [];
while ($row = mysqli_fetch_assoc($comprasQuery)) {
    $comprasData[$row['periodo']] = $row['compras_totales'] ?? 0;
}

// 🔥 UNIR AMBOS DATOS POR PERÍODO
$labelsGrafica = [];
$dataVentas = [];
$dataCompras = [];
$dataGanancias = [];

// 🔥 OBTENER TODOS LOS PERÍODOS ÚNICOS
$allPeriods = array_unique(array_merge(array_keys($ventasData), array_keys($comprasData)));
sort($allPeriods);

foreach ($allPeriods as $periodo) {
    // 🔥 FORMATO DE LABEL SEGÚN PERÍODO
    if ($periodo == 'semana') {
        // ✅ El label ya viene como 'Sem XX' desde la consulta
        $labelsGrafica[] = $comprasData[$periodo]['label'] ?? $ventasData[$periodo]['label'] ?? $periodo;
    } else {
        switch ($periodo) {
            case 'dia':
                $labelsGrafica[] = date('d', strtotime($periodo));
                break;
            case 'mes':
                $labelsGrafica[] = date('M', strtotime($periodo));
                break;
            case 'cuatrimestre':
                $labelsGrafica[] = $periodo;
                break;
            case 'anual':
                $labelsGrafica[] = $periodo;
                break;
            default:
                $labelsGrafica[] = $periodo;
        }
    }

    $dataVentas[] = $ventasData[$periodo]['ventas'] ?? 0;
    $dataCompras[] = $comprasData[$periodo] ?? 0;
    $dataGanancias[] = $ventasData[$periodo]['ganancias'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Los Nietos®</title>

    <!-- CSS DASHBOARD -->
    <link rel="stylesheet" href="../../css/AdminPanel-STYLE/dash.css">
    <link rel="icon" href="../../img/LOGIN/icon_24px.png" type="img/INICIO/icon_24px.png" />

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Chart.js para gráficas -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* 🔥 BOTONES FILTRO MEJORADOS */
        .filtros-grafica {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filtros-grafica .btn-filtro {
            padding: 10px 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: #495057;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            font-family: 'Kalam', cursive;
        }

        .filtros-grafica .btn-filtro:hover {
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
            border-color: #adb5bd;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .filtros-grafica .btn-filtro.activo {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
            border-color: #0d6efd;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.4);
            transform: translateY(-2px);
        }

        /* 🔥 ESTILOS PARA LA GRÁFICA */
        .grafica-container {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            height: 400px;
        }

        /* 🔥 PANEL MEJORADO */
        .panel {
            background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .panel-header h3 {
            margin: 0;
            color: #212529;
            font-size: 18px;
            font-weight: 700;
            font-family: 'Kalam', cursive;
        }

        /* 🔥 ANIMACIÓN DE CONTEO PARA CARDS */
        .contador {
            transition: all 0.1s ease;
        }

        .contador.animando {
            color: #28a745;
            font-weight: 900;
            transform: scale(1.05);
        }

        .contador.completo {
            color: inherit;
            font-weight: 700;
            transform: scale(1);
            transition: all 0.3s ease;
        }

        /* 🔥 EFECTO DE PARPADEO AL COMPLETAR */
        @keyframes pulse-completo {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        .contador.completo {
            animation: pulse-completo 0.5s ease;
        }
    </style>
</head>

<body>

    <!-- ===============================
    SIDEBAR
    ================================-->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">Los Nietos®</div>
            <div class="sidebar-user">
                <i class="bi bi-person-circle"></i>
                <div class="sidebar-user-info">
                    <h4><?= htmlspecialchars($_SESSION["username"]); ?></h4>
                    <p>👑 Administrador</p>
                </div>
            </div>
        </div>

        <nav class="sidebar-menu">
            <div class="sidebar-menu-title">📊 Navegación</div>
            <a href="dashboard_admin.php" class="sidebar-menu-item activo">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>

            <div class="sidebar-divider"></div>

            <!-- <div class="sidebar-menu-title">📦 Inventario</div> -->
            <div class="sidebar-menu-item" onclick="toggleSubmenu('submenu-productos')">
                <i class="bi bi-box-seam"></i>
                <span>Productos</span>
                <i class="bi bi-chevron-down flecha-submenu"></i>
            </div>
            <div id="submenu-productos" class="sidebar-submenu">
                <a href="../productos/CRUD-Product.php" class="sidebar-submenu-item">
                    <i class="bi bi-circle"></i> Administrar Productos
                </a>
                <a href="../productos/carga_masiva.php" class="sidebar-submenu-item">
                    <i class="bi bi-circle"></i> Carga Masiva
                </a>
                <!-- <a href="#" class="sidebar-submenu-item">
                    <i class="bi bi-circle"></i> Traspasos (P)
                </a> -->
                <a href="../productos/registrar_conversion.php" class="sidebar-submenu-item">
                    <i class="bi bi-circle"></i> Registrar Conversión
                </a>
                <a href="../productos/realizar_desglose.php" class="sidebar-submenu-item">
                    <i class="bi bi-circle"></i> Realizar Conversión
                </a>
            </div>

            <!-- 🔥 SECCIÓN PROVEEDORES CON SUBMENÚ -->
            <!-- <div class="sidebar-divider"></div>
            <div class="sidebar-menu-title">🚚 Proveedores</div> -->

            <div class="sidebar-menu-item" onclick="toggleSubmenu('submenu-proveedores')">
                <i class="bi bi-truck"></i>
                <span>Proveedores</span>
                <i class="bi bi-chevron-down flecha-submenu"></i>
            </div>
            <div id="submenu-proveedores" class="sidebar-submenu">
                <a href="../proveedores/CRUD-Proveedores.php" class="sidebar-submenu-item">
                    <i class="bi bi-circle"></i> Administrar Proveedores
                </a>
                <a href="../compras/registrar_compra.php" class="sidebar-submenu-item">
                    <i class="bi bi-circle"></i> Registrar Compra
                </a>
                <a href="../compras/buscar_compra_folio.php" class="sidebar-submenu-item">
                    <i class="bi bi-circle"></i> Tickets
                </a>
                <a href="../compras/historial_compras.php" class="sidebar-submenu-item">
                    <i class="bi bi-circle"></i> Historial de Compras
                </a>
                <a href="../compras/ordenes_compra.php" class="sidebar-submenu-item">
                    <i class="bi bi-circle"></i> Ordenes de Compra
                </a>
            </div>

            <div class="sidebar-menu-item" onclick="toggleSubmenu('submenu-ventas')">
                <i class="bi bi-cash-stack"></i>
                <span>Ventas</span>
                <i class="bi bi-chevron-down flecha-submenu"></i>
            </div>
            <div id="submenu-ventas" class="sidebar-submenu">
                <a href="../ventas/CRUD-Ventas.php" class="sidebar-submenu-item">
                    <i class="bi bi-circle"></i> Registrar Venta
                </a>
                <a href="../ventas/buscar_venta_folio.php" class="sidebar-submenu-item">
                    <i class="bi bi-circle"></i> Ventas
                </a>
                <a href="../ventas/historial_ventas.php" class="sidebar-submenu-item">
                    <i class="bi bi-circle"></i> Historial de Ventas
                </a>
                <a href="../ventas/devoluciones_venta.php" class="sidebar-submenu-item">
                    <i class="bi bi-circle"></i> Devoluciones
                </a>
            </div>

            <!-- <div class="sidebar-divider"></div>
            <div class="sidebar-menu-title">💰 Finanzas</div> -->

            <div class="sidebar-menu-item" onclick="toggleSubmenu('submenu-caja')">
                <i class="bi bi-cash-coin"></i>
                <span>Caja</span>
                <i class="bi bi-chevron-down flecha-submenu"></i>
            </div>
            <div id="submenu-caja" class="sidebar-submenu">
                <a href="../corte/corte_caja.php" class="sidebar-submenu-item">
                    <i class="bi bi-circle"></i> Corte del Día
                </a>
                <a href="../corte/historial_corte.php" class="sidebar-submenu-item">
                    <i class="bi bi-circle"></i> Historial de Caja
                </a>
            </div>

            <!-- <div class="sidebar-divider"></div>
            <div class="sidebar-menu-title">👥 Administración</div> -->

            <a href="CRUD-Usuarios.php" class="sidebar-menu-item">
                <i class="bi bi-people-fill"></i>
                <span>Usuarios</span>
            </a>

            <!-- <a href="../ofertas/buscar_ofertas.php" class="sidebar-menu-item">
                <i class="bi bi-fire"></i>
                <span>Ofertas (P)</span>
            </a> -->

            <div class="sidebar-divider"></div>

            <div class="sidebar-menu-title">⚙️ Sistema</div>
            <!-- <a href="configuracion.php" class="sidebar-menu-item">
                <i class="bi bi-gear-fill"></i>
                <span>Configuración</span>
            </a> -->
            <a href="actividades.php" class="sidebar-menu-item">
                <i class="bi bi-journal-text"></i>
                <span>Actividades</span>
            </a>
            <a href="../reportes/reporte.php" class="sidebar-menu-item">
                <i class="bi bi-journal-text"></i>
                <span>Reportes</span>
            </a>
            <a href="logout.php" class="sidebar-menu-item" style="color: #ffc107;">
                <i class="bi bi-box-arrow-right"></i>
                <span>Cerrar Sesión</span>
            </a>
        </nav>
    </aside>

    <!-- ===============================
    CONTENIDO PRINCIPAL
    ================================-->
    <div class="contenido-principal">

        <!-- BARRA SUPERIOR -->
        <div class="barra-superior">
            <div class="logo">Los Nietos®</div>
            <div class="iconos">
                <a href="dashboard_admin.php" class="icon-btn">
                    <i class="bi bi-house-fill"></i>
                    <span class="tooltip">INICIO</span>
                </a>
                <a href="CRUD-Usuarios.php" class="icon-btn">
                    <i class="bi bi-people-fill"></i>
                    <span class="tooltip">USUARIOS</span>
                </a>
                <a href="../proveedores/CRUD-Proveedores.php" class="icon-btn">
                    <i class="bi bi-truck"></i>
                    <span class="tooltip">PROVEEDORES</span>
                </a>
                <a href="../productos/CRUD-Product.php" class="icon-btn">
                    <i class="bi bi-box-seam"></i>
                    <span class="tooltip">PRODUCTOS</span>
                </a>
                <a href="../ventas/CRUD-Ventas.php" class="icon-btn">
                    <i class="bi bi-cash-stack"></i>
                    <span class="tooltip">VENTAS</span>
                </a>
                <a href="../corte/corte_caja.php" class="icon-btn">
                    <i class="bi bi-cash-coin"></i>
                    <span class="tooltip">CAJA</span>
                </a>
                <a href="logout.php" class="icon-btn">
                    <i class="bi bi-box-arrow-right"></i>
                    <span class="tooltip">SALIR</span>
                </a>
            </div>
        </div>

        <!-- CONTENIDO DASHBOARD -->
        <div class="dashboard-container">

            <!-- HEADER -->
            <div class="dashboard-header">
                <div>
                    <h1>👋 ¡Bienvenido de vuelta, <?= htmlspecialchars($_SESSION["username"]); ?>!</h1>
                    <p>Panel de Administración</p>
                </div>
                <div class="fecha-actual">
                    <p>📅 <?= date('d/m/Y'); ?></p>
                    <p>🕐 <?= date('h:i A'); ?></p>
                </div>
            </div>

            <!-- CARDS DE RESUMEN -->
            <div class="cards-resumen">
                <div class="card">
                    <div class="card-icon productos">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div class="card-info">
                        <h3 data-target="<?= $resumen['total_productos'] ?? 0; ?>" class="contador">0</h3>
                        <p>Productos</p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon ventas">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <div class="card-info">
                        <h3 data-target="<?= $resumen['total_ventas'] ?? 0; ?>" class="contador">0</h3>
                        <p>Ventas Hoy</p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon ventas">
                        <i class="bi bi-currency-dollar"></i>
                    </div>
                    <div class="card-info">
                        <h3 data-target="<?= $resumen['venta_total'] ?? 0; ?>" data-decimales="2"
                            class="contador moneda">0</h3>
                        <p>Venta Total</p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon ganancia">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div class="card-info">
                        <h3 data-target="<?= $resumen['ganancia_total'] ?? 0; ?>" data-decimales="2"
                            class="contador moneda">0</h3>
                        <p>Ganancia</p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon stock">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="card-info">
                        <h3 data-target="<?= $resumen['stock_bajo'] ?? 0; ?>" class="contador">0</h3>
                        <p>Stock Bajo</p>
                    </div>
                </div>
            </div>

            <!-- ===============================
            SECCIÓN PRINCIPAL (GRÁFICA + ACTIVIDAD)
            ================================-->
            <div class="seccion-principal">

                <!-- GRÁFICA DE ESTADO DE RESULTADO -->
                <div class="panel">
                    <div class="panel-header">
                        <h3>📊 Estado de Resultado</h3>
                        <div class="filtros-grafica">
                            <a href="?periodo=dia" class="btn-filtro <?= $periodo == 'dia' ? 'activo' : ''; ?>">
                                <i class="bi bi-calendar-day"></i> Día
                            </a>
                            <a href="?periodo=semana" class="btn-filtro <?= $periodo == 'semana' ? 'activo' : ''; ?>">
                                <i class="bi bi-calendar-week"></i> Semana
                            </a>
                            <a href="?periodo=mes" class="btn-filtro <?= $periodo == 'mes' ? 'activo' : ''; ?>">
                                <i class="bi bi-calendar-month"></i> Mes
                            </a>
                            <a href="?periodo=cuatrimestre"
                                class="btn-filtro <?= $periodo == 'cuatrimestre' ? 'activo' : ''; ?>">
                                <i class="bi bi-calendar3"></i> Cuatrimestre
                            </a>
                            <a href="?periodo=anual" class="btn-filtro <?= $periodo == 'anual' ? 'activo' : ''; ?>">
                                <i class="bi bi-calendar-check"></i> Anual
                            </a>
                        </div>
                    </div>
                    <div class="grafica-container">
                        <canvas id="graficaEstadoResultado"></canvas>
                    </div>

                    <!-- EXPORTAR REPORTES -->
                    <div class="exportar-botones">
                        <a href="exportar_reporte.php?tipo=pdf" class="btn-exportar btn-pdf">
                            <i class="bi bi-file-earmark-pdf"></i>
                            <span>PDF</span>
                        </a>
                        <a href="exportar_reporte.php?tipo=excel" class="btn-exportar btn-excel">
                            <i class="bi bi-file-earmark-excel"></i>
                            <span>Excel</span>
                        </a>
                    </div>
                </div>

                <!-- ACTIVIDAD RECIENTE -->
                <div class="panel">
                    <div class="panel-header">
                        <h3>🕐 Actividad Reciente</h3>
                        <a href="actividad.php" class="ver-mas">Ver más →</a>
                    </div>
                    <div class="actividad-lista">
                        <?php
                        while ($act = mysqli_fetch_assoc($actividad)) {
                            echo '
                                <div class="actividad-item">
                                    <div class="actividad-icono">
                                        <i class="bi bi-box-seam"></i>
                                    </div>
                                    <div class="actividad-info">
                                        <h4>' . htmlspecialchars($act['nombre_producto']) . '</h4>
                                        <p>📦 ' . $act['cantidad'] . ' unidades | $' . number_format($act['total_venta'], 2) . '</p>
                                    </div>
                                    <div class="actividad-fecha">
                                        ' . date('d/m H:i', strtotime($act['fecha_ingreso'])) . '
                                    </div>
                                </div>';
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- ===============================
            NUEVA DISPOSICIÓN (MÉTRICAS IZQUIERDA + ALERTAS DERECHA)
            ================================-->
            <div class="seccion-principal">

                <!-- 🔥 MÉTRICAS DE VENTAS (AHORA A LA IZQUIERDA) -->
                <div class="panel">
                    <div class="panel-header">
                        <h3>📈 Métricas de Ventas</h3>
                    </div>
                    <div class="metricas-laterales">
                        <div class="metrica-item">
                            <div>
                                <h4>Ventas del Mes</h4>
                                <div class="valor">$<?= number_format($resumen['venta_mes'] ?? 0, 2); ?></div>
                            </div>
                            <div class="porcentaje subida">
                                <?= $crecimientoVentas ?>% ↑
                            </div>
                        </div>

                        <div class="metrica-item">
                            <div>
                                <h4>Ventas del Último Año</h4>
                                <div class="valor">$<?= number_format($resumen['venta_ultimo_anio'] ?? 0, 2); ?></div>
                            </div>
                            <div class="porcentaje subida">
                                <?= $crecimientoAnual ?>% ↑
                            </div>
                        </div>

                        <div class="metrica-item">
                            <div>
                                <h4>Ingresos del Mes</h4>
                                <div class="valor">$<?= number_format($resumen['ganancia_mes'] ?? 0, 2); ?></div>
                            </div>
                            <div class="porcentaje subida">
                                <?= $crecimientoIngresos ?>% ↑
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 🔥 ALERTAS IMPORTANTES (AHORA A LA DERECHA - Recuadro Rojo) -->
                <div class="panel panel-alertas">
                    <div class="panel-header">
                        <h3>⚠️ Alertas Importantes</h3>
                        <span class="badge-alertas">
                            <?= $stockBajoCount ?> alertas
                        </span>
                    </div>
                    <div class="alertas-lista">
                        <?php
                        $totalAlertas = 0;

                        // 🟠 Stock Bajo
                        if ($stockBajoCount > 0) {
                            $totalAlertas += $stockBajoCount;
                            echo '<div class="alerta-tipo">
                        <div class="alerta-tipo-header stock">
                            <i class="bi bi-cart-x-fill"></i>
                            <span>Stock Bajo (' . $stockBajoCount . ')</span>
                        </div>';
                            while ($prod = mysqli_fetch_assoc($stockBajo)) {
                                echo '
                    <div class="alerta-item">
                        <div class="alerta-icono stock">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div class="alerta-info">
                            <h4>' . htmlspecialchars($prod['nombre_producto']) . '</h4>
                            <p>📦 ' . $prod['cantidad'] . '/' . $prod['stock_minimo'] . ' unidades mínimas</p>
                        </div>
                    </div>';
                            }
                            echo '</div>';
                        }

                        if ($totalAlertas === 0) {
                            echo '<div class="sin-alertas">
                        <i class="bi bi-check-circle-fill"></i>
                        <p>✅ No hay alertas pendientes</p>
                      </div>';
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- ALERTAS DE CONVERSIÓN -->
            <div class="alertas-conversion">
                <?php
                // 🔥 PRODUCTOS POR CONVERTIR (STOCK BAJO EN HIJOS)
                $alertas = mysqli_query($conn, "SELECT 
                            p1.nombre_producto as padre,
                            p1.cantidad as stock_padre,
                            p2.nombre_producto as hijo,
                            p2.cantidad as stock_hijo,
                            p2.stock_minimo,
                            cu.factor_conversion
                            FROM conversion_unidades cu
                            INNER JOIN productos p1 ON cu.producto_padre_codigo = p1.codigo_barra
                            INNER JOIN productos p2 ON cu.producto_hijo_codigo = p2.codigo_barra
                            WHERE cu.activo = 1 
                            AND p2.cantidad <= p2.stock_minimo
                            AND p1.cantidad > 0
                            LIMIT 5");

                if (mysqli_num_rows($alertas) > 0):
                    ?>
                    <div class="tarjeta-alerta">
                        <h3>⚠️ Productos Sugeridos para Conversión</h3>
                        <ul>
                            <?php while ($alerta = mysqli_fetch_assoc($alertas)): ?>
                                <li>
                                    <strong>
                                        <?= htmlspecialchars($alerta['padre']) ?>
                                    </strong>
                                    (
                                    <?= $alerta['stock_padre'] ?> disponibles)
                                    → Convertir a
                                    <strong>
                                        <?= htmlspecialchars($alerta['hijo']) ?>
                                    </strong>
                                    (Stock actual:
                                    <?= $alerta['stock_hijo'] ?> / Mínimo:
                                    <?= $alerta['stock_minimo'] ?>)
                                </li>
                            <?php endwhile; ?>
                        </ul>
                        <a href="../productos/realizar_desglose.php" class="btn-accion">
                            <i class="bi bi-scissors"></i> Ir a Desglose
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- ===============================
    SCRIPTS
    ================================-->
    <script>
        // 🔥 Toggle Submenú
        function toggleSubmenu(id) {
            const submenu = document.getElementById(id);
            const parent = submenu.previousElementSibling;

            // Cerrar todos los submenús
            document.querySelectorAll('.sidebar-submenu').forEach(sm => {
                if (sm.id !== id) {
                    sm.classList.remove('abierto');
                    sm.previousElementSibling.classList.remove('activo');
                }
            });

            // Alternar el seleccionado
            submenu.classList.toggle('abierto');
            parent.classList.toggle('activo');
        }

        // 🔥 GRÁFICA COMBINADA CON EFECTOS MEJORADOS
        const ctx = document.getElementById('graficaEstadoResultado').getContext('2d');

        // 🔥 CREAR GRADIENTES PARA BARRAS
        const gradientVentas = ctx.createLinearGradient(0, 0, 0, 400);
        gradientVentas.addColorStop(0, 'rgba(40, 167, 69, 0.9)');
        gradientVentas.addColorStop(1, 'rgba(40, 167, 69, 0.3)');

        const gradientCompras = ctx.createLinearGradient(0, 0, 0, 400);
        gradientCompras.addColorStop(0, 'rgba(220, 53, 69, 0.9)');
        gradientCompras.addColorStop(1, 'rgba(220, 53, 69, 0.3)');

        const gradientGanancia = ctx.createLinearGradient(0, 0, 400, 0);
        gradientGanancia.addColorStop(0, 'rgba(255, 193, 7, 0.9)');
        gradientGanancia.addColorStop(1, 'rgba(255, 153, 0, 0.9)');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($labelsGrafica); ?>,
                datasets: [
                    {
                        type: 'bar',
                        label: '💰 Ventas',
                        data: <?= json_encode($dataVentas); ?>,
                        backgroundColor: gradientVentas,
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 2,
                        borderRadius: 10,
                        borderSkipped: false,
                        order: 2,
                        hoverBackgroundColor: 'rgba(40, 167, 69, 1)',
                        hoverBorderColor: 'rgba(33, 150, 60, 1)',
                        hoverBorderWidth: 3,
                        hoverOffset: 10,
                        barThickness: 40,
                        maxBarThickness: 60,
                        shadowOffsetX: 0,
                        shadowOffsetY: 5,
                        shadowBlur: 10,
                        shadowColor: 'rgba(40, 167, 69, 0.3)'
                    },
                    {
                        type: 'bar',
                        label: '📦 Compras',
                        data: <?= json_encode($dataCompras); ?>,
                        backgroundColor: gradientCompras,
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 2,
                        borderRadius: 10,
                        borderSkipped: false,
                        order: 3,
                        hoverBackgroundColor: 'rgba(220, 53, 69, 1)',
                        hoverBorderColor: 'rgba(180, 43, 56, 1)',
                        hoverBorderWidth: 3,
                        hoverOffset: 10,
                        barThickness: 40,
                        maxBarThickness: 60
                    },
                    {
                        type: 'line',
                        label: '📈 Ganancia',
                        data: <?= json_encode($dataGanancias ?? []); ?>,
                        borderColor: gradientGanancia,
                        backgroundColor: 'rgba(255, 193, 7, 0.1)',
                        borderWidth: 4,
                        fill: true,
                        tension: 0.4,
                        order: 1,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: 'rgba(255, 193, 7, 1)',
                        pointBorderWidth: 3,
                        pointRadius: 7,
                        pointHoverRadius: 10,
                        pointHoverBackgroundColor: 'rgba(255, 193, 7, 1)',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 4,
                        lineTension: 0.4,
                        shadowOffsetX: 0,
                        shadowOffsetY: 3,
                        shadowBlur: 8,
                        shadowColor: 'rgba(255, 193, 7, 0.4)'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1500,
                    easing: 'easeOutQuart',
                    onProgress: function (animation) {
                        // 🔥 EFECTO DE PROGRESO
                        const progress = animation.currentStep / animation.numSteps;
                        ctx.canvas.style.opacity = progress;
                    },
                    onComplete: function () {
                        // 🔥 EFECTO AL COMPLETAR
                        ctx.canvas.style.boxShadow = '0 10px 40px rgba(0,0,0,0.15)';
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'center',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'rectRounded',
                            padding: 25,
                            font: {
                                family: "'Kalam', cursive",
                                size: 15,
                                weight: '700'
                            },
                            color: '#495057',
                            borderRadius: 10,
                            generateLabels: function (chart) {
                                const original = Chart.defaults.plugins.legend.labels.generateLabels;
                                const labels = original.call(this, chart);
                                labels.forEach(label => {
                                    label.borderRadius = 10;
                                    label.padding = 15;
                                });
                                return labels;
                            }
                        }
                    },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(33, 37, 41, 0.95)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255,255,255,0.2)',
                        borderWidth: 2,
                        padding: 18,
                        displayColors: true,
                        boxPadding: 10,
                        boxWidth: 20,
                        boxHeight: 20,
                        cornerRadius: 10,
                        caretSize: 10,
                        caretPadding: 10,
                        titleFont: {
                            family: "'Kalam', cursive",
                            size: 16,
                            weight: '700'
                        },
                        bodyFont: {
                            family: "'Kalam', cursive",
                            size: 14,
                            weight: '500'
                        },
                        footerFont: {
                            family: "'Kalam', cursive",
                            size: 13
                        },
                        callbacks: {
                            label: function (context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('es-MX', {
                                        style: 'currency',
                                        currency: 'MXN',
                                        minimumFractionDigits: 2
                                    }).format(context.parsed.y);
                                }
                                return label;
                            },
                            footer: function (tooltipItems) {
                                // 🔥 MOSTRAR TOTAL EN EL FOOTER
                                let total = 0;
                                tooltipItems.forEach(function (tooltipItem) {
                                    total += tooltipItem.parsed.y;
                                });
                                return 'Total: ' + new Intl.NumberFormat('es-MX', {
                                    style: 'currency',
                                    currency: 'MXN'
                                }).format(total);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: function (context) {
                                // 🔥 GRID CON GRADIENTE
                                if (context.tick.value === 0) {
                                    return 'rgba(0,0,0,0.1)';
                                }
                                return 'rgba(0,0,0,0.05)';
                            },
                            lineWidth: 1,
                            drawBorder: false,
                            tickLength: 0
                        },
                        ticks: {
                            padding: 15,
                            font: {
                                family: "'Kalam', cursive",
                                size: 13,
                                weight: '600'
                            },
                            color: '#6c757d',
                            callback: function (value) {
                                return '$' + value.toLocaleString('es-MX');
                            },
                            z: 10
                        },
                        border: {
                            display: false
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            padding: 15,
                            font: {
                                family: "'Kalam', cursive",
                                size: 14,
                                weight: '700'
                            },
                            color: '#495057',
                            z: 10
                        },
                        border: {
                            display: false
                        }
                    }
                }
            }
        });

        // 🔥 EFECTO HOVER EN EL CANVAS
        ctx.canvas.addEventListener('mousemove', function (e) {
            const rect = ctx.canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            ctx.canvas.style.cursor = 'crosshair';
        });

        ctx.canvas.addEventListener('mouseleave', function (e) {
            ctx.canvas.style.cursor = 'default';
        });

        // 🔥 EFECTO DE ENTRADA ANIMADA
        ctx.canvas.style.opacity = '0';
        ctx.canvas.style.transition = 'opacity 0.5s ease, box-shadow 0.5s ease';
        setTimeout(() => {
            ctx.canvas.style.opacity = '1';
        }, 100);
    </script>

    <script>
        // 🔥 ANIMACIÓN DE CONTEO NUMÉRICO PARA CARDS
        function animarContadores() {
            const contadores = document.querySelectorAll('.contador');

            contadores.forEach(contador => {
                const target = parseFloat(contador.dataset.target) || 0;
                const esMoneda = contador.classList.contains('moneda');
                const decimales = parseInt(contador.dataset.decimales) || 0;
                const duracion = 2000; // 2 segundos
                const intervalo = 50; // Actualizar cada 50ms
                const pasos = duracion / intervalo;
                const incremento = target / pasos;

                let actual = 0;
                let pasoActual = 0;

                contador.classList.add('animando');

                const timer = setInterval(() => {
                    pasoActual++;
                    actual = incremento * pasoActual;

                    // 🔥 FORMATEAR SEGÚN TIPO
                    if (esMoneda) {
                        contador.textContent = '$' + actual.toLocaleString('es-MX', {
                            minimumFractionDigits: decimales,
                            maximumFractionDigits: decimales
                        });
                    } else {
                        contador.textContent = Math.floor(actual).toLocaleString('es-MX');
                    }

                    // 🔥 VERIFICAR SI COMPLETÓ
                    if (pasoActual >= pasos || actual >= target) {
                        clearInterval(timer);

                        // 🔥 VALOR FINAL EXACTO
                        if (esMoneda) {
                            contador.textContent = '$' + target.toLocaleString('es-MX', {
                                minimumFractionDigits: decimales,
                                maximumFractionDigits: decimales
                            });
                        } else {
                            contador.textContent = Math.floor(target).toLocaleString('es-MX');
                        }

                        contador.classList.remove('animando');
                        contador.classList.add('completo');

                        // 🔥 QUITAR CLASE DESPUÉS DE LA ANIMACIÓN
                        setTimeout(() => {
                            contador.classList.remove('completo');
                        }, 500);
                    }
                }, intervalo);
            });
        }

        // 🔥 EJECUTAR ANIMACIÓN AL CARGAR LA PÁGINA
        document.addEventListener('DOMContentLoaded', function () {
            // 🔥 PEQUEÑA PAUSA PARA QUE EL DOM ESTÉ LISTO
            setTimeout(() => {
                animarContadores();
            }, 300);
        });
    </script>
</body>

</html>