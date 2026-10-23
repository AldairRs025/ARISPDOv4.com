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
$producto = $_GET['producto'] ?? '';
$tipo_movimiento = $_GET['tipo_movimiento'] ?? 'todos'; // todos, entrada, salida

// 🔥 OBTENER LISTA DE PRODUCTOS PARA EL SELECT
$productos = mysqli_query($conn, "SELECT codigo_barra, nombre_producto FROM productos WHERE estado = 'activo' ORDER BY nombre_producto ASC");

// 🔥 CONSULTA MOVIMIENTOS - USANDO TABLAS EXISTENTES
$movimientos = [];

// 📥 ENTRADAS (Tabla COMPRAS)
if ($tipo_movimiento === 'todos' || $tipo_movimiento === 'entrada') {
    $whereCompras = ["DATE(c.fecha_compra) BETWEEN '$fecha_inicio' AND '$fecha_fin'"];
    if (!empty($producto)) {
        $whereCompras[] = "c.codigo_barra = '" . mysqli_real_escape_string($conn, $producto) . "'";
    }

    $sqlCompras = "
    SELECT 
        c.id,
        c.codigo_barra,
        p.nombre_producto,
        'entrada' as tipo_movimiento,
        c.cantidad,
        c.precio_compra,
        c.total,
        c.compra_id as folio_referencia,
        CONCAT('Compra a proveedor') as descripcion,
        c.fecha_compra as fecha_movimiento,
        u.username as usuario
    FROM compras c
    LEFT JOIN productos p ON c.codigo_barra = p.codigo_barra
    LEFT JOIN usuarios u ON c.usuario_id = u.id
    WHERE " . implode(' AND ', $whereCompras) . "
    ORDER BY c.fecha_compra DESC
    ";

    $resultCompras = mysqli_query($conn, $sqlCompras);
    while ($row = mysqli_fetch_assoc($resultCompras)) {
        $movimientos[] = $row;
    }
}

// 📤 SALIDAS (Tabla VENTAS + DETALLE_VENTA)
if ($tipo_movimiento === 'todos' || $tipo_movimiento === 'salida') {
    $whereVentas = ["DATE(v.fecha_venta) BETWEEN '$fecha_inicio' AND '$fecha_fin'", "v.estado = 'completada'"];
    if (!empty($producto)) {
        $whereVentas[] = "dv.codigo_barra = '" . mysqli_real_escape_string($conn, $producto) . "'";
    }

    $sqlVentas = "
    SELECT 
        dv.id,
        dv.codigo_barra,
        p.nombre_producto,
        'salida' as tipo_movimiento,
        dv.cantidad,
        dv.precio_compra,
        dv.subtotal as total,
        v.folio as folio_referencia,
        CONCAT('Venta - Folio: ', v.folio) as descripcion,
        v.fecha_venta as fecha_movimiento,
        u.username as usuario
    FROM detalle_venta dv
    INNER JOIN ventas v ON dv.venta_id = v.id
    LEFT JOIN productos p ON dv.codigo_barra = p.codigo_barra
    LEFT JOIN usuarios u ON v.usuario_id = u.id
    WHERE " . implode(' AND ', $whereVentas) . "
    ORDER BY v.fecha_venta DESC
    ";

    $resultVentas = mysqli_query($conn, $sqlVentas);
    while ($row = mysqli_fetch_assoc($resultVentas)) {
        $movimientos[] = $row;
    }
}

// 🔥 ORDENAR POR FECHA
usort($movimientos, function ($a, $b) {
    return strtotime($b['fecha_movimiento']) - strtotime($a['fecha_movimiento']);
});

// 🔥 CONTADORES
$total_entradas = 0;
$total_salidas = 0;
$cantidad_entradas = 0;
$cantidad_salidas = 0;

foreach ($movimientos as $mov) {
    if ($mov['tipo_movimiento'] === 'entrada') {
        $total_entradas += $mov['total'];
        $cantidad_entradas += $mov['cantidad'];
    } else {
        $total_salidas += $mov['total'];
        $cantidad_salidas += $mov['cantidad'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📦 Movimientos de Inventario - Los Nietos®</title>
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
            background: linear-gradient(135deg, #333 0%, #555 100%);
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

        .movimiento-entrada {
            color: #28a745;
            font-weight: bold;
        }

        .movimiento-salida {
            color: #dc3545;
            font-weight: bold;
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

        .total-item.entradas p {
            color: #28a745;
        }

        .total-item.salidas p {
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
            <h1>📦 Movimientos de Inventario</h1>
            <p>Historial de entradas (compras) y salidas (ventas) de productos</p>
        </div>

        <!-- FILTROS -->
        <form class="filtros-reporte" method="GET">
            <label>📅 Fecha Inicio:</label>
            <input type="date" name="fecha_inicio" value="<?= $fecha_inicio ?>">

            <label>📅 Fecha Fin:</label>
            <input type="date" name="fecha_fin" value="<?= $fecha_fin ?>">

            <label>📦 Producto:</label>
            <select name="producto" style="min-width: 250px;">
                <option value="">-- Todos los Productos --</option>
                <?php while ($prod = mysqli_fetch_assoc($productos)): ?>
                    <option value="<?= $prod['codigo_barra'] ?>" <?= $producto == $prod['codigo_barra'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($prod['nombre_producto']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>🔄 Tipo:</label>
            <select name="tipo_movimiento">
                <option value="todos" <?= $tipo_movimiento == 'todos' ? 'selected' : '' ?>>Todos</option>
                <option value="entrada" <?= $tipo_movimiento == 'entrada' ? 'selected' : '' ?>>📥 Entradas (Compras)
                </option>
                <option value="salida" <?= $tipo_movimiento == 'salida' ? 'selected' : '' ?>>📤 Salidas (Ventas)</option>
            </select>

            <button type="submit">🔍 Filtrar</button>
            <a href="reporte_movimientos.php"
                style="padding: 10px 25px; background: #dc3545; color: white; border-radius: 8px; text-decoration: none; font-weight: bold;">🔄
                Limpiar</a>
        </form>

        <!-- TOTALES -->
        <div class="totales-reporte">
            <div class="total-item entradas">
                <h4>📥 Total Entradas</h4>
                <p><?= $cantidad_entradas ?> pz</p>
            </div>
            <div class="total-item entradas">
                <h4>💰 Valor Entradas</h4>
                <p>$<?= number_format($total_entradas, 2) ?></p>
            </div>
            <div class="total-item salidas">
                <h4>📤 Total Salidas</h4>
                <p><?= $cantidad_salidas ?> pz</p>
            </div>
            <div class="total-item salidas">
                <h4>💰 Valor Salidas</h4>
                <p>$<?= number_format($total_salidas, 2) ?></p>
            </div>
            <div class="total-item">
                <h4>📊 Balance Neto</h4>
                <p><?= $cantidad_entradas - $cantidad_salidas ?> pz</p>
            </div>
        </div>

        <!-- TABLA -->
        <table class="tabla-reporte">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio Unit.</th>
                    <th>Total</th>
                    <th>Folio Ref.</th>
                    <th>Usuario</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($movimientos) > 0): ?>
                    <?php foreach ($movimientos as $mov): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])) ?></td>
                            <td>
                                <span
                                    class="<?= $mov['tipo_movimiento'] === 'entrada' ? 'movimiento-entrada' : 'movimiento-salida' ?>">
                                    <?= $mov['tipo_movimiento'] === 'entrada' ? '📥 Entrada' : '📤 Salida' ?>
                                </span>
                            </td>
                            <td><strong><?= $mov['codigo_barra'] ?></strong></td>
                            <td><?= htmlspecialchars($mov['nombre_producto']) ?></td>
                            <td style="font-weight: bold;"><?= $mov['cantidad'] ?></td>
                            <td>$<?= number_format($mov['precio_compra'], 2) ?></td>
                            <td
                                style="color: <?= $mov['tipo_movimiento'] === 'entrada' ? '#28a745' : '#dc3545' ?>; font-weight: bold;">
                                $<?= number_format($mov['total'], 2) ?>
                            </td>
                            <td><?= $mov['folio_referencia'] ?? 'N/A' ?></td>
                            <td><?= htmlspecialchars($mov['usuario'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($mov['descripcion']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 50px; color: #666;">
                            <i class="bi bi-inbox" style="font-size: 48px; display: block; margin-bottom: 20px;"></i>
                            No se encontraron movimientos con los filtros seleccionados
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- BOTONES DE ACCIÓN -->
        <div class="botones-accion">
            <a href="../exportar/generar_pdf.php?tipo=movimientos&producto=<?= $producto ?>&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>&tipo_movimiento=<?= $tipo_movimiento ?>" class="btn-accion btn-pdf">
                <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
            </a>
            <a href="../exportar/generar_excel.php?tipo=movimientos&producto=<?= $producto ?>&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>&tipo_movimiento=<?= $tipo_movimiento ?>" class="btn-accion btn-excel">
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