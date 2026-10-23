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
$limite = $_GET['limite'] ?? 10;

// 🔥 CONSULTA PRODUCTOS MÁS VENDIDOS
$sql = "
SELECT 
    p.codigo_barra,
    p.nombre_producto,
    p.marca,
    p.categoria,
    SUM(dv.cantidad) as total_vendido,
    SUM(dv.subtotal) as ingresos_totales,
    AVG(dv.precio_venta) as precio_promedio
FROM detalle_venta dv
INNER JOIN productos p ON dv.codigo_barra = p.codigo_barra
INNER JOIN ventas v ON dv.venta_id = v.id
WHERE DATE(v.fecha_venta) BETWEEN ? AND ?
AND v.estado = 'completada'
GROUP BY p.codigo_barra
ORDER BY total_vendido DESC
LIMIT ?
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssi", $fecha_inicio, $fecha_fin, $limite);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏆 Productos Más Vendidos - Los Nietos®</title>
    <link rel="stylesheet" href="../../../css/Reportes-STYLE/reportes5.css">
    <link rel="icon" href="../../../img/LOGIN/icon_24px.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pacifico&family=Kalam:wght@300;400;700&display=swap');

        body {
            background: #f5f5f5;
            font-family: 'Kalam', cursive;
            margin: 0;
            padding: 0;
        }

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

        .icon-btn:hover {
            transform: scale(1.12);
            box-shadow: 0 0 12px rgba(255, 255, 255, 0.7);
        }

        .icon-btn i {
            font-size: 26px;
            color: #000;
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
            border-bottom: 3px solid #ffc107;
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
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: #000;
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
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4);
        }

        .tabla-reporte {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .tabla-reporte thead {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: #000;
        }

        .tabla-reporte th,
        .tabla-reporte td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .tabla-reporte tbody tr:hover {
            background: #fff8e1;
        }

        .ranking-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-weight: bold;
            font-size: 18px;
        }

        .ranking-1 {
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            color: #000;
        }

        .ranking-2 {
            background: linear-gradient(135deg, #c0c0c0 0%, #e8e8e8 100%);
            color: #000;
        }

        .ranking-3 {
            background: linear-gradient(135deg, #cd7f32 0%, #e8a87c 100%);
            color: #fff;
        }

        .ranking-other {
            background: #e9ecef;
            color: #333;
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

    <!-- CONTENIDO DEL REPORTE -->
    <div class="reporte-container">
        <!-- HEADER -->
        <div class="reporte-header">
            <h1>🏆 Productos Más Vendidos</h1>
            <p>Ranking de los productos con mayor rotación</p>
        </div>

        <!-- FILTROS -->
        <form class="filtros-reporte" method="GET">
            <label>📅 Fecha Inicio:</label>
            <input type="date" name="fecha_inicio" value="<?= $fecha_inicio ?>">

            <label>📅 Fecha Fin:</label>
            <input type="date" name="fecha_fin" value="<?= $fecha_fin ?>">

            <label>🏅 Mostrar:</label>
            <select name="limite">
                <option value="5" <?= $limite == 5 ? 'selected' : '' ?>>Top 5</option>
                <option value="10" <?= $limite == 10 ? 'selected' : '' ?>>Top 10</option>
                <option value="20" <?= $limite == 20 ? 'selected' : '' ?>>Top 20</option>
                <option value="50" <?= $limite == 50 ? 'selected' : '' ?>>Top 50</option>
            </select>

            <button type="submit">🔍 Filtrar</button>
            <a href="reporte_productos_mas_vendidos.php"
                style="padding: 10px 25px; background: #dc3545; color: white; border-radius: 8px; text-decoration: none; font-weight: bold;">🔄
                Limpiar</a>
        </form>

        <!-- TABLA -->
        <table class="tabla-reporte">
            <thead>
                <tr>
                    <th style="width: 80px;">🏅 Ranking</th>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Marca</th>
                    <th>Categoría</th>
                    <th>Total Vendido</th>
                    <th>Ingresos</th>
                    <th>Precio Prom.</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $ranking = 0;
                while ($row = mysqli_fetch_assoc($resultado)):
                    $ranking++;
                    $clase_ranking = $ranking <= 3 ? "ranking-$ranking" : "ranking-other";
                    ?>
                    <tr>
                        <td>
                            <span class="ranking-badge <?= $clase_ranking ?>">
                                <?= $ranking ?>
                            </span>
                        </td>
                        <td><strong>
                                <?= $row['codigo_barra'] ?>
                            </strong></td>
                        <td>
                            <?= $row['nombre_producto'] ?>
                        </td>
                        <td>
                            <?= $row['marca'] ?? 'N/A' ?>
                        </td>
                        <td>
                            <?= $row['categoria'] ?? 'N/A' ?>
                        </td>
                        <td style="font-weight: bold; font-size: 16px; color: #0d6efd;">
                            📦
                            <?= $row['total_vendido'] ?> pz
                        </td>
                        <td style="color: #28a745; font-weight: bold;">
                            $
                            <?= number_format($row['ingresos_totales'], 2) ?>
                        </td>
                        <td>$
                            <?= number_format($row['precio_promedio'], 2) ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- BOTONES DE ACCIÓN -->
        <div class="botones-accion">
            <a href="../exportar/generar_pdf.php?tipo=productos_mas_vendidos&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>&limite=<?= $limite ?>"
                class="btn-accion btn-pdf">
                <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
            </a>
            <a href="../exportar/generar_excel.php?tipo=productos_mas_vendidos&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>&limite=<?= $limite ?>"
                class="btn-accion btn-excel">
                <i class="bi bi-file-earmark-excel"></i> Descargar Excel
            </a>
            <a href="javascript:window.print()" class="btn-accion btn-imprimir">
                <i class="bi bi-printer"></i> Imprimir
            </a>
            <a href="../reporte.php" class="btn-accion" style="background: #6c757d; color: white;">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <!-- BARRA INFERIOR -->
    <div class="barra-inferior"></div>
</body>

</html>