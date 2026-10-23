<?php
session_start();
include("../conexion.php");
require("../fpdf/fpdf.php");

if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    die("No autorizado");
}

$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

if (empty($fecha_inicio) || empty($fecha_fin)) {
    die("Error: Fechas requeridas");
}

// ✅ AHORA (OBTIENE USERNAME CON JOIN)
$tickets_query = mysqli_query($conn, "
SELECT c.*, u.username
FROM historial_compras_cabecera c
LEFT JOIN usuarios u ON c.usuario_id = u.id
WHERE DATE(c.fecha_compra) BETWEEN '$fecha_inicio' AND '$fecha_fin'
ORDER BY c.fecha_compra ASC
");

// if (mysqli_num_rows($tickets_query) === 0) {
//     die("No hay compras en ese rango");
// }

// 🔥 GENERAR PDF EN MEMORIA
$pdf = new FPDF('P', 'mm', 'Letter');
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'Respaldo de Historial de Compras', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, "Periodo: $fecha_inicio a $fecha_fin", 0, 1, 'C');
$pdf->Cell(0, 10, "Fecha: " . date('Y-m-d H:i:s'), 0, 1, 'C');
$pdf->Ln(10);

// 🔥 Cabecera tabla PDF (Columnas ajustadas)
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(200, 220, 255);
$pdf->Cell(35, 8, 'Folio', 1, 0, 'C', true);       // Aumentado de 25 a 35
$pdf->Cell(40, 8, 'Fecha', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'Proveedor', 1, 0, 'C', true);   // Reducido de 50 a 40
$pdf->Cell(30, 8, 'Total', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'Usuario', 1, 1, 'C', true);     // Ajustado

$pdf->SetFont('Arial', '', 9);
$total_archivado = 0;

while ($ticket = mysqli_fetch_assoc($tickets_query)) {
    $total_archivado += $ticket['total_compra'];

    // Obtener proveedor
    $prov_q = mysqli_query($conn, "SELECT nombre FROM proveedores WHERE id = {$ticket['proveedor_id']}");
    $prov_n = mysqli_fetch_assoc($prov_q)['nombre'] ?? 'N/A';

    // 🔥 OBTENER NOMBRE DEL USUARIO (no solo el ID)
    $user_n = $ticket['username'] ?? 'N/A';

    $pdf->Cell(35, 7, $ticket['folio'], 1, 0, 'C');                    // Aumentado
    $pdf->Cell(40, 7, date('d/m/Y', strtotime($ticket['fecha_compra'])), 1, 0, 'C');
    $pdf->Cell(40, 7, substr($prov_n, 0, 15), 1, 0, 'C');             // Reducido y limitado
    $pdf->Cell(30, 7, '$' . number_format($ticket['total_compra'], 2), 1, 0, 'C');
    $pdf->Cell(35, 7, $user_n, 1, 1, 'C');                    // Muestra nombre en lugar de ID
}

// 🔥 ENVIAR PDF COMO DESCARGA DIRECTA
$nombre_pdf = "Respaldo_Compras_" . date('Ymd_His') . ".pdf";
$pdf->Output('D', $nombre_pdf);
?>