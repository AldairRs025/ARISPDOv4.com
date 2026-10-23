<?php
include("../conexion.php");

// 🔥 FORZAR RECARGA DEL RESUMEN SI HUBO ACTUALIZACIÓN
if (isset($_GET['actualizado']) && $_GET['actualizado'] == '1') {
    // El resumen se recalculará automáticamente más abajo
}

// 💡 PROCESAMIENTO DE FILTROS (igual que antes)
// 💡 PROCESAMIENTO DE FILTROS
$whereClauses = [];
$joinLotes = false;

// Filtro de stock mínimo
if (isset($_GET['f_stock_minimo']) && $_GET['f_stock_minimo'] == '1') {
    $whereClauses[] = "p.cantidad <= p.stock_minimo";
}
// Cambiamos 'stock_cero' por 'f_stock_cero'
if (isset($_GET['f_stock_cero']) && $_GET['f_stock_cero'] == '1') {
    $whereClauses[] = "p.cantidad = 0";
}

// Filtro de estado
if (!empty($_GET['estado']) && in_array($_GET['estado'], ['activo', 'inactivo'])) {
    $escaped_estado = mysqli_real_escape_string($conn, $_GET['estado']);
    $whereClauses[] = "p.estado = '$escaped_estado'";
}

// 🔥 NUEVO FILTRO: Marca
if (!empty($_GET['marca'])) {
    $escaped_marca = mysqli_real_escape_string($conn, $_GET['marca']);
    $whereClauses[] = "p.marca = '$escaped_marca'";
}

// 🔥 NUEVO FILTRO: Categoría
if (!empty($_GET['categoria'])) {
    $escaped_categoria = mysqli_real_escape_string($conn, $_GET['categoria']);
    $whereClauses[] = "p.categoria = '$escaped_categoria'";
}

// Construir FROM
$from = "productos p";
if ($joinLotes) {
    $from .= " INNER JOIN lotes l ON p.codigo_barra = l.codigo_barra";
}

// Construir WHERE
$where = '';
if (!empty($whereClauses)) {
    $where = 'WHERE ' . implode(' AND ', $whereClauses);
}

// 🔥 CONSULTA FINAL - CON TOTAL COMPRA CALCULADO (MAX precio_compra de lotes)
$sqlProductos = "SELECT p.*, 
                        COALESCE(MAX(l.precio_compra), 0) AS max_precio_compra,
                        (p.cantidad * COALESCE(MAX(l.precio_compra), 0)) AS total_compra_calculado
                 FROM productos p 
                 LEFT JOIN lotes l ON p.codigo_barra = l.codigo_barra 
                 $where 
                 GROUP BY p.codigo_barra 
                 ORDER BY p.fecha_ingreso DESC";
$resultadoProductos = mysqli_query($conn, $sqlProductos);

// RESUMEN DE INVENTARIO
$resumenSQL = "
SELECT
    COUNT(*) AS total_productos,
    SUM(p.cantidad) AS total_unidades,
    SUM(p.cantidad * COALESCE(l.max_precio_compra, 0)) AS inversion_total,
    SUM(p.total_venta) AS venta_total,
    SUM(p.total_venta - (p.cantidad * COALESCE(l.max_precio_compra, 0))) AS ganancia_total,
    SUM(CASE WHEN p.cantidad <= p.stock_minimo THEN 1 ELSE 0 END) AS stock_bajo
    FROM productos p
    LEFT JOIN (
        SELECT codigo_barra, MAX(precio_compra) AS max_precio_compra
        FROM lotes
        GROUP BY codigo_barra
    ) l ON p.codigo_barra = l.codigo_barra
    ";
$resumenQuery = mysqli_query($conn, $resumenSQL);
$resumen = mysqli_fetch_assoc($resumenQuery);  // ✅ Solo una fila (sin while)
if (!$resumen) {
    $resumen = ['total_productos' => 0, 'total_unidades' => 0, 'inversion_total' => 0, 'venta_total' => 0, 'ganancia_total' => 0, 'stock_bajo' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>• Productos</title>

    <!-- CSS PRINCIPAL -->
    <link rel="stylesheet" href="../../css/Productos-STYLE/tt.css">
    <link rel="icon" href="../../img/LOGIN/icon_24px.png" type="img/INICIO/icon_24px.png" />

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body>
    <!-- BARRA SUPERIOR -->
    <div class="barra-superior">
        <div class="logo">Los Nietos&#174;</div>

        <div class="iconos">
            <a href="../login/dashboard_admin.php" class="icon-btn">
                <i class="bi bi-house-fill"></i>
                <span class="tooltip">INICIO</span>
            </a>
            <a href="../login/CRUD-Usuarios.php" class="icon-btn">
                <i class="bi bi-people-fill"></i>
                <span class="tooltip">USUARIOS</span>
            </a>
            <a href="../proveedores/CRUD-Proveedores.php" class="icon-btn">
                <i class="bi bi-truck"></i>
                <span class="tooltip">PROVEEDORES</span>
            </a>
            <a href="../productos/CRUD-Product.php" class="icon-btn">
                <i class="bi bi-box-seam"></i>
                <span class="tooltip">PRODUCTOS</span>
            </a>
            <a href="../ventas/CRUD-Ventas.php" class="icon-btn">
                <i class="bi bi-cash-stack"></i>
                <span class="tooltip">VENTAS</span>
            </a>
            <a href="../corte/corte_caja.php" class="icon-btn">
                <i class="bi bi-cash-coin"></i>
                <span class="tooltip">CAJA</span>
            </a>
            <a href="logout.php" class="icon-btn">
                <i class="bi bi-box-arrow-right"></i>
                <span class="tooltip">SALIR</span>
            </a>
        </div>
    </div>

    <div class="resumen-inventario">
        <div class="card-resumen">
            <h4>Productos</h4>
            <p id="total_productos"><?= $resumen['total_productos']; ?></p>
        </div>
        <div class="card-resumen">
            <h4>Unidades</h4>
            <p id="total_unidades"><?= $resumen['total_unidades']; ?></p>
        </div>
        <div class="card-resumen">
            <h4>Inversión</h4>
            <p id="inversion_total">$<?= number_format($resumen['inversion_total'], 2); ?></p>
        </div>
        <div class="card-resumen">
            <h4>Venta</h4>
            <p id="venta_total">$<?= number_format($resumen['venta_total'], 2); ?></p>
        </div>
        <div class="card-resumen">
            <h4>Ganancia</h4>
            <p id="ganancia_total">$<?= number_format($resumen['ganancia_total'], 2); ?></p>
        </div>
        <div class="card-resumen alerta">
            <h4>⚠ Stock Bajo</h4>
            <p id="stock_bajo"><?= $resumen['stock_bajo']; ?></p>
        </div>
    </div><br><br>

    <form class="form-productos" action="procesar_producto.php" method="POST" enctype="multipart/form-data"
        autocomplete="off" required>

        <div class="form-grid">
            <input type="hidden" name="modo" id="modo" value="crear">
            <!-- 🔥 NUEVO CAMPO OCULTO PARA CÓDIGO ORIGINAL -->
            <input type="hidden" name="codigo_original" id="codigo_original" value="">

            <div class="form-group col-1">
                <label>Código</label>
                <input type="text" name="codigo_barra" required autocomplete="off" pattern="[0-9]*"
                    title="Solo se permiten números" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
            </div>

            <div class="form-group col-2">
                <label>Producto</label>
                <input type="text" name="nombre_producto" required>
            </div>

            <div class="form-group col-1">
                <label>Marca</label>
                <input type="text" name="marca">
            </div>

            <div class="form-group col-1">
                <label>Categoría</label>
                <input type="text" name="categoria">
                
            </div>

            <div class="form-group col-1">
                <label>Precio de Venta</label>
                <input type="number" step="0.01" min="0.01" name="precio_venta">
            </div>

            <div class="form-group col-1">
                <label>Estado</label>
                <select name="estado">
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                </select>
            </div>

            <div class="form-group col-1">
                <label>Stock Mín.</label>
                <input type="number" name="stock_minimo" value="3">
            </div>

            <!-- input invicible -->
            <div class="form-group col-1">
                <input type="hidden" readonly>
            </div>
            <!-- input invicible -->

            <!-- FILA DE IMAGEN (Izquierda) -->
            <div class="form-group col-2">
                <label>Imagen</label>
                <div class="preview-container">
                    <img id="previewImagen" src="" alt="Preview">
                </div>
                <input type="file" name="imagen" id="imagenInput" accept="image/*">
            </div>

            <!-- FILA DE BOTONES (Derecha - Alineados con el input de archivo) -->
            <div class="form-group col-1"
                style="display: flex; flex-direction: column; justify-content: flex-end; gap: 10px; margin-bottom: 160px;">
                <!-- Label invisible para alineación visual -->
                <label style="visibility: hidden;">Acciones</label>

                <!-- Botón Guardar / Actualizar -->
                <button type="submit" class="btn-guardar" id="btnGuardar" style="width: 100%;">Guardar</button>

                <!-- Botón Cancelar (Oculto por defecto) -->
                <a href="CRUD-Product.php" id="btnCancelar" style="display:none; background:#dc3545; color:white; padding:0 20px; 
              border-radius:6px; text-decoration:none; font-weight:bold; 
              height: 40px; font-size: 16px; line-height: 40px; 
              text-align: center; transition: 0.2s; width: 100%; box-sizing: border-box;">
                    ❌ Cancelar
                </a>
            </div>

            <!-- inputs invicibles -->
            <div class="form-group col-1">
                <!-- <label>Cantidad</label> -->
                <input type="hidden" name="cantidad" readonly value="0" style="background:#ffbcbc;">
            </div>

            <div class="form-group col-1">
                <!-- <label>Precio de Compra</label> -->
                <input type="hidden" step="0.01" name="precio_compra" readonly value="0" style="background:#ffbcbc;">
            </div>

            <div class="form-group col-1">
                <!-- <label>Proveedor</label> -->
                <input type="hidden" name="proveedor" readonly value="" style="background:#ffbcbc;">
            </div>
            <!-- inputs invicibles -->

        </div>
    </form>

    <h3 style="margin-top:150px; position: fixed;">📦 Productos Registrados</h3>
    <div class="buscador-productos" style="margin-top:190px;">
        <input type="text" id="buscador" placeholder="🔍 Buscar producto..." autocomplete="off">
    </div>
    <div class="contenedor-tabla">
        <!-- 🔴 ÁREA ROJA - AQUÍ VA EL FILTRO -->
        <div class="filter-container"
            style="margin-top:150px; margin-left: 25%; padding: 5px; background: #f8f9fa; border-radius: 5px;">
            <form method="GET" action="CRUD-Product.php" id="filterForm" autocomplete="off">

                <!-- Contenedor Principal: Separa Izquierda (Filtros) y Derecha (Botones) -->
                <div class="filter-options"
                    style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center; justify-content: space-between;">

                    <!-- BLOQUE IZQUIERDO: Todos los Filtros juntos -->
                    <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">

                        <!-- Filtro de stock mínimo -->
                        <label style="display: flex; align-items: center; gap: 5px;">
                            <input type="checkbox" name="f_stock_minimo" class="filtro-stock" value="1"
                                onclick="manejarExclusividad(this)" <?php if (isset($_GET['f_stock_minimo']) && $_GET['f_stock_minimo'] == '1')
                                    echo 'checked'; ?>>
                            <span>Stock mínimo</span>
                        </label>

                        <!-- Filtro de stock 0 -->
                        <label style="display: flex; align-items: center; gap: 5px;">
                            <input type="checkbox" name="f_stock_cero" class="filtro-stock" value="1"
                                onclick="manejarExclusividad(this)" <?php if (isset($_GET['f_stock_cero']) && $_GET['f_stock_cero'] == '1')
                                    echo 'checked'; ?>>
                            <span>Stock 0</span>
                        </label>

                        <!-- Filtro de estado -->
                        <label style="display: flex; align-items: center; gap: 5px;">
                            <span>Estado:</span>
                            <select name="estado" style="padding: 5px; border-radius: 4px; border: 1px solid #ddd;">
                                <option value="">Todos</option>
                                <option value="activo" <?php if (isset($_GET['estado']) && $_GET['estado'] == 'activo')
                                    echo 'selected'; ?>>Activos</option>
                                <option value="inactivo" <?php if (isset($_GET['estado']) && $_GET['estado'] == 'inactivo')
                                    echo 'selected'; ?>>Inactivos</option>
                            </select>
                        </label>

                        <!-- Filtro de Marca -->
                        <label style="display: flex; align-items: center; gap: 5px;">
                            <span>Marca:</span>
                            <select name="marca" style="padding: 5px; border-radius: 4px; border: 1px solid #ddd;">
                                <option value="">Todas</option>
                                <?php
                                $marcas = mysqli_query($conn, "SELECT DISTINCT marca FROM productos WHERE marca IS NOT NULL AND marca != '' ORDER BY marca");
                                while ($marca_row = mysqli_fetch_assoc($marcas)) {
                                    $marca_nombre = htmlspecialchars($marca_row['marca']);
                                    $selected = (isset($_GET['marca']) && $_GET['marca'] == $marca_row['marca']) ? 'selected' : '';
                                    echo "<option value='$marca_nombre' $selected>$marca_nombre</option>";
                                }
                                ?>
                            </select>
                        </label>

                        <!-- Filtro de Categoría -->
                        <label style="display: flex; align-items: center; gap: 5px;">
                            <span>Categoría:</span>
                            <select name="categoria" style="padding: 5px; border-radius: 4px; border: 1px solid #ddd;">
                                <option value="">Todas</option>
                                <?php
                                $categorias = mysqli_query($conn, "SELECT DISTINCT categoria FROM productos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria");
                                while ($cat_row = mysqli_fetch_assoc($categorias)) {
                                    $cat_nombre = htmlspecialchars($cat_row['categoria']);
                                    $selected = (isset($_GET['categoria']) && $_GET['categoria'] == $cat_row['categoria']) ? 'selected' : '';
                                    echo "<option value='$cat_nombre' $selected>$cat_nombre</option>";
                                }
                                ?>
                            </select>
                        </label>
                    </div> <!-- Fin Bloque Izquierdo -->

                    <!-- BLOQUE DERECHO: Botones -->
                    <div style="display: flex; gap: 10px; flex-shrink: 0;">
                        <button type="submit" class="btn-filter"
                            style="background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">
                            Filtrar
                        </button>

                        <button type="button" onclick="location.href='CRUD-Product.php'" class="btn-clear"
                            style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">
                            Limpiar
                        </button>
                    </div> <!-- Fin Bloque Derecho -->
                </div>
            </form>
        </div>

        <div class="tabla-container" style="margin-top:10px;">
            <div class="tabla-productos" style="margin-top:0px; border-top:0px; padding-top:0px;">
                <table style="width:100%; border-collapse:collapse; margin-top:10px;">
                    <thead>
                        <tr style="background:#333; color:#fff;">
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Marca</th>
                            <th>Categoría</th>
                            <!-- <th>Precio Compra</th> -->
                            <th>Total Compra</th>
                            <th>Precio Venta</th>
                            <th>Total Venta</th>
                            <th>Estado</th>
                            <th>Imagen</th>
                            <!-- <th>Fecha</th> -->
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($resultadoProductos) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($resultadoProductos)): ?>
                                <tr data-codigo="<?= $row['codigo_barra']; ?>" data-stock="<?= $row['stock_minimo']; ?>">
                                    <td>
                                        <?= $row['codigo_barra']; ?>
                                    </td>
                                    <td>
                                        <?= $row['nombre_producto']; ?>
                                    </td>

                                    <?php
                                    $cantidadTotal = $row['cantidad']; // ✅ Usamos el campo real de la tabla
                                    $stockBajo = $cantidadTotal <= $row['stock_minimo'];
                                    ?>
                                    <td class="<?= $stockBajo ? 'stock-bajo' : 'stock-ok'; ?>">
                                        <?= $cantidadTotal; ?>
                                    </td>

                                    <td>
                                        <?= $row['marca']; ?>
                                    </td>
                                    <td>
                                        <?= $row['categoria']; ?>
                                    </td>
                                    <!-- <td>$
                                        //< number_format($row['precio_compra'], 2); ?>
                                    </td> -->
                                    <td>$
                                        <?= number_format($row['total_compra_calculado'], 2); ?>
                                    </td>
                                    <td>$
                                        <?= number_format($row['precio_venta'], 2); ?>
                                    </td>
                                    <td>$
                                        <?= number_format($row['total_venta'], 2); ?>
                                    </td>
                                    <td>
                                        <span class="estado estado-<?= $row['estado']; ?>">
                                            <?= $row['estado']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <!-- $row['fecha_ingreso'];  -->
                                        <?php if ($row['imagen']): ?>
                                            <img src="../../img/PRODUCTOS/<?= $row['imagen'] ?>" alt="Imagen"
                                                style="max-width: 50px; max-height: 50px;">
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <!-- 🔥 NUEVA COLUMNA ACCIONES -->
                                    <td class="acciones">
                                        <!-- EDITAR -->
                                        <i class="bi bi-pencil-square btn-editar" data-id="<?= $row['codigo_barra']; ?>"
                                            data-producto="<?= htmlspecialchars($row['nombre_producto']); ?>"
                                            data-marca="<?= htmlspecialchars($row['marca']); ?>"
                                            data-categoria="<?= htmlspecialchars($row['categoria']); ?>"
                                            data-cantidad="<?= $row['cantidad']; ?>"
                                            data-precio_compra="<?= $row['precio_compra']; ?>"
                                            data-precio_venta="<?= $row['precio_venta']; ?>"
                                            data-estado="<?= $row['estado']; ?>" data-stock="<?= $row['stock_minimo']; ?>"
                                            data-proveedor="<?= htmlspecialchars($row['proveedor']); ?>"
                                            data-imagen="<?= $row['imagen']; ?>" title="Editar Producto"
                                            style="font-size:18px; margin:0 5px; cursor:pointer; color:#0d6efd; transition:0.2s;">
                                        </i>

                                        <!-- ELIMINAR -->
                                        <i class="bi bi-trash btn-eliminar"
                                            onclick="eliminarProducto('<?= $row['codigo_barra']; ?>')" title="Eliminar Producto"
                                            style="font-size:18px; margin:0 5px; cursor:pointer; color:#dc3545; transition:0.2s;">
                                        </i>

                                        <!-- VER LOTES -->
                                        <button type="button"
                                            onclick="mostrarLotes('<?= $row['codigo_barra'] ?>', '<?= htmlspecialchars($row['nombre_producto']) ?>')"
                                            title="Ver Lotes"
                                            style="background:none; border:none; cursor:pointer; margin:0 5px; font-size:18px; color:#17a2b8; transition:0.2s;">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" style="text-align:center;">No hay productos registrados</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PREVIEW IMAGEN -->
    <script>
        document.getElementById('imagenInput').addEventListener('change', function (event) {
            const file = event.target.files[0];
            const preview = document.getElementById('previewImagen');

            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });
    </script>

    <!-- BUSCADOR -->
    <script>
        document.getElementById('buscador').addEventListener('keyup', function () {
            const filtro = this.value.toLowerCase();
            const filas = document.querySelectorAll("table tbody tr");
            filas.forEach(fila => {
                const textoFila = fila.textContent.toLowerCase();
                fila.style.display = textoFila.includes(filtro) ? "" : "none";
            });
        });
    </script>
    <!-- FILTROS -->
    <script>
        // Mantener filtros al buscar
        document.getElementById('searchInput').addEventListener('keyup', function (e) {
            if (e.key === 'Enter') {
                const filters = new URLSearchParams(window.location.search);
                filters.set('search', this.value);
                window.location.href = 'CRUD-Product.php?' + filters.toString();
            }
        });
    </script>

    <!-- CONFIRMACION PARA ELIMINAR DATOS -->
    <script>
        function eliminarProducto(id) {
            if (confirm("¿Seguro que deseas eliminar este producto?")) {
                window.location.href = "eliminar_producto.php?id=" + id;
            }
        }
    </script>

    <!-- PRECIO DE VENTA MAYOR A PRECIO DE COMPRA -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {

            const form = document.querySelector(".form-productos");
            const precioCompraInput = document.querySelector("input[name='precio_compra']");
            const precioVentaInput = document.querySelector("input[name='precio_venta']");

            const mensajeError = document.createElement("div");
            mensajeError.className = "mensaje-error";
            precioVentaInput.parentElement.appendChild(mensajeError);

            function validarPrecios() {

                const precioCompra = parseFloat(precioCompraInput.value) || 0;
                const precioVenta = parseFloat(precioVentaInput.value) || 0;

                if (precioVenta <= precioCompra && precioVentaInput.value !== "") {

                    precioVentaInput.classList.add("input-error");
                    mensajeError.textContent = "El precio de venta debe ser mayor que el precio de compra.";
                    return false;

                } else {

                    precioVentaInput.classList.remove("input-error");
                    mensajeError.textContent = "";
                    return true;
                }
            }

            precioVentaInput.addEventListener("input", validarPrecios);
            precioCompraInput.addEventListener("input", validarPrecios);

            form.addEventListener("submit", function (e) {
                if (!validarPrecios()) {
                    e.preventDefault();
                    precioVentaInput.focus();
                }
            });

        });
    </script>

    <!-- MODAL DE LOTES -->
    <div id="modalLotes" class="modal-lotes" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; 
            background:rgba(0,0,0,0.6); z-index:9999; overflow:auto;">
        <div style="background:#fff; margin:30px auto; padding:25px; max-width:900px; 
                border-radius:12px; max-height:85vh; overflow-y:auto; box-shadow:0 10px 40px rgba(0,0,0,0.3);">

            <!-- Título + Botones -->
            <div style="display:flex; justify-content:space-between; align-items:center; 
                    margin-bottom:20px; padding-bottom:15px; border-bottom:2px solid #eee;">
                <h3 style="margin:0; color:#333; font-size:20px;">
                    📦 Lotes de: <span id="nombreProductoModal" style="color:#0d6efd;"></span>
                </h3>
                <div style="display:flex; gap:10px;">
                    <button type="button" id="btnAgregarLote" onclick="mostrarFormularioLote()" style="background:#28a745; color:white; border:none; padding:8px 16px; 
                               border-radius:6px; cursor:pointer; font-size:14px; display:flex; 
                               align-items:center; gap:5px; transition:0.2s;">
                        <i class="bi bi-plus-lg"></i> Agregar Lote
                    </button>
                    <button type="button" onclick="cerrarModal()" style="background:#dc3545; color:white; border:none; padding:8px 16px; 
                               border-radius:6px; cursor:pointer; font-size:14px; transition:0.2s;">
                        Cerrar
                    </button>
                </div>
            </div>

            <!-- Contenedor de la Tabla (se oculta al mostrar formularios) -->
            <div id="tablaLotesContainer">
                <table class="table-lotes" style="width:100%; border-collapse:collapse;">
                    <thead id="tablaLotesHeader">
                        <tr style="background:#333; color:white;">
                            <th style="padding:12px 8px;">Cantidad</th>
                            <th style="padding:12px 8px;">Precio Compra</th>
                            <th style="padding:12px 8px;">Proveedor</th>
                            <th style="padding:12px 8px;">Ganancias</th>
                            <th style="padding:12px 8px;">Fecha Ingreso</th>
                            <th style="padding:12px 8px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="cuerpoLotes">
                        <!-- Se cargará dinámicamente -->
                    </tbody>
                </table>
            </div>

            <!-- Contenedor del Formulario (separado de la tabla) -->
            <div id="formularioLoteContainer" style="display:none; margin-top:20px; 
                                                 background:#f8f9fa; padding:20px; 
                                                 border-radius:8px; border:1px solid #dee2e6;">
                <!-- El formulario se cargará aquí -->
            </div>
        </div>
    </div>
    <script>
        let codigoActual = '';
        let nombreActual = '';

        function mostrarLotes(codigo, nombre) {
            codigoActual = codigo;
            nombreActual = nombre;
            document.getElementById('nombreProductoModal').textContent = nombre;
            document.getElementById('btnAgregarLote').style.display = 'flex';
            cerrarFormularioLote();
            cargarLotes(codigo, nombre);
            document.getElementById('modalLotes').style.display = 'block';
        }

        function cargarLotes(codigo, nombre) {
            const cuerpo = document.getElementById('cuerpoLotes');
            const tablaContainer = document.getElementById('tablaLotesContainer');
            const formularioContainer = document.getElementById('formularioLoteContainer');

            // ✅ MOSTRAR TABLA, OCULTAR FORMULARIO
            tablaContainer.style.display = 'block';
            document.getElementById('tablaLotesHeader').style.display = '';
            formularioContainer.style.display = 'none';
            formularioContainer.innerHTML = '';

            cuerpo.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:30px;">Cargando...</td></tr>';

            fetch(`cargar_lotes.php?codigo=${encodeURIComponent(codigo)}`)
                .then(r => r.text())
                .then(html => {
                    cuerpo.innerHTML = html;
                    agregarEventosLotes(); // 🔥 Agregar eventos a los iconos
                })
                .catch(() => {
                    cuerpo.innerHTML = '<tr><td colspan="5" style="color:red; text-align:center;">Error al cargar lotes</td></tr>';
                });
        }

        function mostrarFormularioLote() {
            const tablaContainer = document.getElementById('tablaLotesContainer');
            const formularioContainer = document.getElementById('formularioLoteContainer');

            // ✅ OCULTAR TABLA (incluyendo header), MOSTRAR FORMULARIO
            tablaContainer.style.display = 'none';
            document.getElementById('btnAgregarLote').style.display = 'none';
            formularioContainer.style.display = 'block';
            formularioContainer.innerHTML = '<div style="text-align:center; padding:30px;">Cargando formulario...</div>';

            fetch(`entrada_lotes_modal.php?codigo=${encodeURIComponent(codigoActual)}&nombre=${encodeURIComponent(nombreActual)}`)
                .then(r => r.text())
                .then(html => {
                    formularioContainer.innerHTML = html;
                    inicializarFormularioLote();
                })
                .catch(() => {
                    formularioContainer.innerHTML = '<div style="color:red; text-align:center;">Error al cargar formulario</div>';
                });
        }

        function mostrarFormularioEditarLote(id, cantidad, precio_compra, proveedor) {
            const tablaContainer = document.getElementById('tablaLotesContainer');
            const formularioContainer = document.getElementById('formularioLoteContainer');

            tablaContainer.style.display = 'none';
            document.getElementById('btnAgregarLote').style.display = 'none';
            formularioContainer.style.display = 'block';
            formularioContainer.innerHTML = '<div style="text-align:center; padding:30px;">Cargando formulario...</div>';

            fetch(`editar_lote_modal.php?id=${encodeURIComponent(id)}`)
                .then(r => r.text())
                .then(html => {
                    formularioContainer.innerHTML = html;
                    inicializarFormularioEditarLote();
                })
                .catch(() => {
                    formularioContainer.innerHTML = '<div style="color:red; text-align:center;">Error al cargar formulario</div>';
                });
        }

        function cerrarFormularioLote() {
            const tablaContainer = document.getElementById('tablaLotesContainer');
            const formularioContainer = document.getElementById('formularioLoteContainer');

            // ✅ MOSTRAR TABLA (incluyendo header), OCULTAR FORMULARIO
            tablaContainer.style.display = 'block';
            document.getElementById('tablaLotesHeader').style.display = '';
            document.getElementById('btnAgregarLote').style.display = 'flex';
            formularioContainer.style.display = 'none';
            formularioContainer.innerHTML = '';

            cargarLotes(codigoActual, nombreActual);
        }

        function cerrarModal() {
            document.getElementById('modalLotes').style.display = 'none';
        }

        // 🔥 AGREGAR EVENTOS A LOS ICONOS DE EDITAR/ELIMINAR LOTE
        function agregarEventosLotes() {
            // Evento para Editar Lote
            document.querySelectorAll('.btn-editar-lote').forEach(btn => {
                btn.addEventListener('click', function () {
                    const id = this.dataset.id;
                    const cantidad = this.dataset.cantidad;
                    const caducidad = this.dataset.caducidad;
                    mostrarFormularioEditarLote(id, cantidad, caducidad);
                });
            });

            // Evento para Eliminar Lote
            document.querySelectorAll('.btn-eliminar-lote').forEach(btn => {
                btn.addEventListener('click', function () {
                    const id = this.dataset.id;
                    if (confirm('⚠️ ¿Seguro que deseas eliminar este lote?\n\nLa cantidad se restará del inventario general.')) {
                        eliminarLote(id);
                    }
                });
            });
        }

        // 🔥 INICIALIZAR FORMULARIO DE AGREGAR LOTE (CON NOTIFICACIÓN)
        function inicializarFormularioLote() {
            const form = document.getElementById('formAgregarLote');
            if (!form) return;
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const formData = new FormData(this);
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Guardando...';

                fetch('procesar_lote.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // ✅ ACTUALIZAR LA FILA DEL PRODUCTO
                            actualizarFilaProducto(
                                data.codigo,
                                data.cantidad_total,
                                data.total_compra,
                                data.total_venta
                            );
                            // ✅ MOSTRAR NOTIFICACIÓN
                            mostrarNotificacion('✅ Lote agregado correctamente');
                            cerrarFormularioLote();
                        } else {
                            alert('❌ ' + (data.message || 'Error al guardar'));
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> Agregar';
                        }
                    })
                    .catch(err => {
                        console.error('Error:', err);
                        alert('❌ Error de conexión. Revisa la consola.');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> Agregar';
                    });
            });
        }

        // 🔥 INICIALIZAR FORMULARIO DE EDITAR LOTE (CON NOTIFICACIÓN)
        function inicializarFormularioEditarLote() {
            const form = document.getElementById('formEditarLote');
            if (!form) return;
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const formData = new FormData(this);
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Guardando...';

                fetch('procesar_edicion_lote.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // ✅ ACTUALIZAR LA FILA DEL PRODUCTO
                            actualizarFilaProducto(
                                data.codigo,
                                data.cantidad_total,
                                data.total_compra,
                                data.total_venta
                            );
                            // ✅ MOSTRAR NOTIFICACIÓN
                            mostrarNotificacion('✅ Lote actualizado correctamente');
                            cerrarFormularioLote();
                        } else {
                            alert('❌ ' + (data.message || 'Error al guardar'));
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar Cambios';
                        }
                    })
                    .catch(err => {
                        console.error('Error:', err);
                        alert('❌ Error de conexión. Revisa la consola.');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar Cambios';
                    });
            });
        }

        // 🔥 ELIMINAR LOTE (CON NOTIFICACIÓN)
        function eliminarLote(id_lote) {
            const formData = new FormData();
            formData.append('id_lote', id_lote);

            fetch('eliminar_lote.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // ✅ ACTUALIZAR LA FILA DEL PRODUCTO
                        actualizarFilaProducto(
                            data.codigo,
                            data.cantidad_total,
                            data.total_compra,
                            data.total_venta
                        );
                        // ✅ MOSTRAR NOTIFICACIÓN
                        mostrarNotificacion('🗑️ Lote eliminado correctamente');
                        cerrarFormularioLote();
                    } else {
                        alert('❌ ' + (data.message || 'Error al eliminar'));
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('❌ Error de conexión. Revisa la consola.');
                });
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('modalLotes').addEventListener('click', function (e) {
            if (e.target === this) {
                cerrarModal();
            }
        });

        // 🔥 Función para actualizar la fila del producto (VERSIÓN CORREGIDA)
        function actualizarFilaProducto(codigo, cantidadTotal, totalCompra, totalVenta) {
            const fila = document.querySelector(`tr[data-codigo="${codigo}"]`);
            if (!fila) {
                console.warn('⚠️ No se encontró la fila del producto:', codigo);
                return;
            }

            // ✅ Actualizar cantidad (columna 3 → índice 2)
            const cantidadTd = fila.querySelectorAll('td')[2];
            if (cantidadTd) {
                const stockMinimo = parseInt(fila.dataset.stock) || 0;
                const claseStock = cantidadTotal <= stockMinimo ? 'stock-bajo' : 'stock-ok';
                cantidadTd.innerHTML = `<span class="${claseStock}">${cantidadTotal}</span>`;
                cantidadTd.className = claseStock;
            }

            // ✅ Actualizar Total Compra (columna 6 → índice 5)
            const totalCompraTd = fila.querySelectorAll('td')[5];
            if (totalCompraTd) {
                totalCompraTd.textContent = '$' + parseFloat(totalCompra).toFixed(2);
            }

            // ✅ Actualizar Precio Venta (columna 7 → índice 6) - OBTENER DE BD
            fetch(`cargar_fila_producto.php?codigo=${encodeURIComponent(codigo)}`)
                .then(r => r.text())
                .then(html => {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    const nuevoPrecioVenta = tempDiv.querySelector('td:nth-child(7)');
                    if (nuevoPrecioVenta) {
                        const precioVentaTd = fila.querySelectorAll('td')[6];
                        if (precioVentaTd) {
                            precioVentaTd.textContent = nuevoPrecioVenta.textContent;
                        }
                    }
                })
                .catch(err => console.error('Error al cargar precio de venta:', err));

            // ✅ Actualizar Total Venta (columna 8 → índice 7)
            const totalVentaTd = fila.querySelectorAll('td')[7];
            if (totalVentaTd) {
                totalVentaTd.textContent = '$' + parseFloat(totalVenta).toFixed(2);
            }

            // 🔥 NUEVO: ACTUALIZAR data-cantidad DEL BOTÓN DE EDITAR
            const btnEditar = fila.querySelector('.btn-editar');
            if (btnEditar) {
                btnEditar.setAttribute('data-cantidad', cantidadTotal);
            }

            // ✅ ACTUALIZAR RESUMEN
            actualizarResumen();

            // 🔥 EFECTO VISUAL DE ACTUALIZACIÓN
            fila.style.transition = 'background 0.3s ease';
            fila.style.background = '#d4edda';
            setTimeout(() => {
                fila.style.background = '';
            }, 500);
        }

        // 🔥 Función para actualizar el resumen de inventario (MEJORADA)
        function actualizarResumen() {
            fetch('cargar_resumen.php')
                .then(response => {
                    if (!response.ok) throw new Error('Error al cargar resumen');
                    return response.json();
                })
                .then(data => {
                    const formatCurrency = (num) => {
                        const valor = parseFloat(num);
                        return isNaN(valor) ? '$0.00' : '$' + valor.toLocaleString('es-MX', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    };

                    // ✅ Actualizar cada elemento con animación
                    actualizarConAnimacion('total_productos', data.total_productos || 0);
                    actualizarConAnimacion('total_unidades', data.total_unidades || 0);
                    actualizarConAnimacion('inversion_total', formatCurrency(data.inversion_total));
                    actualizarConAnimacion('venta_total', formatCurrency(data.venta_total));
                    actualizarConAnimacion('ganancia_total', formatCurrency(data.ganancia_total));
                    actualizarConAnimacion('stock_bajo', data.stock_bajo || 0);
                })
                .catch(err => {
                    console.error('❌ Error al actualizar resumen:', err);
                });
        }

        // 🔥 Función auxiliar para actualizar con animación
        function actualizarConAnimacion(id, nuevoValor) {
            const elemento = document.getElementById(id);
            if (elemento && elemento.textContent != nuevoValor) {
                elemento.style.transition = 'color 0.3s ease';
                elemento.style.color = '#28a745';
                elemento.textContent = nuevoValor;
                setTimeout(() => {
                    elemento.style.color = '';
                }, 300);
            }
        }

        // 🔥 VERIFICAR SI HUBO ACCIONES Y MOSTRAR NOTIFICACIÓN
        window.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            let mensaje = '';
            let tipo = ''; // 'success' o 'danger'

            // ✅ VERIFICAR SI ES PRODUCTO ELIMINADO (ROJO)
            if (urlParams.get('eliminado') === '1') {
                mensaje = ' Producto eliminado correctamente';
                tipo = 'danger'; // 🔥 ESTO LO CAMBIA A ROJO
            }
            // ✅ VERIFICAR SI ES PRODUCTO AGREGADO (VERDE)
            else if (urlParams.get('agregado') === '1') {
                mensaje = '✅ Producto agregado correctamente';
                tipo = 'success';
            }
            // ✅ VERIFICAR SI ES PRODUCTO ACTUALIZADO (VERDE)
            else if (urlParams.get('actualizado') === '1') {
                mensaje = ' Producto actualizado correctamente';
                tipo = 'success';
            }

            if (mensaje) {
                // Limpiar la URL
                window.history.replaceState({}, document.title, window.location.pathname);
                // Mostrar notificación con el tipo correcto
                mostrarNotificacion(mensaje, tipo);
                // Refrescar el resumen
                actualizarResumen();
            }
        });

        // 🔥 FUNCIÓN PARA MOSTRAR NOTIFICACIÓN (ACTUALIZADA)
        function mostrarNotificacion(mensaje, tipo = 'success') {
            const notificacion = document.createElement('div');
            // 🔥 CAMBIAR COLOR SEGÚN EL TIPO
            const color = tipo === 'danger' ? '#dc3545' : '#28a745';

            notificacion.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        background: ${color};
        color: white;
        padding: 15px 25px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 10000;
        animation: slideIn 0.3s ease;
        font-weight: bold;
        display: flex;
        align-items: center;
        gap: 10px;
    `;

            // Icono según el tipo
            const icono = tipo === 'danger' ? '🗑️' : '';
            notificacion.innerHTML = `<span>${icono}</span> <span>${mensaje}</span>`;

            document.body.appendChild(notificacion);

            setTimeout(() => {
                notificacion.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notificacion.remove(), 300);
            }, 3000);
        }

        // 🔥 AGREGAR ANIMACIONES CSS
        const style = document.createElement('style');
        style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
        document.head.appendChild(style);
    </script>

    <script>
        // ============================================
        // ✅ CARGAR DATOS AL FORMULARIO (EDITAR) - ÚNICO
        // ============================================
        document.querySelectorAll(".btn-editar").forEach(btn => {
            btn.addEventListener("click", function () {
                // Guardar código original
                const codigoOriginal = this.dataset.id;
                let campoOriginal = document.querySelector("input[name='codigo_original']");
                if (!campoOriginal) {
                    campoOriginal = document.createElement("input");
                    campoOriginal.type = "hidden";
                    campoOriginal.name = "codigo_original";
                    campoOriginal.id = "codigo_original";
                    document.querySelector(".form-productos").appendChild(campoOriginal);
                }
                campoOriginal.value = codigoOriginal;

                // Llenar formulario
                document.querySelector("input[name='codigo_barra']").value = this.dataset.id;
                document.querySelector("input[name='nombre_producto']").value = this.dataset.producto;
                document.querySelector("input[name='marca']").value = this.dataset.marca;
                document.querySelector("select[name='categoria']").value = this.dataset.categoria;
                document.querySelector("input[name='cantidad']").value = this.dataset.cantidad;
                document.querySelector("input[name='precio_compra']").value = this.dataset.precio_compra;
                document.querySelector("input[name='precio_venta']").value = this.dataset.precio_venta;
                document.querySelector("select[name='estado']").value = this.dataset.estado;
                document.querySelector("input[name='stock_minimo']").value = this.dataset.stock;
                document.querySelector("input[name='proveedor']").value = this.dataset.proveedor || '';

                // Mostrar imagen
                const preview = document.getElementById("previewImagen");
                if (this.dataset.imagen && this.dataset.imagen !== "") {
                    preview.src = "../../img/PRODUCTOS/" + this.dataset.imagen;
                    preview.style.display = "block";
                } else {
                    preview.src = "";
                    preview.style.display = "none";
                }

                // 🔥 CAMBIAR BOTÓN A "ACTUALIZAR" (AMARILLO)
                document.getElementById("modo").value = "editar";
                document.getElementById("btnGuardar").innerText = "✏️ Actualizar";
                document.getElementById("btnGuardar").style.background = "#ffc107";
                document.getElementById("btnGuardar").style.color = "#000";

                // 🔥 MOSTRAR BOTÓN CANCELAR
                const btnCancelar = document.getElementById("btnCancelar");
                if (btnCancelar) {
                    btnCancelar.style.display = "block";
                }

                // Scroll arriba
                window.scrollTo({ top: 0, behavior: "smooth" });
            });
        });

        // ============================================
        // ✅ LÓGICA DEL BOTÓN CANCELAR - ÚNICO
        // ============================================
        const btnCancelar = document.getElementById("btnCancelar");
        if (btnCancelar) {
            btnCancelar.addEventListener("click", function (e) {
                e.preventDefault();
                document.querySelector(".form-productos").reset();
                document.getElementById("modo").value = "crear";
                document.getElementById("btnGuardar").innerText = "Guardar";
                document.getElementById("btnGuardar").style.background = "#28a745";
                document.getElementById("btnGuardar").style.color = "#fff";
                document.getElementById("previewImagen").style.display = "none";
                document.getElementById("previewImagen").src = "";
                this.style.display = "none";
            });
        }
    </script>

    <!-- CHECKBOX UTILIZADOS COMO UN RADIOBUTON -->
    <script>
        function manejarExclusividad(elementoSeleccionado) {
            // Si el elemento que se hizo clic NO está marcado (lo estamos desmarcando), no hacemos nada
            if (!elementoSeleccionado.checked) return;

            // Obtenemos todos los checkboxes con la clase 'filtro-stock'
            const todosLosFiltros = document.querySelectorAll('.filtro-stock');

            // Recorremos y desmarcamos todos menos el que se acaba de seleccionar
            todosLosFiltros.forEach(chk => {
                if (chk !== elementoSeleccionado) {
                    chk.checked = false;
                }
            });
        }
    </script>

    <script>
        document.querySelectorAll(".btn-editar").forEach(btn => {
            btn.addEventListener("click", function () {
                // 1. Llenar los datos (tu código existente)
                document.querySelector("input[name='codigo_barra']").value = this.dataset.id;
                document.querySelector("input[name='nombre_producto']").value = this.dataset.producto;
                document.querySelector("input[name='marca']").value = this.dataset.marca;
                document.querySelector("select[name='categoria']").value = this.dataset.categoria;
                document.querySelector("input[name='cantidad']").value = this.dataset.cantidad;
                document.querySelector("input[name='precio_compra']").value = this.dataset.precio_compra;
                document.querySelector("input[name='precio_venta']").value = this.dataset.precio_venta;
                document.querySelector("select[name='estado']").value = this.dataset.estado;
                document.querySelector("input[name='stock_minimo']").value = this.dataset.stock;
                document.querySelector("input[name='proveedor']").value = this.dataset.proveedor || '';

                // Mostrar imagen
                const preview = document.getElementById("previewImagen");
                if (this.dataset.imagen && this.dataset.imagen !== "") {
                    preview.src = "../../img/PRODUCTOS/" + this.dataset.imagen;
                    preview.style.display = "block";
                } else {
                    preview.src = "";
                    preview.style.display = "none";
                }

                // 2. CAMBIAR BOTÓN A "ACTUALIZAR" (AMARILLO)
                document.getElementById("modo").value = "editar";
                document.getElementById("btnGuardar").innerText = "✏️ Actualizar";
                document.getElementById("btnGuardar").style.background = "#ffc107"; // Amarillo
                document.getElementById("btnGuardar").style.color = "#000"; // Texto negro

                // 3. MOSTRAR BOTÓN CANCELAR
                document.getElementById("btnCancelar").style.display = "block";

                // Scroll arriba
                window.scrollTo({ top: 0, behavior: "smooth" });
            });
        });

        // 4. LÓGICA DEL BOTÓN CANCELAR
        document.getElementById("btnCancelar").addEventListener("click", function (e) {
            e.preventDefault(); // Evitar comportamiento de enlace

            // Limpiar formulario
            document.querySelector(".form-productos").reset();

            // Restaurar botón Guardar (Verde)
            document.getElementById("modo").value = "crear";
            document.getElementById("btnGuardar").innerText = "Guardar";
            document.getElementById("btnGuardar").style.background = "#28a745"; // Verde original
            document.getElementById("btnGuardar").style.color = "#fff";

            // Ocultar imagen preview
            document.getElementById("previewImagen").style.display = "none";
            document.getElementById("previewImagen").src = "";

            // Ocultar botón Cancelar
            this.style.display = "none";
        });
    </script>

    <script>
        // Evitar autocompletado agresivo del navegador
        document.addEventListener("DOMContentLoaded", function () {
            const inputs = document.querySelectorAll("#formUsuario input");

            inputs.forEach(input => {
                // Hacerlos readonly al cargar
                input.setAttribute("readonly", "readonly");

                // Quitar el readonly cuando el usuario hace clic
                input.addEventListener("click", function () {
                    this.removeAttribute("readonly");
                });

                // También quitarlo si usan la tecla Tab para navegar
                input.addEventListener("keydown", function () {
                    this.removeAttribute("readonly");
                });
            });
        });
    </script>

    <!-- BARRA INFERIOR -->
    <div class="barra-inferior"></div>
</body>

</html>