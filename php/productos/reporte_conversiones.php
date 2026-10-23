<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../../index.html");
    exit;
}
include("../conexion.php");

// 🔥 HISTORIAL DE DESGLOSES
$historial = mysqli_query($conn, "SELECT hd.*, 
    p1.nombre_producto as nombre_padre,
    p2.nombre_producto as nombre_hijo,
    u.username
    FROM historial_desgloses hd
    INNER JOIN productos p1 ON hd.producto_padre_codigo = p1.codigo_barra
    INNER JOIN productos p2 ON hd.producto_hijo_codigo = p2.codigo_barra
    INNER JOIN usuarios u ON hd.usuario_id = u.id
    ORDER BY hd.fecha_desglose DESC
    LIMIT 100");

// 🔥 STOCK POR NIVEL
$stock_nivel = mysqli_query($conn, "SELECT cu.nivel,
    p1.nombre_producto as padre, p1.cantidad as stock_padre,
    p2.nombre_producto as hijo, p2.cantidad as stock_hijo,
    cu.factor_conversion
    FROM conversion_unidades cu
    INNER JOIN productos p1 ON cu.producto_padre_codigo = p1.codigo_barra
    INNER JOIN productos p2 ON cu.producto_hijo_codigo = p2.codigo_barra
    WHERE cu.activo = 1
    ORDER BY cu.nivel, p1.nombre_producto");

// 🔥 CONVERSIONES MÁS UTILIZADAS
$mas_utilizadas = mysqli_query($conn, "SELECT 
    cu.producto_padre_codigo, p1.nombre_producto,
    COUNT(hd.id) as total_desgloses,
    SUM(hd.cantidad_padre) as total_unidades
    FROM historial_desgloses hd
    INNER JOIN conversion_unidades cu ON hd.producto_padre_codigo = cu.producto_padre_codigo
    INNER JOIN productos p1 ON cu.producto_padre_codigo = p1.codigo_barra
    GROUP BY cu.producto_padre_codigo
    ORDER BY total_desgloses DESC
    LIMIT 10");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>📊 Reportes de Conversión - Los Nietos®</title>
    <!-- MISMO CSS QUE LOS OTROS REPORTES -->
</head>

<body>
    <div class="reporte-container">
        <h1>📊 Reportes de Conversión/Desglose</h1>

        <!-- HISTORIAL -->
        <h2>📜 Historial de Desgloses</h2>
        <table class="tabla-reporte">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Producto Padre</th>
                    <th>Cantidad</th>
                    <th>Producto Hijo</th>
                    <th>Resultado</th>
                    <th>Usuario</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($historial)): ?>
                    <tr>
                        <td>
                            <?= date('d/m/Y H:i', strtotime($row['fecha_desglose'])) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['nombre_padre']) ?>
                        </td>
                        <td>
                            <?= $row['cantidad_padre'] ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['nombre_hijo']) ?>
                        </td>
                        <td>
                            <?= $row['cantidad_hijo'] ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['username']) ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- STOCK POR NIVEL -->
        <h2>📦 Stock por Nivel de Conversión</h2>
        <table class="tabla-reporte">
            <thead>
                <tr>
                    <th>Nivel</th>
                    <th>Producto Padre</th>
                    <th>Stock Padre</th>
                    <th>Factor</th>
                    <th>Producto Hijo</th>
                    <th>Stock Hijo</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($stock_nivel)): ?>
                    <tr>
                        <td>Nivel
                            <?= $row['nivel'] ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['padre']) ?>
                        </td>
                        <td>
                            <?= $row['stock_padre'] ?>
                        </td>
                        <td>×
                            <?= $row['factor_conversion'] ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['hijo']) ?>
                        </td>
                        <td>
                            <?= $row['stock_hijo'] ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- MÁS UTILIZADAS -->
        <h2>🔥 Conversiones Más Utilizadas</h2>
        <table class="tabla-reporte">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Total Desgloses</th>
                    <th>Total Unidades</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($mas_utilizadas)): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($row['nombre_producto']) ?>
                        </td>
                        <td>
                            <?= $row['total_desgloses'] ?>
                        </td>
                        <td>
                            <?= $row['total_unidades'] ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>

</html>