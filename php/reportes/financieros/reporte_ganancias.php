<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../../index.html");
    exit;
}
include("../../conexion.php");

// 🔥 FILTROS
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$usuario = $_GET['usuario'] ?? '';

// 🔥 OBTENER LISTA DE USUARIOS
$usuarios = mysqli_query($conn, "SELECT id, username FROM usuarios WHERE estado = 'activo' ORDER BY username ASC");

// 🔥 CONSULTA GANANCIAS
$whereClauses = ["DATE(v.fecha_venta) BETWEEN '$fecha_inicio' AND '$fecha_fin'", "v.estado = 'completada'"];
if (!empty($usuario)) {
    $whereClauses[] = "v.usuario_id = '" . mysqli_real_escape_string($conn, $usuario) . "'";
}
$where = implode(' AND ', $whereClauses);

$sql = "
SELECT 
    DATE(v.fecha_venta) as fecha,
    COUNT(v.folio) as total_ventas,
    SUM(v.total_venta) as venta_total,
    SUM(v.total_compra) as compra_total,
    SUM(v.ganancia) as ganancia_total,
    u.username as vendedor
FROM ventas v
LEFT JOIN usuarios u ON v.usuario_id = u.id
WHERE $where
GROUP BY DATE(v.fecha_venta)
ORDER BY fecha DESC
";
$resultado = mysqli_query($conn, $sql);

// 🔥 CONTADORES
$total_ventas = 0;
$venta_total = 0;
$compra_total = 0;
$ganancia_total = 0;
$contador_ventas = 0;

mysqli_data_seek($resultado, 0);
while ($row = mysqli_fetch_assoc($resultado)) {
    $contador_ventas += $row['total_ventas'];
    $venta_total += $row['venta_total'];
    $compra_total += $row['compra_total'];
    $ganancia_total += $row['ganancia_total'];
}
mysqli_data_seek($resultado, 0);

$porcentaje_ganancia = $venta_total > 0 ? (($ganancia_total / $venta_total) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💰 Ganancias - Los Nietos®</title>
    <link rel="stylesheet" href="../../../css/Reportes-STYLE/reportes5.css">
    <link rel="icon" href="../../../img/LOGIN/icon_24px.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>

        .barra-superior {
            width: 100%;
            height: 60px;
            background: #000;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            position: fixed;
            top: 0;
            z-index: 1000;
        }
        
        .reporte-container {
            margin: 100px 5% 50px 5%;
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .reporte-header {
            text-align: center;
            border-bottom: 3px solid #28a745;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .reporte-header h1 {
            font-family: 'Pacifico', cursive;
            color: #333;
            font-size: 36px;
            margin: 0;
        }

        .filtros-reporte {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .filtros-reporte label {
            font-weight: bold;
            color: #333;
        }

        .filtros-reporte input[type="date"],
        .filtros-reporte select {
            padding: 10px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-family: 'Kalam', cursive;
            font-size: 14px;
        }

        .filtros-reporte button {
            padding: 10px 25px;
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-family: 'Kalam', cursive;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .filtros-reporte button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
        }

        .tabla-reporte {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .tabla-reporte thead {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
        }

        .tabla-reporte th,
        .tabla-reporte td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .tabla-reporte tbody tr:hover {
            background: #f8f9fa;
        }

        .totales-reporte {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 2px solid #28a745;
            border-radius: 10px;
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .total-item {
            text-align: center;
        }

        .total-item h4 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
        }

        .total-item p {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
        }

        .botones-accion {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-accion {
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: 0.2s;
        }

        .btn-pdf {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .btn-excel {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
        }

        .btn-imprimir {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
        }

        .btn-accion:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        .barra-inferior {
            width: 100%;
            height: 50px;
            background: #333;
            position: fixed;
            bottom: 0;
            left: 0;
        }

        @media print {

            .barra-superior,
            .filtros-reporte,
            .botones-accion,
            .barra-inferior {
                display: none !important;
            }

            .reporte-container {
                margin: 0;
                box-shadow: none;
            }
        }
    </style>
</head>

<body>
    <!-- BARRA SUPERIOR -->
    <div class="barra-superior">
        <div class="logo">Los Nietos&#174;</div>

        <div class="iconos">
            <a href="../../login/dashboard_admin.php" class="icon-btn">
                <i class="bi bi-house-fill"></i>
                <span class="tooltip">INICIO</span>
            </a>
            <a href="../../login/CRUD-Usuarios.php" class="icon-btn">
                <i class="bi bi-people-fill"></i>
                <span class="tooltip">USUARIOS</span>
            </a>
            <a href="../../proveedores/CRUD-Proveedores.php" class="icon-btn">
                <i class="bi bi-truck"></i>
                <span class="tooltip">PROVEEDORES</span>
            </a>
            <a href="../../productos/CRUD-Product.php" class="icon-btn">
                <i class="bi bi-box-seam"></i>
                <span class="tooltip">PRODUCTOS</span>
            </a>
            <a href="../../ventas/CRUD-Ventas.php" class="icon-btn">
                <i class="bi bi-cash-stack"></i>
                <span class="tooltip">VENTAS</span>
            </a>
            <a href="../../corte/corte_caja.php" class="icon-btn">
                <i class="bi bi-cash-coin"></i>
                <span class="tooltip">CAJA</span>
            </a>
            <a href="../logout.php" class="icon-btn">
                <i class="bi bi-box-arrow-right"></i>
                <span class="tooltip">SALIR</span>
            </a>
        </div>
    </div>

    <div class="reporte-container">
        <div class="reporte-header">
            <h1>💰 Reporte de Ganancias</h1>
            <p>Análisis de ganancias por período</p>
        </div>

        <form class="filtros-reporte" method="GET">
            <label>📅 Fecha Inicio:</label>
            <input type="date" name="fecha_inicio" value="<?= $fecha_inicio ?>">
            <label>📅 Fecha Fin:</label>
            <input type="date" name="fecha_fin" value="<?= $fecha_fin ?>">
            <label>👤 Usuario:</label>
            <select name="usuario">
                <option value="">Todos</option>
                <?php while ($user = mysqli_fetch_assoc($usuarios)): ?>
                    <option value="<?= $user['id'] ?>" <?= $usuario == $user['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($user['username']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <button type="submit">🔍 Filtrar</button>
            <a href="reporte_ganancias.php"
                style="padding: 10px 25px; background: #dc3545; color: white; border-radius: 8px; text-decoration: none; font-weight: bold;">🔄
                Limpiar</a>
        </form>

        <div class="totales-reporte">
            <div class="total-item">
                <h4>📦 Total Ventas</h4>
                <p>
                    <?= $contador_ventas ?>
                </p>
            </div>
            <div class="total-item">
                <h4>💰 Venta Total</h4>
                <p>$
                    <?= number_format($venta_total, 2) ?>
                </p>
            </div>
            <div class="total-item">
                <h4>📦 Compra Total</h4>
                <p>$
                    <?= number_format($compra_total, 2) ?>
                </p>
            </div>
            <div class="total-item">
                <h4>📈 Ganancia Total</h4>
                <p>$
                    <?= number_format($ganancia_total, 2) ?>
                </p>
            </div>
            <div class="total-item">
                <h4>📊 % Ganancia</h4>
                <p>
                    <?= number_format($porcentaje_ganancia, 1) ?>%
                </p>
            </div>
        </div>

        <table class="tabla-reporte">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Ventas</th>
                    <th>Vendedor</th>
                    <th>Venta Total</th>
                    <th>Compra Total</th>
                    <th>Ganancia</th>
                    <th>% Ganancia</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($resultado)):
                    $porc = $row['venta_total'] > 0 ? (($row['ganancia_total'] / $row['venta_total']) * 100) : 0;
                    ?>
                    <tr>
                        <td>
                            <?= date('d/m/Y', strtotime($row['fecha'])) ?>
                        </td>
                        <td>
                            <?= $row['total_ventas'] ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['vendedor'] ?? 'N/A') ?>
                        </td>
                        <td style="color: #0d6efd; font-weight: bold;">$
                            <?= number_format($row['venta_total'], 2) ?>
                        </td>
                        <td style="color: #dc3545;">$
                            <?= number_format($row['compra_total'], 2) ?>
                        </td>
                        <td style="color: #28a745; font-weight: bold;">$
                            <?= number_format($row['ganancia_total'], 2) ?>
                        </td>
                        <td
                            style="color: <?= $porc >= 30 ? '#28a745' : ($porc >= 15 ? '#ffc107' : '#dc3545') ?>; font-weight: bold;">
                            <?= number_format($porc, 1) ?>%
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="botones-accion">
            <a href="../exportar/generar_pdf.php?tipo=ganancias&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>" class="btn-accion btn-pdf"><i
                    class="bi bi-file-earmark-pdf"></i> Descargar PDF</a>
            <a href="../exportar/generar_excel.php?tipo=ganancias&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>" class="btn-accion btn-excel"><i
                    class="bi bi-file-earmark-excel"></i> Descargar Excel</a>
            <a href="javascript:window.print()" class="btn-accion btn-imprimir"><i class="bi bi-printer"></i>
                Imprimir</a>
            <a href="../reporte.php" class="btn-accion" style="background: #6c757d; color: white;">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div class="barra-inferior"></div>
</body>

</html>