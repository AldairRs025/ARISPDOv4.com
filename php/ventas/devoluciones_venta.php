<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../../index.html");
    exit;
}
include("../conexion.php");

// 🔥 CONSULTAR LOTES CON PROVEEDOR = "Devolución Venta"
$devoluciones_query = mysqli_query($conn, "
    SELECT l.*, p.nombre_producto 
    FROM lotes l
    LEFT JOIN productos p ON l.codigo_barra = p.codigo_barra
    WHERE l.proveedor = 'Devolución Venta'
    ORDER BY l.fecha_ingreso DESC
");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔄 Devoluciones de Venta - Los Nietos®</title>
    <link rel="stylesheet" href="../../css/Compras-STYLE/Ordenes_Compra.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" href="../../img/LOGIN/icon_24px.png" type="img/INICIO/icon_24px.png" />
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
            color: #000000;
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

        .container {
            margin: 100px auto;
            max-width: 1400px;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 32px;
        }

        .alerta {
            background: #fff3cd;
            border-left: 5px solid #ffc107;
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 8px;
            color: #856404;
        }

        .tabla {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .tabla thead {
            background: #333;
            color: white;
        }

        .tabla th,
        .tabla td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .tabla tbody tr:hover {
            background: #f8f9fa;
        }

        .stock-bajo {
            color: #dc3545;
            font-weight: bold;
        }

        .stock-ok {
            color: #28a745;
            font-weight: bold;
        }

        .total-devolucion {
            color: #28a745;
            font-weight: bold;
        }

        .resumen-devoluciones {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .card-resumen {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .card-resumen h4 {
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .card-resumen p {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }
    </style>
</head>

<body>
    <!-- BARRA SUPERIOR -->
    <div class="barra-superior">
        <div class="logo">Los Nietos&#174;</div>
        <div class="iconos">
            <a href="../login/dashboard_admin.php" class="icon-btn">
                <i class="bi bi-house-fill"></i>
                <span class="tooltip">INICIO</span>
            </a>
            <a href="../login/CRUD-Usuarios.php" class="icon-btn">
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
            <!-- <a href="historial_ventas.php" class="icon-btn">
                <i class="bi bi-clock-history"></i>
                <span class="tooltip">HISTORIAL</span>
            </a> -->
            <a href="logout.php" class="icon-btn">
                <i class="bi bi-box-arrow-right"></i>
                <span class="tooltip">SALIR</span>
            </a>
        </div>
    </div>

    <div class="container">
        <h1>🔄 Devoluciones de Venta</h1>

        <div class="alerta">
            ⚠️ <strong>Información:</strong> Los siguientes registros son productos que se devolvieron al inventario
            al eliminar tickets de venta. Todos fueron registrados con proveedor <strong>"Devolución Venta"</strong>.
        </div>

        <!-- 🔥 RESUMEN DE DEVOLUCIONES -->
        <?php
        // Calcular totales
        mysqli_data_seek($devoluciones_query, 0);
        $total_devoluciones = 0;
        $total_productos = 0;
        $total_valor = 0;
        while ($dev = mysqli_fetch_assoc($devoluciones_query)) {
            $total_devoluciones++;
            $total_productos += $dev['cantidad'];
            $total_valor += $dev['cantidad'] * $dev['ganancia_lote'];
        }
        ?>

        <div class="resumen-devoluciones">
            <div class="card-resumen">
                <h4>📦 Total Devoluciones</h4>
                <p>
                    <?= $total_devoluciones ?>
                </p>
            </div>
            <div class="card-resumen">
                <h4>📊 Productos Devueltos</h4>
                <p>
                    <?= $total_productos ?>
                </p>
            </div>
            <div class="card-resumen">
                <h4>💰 Valor Total</h4>
                <p>$
                    <?= number_format($total_valor, 2) ?>
                </p>
            </div>
        </div>

        <table class="tabla">
            <thead>
                <tr>
                    <th>ID Lote</th>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio Venta</th>
                    <th>Subtotal</th>
                    <th>Fecha Devolución</th>
                </tr>
            </thead>
            <tbody>
                <?php
                mysqli_data_seek($devoluciones_query, 0);
                while ($dev = mysqli_fetch_assoc($devoluciones_query)):
                    $subtotal = $dev['cantidad'] * $dev['precio_compra'];
                    ?>
                    <tr>
                        <td><strong>#
                                <?= $dev['id'] ?>
                            </strong></td>
                        <td>
                            <?= htmlspecialchars($dev['codigo_barra']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($dev['nombre_producto'] ?? 'N/A') ?>
                        </td>
                        <td class="stock-ok">
                            <?= $dev['cantidad'] ?>
                        </td>
                        <td>$
                            <?= number_format($dev['ganancia_lote'], 2) ?>
                        </td>
                        <td class="total-devolucion">$
                            <?= number_format($subtotal, 2) ?>
                        </td>
                        <td>
                            <?= date('d/m/Y h:i A', strtotime($dev['fecha_ingreso'])) ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <?php if (mysqli_num_rows($devoluciones_query) === 0): ?>
            <div style="text-align: center; padding: 60px; color: #666;">
                <i class="bi bi-inbox" style="font-size: 64px; margin-bottom: 20px;"></i>
                <p style="font-size: 18px;">No hay devoluciones registradas</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- BARRA INFERIOR -->
    <div class="barra-inferior"></div>
</body>

</html>