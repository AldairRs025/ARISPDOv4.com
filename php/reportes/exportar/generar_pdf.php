<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../../index.html");
    exit;
}

// 🔥 INCLUIR FPDF
require_once('../../fpdf/fpdf.php');
include("../../conexion.php");

// 🔥 OBTENER PARÁMETROS DEL REPORTE
$tipo_reporte = $_GET['tipo'] ?? 'ventas';
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$fecha = $_GET['fecha'] ?? date('Y-m-d');  // ✅ AGREGADO PARA corte_caja
$producto = $_GET['producto'] ?? '';
$usuario = $_GET['usuario'] ?? '';
$proveedor = $_GET['proveedor'] ?? '';
$limite = $_GET['limite'] ?? 10;  // ✅ AGREGADO PARA productos_mas_vendidos
$tipo_movimiento = $_GET['tipo_movimiento'] ?? 'todos';  // ✅ AGREGADO PARA movimientos

// 🔥 CLASE PDF PERSONALIZADA CON SOPORTE UTF-8
class PDF extends FPDF
{
    // 🔥 FUNCIÓN PARA CONVERTIR UTF-8 A LATIN-1 (IGUAL QUE exportar_reporte.php)
    function UTF8Encode($text)
    {
        return utf8_decode($text);
    }

    function Header()
    {
        // Logo
        $this->Image('../../../img/LOGIN/icon_24px.png', 15, 10, 15);

        // Título
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(180, 10, $this->UTF8Encode('Los Nietos® - Reporte'), 0, 1, 'C');

        // Información del reporte
        $this->SetFont('Arial', '', 10);
        $this->Cell(180, 6, $this->UTF8Encode('Fecha de emisión: ' . date('d/m/Y H:i')), 0, 1, 'C');

        // Línea separadora
        $this->Line(10, 35, 200, 35);
        $this->Ln(10);
    }

    function Footer()
    {
        // Posición a 1.5 cm del final
        $this->SetY(-15);

        // Fuente
        $this->SetFont('Arial', 'I', 8);

        // Número de página
        $this->Cell(0, 10, $this->UTF8Encode('Página ' . $this->PageNo() . '/{nb}'), 0, 0, 'C');

        // Fecha
        $this->Cell(0, 10, $this->UTF8Encode('Generado: ' . date('d/m/Y')), 0, 1, 'R');
    }

    // 🔥 TABLA CON CABECERAS PERSONALIZADAS
    function TablaCabecera($cabeceras, $anchos)
    {
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(51, 51, 51);
        $this->SetTextColor(255);

        for ($i = 0; $i < count($cabeceras); $i++) {
            $this->Cell($anchos[$i], 8, $this->UTF8Encode($cabeceras[$i]), 1, 0, 'C', true);
        }
        $this->Ln();
    }

    function TablaDatos($datos, $anchos, $alineaciones)
    {
        $this->SetFont('Arial', '', 9);
        $this->SetFillColor(248, 249, 250);
        $this->SetTextColor(0);

        $fill = false;
        foreach ($datos as $fila) {
            $this->SetFillColor($fill ? 248 : 255, $fill ? 249 : 255, $fill ? 250 : 255);

            for ($i = 0; $i < count($fila); $i++) {
                $this->Cell($anchos[$i], 7, $this->UTF8Encode($fila[$i]), 1, 0, $alineaciones[$i], $fill);
            }
            $this->Ln();
            $fill = !$fill;
        }
    }

    // 🔥 FUNCIÓN PARA CELDAS CON UTF-8 (MÉTODO EXTRA)
    function UTF8Cell($w, $h, $txt, $border = 0, $ln = 0, $align = 'L', $fill = 0)
    {
        $this->Cell($w, $h, $this->UTF8Encode($txt), $border, $ln, $align, $fill);
    }
}

// 🔥 GENERAR DATOS SEGÚN TIPO DE REPORTE
$datos = [];
$cabeceras = [];
$anchos = [];
$alineaciones = [];
$titulo = '';

switch ($tipo_reporte) {
    case 'ventas_dia':
        $titulo = 'Ventas del Día';
        $cabeceras = ['Folio', 'Hora', 'Vendedor', 'Método', 'Total'];
        $anchos = [40, 25, 40, 35, 50];
        $alineaciones = ['C', 'C', 'C', 'C', 'C']; //L C R

        $sql = "SELECT v.folio, v.fecha_venta, v.total_venta, v.metodo_pago, u.username 
                FROM ventas v 
                LEFT JOIN usuarios u ON v.usuario_id = u.id 
                WHERE DATE(v.fecha_venta) = '$fecha_inicio' 
                AND v.estado = 'completada' 
                ORDER BY v.fecha_venta ASC";
        $resultado = mysqli_query($conn, $sql);

        while ($row = mysqli_fetch_assoc($resultado)) {
            $datos[] = [
                $row['folio'],
                date('H:i', strtotime($row['fecha_venta'])),
                $row['username'] ?? 'N/A',
                $row['metodo_pago'],
                '$' . number_format($row['total_venta'], 2)
            ];
        }
        break;

    case 'ventas_periodo':
        $titulo = 'Ventas por Período';
        $cabeceras = ['Folio', 'Fecha', 'Vendedor', 'Venta', 'Compra', 'Ganancia'];
        $anchos = [35, 30, 35, 35, 30, 30];
        $alineaciones = ['C', 'C', 'C', 'C', 'C', 'C'];

        $sql = "SELECT v.folio, v.fecha_venta, v.total_venta, v.total_compra, v.ganancia, u.username 
                FROM ventas v 
                LEFT JOIN usuarios u ON v.usuario_id = u.id 
                WHERE DATE(v.fecha_venta) BETWEEN '$fecha_inicio' AND '$fecha_fin' 
                AND v.estado = 'completada' 
                ORDER BY v.fecha_venta ASC";
        $resultado = mysqli_query($conn, $sql);

        while ($row = mysqli_fetch_assoc($resultado)) {
            $datos[] = [
                $row['folio'],
                date('d/m/Y', strtotime($row['fecha_venta'])),
                $row['username'] ?? 'N/A',
                '$' . number_format($row['total_venta'], 2),
                '$' . number_format($row['total_compra'], 2),
                '$' . number_format($row['ganancia'], 2)
            ];
        }
        break;

    case 'ventas_producto':
        $titulo = 'Ventas por Producto';
        $cabeceras = ['Folio', 'Fecha', 'Vendedor', 'Cantidad', 'Precio Unit.', 'Subtotal'];
        $anchos = [35, 30, 35, 25, 30, 35];
        $alineaciones = ['C', 'C', 'C', 'C', 'C', 'C'];

        // 🔥 CONSULTA - Ventas de un producto específico
        $sql = "
            SELECT 
                v.folio,
                v.fecha_venta,
                dv.cantidad,
                dv.precio_venta,
                dv.subtotal,
                u.username as vendedor
            FROM detalle_venta dv
            INNER JOIN ventas v ON dv.venta_id = v.id
            LEFT JOIN usuarios u ON v.usuario_id = u.id
            WHERE dv.codigo_barra = '$producto'
            AND DATE(v.fecha_venta) BETWEEN '$fecha_inicio' AND '$fecha_fin'
            AND v.estado = 'completada'
            ORDER BY v.fecha_venta ASC
        ";
        $resultado = mysqli_query($conn, $sql);

        while ($row = mysqli_fetch_assoc($resultado)) {
            $datos[] = [
                $row['folio'],
                date('d/m/Y', strtotime($row['fecha_venta'])),
                $row['vendedor'] ?? 'N/A',
                $row['cantidad'],
                '$' . number_format($row['precio_venta'], 2),
                '$' . number_format($row['subtotal'], 2)
            ];
        }
        break;

    case 'productos_mas_vendidos':
        $titulo = 'Productos Más Vendidos';
        $cabeceras = ['Producto', 'Código', 'Marca', 'Categoría', 'Total Vendido', 'Ingresos', 'Precio Prom.'];
        $anchos = [50, 25, 20, 25, 25, 25, 25];
        $alineaciones = ['C', 'C', 'C', 'C', 'C', 'C', 'C'];

        $limite = $_GET['limite'] ?? 10;

        $sql = "
            SELECT 
                p.codigo_barra,
                p.nombre_producto,
                p.marca,
                p.categoria,
                SUM(dv.cantidad) as total_vendido,
                SUM(dv.subtotal) as ingresos_totales,
                AVG(dv.precio_venta) as precio_promedio
            FROM detalle_venta dv
            INNER JOIN productos p ON dv.codigo_barra = p.codigo_barra
            INNER JOIN ventas v ON dv.venta_id = v.id
            WHERE DATE(v.fecha_venta) BETWEEN '$fecha_inicio' AND '$fecha_fin'
            AND v.estado = 'completada'
            GROUP BY p.codigo_barra
            ORDER BY total_vendido DESC
            LIMIT $limite
        ";
        $resultado = mysqli_query($conn, $sql);

        while ($row = mysqli_fetch_assoc($resultado)) {
            $datos[] = [
                substr($row['nombre_producto'], 0, 25),
                $row['codigo_barra'],
                $row['marca'] ?? 'N/A',
                $row['categoria'] ?? 'N/A',
                $row['total_vendido'],
                '$' . number_format($row['ingresos_totales'], 2),
                '$' . number_format($row['precio_promedio'], 2)
            ];
        }
        break;

    case 'ventas_usuario':
        $titulo = 'Ventas por Usuario';
        $cabeceras = ['Folio', 'Fecha', 'Vendedor', 'Método', 'Venta'];
        $anchos = [35, 30, 35, 30, 35];
        $alineaciones = ['C', 'C', 'C', 'C', 'C'];

        // 🔥 VALIDAR SI ES USUARIO ESPECÍFICO O TODOS
        if (!empty($usuario)) {
            // USUARIO ESPECÍFICO
            $sql = "
                SELECT
                    v.folio,
                    v.fecha_venta,
                    u.username as vendedor,
                    v.metodo_pago,
                    v.total_venta
                FROM ventas v
                LEFT JOIN usuarios u ON v.usuario_id = u.id
                WHERE v.usuario_id = '$usuario'
                AND DATE(v.fecha_venta) BETWEEN '$fecha_inicio' AND '$fecha_fin'
                AND v.estado = 'completada'
                ORDER BY v.fecha_venta ASC
            ";
        } else {
            // 🔥 TODOS LOS USUARIOS
            $sql = "
                SELECT
                    v.folio,
                    v.fecha_venta,
                    u.username as vendedor,
                    v.metodo_pago,
                    v.total_venta
                FROM ventas v
                LEFT JOIN usuarios u ON v.usuario_id = u.id
                WHERE DATE(v.fecha_venta) BETWEEN '$fecha_inicio' AND '$fecha_fin'
                AND v.estado = 'completada'
                ORDER BY v.fecha_venta ASC
            ";
        }

        $resultado = mysqli_query($conn, $sql);

        while ($row = mysqli_fetch_assoc($resultado)) {
            $datos[] = [
                $row['folio'],
                date('d/m/Y', strtotime($row['fecha_venta'])),
                $row['vendedor'] ?? 'N/A',
                $row['metodo_pago'],
                '$' . number_format($row['total_venta'], 2)
            ];
        }

        // 🔥 VALIDAR QUE HAYA DATOS
        if (empty($datos)) {
            $datos = [['⚠️ No hay ventas en este período', '-', '-', '-', '-']];
        }
        break;



    case 'inventario':
        $titulo = 'Inventario Actual';
        $cabeceras = ['Código', 'Producto', 'Marca', 'Categoría', 'Cantidad', 'P. Venta', 'Total Venta', 'Estado'];
        $anchos = [25, 50, 25, 20, 20, 20, 25, 15];
        $alineaciones = ['C', 'C', 'C', 'C', 'C', 'C', 'C', 'C'];

        $sql = "
            SELECT 
                codigo_barra,
                nombre_producto,
                marca,
                categoria,
                cantidad,
                precio_venta,
                total_venta,
                estado
            FROM productos
            ORDER BY nombre_producto ASC
        ";
        $resultado = mysqli_query($conn, $sql);

        while ($row = mysqli_fetch_assoc($resultado)) {
            $datos[] = [
                $row['codigo_barra'],
                substr($row['nombre_producto'], 0, 25),
                $row['marca'] ?? 'N/A',
                $row['categoria'] ?? 'N/A',
                $row['cantidad'],
                '$' . number_format($row['precio_venta'], 2),
                '$' . number_format($row['total_venta'], 2),
                $row['estado']
            ];
        }
        break;

    case 'stock_bajo':
        $titulo = 'Stock Bajo';
        $cabeceras = ['Código', 'Producto', 'Actual', 'Mínimo', 'Estado'];
        $anchos = [30, 70, 25, 25, 40];
        $alineaciones = ['C', 'C', 'C', 'C', 'C'];
        $sql = "SELECT codigo_barra, nombre_producto, cantidad, stock_minimo
                FROM productos
                WHERE cantidad <= stock_minimo
                ORDER BY cantidad ASC";
        $resultado = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($resultado)) {
            $datos[] = [
                $row['codigo_barra'],
                substr($row['nombre_producto'], 0, 35),
                $row['cantidad'],
                $row['stock_minimo'],
                'Bajo'
            ];
        }
        break;

    case 'stock_cero':
        $titulo = 'Stock Cero';
        $cabeceras = ['Código', 'Producto', 'Marca', 'Categoría', 'Stock Mín.', 'P. Venta', 'Estado'];
        $anchos = [25, 60, 25, 25, 20, 20, 20];
        $alineaciones = ['C', 'C', 'C', 'C', 'C', 'C', 'C'];

        $sql = "SELECT codigo_barra, nombre_producto, marca, categoria, stock_minimo, precio_venta, estado
                FROM productos
                WHERE cantidad = 0
                ORDER BY nombre_producto ASC";
        $resultado = mysqli_query($conn, $sql);

        while ($row = mysqli_fetch_assoc($resultado)) {
            $datos[] = [
                $row['codigo_barra'],
                substr($row['nombre_producto'], 0, 30),
                $row['marca'] ?? 'N/A',
                $row['categoria'] ?? 'N/A',
                $row['stock_minimo'],
                '$' . number_format($row['precio_venta'], 2),
                'Sin Stock'
            ];
        }
        break;

    case 'movimientos':
        $titulo = 'Movimientos de Inventario';
        $cabeceras = ['Fecha', 'Tipo', 'Código', 'Producto', 'Cant.', 'Precio Unit.', 'Total', 'Folio Ref.', 'Usuario'];
        $anchos = [20, 17, 25, 35, 15, 25, 15, 30, 20];
        $alineaciones = ['C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C'];

        $movimientos = [];

        // 📥 ENTRADAS (Compras)
        if ($tipo_movimiento === 'todos' || $tipo_movimiento === 'entrada') {
            $whereCompras = ["DATE(c.fecha_compra) BETWEEN '$fecha_inicio' AND '$fecha_fin'"];
            if (!empty($producto)) {
                $whereCompras[] = "c.codigo_barra = '" . mysqli_real_escape_string($conn, $producto) . "'";
            }

            $sqlCompras = "
                SELECT 
                    c.fecha_compra as fecha,
                    'entrada' as tipo_movimiento,
                    c.codigo_barra,
                    p.nombre_producto,
                    c.cantidad,
                    c.precio_compra,
                    c.total,
                    c.compra_id as folio_referencia,
                    u.username as usuario
                FROM compras c
                LEFT JOIN productos p ON c.codigo_barra = p.codigo_barra
                LEFT JOIN usuarios u ON c.usuario_id = u.id
                WHERE " . implode(' AND ', $whereCompras) . "
            ";

            $resultCompras = mysqli_query($conn, $sqlCompras);
            while ($row = mysqli_fetch_assoc($resultCompras)) {
                $movimientos[] = $row;
            }
        }

        // 📤 SALIDAS (Ventas)
        if ($tipo_movimiento === 'todos' || $tipo_movimiento === 'salida') {
            $whereVentas = ["DATE(v.fecha_venta) BETWEEN '$fecha_inicio' AND '$fecha_fin'", "v.estado = 'completada'"];
            if (!empty($producto)) {
                $whereVentas[] = "dv.codigo_barra = '" . mysqli_real_escape_string($conn, $producto) . "'";
            }

            $sqlVentas = "
                SELECT 
                    v.fecha_venta as fecha,
                    'salida' as tipo_movimiento,
                    dv.codigo_barra,
                    p.nombre_producto,
                    dv.cantidad,
                    dv.precio_compra,
                    dv.subtotal as total,
                    v.folio as folio_referencia,
                    u.username as usuario
                FROM detalle_venta dv
                INNER JOIN ventas v ON dv.venta_id = v.id
                LEFT JOIN productos p ON dv.codigo_barra = p.codigo_barra
                LEFT JOIN usuarios u ON v.usuario_id = u.id
                WHERE " . implode(' AND ', $whereVentas) . "
            ";

            $resultVentas = mysqli_query($conn, $sqlVentas);
            while ($row = mysqli_fetch_assoc($resultVentas)) {
                $movimientos[] = $row;
            }
        }

        // 🔥 ORDENAR POR FECHA
        usort($movimientos, function ($a, $b) {
            return strtotime($b['fecha']) - strtotime($a['fecha']);
        });

        foreach ($movimientos as $mov) {
            $datos[] = [
                date('d/m/Y', strtotime($mov['fecha'])),
                $mov['tipo_movimiento'] === 'entrada' ? 'Entrada' : 'Salida',
                $mov['codigo_barra'],
                substr($mov['nombre_producto'] ?? 'N/A', 0, 20),
                $mov['cantidad'],
                '$' . number_format($mov['precio_compra'], 2),
                '$' . number_format($mov['total'], 2),
                $mov['folio_referencia'] ?? 'N/A',
                $mov['usuario'] ?? 'N/A'
            ];
        }

        if (empty($datos)) {
            $datos = [['Sin movimientos en este período', '-', '-', '-', '-', '-', '-', '-', '-']];
        }
        break;


    case 'ganancias':
        $titulo = 'Ganancias por Período';
        $cabeceras = ['Fecha', 'Ventas', 'Venta Total', 'Compra Total', 'Ganancia'];
        $anchos = [35, 30, 40, 40, 45];
        $alineaciones = ['C', 'C', 'C', 'C', 'C'];
        $sql = "SELECT DATE(fecha_venta) as fecha, COUNT(*) as ventas, SUM(total_venta) as venta_total,
                SUM(total_compra) as compra_total, SUM(ganancia) as ganancia
                FROM ventas
                WHERE DATE(fecha_venta) BETWEEN '$fecha_inicio' AND '$fecha_fin'
                AND estado = 'completada'
                GROUP BY DATE(fecha_venta)
                ORDER BY fecha ASC";
        $resultado = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($resultado)) {
            $datos[] = [
                date('d/m/Y', strtotime($row['fecha'])),
                $row['ventas'],
                '$' . number_format($row['venta_total'], 2),
                '$' . number_format($row['compra_total'], 2),
                '$' . number_format($row['ganancia'], 2)
            ];
        }
        break;

    case 'corte_caja':
        $titulo = 'Corte de Caja';
        $cabeceras = ['Folio', 'Hora', 'Vendedor', 'Método', 'Venta', 'Compra', 'Ganancia'];
        $anchos = [35, 15, 35, 20, 25, 25, 35];
        $alineaciones = ['C', 'C', 'C', 'C', 'C', 'C', 'C'];

        $whereClauses = ["DATE(v.fecha_venta) = '$fecha'", "v.estado = 'completada'"];
        if (!empty($usuario)) {
            $whereClauses[] = "v.usuario_id = '" . mysqli_real_escape_string($conn, $usuario) . "'";
        }

        $sql = "
            SELECT
                v.folio,
                v.fecha_venta,
                u.username as vendedor,
                v.metodo_pago,
                v.total_venta,
                v.total_compra,
                v.ganancia
            FROM ventas v
            LEFT JOIN usuarios u ON v.usuario_id = u.id
            WHERE " . implode(' AND ', $whereClauses) . "
            ORDER BY v.fecha_venta ASC
        ";
        $resultado = mysqli_query($conn, $sql);

        while ($row = mysqli_fetch_assoc($resultado)) {
            $datos[] = [
                $row['folio'],
                date('H:i', strtotime($row['fecha_venta'])),
                $row['vendedor'] ?? 'N/A',
                $row['metodo_pago'],
                '$' . number_format($row['total_venta'], 2),
                '$' . number_format($row['total_compra'], 2),
                '$' . number_format($row['ganancia'], 2)
            ];
        }

        if (empty($datos)) {
            $datos = [['Sin ventas en este corte', '-', '-', '-', '-', '-', '-']];
        }
        break;

    case 'compras':
        $titulo = 'Compras a Proveedores';
        $cabeceras = ['Fecha', 'Producto', 'Proveedor', 'Cantidad', 'Total'];
        $anchos = [30, 60, 30, 30, 30];
        $alineaciones = ['C', 'C', 'C', 'C', 'C'];
        $sql = "SELECT c.fecha_compra, p.nombre_producto, pr.nombre as proveedor, c.cantidad, c.total
                FROM compras c
                LEFT JOIN productos p ON c.codigo_barra = p.codigo_barra
                LEFT JOIN proveedores pr ON c.proveedor_id = pr.id
                WHERE DATE(c.fecha_compra) BETWEEN '$fecha_inicio' AND '$fecha_fin'
                ORDER BY c.fecha_compra DESC";
        $resultado = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($resultado)) {
            $datos[] = [
                date('d/m/Y', strtotime($row['fecha_compra'])),
                substr($row['nombre_producto'] ?? 'N/A', 0, 30),
                $row['proveedor'] ?? 'N/A',
                $row['cantidad'],
                '$' . number_format($row['total'], 2)
            ];
        }
        break;


    case 'compras_proveedor':
        $titulo = 'Compras por Proveedor';
        $cabeceras = ['Proveedor', 'Total Compras', 'Cantidad Total', 'Monto Total', 'Primera Compra', 'Última Compra'];
        $anchos = [50, 25, 25, 30, 30, 30];
        $alineaciones = ['C', 'C', 'C', 'C', 'C', 'C'];

        $whereClauses = ["DATE(c.fecha_compra) BETWEEN '$fecha_inicio' AND '$fecha_fin'"];
        if (!empty($proveedor)) {
            $whereClauses[] = "c.proveedor_id = '" . mysqli_real_escape_string($conn, $proveedor) . "'";
        }

        $sql = "
            SELECT 
                c.proveedor_id,
                pr.nombre as proveedor,
                COUNT(DISTINCT c.compra_id) as total_compras,
                SUM(c.cantidad) as cantidad_total,
                SUM(c.total) as monto_total,
                MIN(c.fecha_compra) as primera_compra,
                MAX(c.fecha_compra) as ultima_compra
            FROM compras c
            LEFT JOIN proveedores pr ON c.proveedor_id = pr.id
            LEFT JOIN productos p ON c.codigo_barra = p.codigo_barra
            WHERE " . implode(' AND ', $whereClauses) . "
            GROUP BY c.proveedor_id, pr.nombre
            ORDER BY monto_total DESC
        ";
        $resultado = mysqli_query($conn, $sql);

        while ($row = mysqli_fetch_assoc($resultado)) {
            $datos[] = [
                $row['proveedor'] ?? 'N/A',
                $row['total_compras'],
                $row['cantidad_total'] . ' pz',
                '$' . number_format($row['monto_total'], 2),
                date('d/m/Y', strtotime($row['primera_compra'])),
                date('d/m/Y', strtotime($row['ultima_compra']))
            ];
        }

        if (empty($datos)) {
            $datos = [['Sin compras en este período', '-', '-', '-', '-', '-']];
        }
        break;


    default:
        $titulo = 'Reporte General';
        $cabeceras = ['Dato 1', 'Dato 2', 'Dato 3'];
        $anchos = [70, 70, 70];
        $alineaciones = ['L', 'L', 'L'];
        $datos = [['Sin datos', '-', '-']];
}

// 🔥 CREAR PDF
$pdf = new PDF('P', 'mm', 'Letter');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 14);

// Título del reporte
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, $pdf->UTF8Encode($titulo), 0, 1, 'C');
$pdf->Ln(5);

// Filtros aplicados
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, $pdf->UTF8Encode('Período: ' . date('d/m/Y', strtotime($fecha_inicio)) . ' al ' . date('d/m/Y', strtotime($fecha_fin))), 0, 1);
$pdf->Ln(5);

// Tabla
$pdf->TablaCabecera($cabeceras, $anchos);
$pdf->TablaDatos($datos, $anchos, $alineaciones);

// 🔥 TOTALES (si aplica)
if (!empty($datos) && in_array($tipo_reporte, ['ventas_periodo', 'ganancias', 'compras'])) {
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, $pdf->UTF8Encode('Total de registros: ' . count($datos)), 0, 1, 'R');
}

// 🔥 SALIDA DEL PDF
$pdf->Output('D', 'Reporte_' . $tipo_reporte . '_' . date('Y-m-d') . '.pdf');
?>