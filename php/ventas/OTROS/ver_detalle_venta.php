<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../index.html");
    exit;
}
include("../conexion.php");

$venta_id = (int) ($_GET['id'] ?? 0);
$venta = mysqli_fetch_assoc(mysqli_query($conn, "SELECT v.*, u.username as vendedor 
                                                   FROM ventas v 
                                                   LEFT JOIN usuarios u ON v.usuario_id = u.id 
                                                   WHERE v.id = $venta_id"));

if (!$venta) {
    header("Location: CRUD-Ventas.php");
    exit;
}

$detalle = mysqli_query($conn, "SELECT d.*, p.nombre_producto 
                                FROM detalle_venta d 
                                LEFT JOIN productos p ON d.codigo_barra = p.codigo_barra 
                                WHERE d.venta_id = $venta_id");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>📋 Detalle de Venta - <?= $venta['folio'] ?></title>
    <link rel="stylesheet" href="../../css/AdminPanel-STYLE/dash.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .container {
            margin: 100px 5% 50px;
            padding: 30px;
            background: #fff;
            border-radius: 10px;
            max-width: 800px;
        }

        .tabla {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .tabla th,
        .tabla td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .tabla th {
            background: #333;
            color: white;
        }

        .total {
            font-size: 20px;
            font-weight: bold;
            text-align: right;
            margin-top: 20px;
        }

        .btn-volver {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>📋 Detalle de Venta</h1>

        <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
            <p><strong>Folio:</strong> <?= $venta['folio'] ?></p>
            <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></p>
            <p><strong>Vendedor:</strong> <?= htmlspecialchars($venta['vendedor']) ?></p>
            <p><strong>Método de Pago:</strong> <?= $venta['metodo_pago'] ?></p>
            <p><strong>Estado:</strong> <?= ucfirst($venta['estado']) ?></p>
        </div>

        <table class="tabla">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Código</th>
                    <th>Cantidad</th>
                    <th>P. Venta</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($prod = mysqli_fetch_assoc($detalle)): ?>
                    <tr>
                        <td><?= htmlspecialchars($prod['nombre_producto'] ?? $prod['codigo_barra']) ?></td>
                        <td><?= $prod['codigo_barra'] ?></td>
                        <td><?= $prod['cantidad'] ?></td>
                        <td>$<?= number_format($prod['precio_venta'], 2) ?></td>
                        <td>$<?= number_format($prod['subtotal'], 2) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="total">
            <p>Total Venta: $<?= number_format($venta['total_venta'], 2) ?></p>
            <p>Ganancia: $<?= number_format($venta['ganancia'], 2) ?></p>
        </div>

        <a href="CRUD-Ventas.php" class="btn-volver">← Volver</a>
    </div>
</body>

</html>