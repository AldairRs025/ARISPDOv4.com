<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../../index.html");
    exit;
}
include("../../conexion.php");

// 🔥 FILTROS
$estado = $_GET['estado'] ?? 'todos';
$marca = $_GET['marca'] ?? '';
$categoria = $_GET['categoria'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';

// 🔥 CONSULTA PRINCIPAL
$whereClauses = [];

// ✅ CORREGIDO: Solo filtrar estado si NO es "todos"
if ($estado === 'activo') {
    $whereClauses[] = "p.estado = 'activo'";
} elseif ($estado === 'inactivo') {
    $whereClauses[] = "p.estado = 'inactivo'";
}
// Si es "todos", no agrega ningún filtro de estado

if (!empty($marca)) {
    $whereClauses[] = "p.marca = '" . mysqli_real_escape_string($conn, $marca) . "'";
}
if (!empty($categoria)) {
    $whereClauses[] = "p.categoria = '" . mysqli_real_escape_string($conn, $categoria) . "'";
}
if (!empty($busqueda)) {
    $whereClauses[] = "(p.nombre_producto LIKE '%" . mysqli_real_escape_string($conn, $busqueda) . "%'
OR p.codigo_barra LIKE '%" . mysqli_real_escape_string($conn, $busqueda) . "%')";
}

$where = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$sql = "
SELECT p.*, 
       COALESCE(MAX(l.precio_compra), 0) AS max_precio_compra,
       (p.cantidad * COALESCE(MAX(l.precio_compra), 0)) AS total_compra_calculado
FROM productos p
LEFT JOIN lotes l ON p.codigo_barra = l.codigo_barra
$where
GROUP BY p.codigo_barra
ORDER BY p.nombre_producto ASC
";
$resultado = mysqli_query($conn, $sql);

// 🔥 OBTENER DATOS PARA FILTROS
$marcas = mysqli_query($conn, "SELECT DISTINCT marca FROM productos WHERE marca IS NOT NULL AND marca != '' ORDER BY marca");
$categorias = mysqli_query($conn, "SELECT DISTINCT categoria FROM productos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria");

// 🔥 CONTADORES
$total_productos = 0;
$total_unidades = 0;
$total_inversion = 0;
$total_venta = 0;
$stock_bajo = 0;
$stock_cero = 0;

mysqli_data_seek($resultado, 0);
while ($row = mysqli_fetch_assoc($resultado)) {
    $total_productos++;
    $total_unidades += $row['cantidad'];
    $total_inversion += $row['total_compra_calculado'];
    $total_venta += $row['total_venta'];
    if ($row['cantidad'] <= $row['stock_minimo'])
        $stock_bajo++;
    if ($row['cantidad'] == 0)
        $stock_cero++;
}
mysqli_data_seek($resultado, 0);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📦 Inventario Actual - Los Nietos®</title>
    <link rel="stylesheet" href="../../../css/Reportes-STYLE/reportes5.css">
    <link rel="icon" href="../../../img/LOGIN/icon_24px.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pacifico&family=Kalam:wght@300;400;700&display=swap');

        body {
            background: #f5f5f5;
            font-family: 'Kalam', cursive;
            margin: 0;
            padding: 0;
        }

        .barra-superior {
            width: 100%;
            height: 60px;
            background: #000;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            position: fixed;
            top: 0;
            z-index: 1000;
        }

        .logo {
            font-family: 'Pacifico', cursive;
            font-size: 32px;
            color: white;
        }

        .iconos {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .icon-btn {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 52px;
            height: 52px;
            background: white;
            border-radius: 50%;
            box-shadow: 0 0 6px rgba(0, 0, 0, 0.2);
            transition: 0.25s ease;
            text-decoration: none;
        }

        .icon-btn:hover {
            transform: scale(1.12);
            box-shadow: 0 0 12px rgba(255, 255, 255, 0.7);
        }

        .icon-btn i {
            font-size: 26px;
            color: #000;
        }

        .reporte-container {
            margin: 100px 5% 50px 5%;
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .reporte-header {
            text-align: center;
            border-bottom: 3px solid #0d6efd;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .reporte-header h1 {
            font-family: 'Pacifico', cursive;
            color: #333;
            font-size: 36px;
            margin: 0;
        }

        .filtros-reporte {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .filtros-reporte label {
            font-weight: bold;
            color: #333;
        }

        .filtros-reporte input[type="text"],
        .filtros-reporte select {
            padding: 10px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-family: 'Kalam', cursive;
            font-size: 14px;
        }

        .filtros-reporte button {
            padding: 10px 25px;
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-family: 'Kalam', cursive;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .filtros-reporte button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.4);
        }

        .tabla-reporte {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .tabla-reporte thead {
            background: linear-gradient(135deg, #333 0%, #555 100%);
            color: white;
        }

        .tabla-reporte th,
        .tabla-reporte td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .tabla-reporte tbody tr:hover {
            background: #f8f9fa;
        }

        .stock-ok {
            color: #28a745;
            font-weight: bold;
        }

        .stock-bajo {
            color: #dc3545;
            font-weight: bold;
        }

        .stock-cero {
            color: #dc3545;
            font-weight: bold;
            background: #ffe6e6;
        }

        .totales-reporte {
            background: linear-gradient(135deg, #e7f3ff 0%, #d0e8ff 100%);
            border: 2px solid #0d6efd;
            border-radius: 10px;
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .total-item {
            text-align: center;
        }

        .total-item h4 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
        }

        .total-item p {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
            color: #0d6efd;
        }

        .botones-accion {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-accion {
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: 0.2s;
        }

        .btn-pdf {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .btn-excel {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
        }

        .btn-imprimir {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
        }

        .btn-accion:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        .barra-inferior {
            width: 100%;
            height: 50px;
            background: #333;
            position: fixed;
            bottom: 0;
            left: 0;
        }

        @media print {

            .barra-superior,
            .filtros-reporte,
            .botones-accion,
            .barra-inferior {
                display: none !important;
            }

            .reporte-container {
                margin: 0;
                box-shadow: none;
            }
        }
    </style>
    <style>
        /* Estilos para el Modal de Etiquetas */
        /* Estilos para el Modal de Etiquetas - TEMA OSCURO PROFESIONAL */
        .modal-etiquetas {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            animation: fadeInModal 0.3s ease;
        }

        .modal-etiquetas-content {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-radius: 20px;
            padding: 35px;
            width: 90%;
            max-width: 850px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.6),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: slideUpModal 0.4s ease;
            color: #e2e8f0;
        }

        /* Scrollbar personalizado */
        .modal-etiquetas-content::-webkit-scrollbar {
            width: 10px;
        }

        .modal-etiquetas-content::-webkit-scrollbar-track {
            background: #0f172a;
            border-radius: 10px;
        }

        .modal-etiquetas-content::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            border-radius: 10px;
        }

        .modal-etiquetas-content::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #60a5fa 0%, #a78bfa 100%);
        }

        /* Header del modal */
        .modal-etiquetas-content h3 {
            color: #f8fafc;
            font-size: 26px;
            margin-bottom: 25px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .modal-etiquetas-content h3::before {
            content: '🏷️';
            font-size: 32px;
        }

        /* Botón cerrar */
        .modal-etiquetas-content button[onclick="cerrarModalEtiquetas()"] {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            font-size: 24px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .modal-etiquetas-content button[onclick="cerrarModalEtiquetas()"]:hover {
            transform: rotate(90deg) scale(1.1);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.5);
        }

        /* Pasos del modal */
        .paso-container {
            display: none;
            animation: fadeInPaso 0.4s ease;
        }

        .paso-container.active {
            display: block;
        }

        .paso-container h4 {
            color: #f8fafc;
            font-size: 20px;
            margin-bottom: 25px;
            font-weight: 600;
            padding-left: 15px;
            border-left: 4px solid #3b82f6;
        }

        /* Tarjetas de opción */
        .opcion-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.8) 0%, rgba(15, 23, 42, 0.9) 100%);
            border: 2px solid rgba(59, 130, 246, 0.3);
            border-radius: 14px;
            padding: 20px;
            margin: 12px 0;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 18px;
            color: #e2e8f0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .opcion-card:hover {
            border-color: #3b82f6;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(139, 92, 246, 0.15) 100%);
            transform: translateX(8px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
        }

        .opcion-card.selected {
            border-color: #3b82f6;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.25) 0%, rgba(139, 92, 246, 0.25) 100%);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3), 0 8px 25px rgba(59, 130, 246, 0.4);
        }

        .opcion-card strong {
            color: #f8fafc;
            font-size: 16px;
        }

        .opcion-card small {
            color: #94a3b8;
            display: block;
            margin-top: 5px;
        }

        /* Input de código individual */
        #inputCodigoIndividual input {
            background: #0f172a;
            border: 2px solid rgba(59, 130, 246, 0.4);
            color: #f8fafc;
            padding: 14px 18px;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        #inputCodigoIndividual input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
        }

        /* Grid de formatos */
        #paso2>div[style*="grid"] {
            gap: 18px;
        }

        /* ============================================
   🔥 CHECKBOXS PERSONALIZADOS - PASO 3
   ============================================ */
        .chk-preview {
            display: none;
        }

        .chk-preview+label {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 18px;
            background: linear-gradient(135deg, #979797 100%, #89898a 100%);
            /* background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); */
            border: 2px solid #dee2e6;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            color: #495057;
            margin: 5px;
            position: relative;
            overflow: hidden;
        }

        .chk-preview+label::before {
            content: '';
            width: 22px;
            height: 22px;
            border: 2px solid #adb5bd;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            background: white;
        }

        .chk-preview:checked+label::before {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            border-color: #0d6efd;
            content: '✓';
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
        }

        .chk-preview:checked+label {
            background: linear-gradient(135deg, #e7f3ff 0%, #d0e8ff 100%);
            border-color: #0d6efd;
            color: #000000;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.2);
            transform: translateY(-2px);
        }

        .chk-preview+label:hover {
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .chk-preview:checked+label:hover {
            background: linear-gradient(135deg, #d0e8ff 0%, #b8d4ff 100%);
            color: black;
        }

        /* Iconos para cada checkbox */
        .chk-preview[value="nombre"]+label::after {
            content: '📝';
            margin-left: 5px;
        }

        .chk-preview[value="categoria"]+label::after {
            content: '📂';
            margin-left: 5px;
        }

        .chk-preview[value="precio"]+label::after {
            content: '💵';
            margin-left: 5px;
        }

        .chk-preview[value="barcode"]+label::after {
            content: '📊';
            margin-left: 5px;
        }

        .chk-preview[value="texto_codigo"]+label::after {
            content: '🔢';
            margin-left: 5px;
        }

        .modal-etiquetas-content label {
            color: #e2e8f0;
            font-size: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
            transition: all 0.2s ease;
        }

        .modal-etiquetas-content label:hover {
            color: #f8fafc;
            transform: translateX(3px);
        }

        /* ============================================
   🔥 PREVISUALIZACIÓN MEJORADA
   ============================================ */
        .preview-etiqueta {
            border: 2px solid #0d6efd;
            padding: 20px;
            width: 280px;
            height: 140px;
            margin: 20px auto;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            text-align: center;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(13, 110, 253, 0.15);
            position: relative;
            overflow: hidden;
            color: black;
        }

        .preview-etiqueta::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(13, 110, 253, 0.03) 0%, rgba(11, 94, 215, 0.03) 100%);
            pointer-events: none;
        }

        .preview-etiqueta>div {
            position: relative;
            z-index: 1;
            width: 100%;
        }

        .preview-etiqueta .preview-nombre {
            font-weight: bold;
            font-size: 14px;
            color: #212529;
            margin-bottom: 3px;
        }

        .preview-etiqueta .preview-categoria {
            color: #6c757d;
            font-size: 12px;
            margin-bottom: 3px;
        }

        .preview-etiqueta .preview-precio {
            font-weight: bold;
            font-size: 14px;
            color: #0d6efd;
            margin: 5px 0;
        }

        .preview-etiqueta .preview-barcode {
            height: 35px;
            background: linear-gradient(90deg,
                    #000 0%, #000 10%,
                    transparent 10%, transparent 15%,
                    #000 15%, #000 20%,
                    transparent 20%, transparent 25%,
                    #000 25%, #000 35%,
                    transparent 35%, transparent 40%,
                    #000 40%, #000 50%,
                    transparent 50%, transparent 55%,
                    #000 55%, #000 60%,
                    transparent 60%, transparent 70%,
                    #000 70%, #000 80%,
                    transparent 80%, transparent 85%,
                    #000 85%, #000 90%,
                    transparent 90%, transparent 95%,
                    #000 95%, #000 100%);
            margin: 8px 0;
            border-radius: 3px;
        }

        .preview-etiqueta .preview-texto {
            font-size: 12px;
            color: #000000;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
        }

        /* Lista de productos */
        .lista-productos-etiquetas {
            max-height: 350px;
            overflow-y: auto;
            border: 2px solid rgba(59, 130, 246, 0.3);
            padding: 15px;
            border-radius: 12px;
            background: rgba(15, 23, 42, 0.5);
        }

        .lista-productos-etiquetas::-webkit-scrollbar {
            width: 8px;
        }

        .lista-productos-etiquetas::-webkit-scrollbar-track {
            background: #0f172a;
            border-radius: 8px;
        }

        .lista-productos-etiquetas::-webkit-scrollbar-thumb {
            background: #3b82f6;
            border-radius: 8px;
        }

        .item-etiqueta {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.9) 0%, rgba(15, 23, 42, 0.95) 100%);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .item-etiqueta:hover {
            border-color: #3b82f6;
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2);
        }

        .item-etiqueta strong {
            color: #f8fafc;
            font-size: 15px;
        }

        .item-etiqueta small {
            color: #94a3b8;
        }

        .item-etiqueta input[type="number"] {
            background: #0f172a;
            border: 2px solid rgba(59, 130, 246, 0.4);
            color: #f8fafc;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 14px;
            width: 80px;
            transition: all 0.3s ease;
        }

        .item-etiqueta input[type="number"]:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        /* Botones de acción */
        .btn-accion {
            padding: 14px 32px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .btn-accion:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }

        /* Nota de A Granel */
        div[style*="background:#fff3cd"] {
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.2) 0%, rgba(245, 158, 11, 0.2) 100%) !important;
            border: 2px solid rgba(251, 191, 36, 0.5);
            color: #fcd34d;
            padding: 15px 20px;
            border-radius: 12px;
            font-weight: 600;
        }

        /* Botón Igualar al Stock */
        button[onclick="igualarAlStock()"] {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        button[onclick="igualarAlStock()"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5);
        }

        /* Animaciones */
        @keyframes fadeInModal {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideUpModal {
            from {
                transform: translateY(50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes fadeInPaso {
            from {
                transform: translateX(20px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .modal-etiquetas-content {
                padding: 25px;
                width: 95%;
            }

            .modal-etiquetas-content h3 {
                font-size: 22px;
            }

            .preview-etiqueta {
                width: 100%;
                max-width: 280px;
            }
        }
    </style>
</head>

<body>
    <!-- BARRA SUPERIOR -->
    <div class="barra-superior">
        <div class="logo">Los Nietos&#174;</div>

        <div class="iconos">
            <a href="../../login/dashboard_admin.php" class="icon-btn">
                <i class="bi bi-house-fill"></i>
                <span class="tooltip">INICIO</span>
            </a>
            <a href="../../login/CRUD-Usuarios.php" class="icon-btn">
                <i class="bi bi-people-fill"></i>
                <span class="tooltip">USUARIOS</span>
            </a>
            <a href="../../proveedores/CRUD-Proveedores.php" class="icon-btn">
                <i class="bi bi-truck"></i>
                <span class="tooltip">PROVEEDORES</span>
            </a>
            <a href="../../productos/CRUD-Product.php" class="icon-btn">
                <i class="bi bi-box-seam"></i>
                <span class="tooltip">PRODUCTOS</span>
            </a>
            <a href="../../ventas/CRUD-Ventas.php" class="icon-btn">
                <i class="bi bi-cash-stack"></i>
                <span class="tooltip">VENTAS</span>
            </a>
            <a href="../../corte/corte_caja.php" class="icon-btn">
                <i class="bi bi-cash-coin"></i>
                <span class="tooltip">CAJA</span>
            </a>
            <a href="../logout.php" class="icon-btn">
                <i class="bi bi-box-arrow-right"></i>
                <span class="tooltip">SALIR</span>
            </a>
        </div>
    </div>

    <!-- CONTENIDO DEL REPORTE -->
    <div class="reporte-container">
        <!-- HEADER -->
        <div class="reporte-header">
            <h1>📦 Inventario Actual</h1>
            <p>Reporte completo de todos los productos en inventario</p>
        </div>

        <!-- FILTROS -->
        <form class="filtros-reporte" method="GET">
            <label>🔍 Buscar:</label>
            <input type="text" name="busqueda" placeholder="Nombre o código..."
                value="<?= htmlspecialchars($busqueda) ?>">

            <label>📦 Estado:</label>
            <select name="estado">
                <option value="todos" <?= $estado == 'todos' ? 'selected' : '' ?>>Todos</option>
                <option value="activo" <?= $estado == 'activo' ? 'selected' : '' ?>>Activos</option>
                <option value="inactivo" <?= $estado == 'inactivo' ? 'selected' : '' ?>>Inactivos</option>
            </select>

            <label>🏷️ Marca:</label>
            <select name="marca">
                <option value="">Todas</option>
                <?php while ($m = mysqli_fetch_assoc($marcas)): ?>
                    <option value="<?= htmlspecialchars($m['marca']) ?>" <?= $marca == $m['marca'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['marca']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>📂 Categoría:</label>
            <select name="categoria">
                <option value="">Todas</option>
                <?php while ($c = mysqli_fetch_assoc($categorias)): ?>
                    <option value="<?= htmlspecialchars($c['categoria']) ?>" <?= $categoria == $c['categoria'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['categoria']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <button type="submit">🔍 Filtrar</button>
            <a href="reporte_inventario_actual.php"
                style="padding: 10px 25px; background: #dc3545; color: white; border-radius: 8px; text-decoration: none; font-weight: bold;">🔄
                Limpiar</a>
        </form>

        <!-- TOTALES -->
        <div class="totales-reporte">
            <div class="total-item">
                <h4>📦 Total Productos</h4>
                <p>
                    <?= $total_productos ?>
                </p>
            </div>
            <div class="total-item">
                <h4>📊 Total Unidades</h4>
                <p>
                    <?= $total_unidades ?>
                </p>
            </div>
            <div class="total-item">
                <h4>💰 Inversión Total</h4>
                <p>$
                    <?= number_format($total_inversion, 2) ?>
                </p>
            </div>
            <div class="total-item">
                <h4>💵 Venta Total</h4>
                <p>$
                    <?= number_format($total_venta, 2) ?>
                </p>
            </div>
            <div class="total-item">
                <h4>⚠️ Stock Bajo</h4>
                <p style="color: #dc3545;">
                    <?= $stock_bajo ?>
                </p>
            </div>
            <div class="total-item">
                <h4>❌ Stock Cero</h4>
                <p style="color: #dc3545;">
                    <?= $stock_cero ?>
                </p>
            </div>
        </div>

        <!-- TABLA -->
        <table class="tabla-reporte">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Marca</th>
                    <th>Categoría</th>
                    <th>Stock</th>
                    <th>Stock Mín.</th>
                    <th>Precio Compra</th>
                    <th>Total Compra</th>
                    <th>Precio Venta</th>
                    <th>Total Venta</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($resultado)):
                    $clase_stock = '';
                    if ($row['cantidad'] == 0)
                        $clase_stock = 'stock-cero';
                    elseif ($row['cantidad'] <= $row['stock_minimo'])
                        $clase_stock = 'stock-bajo';
                    else
                        $clase_stock = 'stock-ok';
                    ?>
                    <tr>
                        <td><strong>
                                <?= $row['codigo_barra'] ?>
                            </strong></td>
                        <td>
                            <?= htmlspecialchars($row['nombre_producto']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['marca'] ?? 'N/A') ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['categoria'] ?? 'N/A') ?>
                        </td>
                        <td class="<?= $clase_stock ?>">
                            <?= $row['cantidad'] ?>
                        </td>
                        <td>
                            <?= $row['stock_minimo'] ?>
                        </td>
                        <td>$
                            <?= number_format($row['max_precio_compra'], 2) ?>
                        </td>
                        <td>$
                            <?= number_format($row['total_compra_calculado'], 2) ?>
                        </td>
                        <td>$
                            <?= number_format($row['precio_venta'], 2) ?>
                        </td>
                        <td>$
                            <?= number_format($row['total_venta'], 2) ?>
                        </td>
                        <td>
                            <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; 
                        background: <?= $row['estado'] == 'activo' ? '#d4edda' : '#f8d7da' ?>;
                        color: <?= $row['estado'] == 'activo' ? '#155724' : '#721c24' ?>;">
                                <?= $row['estado'] ?>
                            </span>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- BOTONES DE ACCIÓN -->
        <div class="botones-accion">
            <!-- 🔥 NUEVO BOTÓN CREAR ETIQUETAS -->
            <button type="button" onclick="abrirModalEtiquetas()" class="btn-accion">
                <i class="bi bi-upc-scan"></i> Crear Etiquetas
            </button>
            <a href="../exportar/generar_pdf.php?tipo=inventario" class="btn-accion btn-pdf">
                <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
            </a>
            <a href="../exportar/generar_excel.php?tipo=inventario" class="btn-accion btn-excel">
                <i class="bi bi-file-earmark-excel"></i> Descargar Excel
            </a>
            <a href="javascript:window.print()" class="btn-accion btn-imprimir">
                <i class="bi bi-printer"></i> Imprimir
            </a>
            <a href="../reporte.php" class="btn-accion" style="background: #6c757d; color: white;">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <!-- BARRA INFERIOR -->
    <div class="barra-inferior"></div>

    <!-- 🔥 MODAL DE ETIQUETAS -->
    <div id="modalEtiquetas" class="modal-etiquetas">
        <div class="modal-etiquetas-content">
            <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
                <h3 style="margin:0;">Configuración de Etiquetas</h3>
                <button onclick="cerrarModalEtiquetas()"
                    style="background:none; border:none; font-size:24px; cursor:pointer;">✕</button>
            </div>

            <!-- PASO 1: SELECCIÓN -->
            <div id="paso1" class="paso-container active">
                <h4>1. ¿Qué productos deseas etiquetar?</h4>
                <div class="opcion-card" onclick="seleccionarModo('todos', this)">
                    <input type="radio" name="modo" checked> 📦 <strong>Todos los productos activos</strong>
                </div>
                <div class="opcion-card" onclick="seleccionarModo('individual', this)">
                    <input type="radio" name="modo"> 🔍 <strong>Por Código de Barras</strong>
                </div>
                <div id="inputCodigoIndividual" style="display:none; margin-top:15px;">
                    <input type="text" id="codigoEtiqueta" placeholder="Escanea o escribe el código..."
                        style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;" autocomplete="off">
                </div>
                <button onclick="irPaso(2)" class="btn-accion"
                    style="background:#0d6efd; color:white; width:100%; margin-top:20px;">Continuar ➡️</button>
            </div>

            <!-- PASO 2: FORMATO -->
            <div id="paso2" class="paso-container">
                <h4>2. Selecciona el tamaño de etiqueta</h4>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="opcion-card" onclick="seleccionarFormato('grande', this)">
                        <div style="flex:1;">
                            <strong>Etiquetas Grandes</strong><br>
                            <small>Formato Carta: 10 filas x 3 columnas (30 por hoja)</small>
                        </div>
                        <input type="radio" name="formato" checked>
                    </div>
                    <div class="opcion-card" onclick="seleccionarFormato('chica', this)">
                        <div style="flex:1;">
                            <strong>Etiquetas Chicas</strong><br>
                            <small>Formato Carta: 10 filas x 4 columnas (40 por hoja)</small>
                        </div>
                        <input type="radio" name="formato">
                    </div>
                </div>
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button onclick="irPaso(1)" class="btn-accion" style="background:#6c757d; color:white; flex:1;">⬅️
                        Atrás</button>
                    <button onclick="irPaso(3)" class="btn-accion"
                        style="background:#0d6efd; color:white; flex:1;">Continuar ➡️</button>
                </div>
            </div>

            <!-- PASO 3: PERSONALIZACIÓN -->
            <div id="paso3" class="paso-container">
                <h4>3. Personaliza tu etiqueta</h4>
                <div style="display:flex; gap:20px;">
                    <div style="flex:1;">
                        <input type="checkbox" id="chk_nombre" class="chk-preview" value="nombre" checked>
                        <label for="chk_nombre">Nombre del Producto</label><br>

                        <input type="checkbox" id="chk_categoria" class="chk-preview" value="categoria" checked>
                        <label for="chk_categoria">Categoría</label><br>

                        <input type="checkbox" id="chk_precio" class="chk-preview" value="precio" checked>
                        <label for="chk_precio">Precio Público</label><br>

                        <input type="checkbox" id="chk_barcode" class="chk-preview" value="barcode" checked>
                        <label for="chk_barcode">Código de Barras</label><br>

                        <input type="checkbox" id="chk_texto_codigo" class="chk-preview" value="texto_codigo" checked>
                        <label for="chk_texto_codigo">Código de barras (Números)</label>
                    </div>
                    <div style="flex:1; text-align:center; margin-top:10%;">
                        <p>Previsualización:</p>
                        <div class="preview-etiqueta" id="previewEtiqueta">
                            <!-- Aquí se renderiza la vista previa con JS -->
                        </div>
                    </div>
                </div>
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button onclick="irPaso(2)" class="btn-accion" style="background:#6c757d; color:white; flex:1;">⬅️
                        Atrás</button>
                    <button onclick="irPaso(4)" class="btn-accion"
                        style="background:#0d6efd; color:white; flex:1;">Continuar ➡️</button>
                </div>
            </div>

            <!-- PASO 4: CANTIDADES -->
            <div id="paso4" class="paso-container">
                <h4>4. ¿Cuántas etiquetas por producto?</h4>
                <div style="margin-bottom:15px; background:#fff3cd; padding:10px; border-radius:5px; font-size:14px;">
                    ⚠️ Nota: Los productos de categoría <strong>"A Granel"</strong> siempre tendrán <strong>1
                        etiqueta</strong> automáticamente.
                </div>

                <button onclick="igualarAlStock()"
                    style="background:#28a745; color:white; border:none; padding:8px 15px; border-radius:5px; cursor:pointer; margin-bottom:10px;">
                    📊 Igualar cantidad al Stock disponible
                </button>

                <div class="lista-productos-etiquetas" id="listaProductosEtiquetas">
                    <!-- Se llena con JS -->
                </div>

                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button onclick="irPaso(3)" class="btn-accion" style="background:#6c757d; color:white; flex:1;">⬅️
                        Atrás</button>
                    <button onclick="generarPDF()" class="btn-accion"
                        style="background:#dc3545; color:white; flex:1;">🖨️ Generar PDF</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let datosEtiquetas = { modo: 'todos', codigo: '', formato: 'grande', opciones: [], productos: [] };

        function abrirModalEtiquetas() {
            // Mostrar el modal
            document.getElementById('modalEtiquetas').style.display = 'flex';

            // Volver al paso 1 por defecto
            irPaso(1);

            // Reseteo visual de las tarjetas
            document.querySelectorAll('.opcion-card').forEach(c => c.classList.remove('selected'));

            // 🔥 NUEVO: Cargar las opciones pre-marcadas por defecto
            const opciones = [];
            document.querySelectorAll('.chk-preview:checked').forEach(c => opciones.push(c.value));
            datosEtiquetas.opciones = opciones;

            // 🔥 NUEVO: Generar la previsualización inicial basada en lo que ya está checado
            actualizarPreview();
        }

        function cerrarModalEtiquetas() {
            document.getElementById('modalEtiquetas').style.display = 'none';
        }

        function irPaso(num) {
            document.querySelectorAll('.paso-container').forEach(p => p.classList.remove('active'));
            document.getElementById('paso' + num).classList.add('active');
            if (num === 4) cargarListaProductos();
        }

        function seleccionarModo(modo, el) {
            datosEtiquetas.modo = modo;
            document.querySelectorAll('#paso1 .opcion-card').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            el.querySelector('input').checked = true;

            document.getElementById('inputCodigoIndividual').style.display = (modo === 'individual') ? 'block' : 'none';
        }

        function seleccionarFormato(formato, el) {
            datosEtiquetas.formato = formato;
            document.querySelectorAll('#paso2 .opcion-card').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            el.querySelector('input').checked = true;
        }

        // Lógica de previsualización
        document.querySelectorAll('.chk-preview').forEach(chk => {
            chk.addEventListener('change', () => {
                const opciones = [];
                document.querySelectorAll('.chk-preview:checked').forEach(c => opciones.push(c.value));
                datosEtiquetas.opciones = opciones;
                actualizarPreview();
            });
        });

        function actualizarPreview() {
            const preview = document.getElementById('previewEtiqueta');
            const opciones = datosEtiquetas.opciones;

            let html = '<div style="width: 100%;">';

            // Nombre del Producto
            if (opciones.includes('nombre')) {
                html += '<div class="preview-nombre">Jugo Del Valle Frut de 2Lt</div>';
            }

            // Categoría
            if (opciones.includes('categoria')) {
                html += '<div class="preview-categoria">Jugos</div>';
            }

            // Precio
            if (opciones.includes('precio')) {
                html += '<div class="preview-precio">PRE. UNI: $27.0 &nbsp; &nbsp; PRE. VEN: $30.0</div>';
            }

            // Código de Barras
            if (opciones.includes('barcode')) {
                html += '<div class="preview-barcode"></div>';
            }

            // Texto del código
            if (opciones.includes('texto_codigo')) {
                html += '<div class="preview-texto">3333710074365</div>';
            }

            html += '</div>';

            // Si no hay nada seleccionado
            if (opciones.length === 0) {
                preview.innerHTML = '<div style="color:#999; font-size:12px; text-align:center;">Selecciona opciones para ver previsualización</div>';
            } else {
                preview.innerHTML = html;
            }
        }

        async function cargarListaProductos() {
            const listaDiv = document.getElementById('listaProductosEtiquetas');
            listaDiv.innerHTML = '<p style="text-align:center;">Cargando inventario...</p>';

            let url = 'obtener_productos_etiquetas.php?modo=' + datosEtiquetas.modo;
            if (datosEtiquetas.modo === 'individual') {
                url += '&codigo=' + document.getElementById('codigoEtiqueta').value;
            }

            try {
                const res = await fetch(url);
                const data = await res.json();

                datosEtiquetas.productos = data;

                if (data.length === 0) {
                    listaDiv.innerHTML = '<p style="text-align:center; color:red;">No se encontraron productos.</p>';
                    return;
                }

                let html = '';
                data.forEach((prod, index) => {
                    html += `
            <div class="item-etiqueta">
                <div>
                    <strong>${prod.nombre_producto}</strong><br>
                    <small>${prod.categoria} (Stock: ${prod.cantidad})</small>
                </div>
                <input type="number" id="qty_${index}" value="1" min="1" style="width:60px; padding:5px; border:1px solid #ddd;">
            </div>`;
                });
                listaDiv.innerHTML = html;
                actualizarPreview(); // Inicializar preview
            } catch (e) {
                console.error(e);
                listaDiv.innerHTML = '<p style="text-align:center; color:red;">Error cargando productos.</p>';
            }
        }

        function igualarAlStock() {
            datosEtiquetas.productos.forEach((prod, index) => {
                const input = document.getElementById('qty_' + index);
                if (input) {
                    // LÓGICA "A GRANEL"
                    if (prod.categoria === 'A Granel') {
                        input.value = 1;
                    } else {
                        input.value = Math.floor(prod.cantidad); // Entero
                    }
                }
            });
        }

        function generarPDF() {
            const productosFinal = [];
            datosEtiquetas.productos.forEach((prod, index) => {
                const qty = parseInt(document.getElementById('qty_' + index).value);
                if (qty > 0) {
                    for (let i = 0; i < qty; i++) {
                        productosFinal.push(prod);
                    }
                }
            });

            if (productosFinal.length === 0) {
                alert("⚠️ Debes generar al menos una etiqueta.");
                return;
            }

            const form = new FormData();
            form.append('formato', datosEtiquetas.formato);
            form.append('opciones', JSON.stringify(datosEtiquetas.opciones));
            form.append('productos', JSON.stringify(productosFinal));

            // 🔥 Mostrar loading
            const btnGenerar = document.querySelector('button[onclick="generarPDF()"]');
            const textoOriginal = btnGenerar.innerHTML;
            btnGenerar.disabled = true;
            btnGenerar.innerHTML = '⏳ Generando...';

            fetch('generar_etiquetas.php', {
                method: 'POST',
                body: form
            })
                .then(res => {
                    if (!res.ok) {
                        throw new Error('Error HTTP: ' + res.status);
                    }
                    return res.blob();
                })
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'etiquetas.pdf';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    cerrarModalEtiquetas();
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('❌ Error al generar PDF: ' + err.message);
                })
                .finally(() => {
                    btnGenerar.disabled = false;
                    btnGenerar.innerHTML = textoOriginal;
                });
        }
    </script>
</body>

</html>