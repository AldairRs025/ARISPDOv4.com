<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../../index.html");
    exit;
}
include("../../conexion.php");

// 🔥 FILTROS
$marca = $_GET['marca'] ?? '';
$categoria = $_GET['categoria'] ?? '';

// 🔥 CONSULTA PRINCIPAL - STOCK CERO
$whereClauses = ["p.cantidad = 0", "p.estado = 'activo'"];

if (!empty($marca)) {
    $whereClauses[] = "p.marca = '" . mysqli_real_escape_string($conn, $marca) . "'";
}

if (!empty($categoria)) {
    $whereClauses[] = "p.categoria = '" . mysqli_real_escape_string($conn, $categoria) . "'";
}

$where = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$sql = "
SELECT p.*, 
       COALESCE(MAX(l.precio_compra), 0) AS max_precio_compra,
       (p.cantidad * COALESCE(MAX(l.precio_compra), 0)) AS total_compra_calculado
FROM productos p
LEFT JOIN lotes l ON p.codigo_barra = l.codigo_barra
$where
GROUP BY p.codigo_barra
ORDER BY p.nombre_producto ASC
";
$resultado = mysqli_query($conn, $sql);

// 🔥 OBTENER DATOS PARA FILTROS
$marcas = mysqli_query($conn, "SELECT DISTINCT marca FROM productos WHERE marca IS NOT NULL AND marca != '' ORDER BY marca");
$categorias = mysqli_query($conn, "SELECT DISTINCT categoria FROM productos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria");

// 🔥 CONTADORES
$total_productos = 0;
$total_inversion = 0;
$total_venta = 0;

mysqli_data_seek($resultado, 0);
while ($row = mysqli_fetch_assoc($resultado)) {
    $total_productos++;
    $total_inversion += $row['total_compra_calculado'];
    $total_venta += $row['total_venta'];
}
mysqli_data_seek($resultado, 0);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>❌ Stock Cero - Los Nietos®</title>
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
            border-bottom: 3px solid #dc3545;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .reporte-header h1 {
            font-family: 'Pacifico', cursive;
            color: #dc3545;
            font-size: 36px;
            margin: 0;
        }

        .alerta-critica {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border-left: 5px solid #dc3545;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .alerta-critica i {
            font-size: 48px;
            color: #dc3545;
        }

        .alerta-critica h3 {
            margin: 0 0 5px 0;
            color: #721c24;
        }

        .alerta-critica p {
            margin: 0;
            color: #721c24;
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

        .filtros-reporte select {
            padding: 10px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-family: 'Kalam', cursive;
            font-size: 14px;
        }

        .filtros-reporte button {
            padding: 10px 25px;
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
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
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
        }

        .tabla-reporte {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .tabla-reporte thead {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .tabla-reporte th,
        .tabla-reporte td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .tabla-reporte tbody tr:hover {
            background: #fff5f5;
        }

        .stock-cero {
            color: #dc3545;
            font-weight: bold;
            background: #ffe6e6;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .totales-reporte {
            background: linear-gradient(135deg, #ffe6e6 0%, #ffcccc 100%);
            border: 2px solid #dc3545;
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
            color: #dc3545;
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
            <h1>❌ Stock en Cero</h1>
            <p>Productos agotados que requieren compra urgente</p>
        </div>

        <!-- ALERTA CRÍTICA -->
        <div class="alerta-critica">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <h3>⚠️ Atención Requerida</h3>
                <p>Estos productos no están disponibles para la venta. Se recomienda realizar una compra inmediata.</p>
            </div>
        </div>

        <!-- FILTROS -->
        <form class="filtros-reporte" method="GET">
            <label>🏷️ Marca:</label>
            <select name="marca">
                <option value="">Todas</option>
                <?php while ($m = mysqli_fetch_assoc($marcas)): ?>
                    <option value="<?= htmlspecialchars($m['marca']) ?>" <?= $marca == $m['marca'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['marca']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>📂 Categoría:</label>
            <select name="categoria">
                <option value="">Todas</option>
                <?php while ($c = mysqli_fetch_assoc($categorias)): ?>
                    <option value="<?= htmlspecialchars($c['categoria']) ?>" <?= $categoria == $c['categoria'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['categoria']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <button type="submit">🔍 Filtrar</button>
            <a href="reporte_stock_cero.php"
                style="padding: 10px 25px; background: #6c757d; color: white; border-radius: 8px; text-decoration: none; font-weight: bold;">🔄
                Limpiar</a>
        </form>

        <!-- TOTALES -->
        <div class="totales-reporte">
            <div class="total-item">
                <h4>❌ Productos Sin Stock</h4>
                <p>
                    <?= $total_productos ?>
                </p>
            </div>
            <div class="total-item">
                <h4>💰 Inversión en Stock Cero</h4>
                <p>$
                    <?= number_format($total_inversion, 2) ?>
                </p>
            </div>
            <div class="total-item">
                <h4>💵 Venta Potencial Perdida</h4>
                <p>$
                    <?= number_format($total_venta, 2) ?>
                </p>
            </div>
        </div>

        <!-- TABLA -->
        <table class="tabla-reporte">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Marca</th>
                    <th>Categoría</th>
                    <th>Stock Mín.</th>
                    <th>Último Precio Compra</th>
                    <th>Precio Venta</th>
                    <th>Total Venta Potencial</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($resultado)): ?>
                    <tr>
                        <td><strong>
                                <?= $row['codigo_barra'] ?>
                            </strong></td>
                        <td>
                            <?= htmlspecialchars($row['nombre_producto']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['marca'] ?? 'N/A') ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['categoria'] ?? 'N/A') ?>
                        </td>
                        <td>
                            <?= $row['stock_minimo'] ?> pz
                        </td>
                        <td>$
                            <?= number_format($row['max_precio_compra'], 2) ?>
                        </td>
                        <td>$
                            <?= number_format($row['precio_venta'], 2) ?>
                        </td>
                        <td>$
                            <?= number_format($row['total_venta'], 2) ?>
                        </td>
                        <td>
                            <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; 
                        background: #f8d7da;
                        color: #721c24;">
                                🔴 Sin Stock
                            </span>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- BOTONES DE ACCIÓN -->
        <div class="botones-accion">
            <a href="../exportar/generar_pdf.php?tipo=stock_cero" class="btn-accion btn-pdf">
                <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
            </a>
            <a href="../exportar/generar_excel.php?tipo=stock_cero" class="btn-accion btn-excel">
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