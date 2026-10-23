<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../../index.html");
    exit;
}
include("../conexion.php");

// Obtener productos con stock bajo
$stockBajo = mysqli_query($conn, "SELECT * FROM productos WHERE cantidad <= stock_minimo ORDER BY cantidad ASC");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📦 Órdenes de Compra - Los Nietos®</title>
    <link rel="stylesheet" href="../../css/Compras-STYLE/Ordenes_Compra.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

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
            <a href="logout.php" class="icon-btn">
                <i class="bi bi-box-arrow-right"></i>
                <span class="tooltip">SALIR</span>
            </a>
        </div>
    </div>

    <div class="container">
        <h1>📦 Órdenes de Compra Sugeridas</h1>

        <div class="alerta">
            ⚠️ <strong>Recomendación:</strong> Los siguientes productos tienen stock bajo y deberían reordenarse.
        </div>

        <table class="tabla">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Código</th>
                    <th>Stock Actual</th>
                    <th>Stock Mínimo</th>
                    <th>Sugerido Comprar</th>
                    <th>Proveedor</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($prod = mysqli_fetch_assoc($stockBajo)):
                    $sugerido = $prod['stock_minimo'] * 2 - $prod['cantidad'];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($prod['nombre_producto']) ?></td>
                        <td><?= $prod['codigo_barra'] ?></td>
                        <td class="stock-bajo"><?= $prod['cantidad'] ?></td>
                        <td><?= $prod['stock_minimo'] ?></td>
                        <td><strong><?= $sugerido ?></strong></td>
                        <td><?= htmlspecialchars($prod['proveedor'] ?? 'N/A') ?></td>
                        <td>
                            <a href="registrar_compra.php?codigo=<?= $prod['codigo_barra'] ?>" class="btn-ordenar">
                                🛒 Ordenar
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