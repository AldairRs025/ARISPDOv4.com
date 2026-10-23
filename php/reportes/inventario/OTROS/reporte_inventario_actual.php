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
        .modal-etiquetas {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }

        .modal-etiquetas-content {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .paso-container {
            display: none;
        }

        .paso-container.active {
            display: block;
            animation: fadeIn 0.3s;
        }

        .opcion-card {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .opcion-card:hover {
            border-color: #0d6efd;
            background: #f0f4ff;
        }

        .opcion-card.selected {
            border-color: #0d6efd;
            background: #e7f3ff;
        }

        .preview-etiqueta {
            border: 1px solid #ccc;
            padding: 20px;
            width: 250px;
            height: 100px;
            margin: 20px auto;
            background: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            font-family: sans-serif;
            font-size: 12px;
            text-align: center;
        }

        .lista-productos-etiquetas {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #eee;
            padding: 10px;
        }

        .item-etiqueta {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            border-bottom: 1px solid #eee;
            align-items: center;
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
                <h3 style="margin:0;">🏷️ Configuración de Etiquetas</h3>
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
                        style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
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
                        <label><input type="checkbox" class="chk-preview" value="nombre" checked> Nombre del
                            Producto</label><br>
                        <label><input type="checkbox" class="chk-preview" value="categoria" checked>
                            Categoría</label><br>
                        <label><input type="checkbox" class="chk-preview" value="precio" checked> Precio
                            Público</label><br>
                        <label><input type="checkbox" class="chk-preview" value="barcode" checked> Código de
                            Barras</label><br>
                        <label><input type="checkbox" class="chk-preview" value="texto_codigo" checked> Código de barras
                            (Números)</label>
                    </div>
                    <div style="flex:1; text-align:center;">
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
            document.getElementById('modalEtiquetas').style.display = 'flex';
            irPaso(1);
            // Reseteo visual
            document.querySelectorAll('.opcion-card').forEach(c => c.classList.remove('selected'));
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
            let html = '';

            // Nombre del Producto
            if (opciones.includes('nombre')) {
                html += '<div style="font-weight:bold; margin-bottom:2px; font-size:14px;">Playera Negra</div>';
            }

            // Categoría
            if (opciones.includes('categoria')) {
                html += '<div style="color:#666; font-size:11px; margin-bottom:2px;">Vestimenta</div>';
            }

            // Precio
            if (opciones.includes('precio')) {
                html += '<div style="font-weight:bold; margin-top:2px; font-size:13px;">$145.50</div>';
            }

            // Código de Barras (SOLO BARRAS, SIN NÚMEROS)
            if (opciones.includes('barcode')) {
                html += '<div style="height:30px; background:#000; width:80%; margin:5px 0;"></div>';
            }

            // Código de barras (SOLO NÚMEROS, SIN BARRAS)
            if (opciones.includes('texto_codigo')) {
                html += '<div style="font-size:10px; margin-top:2px;">3333710074365</div>';
            }

            preview.innerHTML = html || '<div style="color:#999; font-size:12px;">Selecciona opciones para ver previsualización</div>';
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