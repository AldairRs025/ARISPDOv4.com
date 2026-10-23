<?php
// Suprimir errores para evitar corrupción del PDF
error_reporting(0);
ini_set('display_errors', 0);

require_once('../../../vendor/tecnickcom/tcpdf/tcpdf.php');

class EtiquetaPDF extends TCPDF
{
    var $formato;
    var $opciones;
    
    // Constructor
    public function __construct($formato, $opciones)
    {
        // Orientación, unidad, tamaño
        parent::__construct('P', 'mm', 'Letter', true, 'UTF-8', false);
        $this->formato = $formato;
        $this->opciones = $opciones;
        
        // Configurar márgenes y página
        $this->SetMargins(0, 0, 0);
        $this->SetAutoPageBreak(false, 0);
        $this->SetCreator('Los Nietos');
    }
    
    function Header() {}
    function Footer() {}
    
    function DrawLabel($x, $y, $w, $h, $prod)
    {
        // Fondo blanco con borde
        $this->SetFillColor(255, 255, 255);
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.1);
        $this->Rect($x, $y, $w, $h, 'DF');
        
        $posY = $y+1;
        
        // 1. Nombre del Producto (SOLO si está seleccionado)
        // Verificamos que exista y no esté vacío
        if (in_array('nombre', $this->opciones) && !empty($prod['nombre_producto'])) {
            $this->SetFont('helvetica', 'B', ($this->formato == 'grande') ? 9 : 7); // Aumenté un poco el tamaño
            $this->SetXY($x, $posY);
            $this->MultiCell($w, 4, $prod['nombre_producto'], 0, 'C', false, 0, '', '', true, 0, false, true, 4, 'M');
            $posY += 3.5;
            // $posY += 5;
        }
        
        // 3. Categoria (SOLO si está seleccionado)
        if (in_array('categoria', $this->opciones) && !empty($prod['categoria'])) {
            $this->SetFont('helvetica', 'B', ($this->formato == 'grande') ? 9 : 7); // Aumenté un poco el tamaño
            $this->SetXY($x, $posY);
            $this->MultiCell($w, 4, $prod['categoria'], 0, 'C', false, 0, '', '', true, 0, false, true, 4, 'M');
            $posY += 4;
        }

        // 3. Precio (SOLO si está seleccionado)
        // if (in_array('precio', $this->opciones)) {
        //     $this->SetFont('helvetica', 'B', ($this->formato == 'grande') ? 11 : 9);
        //     $this->SetXY($x, $posY);
        //     $this->MultiCell($w, 5, '$' . number_format($prod['precio_venta'] ?? 0, 2), 0, 'C', false, 0, '', '', true, 0, false, true, 5, 'M');
        //     $posY += 4;
        //     // $posY += 6;
        // }
        if (in_array('precio', $this->opciones)) {
        
        $precioCompra = $prod['ultimo_precio_compra'] ?? 0;
        $precioVenta = $prod['precio_venta'] ?? 0;
        
        // Formateo del texto: PRE. UNI: $10.00    PRE. VEN: $20.00
        $textoPrecios = "PRE. UNI: $" . number_format($precioCompra, 2) . "    PRE. VEN: $" . number_format($precioVenta, 2);
        
        $this->SetFont('helvetica', 'B', ($this->formato == 'grande') ? 8 : 7);
        $this->SetXY($x, $posY);
        $this->MultiCell($w, 4, $textoPrecios, 0, 'C', false, 0, '', '', true, 0, false, true, 4, 'M');
        
        $posY += 4; // Espacio para bajar al código de barras
    }

        // 4. Código de Barras - TCPDF nativo
        if (in_array('barcode', $this->opciones)) {
            $barcodeH = ($this->formato == 'grande') ? 10 : 8;
            $barcodeW = $w * 0.9;
            $barcodeX = $x + ($w - $barcodeW) / 2;
            
            $style = array(
                'border' => false,
                'vpadding' => 'auto',
                'hpadding' => 'auto',
                'fgcolor' => array(0, 0, 0),
                'bgcolor' => false,
                'text' => in_array('texto_codigo', $this->opciones), // Muestra texto solo si está marcado
                'text_color' => array(0, 0, 0),
                'font_size' => ($this->formato == 'grande') ? 7 : 6,
                'stretch' => false,
                'module_width' => 1,
                'module_height' => 1
            );
            
            $this->write1DBarcode($prod['codigo_barra'] ?? '', 'C128', $barcodeX, $posY, $barcodeW, $barcodeH, 0.4, $style, 'N');
            $posY += $barcodeH + 2;
        } else if (in_array('texto_codigo', $this->opciones)) {
            // Si no hay barcode pero sí texto_codigo, mostramos solo el número
            $this->SetFont('helvetica', '', ($this->formato == 'grande') ? 7 : 6);
            $this->SetXY($x, $posY);
            $this->MultiCell($w, 3, $prod['codigo_barra'] ?? '', 0, 'C', false, 0, '', '', true, 0, false, true, 3, 'M');
            $posY += 3;
        }
    }
}

// ============================================
// CONFIGURACIÓN PRINCIPAL
// ============================================
$formato = $_POST['formato'] ?? 'grande';
$opciones = json_decode($_POST['opciones'], true) ?? [];
$productos = json_decode($_POST['productos'], true) ?? [];

// Validación
if (!is_array($productos) || empty($productos)) {
    die("Error: No se recibieron productos válidos.");
}

// Crear PDF con TCPDF
$pdf = new EtiquetaPDF($formato, $opciones);

// Dimensiones según formato
if ($formato == 'grande') {
    $cols = 3;
    $rows = 10;
    $labelW = 65;
    $labelH = 25;
    $marginX = 2.5;
    $marginY = 5;
    $gapX = 4.5;
    $gapY = 4.5;
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

// Agregar primera página
$pdf->AddPage();

$col = 0;
$row = 0;

foreach ($productos as $prod) {
    // Calcular posición
    $x = $marginX + ($col * ($labelW + $gapX));
    $y = $marginY + ($row * ($labelH + $gapY));
    
    // Verificar si necesita nueva columna
    if ($col >= $cols) {
        $col = 0;
        $row++;
        $x = $marginX;
        $y = $marginY + ($row * ($labelH + $gapY));
    }
    
    // Verificar si necesita nueva página
    if ($y + $labelH > 275) {
        $pdf->AddPage();
        $row = 0;
        $col = 0;
        $x = $marginX;
        $y = $marginY;
    }
    
    // Dibujar etiqueta
    $pdf->DrawLabel($x, $y, $labelW, $labelH, $prod);
    
    $col++;
}

// Output - Forzar descarga
$pdf->Output('etiquetas.pdf', 'D');
?>