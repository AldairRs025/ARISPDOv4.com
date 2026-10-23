<?php
session_start();

if (ob_get_level()) {
    ob_end_clean();
}

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

echo "<h1>✅ PhpSpreadsheet cargado correctamente</h1>";
// echo "<p>✅ Versión: " . \PhpOffice\PhpSpreadsheet\Settings::getLibVersion() . "</p>"; // ❌ ELIMINAR ESTA LÍNEA
echo "<p>✅ PhpSpreadsheet está instalado y funcionando</p>";
echo "<hr>";

try {
    $spreadsheet = new Spreadsheet();
    echo "<p>✅ Spreadsheet creado</p>";

    $spreadsheet->getActiveSheet()->setCellValue('A1', 'Test');
    echo "<p>✅ Celda escrita</p>";

    $writer = new Xlsx($spreadsheet);
    echo "<p>✅ Writer creado</p>";

    echo "<h2>✅ Todo funciona correctamente</h2>";

} catch (Exception $e) {
    echo "<h2>❌ Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
exit;
?>