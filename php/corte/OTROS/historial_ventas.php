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
    $whereClauses[] = "DATE(v.fecha_venta) >= ?";
    $params[] = $_GET['fecha_desde'];
    $types .= "s";
}

// Filtro por fecha hasta
if (!empty($_GET['fecha_hasta'])) {
    $whereClauses[] = "DATE(v.fecha_venta) <= ?";
    $params[] = $_GET['fecha_hasta'];
    $types .= "s";
}

// Filtro por vendedor
if (!empty($_GET['vendedor'])) {
    $whereClauses[] = "u.username = ?";
    $params[] = $_GET['vendedor'];
    $types .= "s";
}

// Filtro por estado
if (!empty($_GET['estado']) && in_array($_GET['estado'], ['completada', 'cancelada', 'pendiente'])) {
    $whereClauses[] = "v.estado = ?";
    $params[] = $_GET['estado'];
    $types .= "s";
}

// Construir WHERE
$where = '';
if (!empty($whereClauses)) {
    $where = 'WHERE ' . implode(' AND ', $whereClauses);
}

// 🔥 CONSULTA PRINCIPAL
$sql = "SELECT v.*, u.username as vendedor
        FROM ventas v
        LEFT JOIN usuarios u ON v.usuario_id = u.id
        $where
        ORDER BY v.fecha_venta DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$ventas = mysqli_stmt_get_result($stmt);

// 🔥 OBTENER ESTADÍSTICAS
$statsSQL = "SELECT 
    COUNT(*) as total_ventas,
    COALESCE(SUM(total_venta), 0) as total_ingresos,
    COALESCE(SUM(ganancia), 0) as total_ganancias,
    COALESCE(SUM(total_compra), 0) as total_compras
    FROM ventas WHERE estado = 'completada'";
$stats = mysqli_fetch_assoc(mysqli_query($conn, $statsSQL));

// 🔥 OBTENER VENDEDORES PARA FILTRO
$vendedores = mysqli_query($conn, "SELECT DISTINCT username FROM usuarios WHERE rol IN ('admin', 'empleado') ORDER BY username");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Historial de Ventas - Los Nietos®</title>
    <link rel="stylesheet" href="../../css/Ventas-STYLE/Historial_Ventas.css">
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

        .icon-btn {
            position: relative;
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

        .stat-card.ingresos {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-card.ganancias {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-card.compras {
            background: linear-gradient(135deg, #434343 0%, #000000 100%);
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

        .tabla-ventas {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }

        .tabla-ventas thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .tabla-ventas th,
        .tabla-ventas td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .tabla-ventas th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
        }

        .tabla-ventas tbody tr:hover {
            background: #f8f9fa;
        }

        .estado-completada {
            background: #d4edda;
            color: #155724;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .estado-cancelada {
            background: #f8d7da;
            color: #721c24;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .estado-pendiente {
            background: #fff3cd;
            color: #856404;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        /* BOTONES DE ACCIÓN */
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
            <a href="historial_ventas.php" class="icon-btn">
                <i class="bi bi-clock-history"></i><span class="tooltip">HISTORIAL</span>
            </a>
            <a href="logout.php" class="icon-btn">
                <i class="bi bi-box-arrow-right"></i><span class="tooltip">SALIR</span>
            </a>
        </div>
    </div>

    <div class="container">
        <h1>📊 Historial de Ventas</h1>

        <!-- ESTADÍSTICAS -->
        <div class="stats-container">
            <div class="stat-card ventas">
                <h4>🛒 Total Ventas</h4>
                <p>
                    <?= number_format($stats['total_ventas']) ?>
                </p>
            </div>
            <div class="stat-card ingresos">
                <h4>💰 Total Ingresos</h4>
                <p>$
                    <?= number_format($stats['total_ingresos'], 2) ?>
                </p>
            </div>
            <div class="stat-card ganancias">
                <h4>📈 Total Ganancias</h4>
                <p>$
                    <?= number_format($stats['total_ganancias'], 2) ?>
                </p>
            </div>
            <div class="stat-card compras">
                <h4>📦 Total Compras</h4>
                <p>$
                    <?= number_format($stats['total_compras'], 2) ?>
                </p>
            </div>
        </div>

        <!-- FILTROS -->
        <div class="filtros-container">
            <form method="GET" action="historial_ventas.php">
                <div class="filtros-grid">
                    <div class="filtro-group">
                        <label>📅 Fecha Desde:</label>
                        <input type="date" name="fecha_desde" value="<?= $_GET['fecha_desde'] ?? '' ?>">
                    </div>
                    <div class="filtro-group">
                        <label>📅 Fecha Hasta:</label>
                        <input type="date" name="fecha_hasta" value="<?= $_GET['fecha_hasta'] ?? '' ?>">
                    </div>
                    <div class="filtro-group">
                        <label>👤 Vendedor:</label>
                        <select name="vendedor">
                            <option value="">Todos</option>
                            <?php while ($v = mysqli_fetch_assoc($vendedores)): ?>
                                <option value="<?= $v['username'] ?>" <?= (($_GET['vendedor'] ?? '') == $v['username']) ? 'selected' : '' ?>>
                                    <?= $v['username'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filtro-group">
                        <label>✅ Estado:</label>
                        <select name="estado">
                            <option value="">Todos</option>
                            <option value="completada" <?= (($_GET['estado'] ?? '') == 'completada') ? 'selected' : '' ?>
                                >Completada</option>
                            <option value="cancelada" <?= (($_GET['estado'] ?? '') == 'cancelada') ? 'selected' : '' ?>
                                >Cancelada</option>
                            <option value="pendiente" <?= (($_GET['estado'] ?? '') == 'pendiente') ? 'selected' : '' ?>
                                >Pendiente</option>
                        </select>
                    </div>
                    <div class="filtro-group" style="display: flex; gap: 10px;">
                        <button type="submit" class="btn-filtrar">🔍 Filtrar</button>
                        <button type="button" class="btn-limpiar"
                            onclick="window.location.href='historial_ventas.php'">🧹 Limpiar</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- TABLA DE VENTAS -->
        <div class="tabla-container">
            <table class="tabla-ventas">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Fecha</th>
                        <th>Vendedor</th>
                        <th>Total</th>
                        <th>Ganancia</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($ventas) > 0): ?>
                        <?php while ($venta = mysqli_fetch_assoc($ventas)): ?>
                            <tr>
                                <td><strong>
                                        <?= htmlspecialchars($venta['folio']) ?>
                                    </strong></td>
                                <td>
                                    <?= date('d/m/Y h:i A', strtotime($venta['fecha_venta'])) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($venta['vendedor'] ?? 'N/A') ?>
                                </td>
                                <td style="color: #28a745; font-weight: bold;">$
                                    <?= number_format($venta['total_venta'], 2) ?>
                                </td>
                                <td style="color: <?= $venta['ganancia'] >= 0 ? '#28a745' : '#dc3545' ?>; font-weight: bold;">
                                    $
                                    <?= number_format($venta['ganancia'], 2) ?>
                                </td>
                                <td>
                                    <span class="estado-<?= $venta['estado'] ?>">
                                        <?= ucfirst($venta['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="buscar_venta_folio.php?folio=<?= urlencode($venta['folio']) ?>" class="btn-ver">
                                        👁️ Ver Detalle
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="sin-resultados">
                                <i class="bi bi-inbox"></i>
                                <p>No se encontraron ventas con los filtros seleccionados</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- BARRA INFERIOR -->
    <div class="barra-inferior"></div>
</body>

</html>