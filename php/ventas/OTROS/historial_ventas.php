<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../../index.html");
    exit;
}
include("../conexion.php");

// 🔥 FILTROS
$filtro_vendedor = $_GET['vendedor'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_fecha_inicio = $_GET['fecha_inicio'] ?? '';
$filtro_fecha_fin = $_GET['fecha_fin'] ?? '';

$where = [];
if ($filtro_vendedor)
    $where[] = "v.usuario_id = '" . mysqli_real_escape_string($conn, $filtro_vendedor) . "'";
if ($filtro_estado)
    $where[] = "v.estado = '" . mysqli_real_escape_string($conn, $filtro_estado) . "'";
if ($filtro_fecha_inicio)
    $where[] = "v.fecha_venta >= '" . mysqli_real_escape_string($conn, $filtro_fecha_inicio) . "'";
if ($filtro_fecha_fin)
    $where[] = "v.fecha_venta <= '" . mysqli_real_escape_string($conn, $filtro_fecha_fin) . "'";

// 🔥 CONSULTAR VENTAS
$sql = "SELECT v.*, u.username as vendedor
FROM ventas v
LEFT JOIN usuarios u ON v.usuario_id = u.id
" . (!empty($where) ? "WHERE " . implode(' AND ', $where) : "") . "
ORDER BY v.fecha_venta DESC";

$ventas = mysqli_query($conn, $sql);

// 🔥 TOTAL
$totalSQL = "SELECT SUM(v.total_venta) as total_ventas
FROM ventas v
" . (!empty($where) ? "WHERE " . implode(' AND ', $where) : "");
$total = mysqli_fetch_assoc(mysqli_query($conn, $totalSQL))['total_ventas'] ?? 0;

// 🔥 TOTAL GANANCIAS
$gananciaSQL = "SELECT SUM(v.ganancia) as total_ganancias
FROM ventas v
" . (!empty($where) ? "WHERE " . implode(' AND ', $where) : "");
$ganancia = mysqli_fetch_assoc(mysqli_query($conn, $gananciaSQL))['total_ganancias'] ?? 0;

// 🔥 USUARIOS PARA FILTRO
$usuarios = mysqli_query($conn, "SELECT * FROM usuarios WHERE estado = 'activo' ORDER BY username ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📋 Historial de Ventas - Los Nietos®</title>
    <link rel="stylesheet" href="../../css/Ventas-STYLE/Historial.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .estado-completada {
            background: #d4edda;
            color: #155724;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .estado-cancelada {
            background: #f8d7da;
            color: #721c24;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .estado-pendiente {
            background: #fff3cd;
            color: #856404;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .resumen-ventas {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .tarjeta-resumen {
            flex: 1;
            background: linear-gradient(135deg, #e7f3ff 0%, #d0e8ff 100%);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .tarjeta-resumen.ganancia {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        }
        .tarjeta-resumen h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }
        .tarjeta-resumen p {
            margin: 0;
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }
        .btn-ver-ticket {
            background: #0d6efd;
            color: white;
            padding: 5px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 12px;
            display: inline-block;
            transition: 0.2s;
        }
        .btn-ver-ticket:hover {
            background: #0b5ed7;
            transform: scale(1.05);
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
            <a href="logout.php" class="icon-btn">
                <i class="bi bi-box-arrow-right"></i><span class="tooltip">SALIR</span>
            </a>
        </div>
    </div>

    <div class="container">
        <h1>📋 Historial de Ventas</h1>
        
        <!-- 🔥 BOTÓN NUEVA VENTA (Sin botón de depurar) -->
        <a href="CRUD-Ventas.php" class="btn-nueva">➕ Nueva Venta</a>
        
        <!-- 🔥 RESUMEN DE VENTAS -->
        <div class="resumen-ventas">
            <div class="tarjeta-resumen">
                <h3>💰 Total Vendido</h3>
                <p>$<?= number_format($total, 2) ?></p>
            </div>
            <div class="tarjeta-resumen ganancia">
                <h3>📈 Ganancia Total</h3>
                <p>$<?= number_format($ganancia, 2) ?></p>
            </div>
        </div>
        
        <!-- FILTROS -->
        <form method="GET" class="filtros">
            <div class="filtro-group">
                <label>Vendedor</label>
                <select name="vendedor">
                    <option value="">Todos</option>
                    <?php while ($usuario = mysqli_fetch_assoc($usuarios)): ?>
                        <option value="<?= $usuario['id'] ?>" <?= $filtro_vendedor == $usuario['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($usuario['username']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filtro-group">
                <label>Estado</label>
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="completada" <?= $filtro_estado == 'completada' ? 'selected' : '' ?>>✅ Completada</option>
                    <option value="cancelada" <?= $filtro_estado == 'cancelada' ? 'selected' : '' ?>>❌ Cancelada</option>
                    <option value="pendiente" <?= $filtro_estado == 'pendiente' ? 'selected' : '' ?>>⏳ Pendiente</option>
                </select>
            </div>
            <div class="filtro-group">
                <label>Fecha Inicio</label>
                <input type="date" name="fecha_inicio" value="<?= $filtro_fecha_inicio ?>">
            </div>
            <div class="filtro-group">
                <label>Fecha Fin</label>
                <input type="date" name="fecha_fin" value="<?= $filtro_fecha_fin ?>">
            </div>
            <button type="submit" class="btn-filtrar">🔍 Filtrar</button>
            <a href="historial_ventas.php" class="btn-limpiar">🧹 Limpiar</a>
        </form>
        
        <!-- TABLA DE VENTAS -->
        <table class="tabla">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Folio</th>
                    <th>Vendedor</th>
                    <th>Estado</th>
                    <th>Total Venta</th>
                    <th>Ganancia</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($venta = mysqli_fetch_assoc($ventas)): ?>
                    <tr>
                        <td><?= date('d/m/Y h:i A', strtotime($venta['fecha_venta'])) ?></td>
                        <td><strong><?= htmlspecialchars($venta['folio']) ?></strong></td>
                        <td><?= htmlspecialchars($venta['vendedor'] ?? 'N/A') ?></td>
                        <td>
                            <span class="estado-<?= $venta['estado'] ?>">
                                <?= ucfirst($venta['estado']) ?>
                            </span>
                        </td>
                        <td style="font-weight: bold; color: #0d6efd;">
                            $<?= number_format($venta['total_venta'], 2) ?>
                        </td>
                        <td style="font-weight: bold; color: #28a745;">
                            $<?= number_format($venta['ganancia'], 2) ?>
                        </td>
                        <td>
                            <!-- 🔥 BOTÓN VER TICKET - REDIRIGE A BUSCAR_VENTA_FOLIO.PHP -->
                            <a href="buscar_venta_folio.php?folio=<?= urlencode($venta['folio']) ?>" class="btn-ver-ticket">
                                🔍 Ver Ticket
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- BARRA INFERIOR -->
    <div class="barra-inferior"></div>
</body>
</html>