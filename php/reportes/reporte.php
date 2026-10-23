<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../index.html");
    exit;
}
include("../conexion.php");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>📊 Reportes - Los Nietos®</title>
    <link rel="stylesheet" href="../../css/Reportes-STYLE/reportes3.css">
    <link rel="icon" href="../../img/LOGIN/icon_24px.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body>
    <!-- BARRA SUPERIOR -->
    <div class="barra-superior">
        <div class="logo">Los Nietos®</div>
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

    <!-- CONTENIDO PRINCIPAL -->
    <div class="reportes-container">
        <h1>📊 Centro de Reportes</h1>

        <!-- BUSCADOR -->
        <div class="buscador-reportes">
            <input type="text" id="buscadorReportes" placeholder="🔍 Buscar reporte...">
        </div>

        <!-- SECCIONES DE REPORTES -->
        <div class="grid-reportes">

            <!-- 📊 VENTAS -->
            <div class="card-reporte categoria-ventas">
                <div class="card-header">
                    <i class="bi bi-cash-stack"></i>
                    <h3>Ventas</h3>
                </div>
                <ul class="lista-reportes">
                    <li><a href="ventas/reporte_ventas_dia.php">📄 Ventas del Día</a></li>
                    <li><a href="ventas/reporte_ventas_periodo.php">📄 Ventas por Período</a></li>
                    <li><a href="ventas/reporte_ventas_producto.php">📄 Ventas por Producto</a></li>
                    <li><a href="ventas/reporte_productos_mas_vendidos.php">📄 Productos Más Vendidos</a></li>
                    <li><a href="ventas/reporte_ventas_usuario.php">📄 Ventas por Usuario</a></li>
                </ul>
            </div>

            <!-- 📦 INVENTARIO -->
            <div class="card-reporte categoria-inventario">
                <div class="card-header">
                    <i class="bi bi-box-seam"></i>
                    <h3>Inventario</h3>
                </div>
                <ul class="lista-reportes">
                    <li><a href="inventario/reporte_inventario_actual.php">📄 Inventario Actual</a></li>
                    <li><a href="inventario/reporte_stock_bajo.php">📄 Stock Bajo</a></li>
                    <li><a href="inventario/reporte_stock_cero.php">📄 Stock Cero</a></li>
                    <li><a href="inventario/reporte_movimientos.php">📄 Movimientos</a></li>
                </ul>
            </div>

            <!-- 💰 FINANZAS -->
            <div class="card-reporte categoria-finanzas">
                <div class="card-header">
                    <i class="bi bi-graph-up-arrow"></i>
                    <h3>Finanzas</h3>
                </div>
                <ul class="lista-reportes">
                    <li><a href="financieros/reporte_ganancias.php">📄 Ganancias</a></li>
                    <li><a href="financieros/reporte_corte_caja.php">📄 Corte de Caja</a></li>
                    <li><a href="financieros/reporte_compras.php">📄 Compras</a></li>
                </ul>
            </div>

            <!-- 🚚 PROVEEDORES -->
            <div class="card-reporte categoria-proveedores">
                <div class="card-header">
                    <i class="bi bi-truck"></i>
                    <h3>Proveedores</h3>
                </div>
                <ul class="lista-reportes">
                    <li><a href="proveedores/reporte_compras_proveedor.php">📄 Compras por Proveedor</a></li>
                    <!-- <li><a href="proveedores/reporte_lotes_vencidos.php">📄 Lotes Vencidos</a></li> -->
                </ul>
            </div>
        </div>
    </div>

    <!-- BARRA INFERIOR -->
    <div class="barra-inferior"></div>
</body>

</html>