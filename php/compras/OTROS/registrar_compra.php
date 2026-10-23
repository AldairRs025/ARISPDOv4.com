<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../../index.html");
    exit;
}
include("../conexion.php");
include("../login/registrar_actividad.php");

$user_id = $_SESSION["user_id"] ?? 0;
$username = $_SESSION["username"] ?? 'Desconocido';
$mensaje = '';
$tipo_mensaje = '';
$folio_generado = '';

/* ============================================
🔥 PROCESAR COMPRA MÚLTIPLE CON FOLIO
============================================ */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion']) && $_POST['accion'] === 'registrar_compra_multiple') {
    $proveedor_id = $_POST['proveedor_id'] ?? '';
    $fecha_compra_input = $_POST['fecha_compra'] ?? date('Y-m-d\TH:i');
    $fecha_compra = date('Y-m-d H:i:s', strtotime($fecha_compra_input));
    $productos_compra = json_decode($_POST['productos_json'], true);

    if (!empty($productos_compra) && !empty($proveedor_id)) {
        mysqli_begin_transaction($conn);
        try {
            // 🔥 GENERAR FOLIO ÚNICO
            $fecha_folio = date('Ymd');
            $query_folio = "SELECT folio FROM compras_cabecera 
                WHERE DATE(fecha_registro) = CURDATE() 
                ORDER BY id DESC LIMIT 1";
            $result = mysqli_query($conn, $query_folio);
            $ultimo_folio = mysqli_fetch_assoc($result);

            if ($ultimo_folio) {
                $partes = explode('-', $ultimo_folio['folio']);
                $ultimo_numero = intval(end($partes));
                $siguiente_numero = $ultimo_numero + 1;
            } else {
                $siguiente_numero = 1;
            }

            $folio = 'CMP-' . $fecha_folio . '-' . str_pad($siguiente_numero, 4, '0', STR_PAD_LEFT);
            $folio_generado = $folio;

            // 🔥 CALCULAR TOTAL DE LA COMPRA
            $total_compra = 0;
            foreach ($productos_compra as $prod) {
                $total_compra += (float) $prod['subtotal'];
            }

            // ✅ 1. INSERTAR EN TABLAS REALES (NO SE ELIMINAN NUNCA)
            $sql_cabecera = "INSERT INTO compras_cabecera (folio, proveedor_id, fecha_compra, total_compra, usuario_id)
            VALUES (?, ?, ?, ?, ?)";
            $stmt_cabecera = mysqli_prepare($conn, $sql_cabecera);
            mysqli_stmt_bind_param($stmt_cabecera, "sisdi", $folio, $proveedor_id, $fecha_compra, $total_compra, $user_id);
            mysqli_stmt_execute($stmt_cabecera);
            $compra_id = mysqli_insert_id($conn);

            // ✅ 2. INSERTAR EN TABLAS DE HISTORIAL (SÍ SE PUEDEN DEPURAR)
            $sql_historial_cabecera = "INSERT INTO historial_compras_cabecera (folio, proveedor_id, fecha_compra, total_compra, usuario_id)
            VALUES (?, ?, ?, ?, ?)";
            $stmt_historial = mysqli_prepare($conn, $sql_historial_cabecera);
            mysqli_stmt_bind_param($stmt_historial, "sisdi", $folio, $proveedor_id, $fecha_compra, $total_compra, $user_id);
            mysqli_stmt_execute($stmt_historial);
            $historial_id = mysqli_insert_id($conn);

            // 🔥 OBTENER NOMBRE DEL PROVEEDOR
            $prov_result = mysqli_query($conn, "SELECT nombre FROM proveedores WHERE id = $proveedor_id");
            $prov_nombre = mysqli_fetch_assoc($prov_result)['nombre'];

            // ✅ 3. INSERTAR DETALLES Y ACTUALIZAR INVENTARIO
            foreach ($productos_compra as $prod) {
                $codigo = $prod['codigo'];
                $cantidad = (float) $prod['cantidad'];
                $precio = (float) $prod['precio'];
                $subtotal = (float) $prod['subtotal'];

                // 🔥 OBTENER PRECIO DE VENTA PARA CALCULAR GANANCIAS
                $producto = mysqli_fetch_assoc(mysqli_query($conn, "SELECT precio_venta FROM productos WHERE codigo_barra = '$codigo'"));
                $precio_venta = $producto['precio_venta'];

                // 🔥 CALCULAR GANANCIAS
                $ganancia_unitaria = $precio_venta - $precio;
                $ganancia_lote = $ganancia_unitaria * $cantidad;
                $porcentaje_ganancia = $precio > 0 ? (($ganancia_unitaria / $precio) * 100) : 0;

                // ✅ INSERTAR LOTE (TABLA REAL - NO SE ELIMINA)
                $sql_lote = "INSERT INTO lotes (codigo_barra, cantidad, precio_compra, proveedor, ganancia_lote, porcentaje_ganancia, fecha_ingreso, compra_id, es_de_compra)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, 1)";
                $stmt = mysqli_prepare($conn, $sql_lote);
                mysqli_stmt_bind_param($stmt, "sdssddi", $codigo, $cantidad, $precio, $prov_nombre, $ganancia_lote, $porcentaje_ganancia, $compra_id);
                mysqli_stmt_execute($stmt);

                // ✅ ACTUALIZAR PRODUCTO (Inventario REAL - NO SE AFECTA AL DEPURAR)
                $current = mysqli_fetch_assoc(mysqli_query($conn, "SELECT cantidad, total_compra, precio_venta FROM productos WHERE codigo_barra = '$codigo'"));
                $nueva_cantidad = $current['cantidad'] + $cantidad;
                $nuevo_total_compra = $current['total_compra'] + ($cantidad * $precio);
                $nuevo_total_venta = $nueva_cantidad * $current['precio_venta'];
                $sql_update = "UPDATE productos SET cantidad = ?, total_compra = ?, total_venta = ? WHERE codigo_barra = ?";
                $stmt2 = mysqli_prepare($conn, $sql_update);
                mysqli_stmt_bind_param($stmt2, "ddds", $nueva_cantidad, $nuevo_total_compra, $nuevo_total_venta, $codigo);
                mysqli_stmt_execute($stmt2);

                // ✅ INSERTAR DETALLE DE COMPRA REAL
                $sql_detalle = "INSERT INTO compras (compra_id, proveedor_id, codigo_barra, cantidad, precio_compra, total, fecha_compra, usuario_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt3 = mysqli_prepare($conn, $sql_detalle);
                mysqli_stmt_bind_param($stmt3, "iisdddsi", $compra_id, $proveedor_id, $codigo, $cantidad, $precio, $subtotal, $fecha_compra, $user_id);
                mysqli_stmt_execute($stmt3);

                // ✅ INSERTAR DETALLE EN HISTORIAL (SÍ SE PUEDE DEPURAR)
                $sql_historial_detalle = "INSERT INTO historial_compras_detalle (historial_id, proveedor_id, codigo_barra, cantidad, precio_compra, total, fecha_compra, usuario_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt4 = mysqli_prepare($conn, $sql_historial_detalle);
                mysqli_stmt_bind_param($stmt4, "iisdddsi", $historial_id, $proveedor_id, $codigo, $cantidad, $precio, $subtotal, $fecha_compra, $user_id);
                mysqli_stmt_execute($stmt4);
            }

            mysqli_commit($conn);

            // Registrar actividad
            registrar_actividad(
                $conn,
                $user_id,
                $username,
                "CREATE",
                "Compra registrada: Folio $folio, Total: $$total_compra",
                "compras_cabecera",
                $compra_id,
                [],
                $_POST
            );

            $mensaje = "✅ Compra registrada correctamente<br>";
            $mensaje .= "📋 <strong>Folio:</strong> $folio<br>";
            $mensaje .= "💰 <strong>Total:</strong> $$total_compra<br>";
            $mensaje .= "📦 <strong>Productos:</strong> " . count($productos_compra);
            $tipo_mensaje = 'success';

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $mensaje = '❌ Error: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = '❌ Debes seleccionar proveedor y al menos un producto';
        $tipo_mensaje = 'error';
    }
}

$proveedores = mysqli_query($conn, "SELECT * FROM proveedores ORDER BY nombre ASC");
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>• Registrar Compra</title>
    <link rel="stylesheet" href="../../css/Compras-STYLE/Reg_Compra5.css">
    <link rel="icon" href="../../img/LOGIN/icon_24px.png" type="img/INICIO/icon_24px.png" />
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

    <div class="container">
        <h1>🛒 Registrar Compra</h1>

        <?php if ($mensaje): ?>
            <div class="mensaje <?= $tipo_mensaje ?>"><?= $mensaje ?></div>
            <?php if ($folio_generado): ?>
                <div class="mensaje success" style="text-align:center; margin-top:10px;">
                    <a href="buscar_compra_folio.php?folio=<?= $folio_generado ?>" class="btn-nueva">
                        🔍 Ver Compra con Folio <?= $folio_generado ?>
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- LAYOUT DIVIDIDO -->
        <div class="compra-layout">
            <!-- PANEL IZQUIERDO: TABLA DE PRODUCTOS (CARRITO) -->
            <div class="panel-izquierdo carrito-container">
                <h3 style="margin-bottom:15px; border-bottom:2px solid #0d6efd; padding-bottom:10px;">
                    📋 Productos Agregados
                </h3>
                <div class="tabla-carrito-wrapper">
                    <table class="tabla-carrito">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Código</th>
                                <th>Cant.</th>
                                <th>P. Unit.</th>
                                <th>Subtotal</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaCarritoBody">
                            <tr>
                                <td colspan="6" style="text-align:center; color:#666; padding:20px;">No hay productos
                                    agregados</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="total-compra-box" style="font-size:24px; text-align:right; margin-top:auto;">
                    Total: $<span id="granTotal">0.00</span>
                </div>
                <input type="hidden" name="productos_json" id="productosJson" form="formCompraMultiple">


                <div style="display:flex; gap:10px; margin-top:10px;">
                    <button type="submit" form="formCompraMultiple" class="btn-guardar-final" id="btnGuardarFinal"
                        disabled style="width:100%; margin-top:15px;">
                        💾 Registrar Compra Completa
                    </button>
                    <a href="buscar_compra_folio.php" class="btn-nueva"
                        style="display:block; text-align:center; margin-top:10px;">
                        🔍 Consultar ticket
                    </a>
                    <a href="historial_compras.php" class="btn-volver"
                        style="display:block; text-align:center; margin-top:10px;">
                        ← Ver Historial
                    </a>
                </div>
            </div>

            <!-- PANEL DERECHO: FORMULARIO DE INGRESO -->
            <div class="panel-derecho">
                <form method="POST" id="formCompraMultiple" autocomplete="off" required novalidate>
                    <!-- 🔥 CAMPOS FALSOS PARA ENGÑAR AL NAVEGADOR -->
                    <input type="text" style="display:none" autocomplete="off">
                    <input type="password" style="display:none" autocomplete="off">
                    <!-- ------------------------------------------- -->
                    <input type="hidden" name="accion" value="registrar_compra_multiple">

                    <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                        <!-- FILA 1: Proveedor y Fecha -->
                        <div class="form-group col-2" style="grid-column: span 2;">
                            <label>Proveedor *</label>
                            <select name="proveedor_id" required>
                                <option value="">Seleccionar proveedor</option>
                                <?php while ($prov = mysqli_fetch_assoc($proveedores)): ?>
                                    <option value="<?= $prov['id'] ?>"><?= htmlspecialchars($prov['nombre']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group col-2" style="grid-column: span 2;">
                            <label>Fecha de Compra *</label>
                            <input type="datetime-local" name="fecha_compra" value="<?= date('Y-m-d\TH:i') ?>" required
                                id="fechaCompra"
                                style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>

                        <!-- FILA 2: CÓDIGO DE BARRAS -->
                        <div class="form-group col-2" style="grid-column: span 2;">
                            <label>🔍 Código de Barras *</label>
                            <input type="text" id="codigoBarrasInput" name="codigo_barra_fake_123"
                                placeholder="Escanea o escribe el código..." style="font-size:16px; padding:10px;"
                                autofocus required autocomplete="off" autocorrect="off" autocapitalize="off"
                                spellcheck="false" data-lpignore="true" data-form-type="other" readonly>
                        </div>

                        <!-- FILA 3: IMAGEN PREVIEW -->
                        <div class="form-group col-2" style="grid-column: span 2;">
                            <div class="contenedor-marco-imagen">
                                <img id="previewProducto" class="preview-img-compra" src="" alt="Vista previa">
                                <span id="textoSinImagen" style="color:#ccc; font-size:14px;">📷 Escanea un producto
                                    para ver la imagen</span>
                            </div>
                            <div id="infoProducto" class="info-producto-encontrado">
                                <h4>✅ Producto Encontrado</h4>
                                <p><strong>Nombre:</strong> <span id="nombreProducto"></span></p>
                                <p><strong>Código:</strong> <span id="codigoProducto"></span></p>
                                <p><strong>Stock Actual:</strong> <span id="stockActual"
                                        style="color:#dc3545; font-weight:bold;"></span></p>
                                <p><strong>Precio Venta:</strong> $<span id="precioVentaProducto"></span></p>
                            </div>
                        </div>

                        <!-- FILA 4: Cantidad y Precio -->
                        <div class="form-group">
                            <label>Cantidad *</label>
                            <input type="number" id="cantidadInput" step="0.01" min="0.01" required placeholder="0"
                                disabled>
                        </div>
                        <div class="form-group">
                            <label>Precio Unitario *</label>
                            <input type="number" id="precioInput" step="0.01" min="0.01" required placeholder="0.00"
                                disabled>
                        </div>

                        <!-- BOTÓN AGREGAR -->
                        <div class="form-group col-2" style="grid-column: span 2; margin-top:10px;">
                            <button type="button" class="btn-agregar-carrito" onclick="agregarAlCarrito()"
                                style="width:100%; padding:12px; font-size:16px;" disabled id="btnAgregar">
                                ➕ Agregar Producto a la Lista
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- BARRA INFERIOR -->
    <div class="barra-inferior"></div>

    <!-- JAVASCRIPT -->
    <script>
        // 🔥 VARIABLES GLOBALES - DEBEN ESTAR AL INICIO DEL SCRIPT
        let carrito = [];
        let productoActual = null;
        let modoEdicion = false;
        let indiceEdicion = -1;

        // ============================================
        // BUSCAR PRODUCTO POR CÓDIGO DE BARRAS
        // ============================================
        function buscarProductoPorCodigo(codigo) {
            if (!codigo || codigo.trim() === '') {
                alert('⚠️ Ingresa un código de barras');
                return;
            }

            fetch(`buscar_producto.php?codigo=${encodeURIComponent(codigo)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.existe) {
                        productoActual = {
                            codigo: data.codigo,
                            nombre: data.nombre,
                            stock: data.cantidad,
                            precio_venta: data.precio_venta,
                            imagen: data.imagen
                        };

                        document.getElementById('nombreProducto').textContent = data.nombre;
                        document.getElementById('codigoProducto').textContent = data.codigo;
                        document.getElementById('stockActual').textContent = data.cantidad;
                        document.getElementById('precioVentaProducto').textContent = parseFloat(data.precio_venta).toFixed(2);
                        document.getElementById('infoProducto').style.display = 'block';

                        const img = document.getElementById('previewProducto');
                        const texto = document.getElementById('textoSinImagen');
                        if (data.imagen && data.imagen !== '') {
                            img.src = '../../img/PRODUCTOS/' + data.imagen;
                            img.style.display = 'block';
                            texto.style.display = 'none';
                        } else {
                            img.style.display = 'none';
                            texto.style.display = 'block';
                            texto.textContent = '📷 Sin imagen disponible';
                        }

                        document.getElementById('cantidadInput').disabled = false;
                        document.getElementById('precioInput').disabled = false;
                        document.getElementById('btnAgregar').disabled = false;
                        document.getElementById('cantidadInput').focus();
                        document.getElementById('cantidadInput').select();

                        setTimeout(() => {
                            document.getElementById('codigoBarrasInput').value = '';
                        }, 2000);
                    } else {
                        alert('❌ Producto no encontrado: ' + codigo);
                        document.getElementById('codigoBarrasInput').value = '';
                        document.getElementById('codigoBarrasInput').focus();
                        resetearFormularioProducto();
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('❌ Error al buscar producto');
                });
        }

        // ============================================
        // RESETEAR FORMULARIO DE PRODUCTO
        // ============================================
        function resetearFormularioProducto() {
            document.getElementById('infoProducto').style.display = 'none';
            document.getElementById('cantidadInput').disabled = true;
            document.getElementById('precioInput').disabled = true;
            document.getElementById('btnAgregar').disabled = true;
            document.getElementById('cantidadInput').value = '';
            document.getElementById('precioInput').value = '';
            productoActual = null;
        }

        // ============================================
        // AGREGAR PRODUCTO AL CARRITO (O ACTUALIZAR)
        // ============================================
        function agregarAlCarrito() {
            console.log('🔍 MODO EDICIÓN:', modoEdicion);
            console.log('📍 ÍNDICE EDICIÓN:', indiceEdicion);

            // Validar proveedor
            const proveedorSelect = document.querySelector('select[name="proveedor_id"]');
            if (!proveedorSelect || !proveedorSelect.value) {
                alert('⚠️ Debes seleccionar un proveedor antes de agregar productos');
                proveedorSelect.focus();
                return;
            }

            if (!productoActual) {
                alert('⚠️ Escanea o busca un producto primero');
                return;
            }

            const cantidad = parseFloat(document.getElementById('cantidadInput').value);
            const precio = parseFloat(document.getElementById('precioInput').value);

            if (!cantidad || !precio) {
                alert('⚠️ Completa cantidad y precio');
                return;
            }

            // 🔥 MODO EDICIÓN: Actualizar producto existente
            if (modoEdicion === true && indiceEdicion >= 0) {
                console.log('✏️ ACTUALIZANDO producto en índice:', indiceEdicion);

                // Actualizar el producto en la posición original
                carrito[indiceEdicion] = {
                    codigo: productoActual.codigo,
                    nombre: productoActual.nombre,
                    cantidad: cantidad,
                    precio: precio,
                    subtotal: cantidad * precio
                };

                // Resetear modo edición
                modoEdicion = false;
                indiceEdicion = -1;

                // Cambiar botón a estado normal
                const btnAgregar = document.getElementById('btnAgregar');
                btnAgregar.innerHTML = '➕ Agregar Producto a la Lista';
                btnAgregar.classList.remove('btn-actualizar-carrito');

                alert('✅ Producto actualizado correctamente');
            } else {
                // 🔥 MODO NORMAL: Agregar nuevo producto
                console.log('➕ AGREGANDO nuevo producto');

                const subtotal = cantidad * precio;
                const existe = carrito.find(p => p.codigo === productoActual.codigo);

                if (existe) {
                    if (confirm('Este producto ya está en la lista. ¿Deseas sumar la cantidad?')) {
                        existe.cantidad += cantidad;
                        existe.subtotal = existe.cantidad * existe.precio;
                    } else {
                        return;
                    }
                } else {
                    carrito.push({
                        codigo: productoActual.codigo,
                        nombre: productoActual.nombre,
                        cantidad: cantidad,
                        precio: precio,
                        subtotal: subtotal
                    });
                }
            }

            renderizarCarrito();

            // Limpiar formulario
            document.getElementById('cantidadInput').value = '';
            document.getElementById('precioInput').value = '';
            document.getElementById('codigoBarrasInput').value = '';
            document.getElementById('infoProducto').style.display = 'none';

            const img = document.getElementById('previewProducto');
            const texto = document.getElementById('textoSinImagen');
            img.style.display = 'none';
            img.src = '';
            texto.style.display = 'block';
            texto.textContent = '📷 Escanea un producto para ver la imagen';

            productoActual = null;
            document.getElementById('cantidadInput').disabled = true;
            document.getElementById('precioInput').disabled = true;
            document.getElementById('btnAgregar').disabled = true;
            document.getElementById('codigoBarrasInput').focus();
        }

        // ============================================
        // ELIMINAR PRODUCTO DEL CARRITO
        // ============================================
        function eliminarDelCarrito(index) {
            carrito.splice(index, 1);
            renderizarCarrito();
        }

        // ============================================
        // EDITAR PRODUCTO DEL CARRITO
        // ============================================
        function editarEnCarrito(index) {
            console.log('✏️ EDITAR: Índice recibido:', index);
            console.log('✏️ EDITAR: Carrito actual:', carrito);

            if (index < 0 || index >= carrito.length) {
                alert('❌ Índice inválido');
                return;
            }

            const producto = carrito[index];
            console.log('✏️ EDITAR: Producto a editar:', producto);

            // Buscar el producto en la base de datos
            fetch(`buscar_producto.php?codigo=${encodeURIComponent(producto.codigo)}`)
                .then(response => response.json())
                .then(data => {
                    console.log('✏️ EDITAR: Data recibida:', data);
                    if (data.existe) {
                        // Llenar el formulario con los datos del producto
                        productoActual = {
                            codigo: data.codigo,
                            nombre: data.nombre,
                            stock: data.cantidad,
                            precio_venta: data.precio_venta,
                            imagen: data.imagen
                        };

                        // Mostrar información del producto
                        document.getElementById('codigoBarrasInput').value = data.codigo;
                        document.getElementById('nombreProducto').textContent = data.nombre;
                        document.getElementById('codigoProducto').textContent = data.codigo;
                        document.getElementById('stockActual').textContent = data.cantidad;
                        document.getElementById('precioVentaProducto').textContent = parseFloat(data.precio_venta).toFixed(2);
                        document.getElementById('infoProducto').style.display = 'block';

                        // Mostrar imagen
                        const img = document.getElementById('previewProducto');
                        const texto = document.getElementById('textoSinImagen');
                        if (data.imagen && data.imagen !== '') {
                            img.src = '../../img/PRODUCTOS/' + data.imagen;
                            img.style.display = 'block';
                            texto.style.display = 'none';
                        } else {
                            img.style.display = 'none';
                            texto.style.display = 'block';
                            texto.textContent = '📷 Sin imagen disponible';
                        }

                        // Llenar cantidad y precio
                        document.getElementById('cantidadInput').value = producto.cantidad;
                        document.getElementById('precioInput').value = producto.precio;

                        // Habilitar campos
                        document.getElementById('cantidadInput').disabled = false;
                        document.getElementById('precioInput').disabled = false;
                        document.getElementById('btnAgregar').disabled = false;

                        // 🔥 ACTIVAR MODO EDICIÓN - SIN ELIMINAR DEL CARRITO
                        modoEdicion = true;
                        indiceEdicion = index;
                        console.log('✅ MODO EDICIÓN ACTIVADO:', modoEdicion);
                        console.log('✅ ÍNDICE EDICIÓN:', indiceEdicion);

                        // Cambiar texto y estilo del botón
                        const btnAgregar = document.getElementById('btnAgregar');
                        btnAgregar.innerHTML = '✏️ Actualizar Producto en la Lista';
                        btnAgregar.classList.add('btn-actualizar-carrito');

                        // Enfocar en cantidad
                        document.getElementById('cantidadInput').focus();
                        document.getElementById('cantidadInput').select();

                        // ✅ CORRECCIÓN: NO eliminar del carrito, solo ocultar visualmente si se desea
                        // carrito.splice(index, 1); // ❌ ELIMINAR ESTA LÍNEA
                        // renderizarCarrito(); // ❌ ELIMINAR ESTA LÍNEA
                    } else {
                        // alert('❌ Producto no encontrado en la base de datos');
                    }
                })
                .catch(err => {
                    console.error('✏️ EDITAR: Error:', err);
                    alert('❌ Error al cargar el producto: ' + err.message);
                });
        }

        // ============================================
        // RENDERIZAR CARRITO
        // ============================================
        function renderizarCarrito() {
            const tbody = document.getElementById('tablaCarritoBody');
            const granTotalSpan = document.getElementById('granTotal');
            const btnGuardar = document.getElementById('btnGuardarFinal');
            const inputJson = document.getElementById('productosJson');

            tbody.innerHTML = '';
            let granTotal = 0;

            if (carrito.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#666; padding:20px;">No hay productos agregados</td></tr>';
                btnGuardar.disabled = true;
                inputJson.value = '';
            } else {
                btnGuardar.disabled = false;
                carrito.forEach((prod, index) => {
                    granTotal += prod.subtotal;
                    tbody.innerHTML += `
                <tr>
                    <td style="font-weight:bold;">${prod.nombre}</td>
                    <td style="font-size:12px;">${prod.codigo}</td>
                    <td>${prod.cantidad}</td>
                    <td>$${prod.precio.toFixed(2)}</td>
                    <td>$${prod.subtotal.toFixed(2)}</td>
                    <td>
                        <button type="button" onclick="editarEnCarrito(${index})" class="btn-editar-carrito" title="Editar">✏️</button>
                        <button type="button" onclick="eliminarDelCarrito(${index})" class="btn-eliminar-carrito" title="Eliminar">🗑️</button>
                    </td>
                </tr>`;
                });
                inputJson.value = JSON.stringify(carrito);
            }
            granTotalSpan.textContent = granTotal.toFixed(2);
        }

        // ============================================
        // EVENT LISTENER - ENTER EN CÓDIGO DE BARRAS
        // ============================================
        document.getElementById('codigoBarrasInput').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarProductoPorCodigo(this.value);
            }
        });

        // ============================================
        // INICIALIZACIÓN - DOM READY
        // ============================================
        document.addEventListener("DOMContentLoaded", function () {
            const inputCodigo = document.getElementById('codigoBarrasInput');
            const inputCantidad = document.getElementById('cantidadInput');
            const inputPrecio = document.getElementById('precioInput');
            const btnAgregar = document.getElementById('btnAgregar');

            // Hacer readonly al cargar
            inputCodigo.setAttribute('readonly', 'readonly');

            // Quitar readonly al interactuar
            inputCodigo.addEventListener('click', function () { this.removeAttribute('readonly'); });
            inputCodigo.addEventListener('keydown', function () { this.removeAttribute('readonly'); });
            inputCodigo.addEventListener('focus', function () { this.removeAttribute('readonly'); });

            // Prevenir que Enter active el botón de "Registrar Compra Completa"
            const formCompraMultiple = document.getElementById('formCompraMultiple');
            formCompraMultiple.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    return false;
                }
            });

            // Enter en Código de Barras → buscar producto
            inputCodigo.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    buscarProductoPorCodigo(this.value);
                }
            });

            // Enter en Cantidad → ir a Precio Unitario
            inputCantidad.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (!inputPrecio.disabled) {
                        inputPrecio.focus();
                        inputPrecio.select();
                    }
                }
            });

            // Enter en Precio Unitario → Agregar/Actualizar Producto
            inputPrecio.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (!btnAgregar.disabled) {
                        agregarAlCarrito();
                    }
                }
            });
        });
    </script>

    <script>
        // 🔥 EVITAR AUTOCOMPLETADO AGRESIVO
        document.addEventListener("DOMContentLoaded", function () {
            const inputCodigo = document.getElementById('codigoBarrasInput');

            // Hacer readonly al cargar
            inputCodigo.setAttribute('readonly', 'readonly');

            // Quitar readonly al hacer clic
            inputCodigo.addEventListener('click', function () {
                this.removeAttribute('readonly');
            });

            // Quitar readonly al usar Tab
            inputCodigo.addEventListener('keydown', function () {
                this.removeAttribute('readonly');
            });

            // Quitar readonly al enfocar
            inputCodigo.addEventListener('focus', function () {
                this.removeAttribute('readonly');
            });
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const inputCodigo = document.getElementById('codigoBarrasInput');
            inputCodigo.setAttribute('readonly', 'readonly');
            inputCodigo.addEventListener('click', function () { this.removeAttribute('readonly'); });
            inputCodigo.addEventListener('keydown', function () { this.removeAttribute('readonly'); });
            inputCodigo.addEventListener('focus', function () { this.removeAttribute('readonly'); });

            const inputCantidad = document.getElementById('cantidadInput');
            const inputPrecio = document.getElementById('precioInput');
            const btnAgregar = document.getElementById('btnAgregar');

            // Prevenir que Enter active el botón de "Registrar Compra Completa"
            const formCompraMultiple = document.getElementById('formCompraMultiple');
            formCompraMultiple.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    return false;
                }
            });

            // Enter en Código de Barras → buscar producto
            inputCodigo.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    buscarProductoPorCodigo(this.value);
                }
            });

            // Enter en Cantidad → ir a Precio Unitario
            inputCantidad.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (!inputPrecio.disabled) {
                        inputPrecio.focus();
                        inputPrecio.select();
                    }
                }
            });

            // Enter en Precio Unitario → Agregar Producto a la Lista
            inputPrecio.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (!btnAgregar.disabled) {
                        agregarAlCarrito();
                    }
                }
            });
        });
    </script>

    <!-- <script>
        // 🔥 EVITAR QUE SE MODIFIQUE AUTOMÁTICAMENTE LA FECHA
        document.addEventListener('DOMContentLoaded', function () {
            const fechaInput = document.getElementById('fechaCompra');

            // Guardar el valor inicial
            let valorOriginal = fechaInput.value;

            // Solo actualizar si el usuario lo modifica explícitamente
            fechaInput.addEventListener('focus', function () {
                // No hacer nada al enfocar - permitir edición manual
            });

            // Prevenir que JavaScript cambie el valor automáticamente
            const observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                        // Si algo intenta cambiar el valor automáticamente, lo prevenimos
                        console.log('Intento de modificación automática detectado');
                    }
                });
            });

            observer.observe(fechaInput, { attributes: true });
        });
    </script> -->
</body>

</html>