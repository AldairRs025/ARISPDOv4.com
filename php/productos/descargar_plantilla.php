<?php
// 🔥 INICIAR SESIÓN
session_start();

// 🔥 VALIDAR USUARIO
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../index.html");
    exit;
}

// 🔥 LIMPIAR CUALQUIER OUTPUT ANTERIOR (IMPORTANTE)
if (ob_get_level()) {
    ob_end_clean();
}

// 🔥 INCLUIR PHPSpreadsheet
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// 🔥 HEADERS PARA EXCEL (ANTES DE CUALQUIER OUTPUT)
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Plantilla_Productos_LosNietos.xlsx"');
header('Cache-Control: max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// 🔥 CREAR NUEVO EXCEL
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// 🔥 ENCABEZADOS
$sheet->setCellValue('A1', 'codigo_barra');
$sheet->setCellValue('B1', 'nombre_producto');
$sheet->setCellValue('C1', 'cantidad');
$sheet->setCellValue('D1', 'marca');
$sheet->setCellValue('E1', 'categoria');
$sheet->setCellValue('F1', 'precio_compra');
$sheet->setCellValue('G1', 'precio_venta');
$sheet->setCellValue('H1', 'stock_minimo');
$sheet->setCellValue('I1', 'proveedor');

// 🔥 ESTILOS DE ENCABEZADOS
$sheet->getStyle('A1:I1')->getFont()->setBold(true);
$sheet->getStyle('A1:I1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
    ->getStartColor()->setARGB('FF4472C4');
$sheet->getStyle('A1:I1')->getFont()->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A1:I1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

// 🔥 EJEMPLOS DE DATOS
$ejemplos = [
    ['750100123456', 'Leche Entera 1L', 100, 'Alpura', 'Lácteos', 18.50, 25.00, 20, 'Distribuidora ABC'],
    ['750100123457', 'Pan Blanco', 50, 'Bimbo', 'Panadería', 12.00, 18.00, 15, 'Distribuidora ABC'],
    ['750100123458', 'Refresco Cola', 200, 'Coca-Cola', 'Bebidas', 15.00, 22.00, 30, 'Distribuidora XYZ'],
];

$fila = 2;
foreach ($ejemplos as $ejemplo) {
    $columna = 'A';
    foreach ($ejemplo as $valor) {
        $sheet->setCellValue($columna . $fila, $valor);
        $columna++;
    }
    $fila++;
}

// 🔥 AJUSTAR ANCHO DE COLUMNAS
foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// 🔥 BORDES PARA LAS CELDAS DE EJEMPLO
$sheet->getStyle('A1:I' . ($fila - 1))->getBorders()->getAllBorders()
    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

// 🔥 GUARDAR Y ENVIAR
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

// 🔥 IMPORTANTE: TERMINAR EJECUCIÓN
exit;
?>