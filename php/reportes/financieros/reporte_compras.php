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
$proveedor = $_GET['proveedor'] ?? '';

// 🔥 OBTENER LISTA DE PROVEEDORES (SIN FILTRO estado)
$proveedores = mysqli_query($conn, "SELECT id, nombre FROM proveedores ORDER BY nombre ASC");

// 🔥 CONSULTA COMPRAS
$whereClauses = ["DATE(c.fecha_compra) BETWEEN '$fecha_inicio' AND '$fecha_fin'"];
if (!empty($proveedor)) {
    $whereClauses[] = "c.proveedor_id = '" . mysqli_real_escape_string($conn, $proveedor) . "'";
}
$where = implode(' AND ', $whereClauses);

$sql = "
SELECT 
    c.compra_id,
    c.fecha_compra,
    c.codigo_barra,
    p.nombre_producto,
    c.cantidad,
    c.precio_compra,
    c.total,
    pr.nombre as proveedor,
    u.username as usuario
FROM compras c
LEFT JOIN productos p ON c.codigo_barra = p.codigo_barra
LEFT JOIN proveedores pr ON c.proveedor_id = pr.id
LEFT JOIN usuarios u ON c.usuario_id = u.id
WHERE $where
ORDER BY c.fecha_compra DESC
";
$resultado = mysqli_query($conn, $sql);

// 🔥 CONTADORES
$total_compras = 0;
$cantidad_total = 0;
$monto_total = 0;

mysqli_data_seek($resultado, 0);
while ($row = mysqli_fetch_assoc($resultado)) {
    $total_compras++;
    $cantidad_total += $row['cantidad'];
    $monto_total += $row['total'];
}
mysqli_data_seek($resultado, 0);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛒 Compras - Los Nietos®</title>
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
            border-bottom: 3px solid #0d6efd;
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
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
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
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.4);
        }

        .tabla-reporte {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .tabla-reporte thead {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
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
            background: linear-gradient(135deg, #e7f3ff 0%, #d0e8ff 100%);
            border: 2px solid #0d6efd;
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
            color: #0d6efd;
        }

        .metodos-pago {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .metodo-item {
            text-align: center;
            padding: 15px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .metodo-item h4 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
        }

        .metodo-item p {
            margin: 0;
            font-size: 22px;
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

<body class="reporte-pdf">
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
    <div class="reportes-container">
        <!-- HEADER -->
        <div class="reporte-header">
            <h1>🛒 Reporte de Compras</h1>
            <p>Historial de compras a proveedores</p>
        </div>

        <!-- FILTROS -->
        <form class="filtros-reporte" method="GET">
            <label>📅 Fecha Inicio:</label>
            <input type="date" name="fecha_inicio" value="<?= $fecha_inicio ?>">

            <label>📅 Fecha Fin:</label>
            <input type="date" name="fecha_fin" value="<?= $fecha_fin ?>">

            <label>🚚 Proveedor:</label>
            <select name="proveedor">
                <option value="">Todos</option>
                <?php while ($prov = mysqli_fetch_assoc($proveedores)): ?>
                    <option value="<?= $prov['id'] ?>" <?= $proveedor == $prov['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($prov['nombre']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <button type="submit">🔍 Filtrar</button>
            <a href="reporte_compras.php"
                style="padding: 10px 25px; background: #dc3545; color: white; border-radius: 8px; text-decoration: none; font-weight: bold;">🔄
                Limpiar</a>
        </form>

        <!-- TOTALES -->
        <div class="totales-reporte"
            style="background: linear-gradient(135deg, #e7dff5 0%, #d5c4e8 100%); border: 2px solid #6610f2;">
            <div class="total-item">
                <h4>📦 Total Compras</h4>
                <p><?= $total_compras ?></p>
            </div>
            <div class="total-item">
                <h4>📊 Cantidad Total</h4>
                <p><?= $cantidad_total ?> pz</p>
            </div>
            <div class="total-item">
                <h4>💰 Monto Total</h4>
                <p>$<?= number_format($monto_total, 2) ?></p>
            </div>
        </div>

        <!-- TABLA -->
        <table class="tabla-reporte">
            <thead>
                <tr>
                    <th>Compra ID</th>
                    <th>Fecha</th>
                    <th>Producto</th>
                    <th>Código</th>
                    <th>Proveedor</th>
                    <th>Cantidad</th>
                    <th>Precio Unit.</th>
                    <th>Total</th>
                    <th>Usuario</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($resultado) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($resultado)): ?>
                        <tr>
                            <td><strong><?= $row['compra_id'] ?></strong></td>
                            <td><?= date('d/m/Y', strtotime($row['fecha_compra'])) ?></td>
                            <td><?= htmlspecialchars($row['nombre_producto'] ?? 'N/A') ?></td>
                            <td><?= $row['codigo_barra'] ?></td>
                            <td><?= htmlspecialchars($row['proveedor'] ?? 'N/A') ?></td>
                            <td style="font-weight: bold;"><?= $row['cantidad'] ?></td>
                            <td>$<?= number_format($row['precio_compra'], 2) ?></td>
                            <td style="color: #6610f2; font-weight: bold;">$<?= number_format($row['total'], 2) ?></td>
                            <td><?= htmlspecialchars($row['usuario'] ?? 'N/A') ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 50px; color: #666;">
                            <i class="bi bi-inbox" style="font-size: 48px; display: block; margin-bottom: 20px;"></i>
                            No hay compras registradas en este período
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- BOTONES DE ACCIÓN -->
        <div class="botones-accion">
            <a href="../exportar/generar_pdf.php?tipo=compras&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>&proveedor=<?= $proveedor ?>" class="btn-accion btn-pdf"><i
                    class="bi bi-file-earmark-pdf"></i> Descargar PDF</a>
            <a href="../exportar/generar_excel.php?tipo=compras&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>&proveedor=<?= $proveedor ?>" class="btn-accion btn-excel"><i
                    class="bi bi-file-earmark-excel"></i> Descargar Excel</a>
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