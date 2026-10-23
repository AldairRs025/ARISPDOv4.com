<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../../index.html");
    exit;
}

include("../../conexion.php");

// 🔥 OBTENER PARÁMETROS DEL REPORTE
$tipo_reporte = $_GET['tipo'] ?? 'ventas';
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$producto = $_GET['producto'] ?? '';
$usuario = $_GET['usuario'] ?? '';
$proveedor = $_GET['proveedor'] ?? '';

// 🔥 GENERAR DATOS SEGÚN TIPO DE REPORTE
$datos = [];
$cabeceras = [];
$titulo = '';
$nombre_archivo = 'Reporte_' . $tipo_reporte . '_' . date('Y-m-d') . '.xls';

switch ($tipo_reporte) {
    case 'ventas_dia':
        $titulo = 'Ventas del Día';
        $cabeceras = ['Folio', 'Fecha', 'Hora', 'Vendedor', 'Método Pago', 'Total Venta'];

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
                date('d/m/Y', strtotime($row['fecha_venta'])),
                date('H:i', strtotime($row['fecha_venta'])),
                $row['username'] ?? 'N/A',
                $row['metodo_pago'],
                $row['total_venta']
            ];
        }
        break;

    case 'ventas_periodo':
        $titulo = 'Ventas por Período';
        $cabeceras = ['Folio', 'Fecha', 'Vendedor', 'Total Venta', 'Total Compra', 'Ganancia'];

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
                $row['total_venta'],
                $row['total_compra'],
                $row['ganancia']
            ];
        }
        break;

    case 'ventas_producto':
        $titulo = 'Ventas por Producto';
        $cabeceras = ['Folio', 'Fecha', 'Vendedor', 'Cantidad', 'Precio Unit.', 'Subtotal'];

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
                $row['precio_venta'],
                $row['subtotal']
            ];
        }
        break;

    case 'productos_mas_vendidos':
        $titulo = 'Productos Más Vendidos';
        $cabeceras = ['Producto', 'Código', 'Marca', 'Categoría', 'Total Vendido', 'Ingresos', 'Precio Prom.'];

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
                $row['nombre_producto'],
                $row['codigo_barra'],
                $row['marca'] ?? 'N/A',
                $row['categoria'] ?? 'N/A',
                $row['total_vendido'],
                $row['ingresos_totales'],
                $row['precio_promedio']
            ];
        }
        break;

    case 'ventas_usuario':
        $titulo = 'Ventas por Usuario';
        $cabeceras = ['Folio', 'Fecha', 'Vendedor', 'Método', 'Venta'];

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
                $row['total_venta']
            ];
        }

        // 🔥 VALIDAR QUE HAYA DATOS
        if (empty($datos)) {
            $datos = [['⚠️ No hay ventas en este período', '-', '-', '-', '-']];
        }
        break;


    case 'inventario':
        $titulo = 'Inventario Actual';
        $cabeceras = ['Código Barra', 'Producto', 'Marca', 'Categoría', 'Cantidad', 'Precio Venta', 'Total Venta', 'Estado'];

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
                $row['nombre_producto'],
                $row['marca'] ?? 'N/A',
                $row['categoria'] ?? 'N/A',
                $row['cantidad'],
                $row['precio_venta'],
                $row['total_venta'],
                $row['estado']
            ];
        }
        break;

    case 'stock_bajo':
        $titulo = 'Stock Bajo';
        $cabeceras = ['Código', 'Producto', 'Cantidad Actual', 'Stock Mínimo', 'Faltante'];
        $sql = "SELECT codigo_barra, nombre_producto, cantidad, stock_minimo
                FROM productos
                WHERE cantidad <= stock_minimo
                ORDER BY cantidad ASC";
        $resultado = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($resultado)) {
            $datos[] = [
                $row['codigo_barra'],
                $row['nombre_producto'],
                $row['cantidad'],
                $row['stock_minimo'],
                $row['stock_minimo'] - $row['cantidad']
            ];
        }
        break;

    case 'stock_cero':
        $titulo = 'Stock Cero';
        $cabeceras = ['Código Barra', 'Producto', 'Marca', 'Categoría', 'Stock Mínimo', 'Precio Venta', 'Estado'];

        $sql = "SELECT codigo_barra, nombre_producto, marca, categoria, stock_minimo, precio_venta, estado
                FROM productos
                WHERE cantidad = 0
                ORDER BY nombre_producto ASC";
        $resultado = mysqli_query($conn, $sql);

        while ($row = mysqli_fetch_assoc($resultado)) {
            $datos[] = [
                $row['codigo_barra'],
                $row['nombre_producto'],
                $row['marca'] ?? 'N/A',
                $row['categoria'] ?? 'N/A',
                $row['stock_minimo'],
                $row['precio_venta'],
                'Sin Stock'
            ];
        }
        break;

    case 'movimientos':
        $titulo = 'Movimientos de Inventario';
        $cabeceras = ['Fecha', 'Tipo', 'Código', 'Producto', 'Cantidad', 'Precio Unit.', 'Total', 'Folio Ref.', 'Usuario'];

        // 🔥 ENTRADAS (Compras)
        $whereCompras = ["DATE(c.fecha_compra) BETWEEN '$fecha_inicio' AND '$fecha_fin'"];
        if (!empty($producto)) {
            $whereCompras[] = "c.codigo_barra = '" . mysqli_real_escape_string($conn, $producto) . "'";
        }
        if ($tipo_movimiento !== 'todos' && $tipo_movimiento === 'entrada') {
            // Solo entradas
        } elseif ($tipo_movimiento !== 'todos' && $tipo_movimiento === 'salida') {
            // Solo salidas - no agregar compras
            $whereCompras[] = "1 = 0";
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

        // 🔥 SALIDAS (Ventas)
        $whereVentas = ["DATE(v.fecha_venta) BETWEEN '$fecha_inicio' AND '$fecha_fin'", "v.estado = 'completada'"];
        if (!empty($producto)) {
            $whereVentas[] = "dv.codigo_barra = '" . mysqli_real_escape_string($conn, $producto) . "'";
        }
        if ($tipo_movimiento !== 'todos' && $tipo_movimiento === 'salida') {
            // Solo salidas
        } elseif ($tipo_movimiento !== 'todos' && $tipo_movimiento === 'entrada') {
            // Solo entradas - no agregar ventas
            $whereVentas[] = "1 = 0";
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

        // 🔥 UNIR AMBOS RESULTADOS
        $movimientos = [];
        $resultCompras = mysqli_query($conn, $sqlCompras);
        while ($row = mysqli_fetch_assoc($resultCompras)) {
            $movimientos[] = $row;
        }
        $resultVentas = mysqli_query($conn, $sqlVentas);
        while ($row = mysqli_fetch_assoc($resultVentas)) {
            $movimientos[] = $row;
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
                $mov['nombre_producto'] ?? 'N/A',
                $mov['cantidad'],
                $mov['precio_compra'],
                $mov['total'],
                $mov['folio_referencia'] ?? 'N/A',
                $mov['usuario'] ?? 'N/A'
            ];
        }
        break;


    case 'ganancias':
        $titulo = 'Ganancias por Período';
        $cabeceras = ['Fecha', 'Total Ventas', 'Venta Total', 'Compra Total', 'Ganancia', '% Ganancia'];
        $sql = "SELECT DATE(fecha_venta) as fecha, COUNT(*) as ventas, SUM(total_venta) as venta_total,
                SUM(total_compra) as compra_total, SUM(ganancia) as ganancia
                FROM ventas
                WHERE DATE(fecha_venta) BETWEEN '$fecha_inicio' AND '$fecha_fin'
                AND estado = 'completada'
                GROUP BY DATE(fecha_venta)
                ORDER BY fecha ASC";
        $resultado = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($resultado)) {
            $porcentaje = $row['venta_total'] > 0 ? (($row['ganancia'] / $row['venta_total']) * 100) : 0;
            $datos[] = [
                date('d/m/Y', strtotime($row['fecha'])),
                $row['ventas'],
                $row['venta_total'],
                $row['compra_total'],
                $row['ganancia'],
                number_format($porcentaje, 2) . '%'
            ];
        }
        break;

    case 'corte_caja':
        $titulo = 'Corte de Caja';
        $cabeceras = ['Folio', 'Fecha', 'Hora', 'Vendedor', 'Método', 'Venta', 'Compra', 'Ganancia'];

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
                date('d/m/Y', strtotime($row['fecha_venta'])),
                date('H:i', strtotime($row['fecha_venta'])),
                $row['vendedor'] ?? 'N/A',
                $row['metodo_pago'],
                $row['total_venta'],
                $row['total_compra'],
                $row['ganancia']
            ];
        }
        break;

    case 'compras':
        $titulo = 'Compras a Proveedores';
        $cabeceras = ['Fecha', 'Código', 'Producto', 'Proveedor', 'Cantidad', 'Precio Compra', 'Total'];
        $sql = "SELECT c.fecha_compra, c.codigo_barra, p.nombre_producto, pr.nombre as proveedor, c.cantidad, c.precio_compra, c.total
                FROM compras c
                LEFT JOIN productos p ON c.codigo_barra = p.codigo_barra
                LEFT JOIN proveedores pr ON c.proveedor_id = pr.id
                WHERE DATE(c.fecha_compra) BETWEEN '$fecha_inicio' AND '$fecha_fin'
                ORDER BY c.fecha_compra DESC";
        $resultado = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($resultado)) {
            $datos[] = [
                date('d/m/Y', strtotime($row['fecha_compra'])),
                $row['codigo_barra'],
                $row['nombre_producto'] ?? 'N/A',
                $row['proveedor'] ?? 'N/A',
                $row['cantidad'],
                $row['precio_compra'],
                $row['total']
            ];
        }
        break;


    case 'compras_proveedor':
        $titulo = 'Compras por Proveedor';
        $cabeceras = ['Proveedor', 'Total Compras', 'Cantidad Total', 'Monto Total', 'Primera Compra', 'Última Compra'];

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
                $row['cantidad_total'],
                $row['monto_total'],
                date('d/m/Y', strtotime($row['primera_compra'])),
                date('d/m/Y', strtotime($row['ultima_compra']))
            ];
        }
        break;

    default:
        $titulo = 'Reporte General';
        $cabeceras = ['Dato 1', 'Dato 2', 'Dato 3'];
        $datos = [['Sin datos', '-', '-']];
}

// 🔥 CREAR ARCHIVO EXCEL (HTML TABLE FORMAT)
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$nombre_archivo\"");
header("Pragma: no-cache");
header("Expires: 0");

// 🔥 ESTILOS PARA EXCEL
echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
echo '<style>';
echo 'table { border-collapse: collapse; width: 100%; }';
echo 'th { background-color: #333; color: white; font-weight: bold; padding: 10px; border: 1px solid #000; }';
echo 'td { padding: 8px; border: 1px solid #000; }';
echo 'tr:nth-child(even) { background-color: #f8f9fa; }';
echo 'tr:hover { background-color: #e9ecef; }';
echo '.titulo { font-size: 18px; font-weight: bold; text-align: center; }';
echo '.info { font-size: 12px; text-align: center; margin-bottom: 20px; }';
echo '.totales { font-weight: bold; background-color: #d4edda; }';
echo '</style>';
echo '</head>';
echo '<body>';

// 🔥 TÍTULO
echo '<h1 class="titulo">Los Nietos® - ' . $titulo . '</h1>';
echo '<p class="info">Fecha de emisión: ' . date('d/m/Y H:i') . '</p>';
echo '<p class="info">Período: ' . date('d/m/Y', strtotime($fecha_inicio)) . ' al ' . date('d/m/Y', strtotime($fecha_fin)) . '</p>';
echo '<br>';

// 🔥 TABLA DE DATOS
echo '<table>';
echo '<thead><tr>';
foreach ($cabeceras as $cabecera) {
    echo '<th>' . $cabecera . '</th>';
}
echo '</tr></thead>';
echo '<tbody>';

foreach ($datos as $fila) {
    echo '<tr>';
    foreach ($fila as $celda) {
        // 🔥 FORMATO DE MONEDA PARA VALORES NUMÉRICOS
        if (is_numeric($celda) && $celda > 100) {
            echo '<td style="text-align: right;">$' . number_format($celda, 2) . '</td>';
        } else {
            echo '<td>' . $celda . '</td>';
        }
    }
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';

// 🔥 TOTALES
if (!empty($datos) && in_array($tipo_reporte, ['ventas_periodo', 'ganancias', 'compras', 'inventario'])) {
    echo '<br><br>';
    echo '<table>';
    echo '<tr class="totales">';
    echo '<td><strong>Total de Registros:</strong></td>';
    echo '<td>' . count($datos) . '</td>';
    echo '</tr>';

    // 🔥 CALCULAR TOTALES GENERALES (si aplica)
    if (in_array($tipo_reporte, ['ventas_periodo', 'ganancias'])) {
        $venta_total = array_sum(array_column($datos, 3));
        $compra_total = array_sum(array_column($datos, 4));
        $ganancia_total = array_sum(array_column($datos, 5));

        echo '<tr class="totales">';
        echo '<td><strong>Venta Total:</strong></td>';
        echo '<td style="text-align: right;">$' . number_format($venta_total, 2) . '</td>';
        echo '</tr>';

        echo '<tr class="totales">';
        echo '<td><strong>Compra Total:</strong></td>';
        echo '<td style="text-align: right;">$' . number_format($compra_total, 2) . '</td>';
        echo '</tr>';

        echo '<tr class="totales">';
        echo '<td><strong>Ganancia Total:</strong></td>';
        echo '<td style="text-align: right;">$' . number_format($ganancia_total, 2) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}

echo '</body>';
echo '</html>';
?>