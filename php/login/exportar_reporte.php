<?php
session_start();
include("../conexion.php");
include("../login/registrar_actividad.php"); // 🔥 NUEVO: Incluir función de registro

// 🔥 Validar sesión de admin
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../../index.html");
    exit;
}

$user_id = $_SESSION["user_id"] ?? 0; // 🔥 NUEVO: Obtener user_id
$username = $_SESSION["username"] ?? 'Desconocido'; // 🔥 NUEVO: Obtener username

$tipo = $_GET['tipo'] ?? 'pdf';
$fecha_reporte = date('Y-m-d_H-i-s');

// 🔥 OBTENER DATOS DEL RESUMEN
$resumenSQL = "
SELECT
COUNT(codigo_barra) AS total_productos,
SUM(cantidad) AS total_unidades,
SUM(total_compra) AS inversion_total,
SUM(total_venta) AS venta_total,
SUM(total_venta - total_compra) AS ganancia_total,
SUM(CASE WHEN cantidad <= stock_minimo THEN 1 ELSE 0 END) AS stock_bajo
FROM productos
";
$resumen = mysqli_fetch_assoc(mysqli_query($conn, $resumenSQL));

// 🔥 OBTENER TODOS LOS PRODUCTOS
$productosSQL = "SELECT * FROM productos ORDER BY nombre_producto ASC";
$productos = mysqli_query($conn, $productosSQL);

// 🔥 OBTENER LOTES VENCIDOS
$lotesVencidos = [];

/* ============================================
📄 GENERAR PDF (CON FPDF Y UTF-8)
============================================ */
if ($tipo === 'pdf') {
    require_once('../fpdf/fpdf.php');
    class PDF extends FPDF
    {
        // 🔥 FUNCIÓN PARA CONVERTIR UTF-8 A LATIN-1
        function UTF8Encode($text)
        {
            return utf8_decode($text);
        }
        function Header()
        {
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, $this->UTF8Encode('Los Nietos® - Reporte de Inventario'), 0, 1, 'C');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 6, $this->UTF8Encode('Fecha: ' . date('d/m/Y H:i')), 0, 1, 'C');
            $this->Ln(5);
        }
        function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, $this->UTF8Encode('Página ' . $this->PageNo() . '/{nb}'), 0, 0, 'C');
        }
        // 🔥 FUNCIÓN PARA CELDAS CON UTF-8
        function UTF8Cell($w, $h, $txt, $border = 0, $ln = 0, $align = 'L', $fill = 0)
        {
            $this->Cell($w, $h, $this->UTF8Encode($txt), $border, $ln, $align, $fill);
        }
    }
    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 10);

    // 🔥 RESUMEN
    $pdf->SetFillColor(200, 220, 255);
    $pdf->UTF8Cell(0, 8, 'RESUMEN GENERAL', 1, 1, 'C', true);
    $pdf->Ln(2);
    $pdf->UTF8Cell(40, 6, 'Total Productos:', 1);
    $pdf->UTF8Cell(40, 6, $resumen['total_productos'], 1);
    $pdf->UTF8Cell(40, 6, 'Total Unidades:', 1);
    $pdf->UTF8Cell(40, 6, $resumen['total_unidades'], 1, 1);
    $pdf->UTF8Cell(40, 6, 'Inversión Total:', 1);
    $pdf->UTF8Cell(40, 6, '$' . number_format($resumen['inversion_total'], 2), 1);
    $pdf->UTF8Cell(40, 6, 'Venta Total:', 1);
    $pdf->UTF8Cell(40, 6, '$' . number_format($resumen['venta_total'], 2), 1, 1);
    $pdf->UTF8Cell(40, 6, 'Ganancia:', 1);
    $pdf->UTF8Cell(40, 6, '$' . number_format($resumen['ganancia_total'], 2), 1);
    $pdf->UTF8Cell(40, 6, 'Stock Bajo:', 1);
    $pdf->UTF8Cell(40, 6, $resumen['stock_bajo'], 1, 1);
    $pdf->Ln(5);

    // 🔥 TABLA DE PRODUCTOS
    $pdf->SetFillColor(180, 200, 255);
    $pdf->UTF8Cell(0, 8, 'LISTADO DE PRODUCTOS', 1, 1, 'C', true);
    $pdf->Ln(2);

    // Encabezados
    $pdf->SetFillColor(220, 220, 220);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->UTF8Cell(20, 6, 'Código', 1, 0, 'C', true);
    $pdf->UTF8Cell(50, 6, 'Producto', 1, 0, 'L', true);
    $pdf->UTF8Cell(20, 6, 'Cantidad', 1, 0, 'C', true);
    $pdf->UTF8Cell(25, 6, 'P. Compra', 1, 0, 'R', true);
    $pdf->UTF8Cell(25, 6, 'P. Venta', 1, 0, 'R', true);
    $pdf->UTF8Cell(25, 6, 'Total', 1, 0, 'R', true);
    $pdf->UTF8Cell(25, 6, 'Estado', 1, 1, 'C', true);

    // Datos
    $pdf->SetFont('Arial', '', 8);
    while ($prod = mysqli_fetch_assoc($productos)) {
        $pdf->UTF8Cell(20, 5, $prod['codigo_barra'], 1, 0, 'C');
        $pdf->UTF8Cell(50, 5, substr($prod['nombre_producto'], 0, 25), 1, 0, 'L');
        $pdf->UTF8Cell(20, 5, $prod['cantidad'], 1, 0, 'C');
        $pdf->UTF8Cell(25, 5, '$' . number_format($prod['precio_compra'], 2), 1, 0, 'R');
        $pdf->UTF8Cell(25, 5, '$' . number_format($prod['precio_venta'], 2), 1, 0, 'R');
        $pdf->UTF8Cell(25, 5, '$' . number_format($prod['total_venta'], 2), 1, 0, 'R');
        $pdf->UTF8Cell(25, 5, ucfirst($prod['estado']), 1, 1, 'C');
    }
    $pdf->Ln(5);

    // 🔥 DESCARGAR PDF
    $pdf->Output('D', 'Reporte_LosNietos_' . $fecha_reporte . '.pdf');

    // 🔥 NUEVO: REGISTRAR ACTIVIDAD DE EXPORTACIÓN PDF
    registrar_actividad(
        $conn,
        $user_id,
        $username,
        "EXPORTAR",
        "Reporte PDF exportado: Inventario",
        "reportes",
        $fecha_reporte,
        [],
        ['tipo' => 'pdf', 'total_productos' => $resumen['total_productos']]
    );

    exit;
}

/* ============================================
📊 GENERAR EXCEL
============================================ */ elseif ($tipo === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Reporte_LosNietos_' . $fecha_reporte . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office"
    xmlns:x="urn:schemas-microsoft-com:office:excel"
    xmlns="http://www.w3.org/TR/REC-html40">
    <head>
    <meta charset="UTF-8">
    <style>
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #000; padding: 5px; text-align: left; }
    th { background: #4472C4; color: white; }
    .resumen { background: #FFC000; font-weight: bold; }
    </style>
    </head>
    <body>';

    echo '<h2>Los Nietos® - Reporte de Inventario</h2>';
    echo '<p>Fecha: ' . date('d/m/Y H:i') . '</p><br>';

    echo '<h3>RESUMEN GENERAL</h3>';
    echo '<table>';
    echo '<tr class="resumen">';
    echo '<td>Total Productos</td><td>' . $resumen['total_productos'] . '</td>';
    echo '<td>Total Unidades</td><td>' . $resumen['total_unidades'] . '</td>';
    echo '</tr>';
    echo '<tr class="resumen">';
    echo '<td>Inversión Total</td><td>$' . number_format($resumen['inversion_total'], 2) . '</td>';
    echo '<td>Venta Total</td><td>$' . number_format($resumen['venta_total'], 2) . '</td>';
    echo '</tr>';
    echo '<tr class="resumen">';
    echo '<td>Ganancia</td><td>$' . number_format($resumen['ganancia_total'], 2) . '</td>';
    echo '<td>Stock Bajo</td><td>' . $resumen['stock_bajo'] . '</td>';
    echo '</tr>';
    echo '</table><br>';

    echo '<h3>LISTADO DE PRODUCTOS</h3>';
    echo '<table>';
    echo '<tr>';
    echo '<th>Código</th><th>Producto</th><th>Marca</th><th>Categoría</th>';
    echo '<th>Cantidad</th><th>P. Compra</th><th>P. Venta</th><th>Total</th><th>Estado</th>';
    echo '</tr>';

    mysqli_data_seek($productos, 0);
    while ($prod = mysqli_fetch_assoc($productos)) {
        echo '<tr>';
        echo '<td>' . $prod['codigo_barra'] . '</td>';
        echo '<td>' . $prod['nombre_producto'] . '</td>';
        echo '<td>' . $prod['marca'] . '</td>';
        echo '<td>' . $prod['categoria'] . '</td>';
        echo '<td>' . $prod['cantidad'] . '</td>';
        echo '<td>$' . number_format($prod['precio_compra'], 2) . '</td>';
        echo '<td>$' . number_format($prod['precio_venta'], 2) . '</td>';
        echo '<td>$' . number_format($prod['total_venta'], 2) . '</td>';
        echo '<td>' . ucfirst($prod['estado']) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</body></html>';

    // 🔥 NUEVO: REGISTRAR ACTIVIDAD DE EXPORTACIÓN EXCEL
    registrar_actividad(
        $conn,
        $user_id,
        $username,
        "EXPORTAR",
        "Reporte Excel exportado: Inventario",
        "reportes",
        $fecha_reporte,
        [],
        ['tipo' => 'excel', 'total_productos' => $resumen['total_productos']]
    );

    exit;
}

/* ============================================
📧 GENERAR EMAIL (SIMULADO)
============================================ */ elseif ($tipo === 'email') {
    echo '<!DOCTYPE html>
    <html>
    <head>
    <meta charset="UTF-8">
    <title>Reporte Enviado</title>
    <style>
    body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
    .success { color: #28a745; font-size: 24px; margin-bottom: 20px; }
    .btn { background: #0d6efd; color: white; padding: 10px 20px;
    text-decoration: none; border-radius: 5px; display: inline-block; }
    </style>
    </head>
    <body>
    <div class="success">✅ Reporte enviado por email</div>
    <p>El reporte ha sido enviado a tu correo registrado.</p>
    <br>
    <a href="dashboard_admin.php" class="btn">← Volver al Dashboard</a>
    </body>
    </html>';

    // 🔥 NUEVO: REGISTRAR ACTIVIDAD DE ENVÍO POR EMAIL
    registrar_actividad(
        $conn,
        $user_id,
        $username,
        "EXPORTAR",
        "Reporte enviado por email: Inventario",
        "reportes",
        $fecha_reporte,
        [],
        ['tipo' => 'email', 'total_productos' => $resumen['total_productos']]
    );

    exit;
}

header("Location: dashboard_admin.php");
exit;
?>