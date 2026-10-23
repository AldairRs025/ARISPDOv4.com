<?php
// 🔥 Suprimir errores para evitar corrupción del PDF
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
ob_clean();

require_once('../../fpdf/fpdf.php');

class EtiquetaPDF extends FPDF
{
    var $formato;
    var $opciones;

    function Header()
    {
    }
    function Footer()
    {
    }

    function SetConfig($formato, $opciones)
    {
        $this->formato = $formato;
        $this->opciones = $opciones;
    }

    function DrawLabel($x, $y, $w, $h, $prod)
    {
        // Fondo blanco
        $this->SetFillColor(255, 255, 255);
        $this->Rect($x, $y, $w, $h, 'F'); // 'F' = Fill (relleno)

        // Borde negro
        $this->SetDrawColor(0, 0, 0);
        $this->Rect($x, $y, $w, $h, 'D'); // 'D' = Draw (borde)


        $posY = $y + 2;

        // $padding = 1.5;
        // $posY = $y + $padding;
        // $usableW = $w - ($padding * 2);

        // 1. Nombre (Negrita)
        if (in_array('nombre', $this->opciones)) {
            $this->SetXY($x, $posY);
            $this->SetFont('Arial', 'B', ($this->formato == 'grande') ? 8 : 7);
            $this->MultiCell($w, 3.5, utf8_decode($prod['nombre_producto'] ?? ''), 0, 'C');
            $posY += 4;
        }

        // 2. Categoría
        if (in_array('categoria', $this->opciones)) {
            $this->SetXY($x, $posY);
            $this->SetFont('Arial', '', ($this->formato == 'grande') ? 7 : 6);
            $this->MultiCell($w, 3, utf8_decode($prod['categoria'] ?? ''), 0, 'C');
            $posY += 3.5;
        }

        // 3. Precio
        if (in_array('precio', $this->opciones)) {
            $this->SetXY($x, $posY);
            $this->SetFont('Arial', 'B', ($this->formato == 'grande') ? 11 : 9);
            $this->MultiCell($w, 5, '$' . number_format($prod['precio_venta'] ?? 0, 2), 0, 'C');
            $posY += 5.5;
        }

        // 4. Código de Barras (VECTORIAL - SIN IMÁGENES PNG)
        if (in_array('barcode', $this->opciones)) {
            // Altura reducida para mejor estética
            $barcodeH = ($this->formato == 'grande') ? 4 : 2;
            $barcodeW = $w * .9;
            $barcodeX = $x + ($w - $barcodeW) / 2;

            // Llamada a la función vectorial
            $this->DrawBarcodeVector($barcodeX, $posY, $barcodeW, $barcodeH, $prod['codigo_barra'] ?? '');
            $posY += $barcodeH + 1;
        }

        // 5. Texto del código
        if (in_array('texto_codigo', $this->opciones)) {
            $this->SetXY($x, $posY);
            $this->SetFont('Arial', '', ($this->formato == 'grande') ? 7 : 6);
            $this->MultiCell($w, 3, $prod['codigo_barra'] ?? '', 0, 'C');
        }
    }

    // 🔥 FUNCIÓN NUEVA: Dibuja barras vectoriales (0 borrosidad)
    // Usa patrones rectangulares en lugar de imágenes
    function DrawBarcodeVector($x, $y, $w, $h, $code)
    {
        // Tabla simplificada Code 39 (Patrón barra/espacio)
        // $pattern = [
        //     '0' => '101001101101', '1' => '110100101011', '2' => '101100101011',
        //     '3' => '110110010101', '4' => '101001101011', '5' => '110100110101',
        //     '6' => '101100110101', '7' => '101001011011', '8' => '110100101101',
        //     '9' => '101100101101', '-' => '100101011011', '.' => '110010101101'
        // ];

        // Tabla simplificada Code 11 (Patrón barra/espacio)
        $pattern = [
            '0' => '1010110110', '1' => '11010110110', '2' => '10010110110',
            '3' => '110010110110', '4' => '10110110110', '5' => '11011010110',
            '6' => '10011010110', '7' => '1010110110', '8' => '11010110110',
            '9' => '10010110110', '-' => '10110110110'
        ];

        // Añadir asteriscos de inicio y fin
        $fullCode = '*' . strtoupper($code) . '*';
        $binary = '';

        for ($i = 0; $i < strlen($fullCode); $i++) {
            $c = $fullCode[$i];
            if (isset($pattern[$c])) {
                $binary .= $pattern[$c];
            }
        }

        // Dibujar rectángulos negros directamente
        $barW = $w / strlen($binary);
        $this->SetFillColor(0, 0, 0); // Color Negro Puro

        for ($i = 0; $i < strlen($binary); $i++) {
            if ($binary[$i] == '1') {
                // Dibujamos un rectángulo negro sólido (Vector)
                // El +0.1 es un pequeño solapamiento para evitar líneas blancas microscópicas
                $this->Rect($x + ($i * $barW), $y, $barW + 0.1, $h, 'F');
            }
        }
    }
}

// Configuración Principal
$formato = $_POST['formato'] ?? 'grande';
$opciones = json_decode($_POST['opciones'], true) ?? [];
$productos = json_decode($_POST['productos'], true) ?? [];

$pdf = new EtiquetaPDF();
$pdf->SetConfig($formato, $opciones);

if ($formato == 'grande') {
    $cols = 3;
    $rows = 10;
    $labelW = 65;
    $labelH = 25;
    // Ajuste de márgenes para centrar la columna 3
    $marginX = 2.5;
    $marginY = 12;
    $gapX = 4.5; // Espacio horizontal entre etiquetas
    $gapY = 4.5; // Espacio vertical
} else {
    $cols = 4;
    $rows = 10;
    $labelW = 48;
    $labelH = 25;
    $marginX = 2.5;
    $marginY = 12;
    $gapX = 3.5;
    $gapY = 4.5;
}

$pdf->AddPage();
$col = 0;
$row = 0;

foreach ($productos as $prod) {
    // Calcular posición exacta usando gaps
    $x = $marginX + ($col * ($labelW + $gapX));
    $y = $marginY + ($row * ($labelH + $gapY));

    // Si terminamos la fila, saltar a la siguiente
    if ($col >= $cols) {
        $col = 0;
        $row++;
        $x = $marginX;
        $y = $marginY + ($row * ($labelH + $gapY));
    }

    // Si terminamos la página (aprox 279mm altura)
    if ($y + $labelH > 275) {
        $pdf->AddPage();
        $row = 0;
        $col = 0;
        $x = $marginX;
        $y = $marginY;
    }

    $pdf->DrawLabel($x, $y, $labelW, $labelH, $prod);
    $col++;
}

ob_end_clean();
$pdf->Output('D', 'etiquetas.pdf');
?>