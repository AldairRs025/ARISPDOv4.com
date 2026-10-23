<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../../index.html");
    exit;
}
include("../conexion.php");

// 🔥 FILTROS DE BÚSQUEDA
$whereClauses = [];
$params = [];
$types = "";

// Filtro por fecha desde
if (!empty($_GET['fecha_desde'])) {
    $whereClauses[] = "c.fecha_corte >= ?";
    $params[] = $_GET['fecha_desde'];
    $types .= "s";
}

// Filtro por fecha hasta
if (!empty($_GET['fecha_hasta'])) {
    $whereClauses[] = "c.fecha_corte <= ?";
    $params[] = $_GET['fecha_hasta'];
    $types .= "s";
}

// Filtro por usuario
if (!empty($_GET['usuario'])) {
    $whereClauses[] = "u.username = ?";
    $params[] = $_GET['usuario'];
    $types .= "s";
}

// Filtro por estado
if (!empty($_GET['estado']) && in_array($_GET['estado'], ['cuadrado', 'faltante', 'sobrante'])) {
    $whereClauses[] = "c.estado = ?";
    $params[] = $_GET['estado'];
    $types .= "s";
}

// Construir WHERE para prepared statement
$where = '';
if (!empty($whereClauses)) {
    $where = 'WHERE ' . implode(' AND ', $whereClauses);
}

// 🔥 CONSULTA PRINCIPAL (con prepared statement)
$sql = "SELECT c.*, u.username as usuario
        FROM cortes_caja c
        LEFT JOIN usuarios u ON c.usuario_id = u.id
        $where
        ORDER BY c.fecha_corte DESC, c.hora_corte DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$cortes = mysqli_stmt_get_result($stmt);

// 🔥 OBTENER ESTADÍSTICAS (WHERE diferente - sin placeholders)
$statsWhereClauses = [];
if (!empty($_GET['fecha_desde'])) {
    $statsWhereClauses[] = "c.fecha_corte >= '" . mysqli_real_escape_string($conn, $_GET['fecha_desde']) . "'";
}
if (!empty($_GET['fecha_hasta'])) {
    $statsWhereClauses[] = "c.fecha_corte <= '" . mysqli_real_escape_string($conn, $_GET['fecha_hasta']) . "'";
}
if (!empty($_GET['usuario'])) {
    $statsWhereClauses[] = "u.username = '" . mysqli_real_escape_string($conn, $_GET['usuario']) . "'";
}
if (!empty($_GET['estado']) && in_array($_GET['estado'], ['cuadrado', 'faltante', 'sobrante'])) {
    $statsWhereClauses[] = "c.estado = '" . mysqli_real_escape_string($conn, $_GET['estado']) . "'";
}

$statsWhere = '';
if (!empty($statsWhereClauses)) {
    $statsWhere = 'WHERE ' . implode(' AND ', $statsWhereClauses);
}

$statsSQL = "SELECT
    COUNT(*) as total_cortes,
    COALESCE(SUM(c.total_ventas_bd), 0) as total_ventas,
    COALESCE(SUM(c.total_efectivo_contado), 0) as total_efectivo,
    COALESCE(SUM(c.diferencia), 0) as diferencia_total,
    SUM(CASE WHEN c.estado = 'cuadrado' THEN 1 ELSE 0 END) as cuadrados,
    SUM(CASE WHEN c.estado = 'faltante' THEN 1 ELSE 0 END) as faltantes,
    SUM(CASE WHEN c.estado = 'sobrante' THEN 1 ELSE 0 END) as sobrantes
    FROM cortes_caja c
    LEFT JOIN usuarios u ON c.usuario_id = u.id
    $statsWhere";

$stats = mysqli_fetch_assoc(mysqli_query($conn, $statsSQL));

// 🔥 OBTENER USUARIOS PARA FILTRO
$usuarios = mysqli_query($conn, "SELECT DISTINCT username FROM usuarios WHERE rol IN ('admin', 'empleado') ORDER BY username");
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Historial de Cortes de Caja - Los Nietos®</title>
    <link rel="stylesheet" href="../../css/Ventas-STYLE/Historial_Corte.css">
    <link rel="icon" href="../../img/LOGIN/icon_24px.png" type="img/INICIO/icon_24px.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Kalam:wght@300;400;700&display=swap"
        rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f5f5;
            font-family: 'Kalam', cursive;
        }

        /* BARRA SUPERIOR */
        .barra-superior {
            width: 100%;
            height: 60px;
            background: #000;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            position: relative;
            z-index: 1000;
        }

        .logo {
            font-family: 'Pacifico', cursive;
            font-size: 32px;
            color: white;
        }

        .iconos {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .icon-btn {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 52px;
            height: 52px;
            background: white;
            border-radius: 50%;
            box-shadow: 0 0 6px rgba(0, 0, 0, 0.2);
            transition: 0.25s ease;
            text-decoration: none;
            position: relative;
        }

        .icon-btn i {
            font-size: 26px;
            color: #000;
            transition: all 0.25s ease;
        }

        .icon-btn:hover {
            transform: scale(1.12);
            box-shadow: 0 0 12px rgba(255, 255, 255, 0.7);
        }

        .icon-btn:hover i {
            color: #474747;
            text-shadow: 0 0 6px rgba(0, 0, 0, 0.8);
        }

        .icon-btn .tooltip {
            position: absolute;
            top: 120%;
            left: 50%;
            transform: translateX(-50%);
            background: #8b8b8b;
            color: #000;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            z-index: 9999;
        }

        .icon-btn .tooltip::after {
            content: "";
            position: absolute;
            top: -13px;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-bottom-color: #000;
        }

        .icon-btn:hover .tooltip {
            opacity: 1;
        }

        .barra-inferior {
            width: 100%;
            height: 50px;
            background: #333;
            position: fixed;
            bottom: 0;
            left: 0;
        }

        /* CONTENEDOR PRINCIPAL */
        .container {
            margin: 100px auto;
            padding: 30px;
            max-width: 1400px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        /* TÍTULO */
        h1 {
            font-family: 'Pacifico', cursive;
            font-size: 36px;
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }

        /* TARJETAS DE ESTADÍSTICAS */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .stat-card h4 {
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .stat-card p {
            font-size: 28px;
            font-weight: bold;
            margin: 0;
        }

        .stat-card.ventas {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .stat-card.efectivo {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-card.diferencia {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-card.cuadrados {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }

        .stat-card.faltantes {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        .stat-card.sobrantes {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: #000;
        }

        /* FILTROS */
        .filtros-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border: 2px solid #e2e8f0;
        }

        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filtro-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .filtro-group input,
        .filtro-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Kalam', cursive;
        }

        .filtro-group input:focus,
        .filtro-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-filtrar {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
        }

        .btn-limpiar {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
        }

        /* TABLA */
        .tabla-container {
            overflow-x: auto;
        }

        .tabla-cortes {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }

        .tabla-cortes thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .tabla-cortes th,
        .tabla-cortes td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .tabla-cortes th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
        }

        .tabla-cortes tbody tr:hover {
            background: #f8f9fa;
        }

        .estado-cuadrado {
            background: #d4edda;
            color: #155724;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .estado-faltante {
            background: #f8d7da;
            color: #721c24;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .estado-sobrante {
            background: #fff3cd;
            color: #856404;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .btn-ver {
            background: #0d6efd;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
        }

        .btn-ver:hover {
            background: #0b5ed7;
        }

        /* SIN RESULTADOS */
        .sin-resultados {
            text-align: center;
            padding: 60px;
            color: #666;
        }

        .sin-resultados i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #ddd;
        }

        /* MODAL DETALLE */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 24px;
            font-family: 'Pacifico', cursive;
        }

        .btn-cerrar {
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-cerrar:hover {
            transform: scale(1.2);
        }

        .modal-body {
            padding: 30px;
            overflow-y: auto;
            max-height: calc(90vh - 80px);
        }

        .detalle-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .detalle-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .detalle-item strong {
            display: block;
            color: #666;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .detalle-item span {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }

        .tabla-billetes {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .tabla-billetes th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 13px;
        }

        .tabla-billetes td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .tabla-billetes tbody tr:hover {
            background: #f8f9fa;
        }

        .diferencia-positiva {
            color: #28a745;
            font-weight: bold;
        }

        .diferencia-negativa {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <!-- BARRA SUPERIOR -->
    <div class="barra-superior">
        <div class="logo">Los Nietos&#174;</div>
        <div class="iconos">
            <a href="../login/dashboard_admin.php" class="icon-btn">
                <i class="bi bi-house-fill"></i><span class="tooltip">INICIO</span>
            </a>
            <a href="../login/CRUD-Usuarios.php" class="icon-btn">
                <i class="bi bi-people-fill"></i><span class="tooltip">USUARIOS</span>
            </a>
            <a href="../proveedores/CRUD-Proveedores.php" class="icon-btn">
                <i class="bi bi-truck"></i><span class="tooltip">PROVEEDORES</span>
            </a>
            <a href="../productos/CRUD-Product.php" class="icon-btn">
                <i class="bi bi-box-seam"></i><span class="tooltip">PRODUCTOS</span>
            </a>
            <a href="../ventas/CRUD-Ventas.php" class="icon-btn">
                <i class="bi bi-cash-stack"></i><span class="tooltip">VENTAS</span>
            </a>
            <a href="corte_caja.php" class="icon-btn">
                <i class="bi bi-cash-coin"></i><span class="tooltip">CORTE CAJA</span>
            </a>
            <a href="logout.php" class="icon-btn">
                <i class="bi bi-box-arrow-right"></i><span class="tooltip">SALIR</span>
            </a>
        </div>
    </div>

    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
            <h1 style="margin:0;">📊 Historial de Cortes de Caja</h1>
            <a href="corte_caja.php"
                style="background:linear-gradient(135deg, #000000 0%, #828282 100%); color:white; padding:15px 30px; border-radius:10px; text-decoration:none; font-weight:bold; font-size:16px; box-shadow:0 4px 15px rgba(0, 0, 0, 0.4); transition:all 0.3s;">
                ➕ Nuevo Corte de Caja
            </a>
        </div>

        <!-- ESTADÍSTICAS -->
        <div class="stats-container">
            <div class="stat-card">
                <h4>📋 Total Cortes</h4>
                <p>
                    <?= number_format($stats['total_cortes']) ?>
                </p>
            </div>
            <div class="stat-card ventas">
                <h4>💰 Ventas en BD</h4>
                <p>$
                    <?= number_format($stats['total_ventas'], 2) ?>
                </p>
            </div>
            <div class="stat-card efectivo">
                <h4>💵 Efectivo Contado</h4>
                <p>$
                    <?= number_format($stats['total_efectivo'], 2) ?>
                </p>
            </div>
            <div class="stat-card diferencia">
                <h4>📊 Diferencia Total</h4>
                <p>$
                    <?= number_format($stats['diferencia_total'], 2) ?>
                </p>
            </div>
            <div class="stat-card cuadrados">
                <h4>✅ Cuadrados</h4>
                <p>
                    <?= number_format($stats['cuadrados']) ?>
                </p>
            </div>
            <div class="stat-card faltantes">
                <h4>❌ Faltantes</h4>
                <p>
                    <?= number_format($stats['faltantes']) ?>
                </p>
            </div>
            <div class="stat-card sobrantes">
                <h4>💰 Sobrantes</h4>
                <p>
                    <?= number_format($stats['sobrantes']) ?>
                </p>
            </div>
        </div>

        <!-- FILTROS -->
        <div class="filtros-container">
            <form method="GET" action="historial_corte.php" id="formFiltros">
                <div class="filtros-grid">
                    <div class="filtro-group">
                        <label>📅 Fecha Desde:</label>
                        <input type="date" name="fecha_desde" value="<?= htmlspecialchars($_GET['fecha_desde'] ?? '') ?>" 
                            placeholder="dd/mm/aaaa">
                    </div>
                    <div class="filtro-group">
                        <label>📅 Fecha Hasta:</label>
                        <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($_GET['fecha_hasta'] ?? '') ?>" 
                            placeholder="dd/mm/aaaa">
                    </div>
                    <div class="filtro-group">
                        <label>👤 Usuario:</label>
                        <select name="usuario">
                            <option value="">Todos los usuarios</option>
                            <?php 
                            // Reiniciar el puntero de $usuarios si ya se usó
                            mysqli_data_seek($usuarios, 0);
                            while ($u = mysqli_fetch_assoc($usuarios)): 
                            ?>
                                <option value="<?= htmlspecialchars($u['username']) ?>" 
                                        <?= (($_GET['usuario'] ?? '') == $u['username']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['username']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filtro-group">
                        <label>✅ Estado:</label>
                        <select name="estado">
                            <option value="">Todos los estados</option>
                            <option value="cuadrado" <?= (($_GET['estado'] ?? '') == 'cuadrado') ? 'selected' : '' ?>>
                                Cuadrado
                            </option>
                            <option value="faltante" <?= (($_GET['estado'] ?? '') == 'faltante') ? 'selected' : '' ?>>
                                Faltante
                            </option>
                            <option value="sobrante" <?= (($_GET['estado'] ?? '') == 'sobrante') ? 'selected' : '' ?>>
                                Sobrante
                            </option>
                        </select>
                    </div>
                    <div class="filtro-group" style="display:flex; gap:10px; align-items:flex-end;">
                        <button type="submit" class="btn-filtrar">
                            🔍 Filtrar
                        </button>
                        <button type="button" class="btn-limpiar" onclick="limpiarFiltros()">
                            🧹 Limpiar
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- TABLA DE CORTES -->
        <div class="tabla-container">
            <table class="tabla-cortes">
                <thead>
                    <tr>
                        <th>ID Corte</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Usuario</th>
                        <th>Ventas (BD)</th>
                        <th>Efectivo</th>
                        <th>Diferencia</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($cortes) > 0): ?>
                        <?php while ($corte = mysqli_fetch_assoc($cortes)): ?>
                            <tr>
                                <td><strong>#
                                        <?= $corte['id'] ?>
                                    </strong></td>
                                <td>
                                    <?= date('d/m/Y', strtotime($corte['fecha_corte'])) ?>
                                </td>
                                <td>
                                    <?= date('h:i A', strtotime($corte['hora_corte'])) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($corte['usuario'] ?? 'N/A') ?>
                                </td>
                                <td>$
                                    <?= number_format($corte['total_ventas_bd'], 2) ?>
                                </td>
                                <td>$
                                    <?= number_format($corte['total_efectivo_contado'], 2) ?>
                                </td>
                                <td class="<?= $corte['diferencia'] >= 0 ? 'diferencia-positiva' : 'diferencia-negativa' ?>">
                                    $
                                    <?= number_format($corte['diferencia'], 2) ?>
                                </td>
                                <td>
                                    <span class="estado-<?= $corte['estado'] ?>">
                                        <?= ucfirst($corte['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="#" class="btn-ver" onclick="verDetalle(<?= $corte['id'] ?>); return false;">
                                        👁️ Ver Detalle
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="sin-resultados">
                                <i class="bi bi-inbox"></i>
                                <p>No se encontraron cortes de caja con los filtros seleccionados</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- BARRA INFERIOR -->
    <div class="barra-inferior"></div>

    <!-- 🔥 MODAL CON PESTAÑAS -->
    <div id="modalDetalle" class="modal">
        <div class="modal-content" style="max-width:1000px;">
            <div class="modal-header">
                <h2>📊 Cortes del Día: <span id="fechaCorte"></span></h2>
                <button class="btn-cerrar" onclick="cerrarModal()">✕</button>
            </div>
            <div class="modal-body" id="modalBody">
                <div style="text-align:center; padding:40px;">
                    <i class="bi bi-hourglass-split" style="font-size:48px; color:#667eea;"></i>
                    <p>Cargando...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL ELIMINAR -->
    <div id="modalEliminar" class="modal">
        <div class="modal-content" style="max-width:500px;">
            <div class="modal-header" style="background:linear-gradient(135deg, #dc3545, #c82333);">
                <h2>🗑️ Eliminar Corte</h2>
                <button class="btn-cerrar" onclick="cerrarModalEliminar()">✕</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom:20px;">⚠️ ¿Estás seguro de eliminar este corte? Esta acción no se puede deshacer.</p>
                <input type="hidden" id="corteIdEliminar">
                <div style="margin-bottom:20px;">
                    <label style="display:block; margin-bottom:8px; font-weight:bold;">🔐 Contraseña de Admin:</label>
                    <input type="password" id="passwordEliminar" placeholder="Ingresa tu contraseña" 
                        style="width:100%; padding:12px; border:2px solid #ddd; border-radius:8px; font-size:16px;">
                </div>
                <button onclick="confirmarEliminar()" 
                        style="width:100%; padding:15px; background:#dc3545; color:white; border:none; border-radius:8px; font-size:16px; font-weight:bold; cursor:pointer;">
                    🗑️ Eliminar Corte
                </button>
            </div>
        </div>
    </div>

    <script>
        let corteIdEliminar = null;

        function verDetalle(id) {
            console.log('🔍 Abriendo corte:', id);
            document.getElementById('modalDetalle').style.display = 'flex';
            document.getElementById('modalBody').innerHTML = `
                <div style="text-align:center; padding:40px;">
                    <i class="bi bi-hourglass-split" style="font-size:48px; color:#667eea;"></i>
                    <p style="margin-top:20px; color:#666;">Cargando...</p>
                </div>
            `;
            
            fetch(`obtener_detalle_corte.php?id=${id}`)
            .then(r => {
                console.log('📡 Respuesta:', r.status);
                if (!r.ok) throw new Error('Error HTTP: ' + r.status);
                return r.json();
            })
            .then(data => {
                console.log('✅ Datos:', data);
                if (!data.success) {
                    document.getElementById('modalBody').innerHTML = `
                        <div style="text-align:center; color:#dc3545; padding:40px;">
                            <i class="bi bi-exclamation-triangle" style="font-size:48px;"></i>
                            <p style="margin-top:20px;">❌ ${data.message}</p>
                        </div>
                    `;
                    return;
                }
                
                const c = data.corte_actual;
                const resumen = data.resumen_dia;
                
                // 🔥 FILTRAR COMPRAS POR HORA
                const horaCorte = c.hora_completa;
                // 🔥 FILTRAR COMPRAS POR PERIODO DEL CORTE
                const fechaInicio = c.hora_inicio.split(' ')[0] || c.fecha;
                const horaInicio = c.hora_inicio.split(' ')[1] || '00:00 AM';
                // 🔥 FILTRAR COMPRAS POR PERIODO DEL CORTE (ya vienen filtradas del PHP)
                const comprasFiltradas = data.compras_dia;

                // 🔥 PESTAÑAS
                let tabsHtml = `<div style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">`;
                data.cortes_del_dia.forEach((corte, index) => {
                    const activo = corte.id == id ? 'style="background:#667eea; color:white;"' : '';
                    tabsHtml += `<button onclick="cambiarPestaña(${corte.id})" ${activo}
                        style="padding:10px 20px; border:none; border-radius:8px; background:#f8f9fa; cursor:pointer; font-weight:bold;">
                        Corte #${corte.id} - ${new Date(corte.hora_corte).toLocaleTimeString('es-ES', {hour:'2-digit', minute:'2-digit'})}
                    </button>`;
                });
                tabsHtml += `</div>`;
                
                // 🔥 CONTENIDO DE LA PESTAÑA ACTUAL
                // 🔥 CONTENIDO DE LA PESTAÑA ACTUAL
                let contenidoHtml = `
                ${tabsHtml}
                <div class="detalle-grid">
                    <div class="detalle-item"><strong>📅 Fecha</strong><span>${c.fecha}</span></div>
                    <div class="detalle-item"><strong>🕐 Hora Corte</strong><span>${c.hora}</span></div>
                    <div class="detalle-item"><strong>🕐 Hora Inicio</strong><span>${c.hora_inicio}</span></div>
                    <div class="detalle-item"><strong>👤 Usuario</strong><span>${c.usuario}</span></div>
                    <div class="detalle-item"><strong>✅ Estado</strong><span style="color:${c.estado === 'cuadrado' ? '#28a745' : c.estado === 'faltante' ? '#dc3545' : '#ffc107'}">${c.estado.toUpperCase()}</span></div>
                    ${c.corte_especial == 1 ? `<div class="detalle-item" style="border-left-color:#ffc107;"><strong>⚠️ Corte Especial</strong><span style="color:#ffc107;">SÍ (cruza 2 días)</span></div>` : ''}
                    <div class="detalle-item"><strong>💰 Ventas BD</strong><span>$${c.total_ventas_bd}</span></div>
                    <div class="detalle-item"><strong>🛒 Compras</strong><span style="color:#dc3545;">-$${c.total_compras_dia}</span></div>
                    <div class="detalle-item"><strong>💵 Efectivo Contado</strong><span>$${c.total_efectivo}</span></div>
                    <div class="detalle-item"><strong>📊 Efectivo Esperado</strong><span>$${c.efectivo_esperado}</span></div>
                    <div class="detalle-item"><strong>📈 Diferencia</strong><span style="color:${parseFloat(c.diferencia) >= 0 ? '#28a745' : '#dc3545'}">$${c.diferencia}</span></div>
                    ${c.observaciones ? `<div class="detalle-item" style="grid-column:1/-1;"><strong>📝 Observaciones</strong><span>${c.observaciones}</span></div>` : ''}
                </div>
                
                <h3 style="margin:20px 0 15px 0; border-bottom:2px solid #667eea; padding-bottom:10px;">💰 Desglose de Efectivo</h3>
                <table class="tabla-billetes">
                    <thead><tr><th>Denominación</th><th>Cantidad</th><th>Subtotal</th></tr></thead>
                    <tbody>`;
                
                const billetes = [1000, 500, 200, 100, 50, 20];
                const monedas = [20, 10, 5, 2, 1, 0.5];
                
                // 🔥 BILLETES
                billetes.forEach(denom => {
                    const clave = `${denom}_billete`;
                    const cantidad = c.detalle_billetes[clave] || 0;
                    if (cantidad > 0) {
                        contenidoHtml += `<tr><td>🟫 $${denom}</td><td>${cantidad}</td><td>$${(cantidad*denom).toFixed(2)}</td></tr>`;
                    }
                });
                
                // 🔥 MONEDAS
                monedas.forEach(denom => {
                    const clave = `${denom}_moneda`;
                    const cantidad = c.detalle_billetes[clave] || 0;
                    if (cantidad > 0) {
                        contenidoHtml += `<tr><td>🪙 $${denom}</td><td>${cantidad}</td><td>$${(cantidad*denom).toFixed(2)}</td></tr>`;
                    }
                });
                
                contenidoHtml += `</tbody></table>
                
                <div style="margin-top:30px; padding:20px; background:#f8f9fa; border-radius:10px;">
                    <h3 style="margin:20px 0 15px 0; border-bottom:2px solid #667eea; padding-bottom:10px;">
                        📈 Resumen del Día (${resumen.total_cortes} cortes normales)
                        ${resumen.total_cortes_incluyendo_especiales > resumen.total_cortes ? 
                            `<span style="font-size:12px; color:#ffc107;">(${resumen.total_cortes_incluyendo_especiales - resumen.total_cortes} especiales excluidos)</span>` : ''}
                    </h3>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:15px;">
                        <div style="background:white; padding:15px; border-radius:8px; border:2px solid #0d6efd;">
                            <strong>💰 Caja Inicial</strong><br>
                            <span style="font-size:24px; color:#0d6efd;">$${c.caja_inicial}</span>
                        </div>
                        <div style="background:white; padding:15px; border-radius:8px;">
                            <strong>💰 Ventas Totales</strong><br>
                            <span style="font-size:24px; color:#28a745;">$${resumen.ventas_totales}</span>
                        </div>
                        <div style="background:white; padding:15px; border-radius:8px;">
                            <strong>🛒 Compras Totales</strong><br>
                            <span style="font-size:24px; color:#dc3545;">-$${resumen.compras_totales}</span>
                        </div>
                        <div style="background:white; padding:15px; border-radius:8px;">
                            <strong>💵 Efectivo Total</strong><br>
                            <span style="font-size:24px; color:#0d6efd;">$${resumen.efectivo_total}</span>
                        </div>
                        <div style="background:white; padding:15px; border-radius:8px;">
                            <strong>📊 Diferencia Total</strong><br>
                            <span style="font-size:24px; color:${parseFloat(resumen.diferencia_total) >= 0 ? '#28a745' : '#dc3545'}">$${resumen.diferencia_total}</span>
                        </div>
                    </div>
                </div>`;
                
                // 🔥 COMPRAS FILTRADAS
                if (comprasFiltradas.length > 0) {
                    contenidoHtml += `<h3 style="margin:20px 0 15px 0; border-bottom:2px solid #667eea; padding-bottom:10px;">🛒 Compras (antes de este corte)</h3>
                    <table class="tabla-billetes">
                        <thead><tr><th>Proveedor</th><th>Total</th><th>Hora</th></tr></thead>
                        <tbody>`;
                    comprasFiltradas.forEach(comp => {
                        const horaCompra = comp.fecha_compra.split(' ')[1] || '00:00';
                        contenidoHtml += `<tr><td>${comp.proveedor}</td><td style="color:#dc3545;">-$${parseFloat(comp.total).toFixed(2)}</td><td>${horaCompra}</td></tr>`;
                    });
                    contenidoHtml += `</tbody></table>`;
                } else {
                    contenidoHtml += `<p style="text-align:center; color:#666; padding:20px;">No hay compras registradas antes de este corte</p>`;
                }
                
                // 🔥 BOTONES DE ACCIÓN
                contenidoHtml += `<div style="margin-top:30px; display:flex; gap:10px;">
                    <a href="corte_caja.php?edit_id=${c.id}" style="flex:1; padding:15px; background:#0d6efd; color:white; text-align:center; border-radius:8px; text-decoration:none; font-weight:bold;">✏️ Editar este Corte</a>
                    <button onclick="abrirModalEliminar(${c.id})" style="flex:1; padding:15px; background:#dc3545; color:white; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">🗑️ Eliminar</button>
                </div>`;
                
                document.getElementById('modalBody').innerHTML = contenidoHtml;
                document.getElementById('fechaCorte').textContent = c.fecha;
            })
            .catch(err => {
                console.error('❌ Error detallado:', err);
                document.getElementById('modalBody').innerHTML = `
                    <div style="text-align:center; color:#dc3545; padding:40px;">
                        <i class="bi bi-exclamation-triangle" style="font-size:48px;"></i>
                        <p style="margin-top:20px;">❌ Error al cargar</p>
                        <p style="font-size:12px; color:#666;">${err.message}</p>
                    </div>
                `;
            });
        }

        function cambiarPestaña(id) {
            verDetalle(id);
        }

        function abrirModalEliminar(id) {
            corteIdEliminar = id;
            document.getElementById('modalEliminar').style.display = 'flex';
            document.getElementById('passwordEliminar').value = '';
        }

        function cerrarModalEliminar() {
            document.getElementById('modalEliminar').style.display = 'none';
            corteIdEliminar = null;
        }

        function confirmarEliminar() {
            const password = document.getElementById('passwordEliminar').value;
            if (!password) {
                alert('❌ Ingresa tu contraseña');
                return;
            }
            
            const formData = new FormData();
            formData.append('corte_id', corteIdEliminar);
            formData.append('password', password);
            
            fetch('eliminar_corte_caja.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    cerrarModalEliminar();
                    cerrarModal();
                    location.reload();
                } else {
                    alert('❌ ' + data.message);
                }
            })
            .catch(err => alert('❌ Error: ' + err.message));
        }

        function cerrarModal() {
            document.getElementById('modalDetalle').style.display = 'none';
        }

        function limpiarFiltros() {
            window.location.href = 'historial_corte.php';
        }

        document.getElementById('modalDetalle').addEventListener('click', e => { if (e.target === this) cerrarModal(); });
        document.getElementById('modalEliminar').addEventListener('click', e => { if (e.target === this) cerrarModalEliminar(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') { cerrarModal(); cerrarModalEliminar(); } });
    </script>

    <script>
        function limpiarFiltros() {
            // Redirigir sin parámetros
            window.location.href = 'historial_corte.php';
        }
    </script>
</body>

</html>