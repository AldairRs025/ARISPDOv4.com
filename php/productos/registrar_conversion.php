<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../../index.html");
    exit;
}
include("../conexion.php");

$mensaje = "";
$tipo_mensaje = "";

// 🔥 PROCESAR FORMULARIO
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $producto_padre = mysqli_real_escape_string($conn, $_POST['producto_padre']);
    $producto_hijo = mysqli_real_escape_string($conn, $_POST['producto_hijo']);
    $factor_conversion = floatval($_POST['factor_conversion']);
    $nivel = intval($_POST['nivel']);
    $usuario_id = $_SESSION["user_id"];

    // 🔥 VALIDACIONES
    if (empty($producto_padre) || empty($producto_hijo)) {
        $mensaje = "❌ Debes seleccionar ambos productos";
        $tipo_mensaje = "error";
    } elseif ($producto_padre === $producto_hijo) {
        $mensaje = "❌ El producto padre y hijo no pueden ser el mismo";
        $tipo_mensaje = "error";
    } elseif ($factor_conversion <= 0) {
        $mensaje = "❌ El factor de conversión debe ser mayor a 0";
        $tipo_mensaje = "error";
    } else {
        // 🔥 VERIFICAR SI YA EXISTE LA CONVERSIÓN
        $check = mysqli_query($conn, "SELECT id FROM conversion_unidades 
            WHERE producto_padre_codigo = '$producto_padre' 
            AND producto_hijo_codigo = '$producto_hijo' 
            AND nivel = $nivel 
            AND activo = 1");

        if (mysqli_num_rows($check) > 0) {
            $mensaje = "⚠️ Esta conversión ya está registrada";
            $tipo_mensaje = "warning";
        } else {
            // 🔥 INSERTAR CONVERSIÓN
            $sql = "INSERT INTO conversion_unidades 
                (producto_padre_codigo, producto_hijo_codigo, factor_conversion, nivel, usuario_id) 
                VALUES ('$producto_padre', '$producto_hijo', $factor_conversion, $nivel, $usuario_id)";

            if (mysqli_query($conn, $sql)) {
                $mensaje = "✅ Conversión registrada correctamente";
                $tipo_mensaje = "success";

                // 🔥 REGISTRAR ACTIVIDAD
                include("../login/registrar_actividad.php");
                registrar_actividad(
                    $conn,
                    $usuario_id,
                    $_SESSION["username"],
                    "CREAR",
                    "Regla de conversión registrada: $producto_padre → $producto_hijo",
                    "conversion_unidades",
                    mysqli_insert_id($conn)
                );
            } else {
                $mensaje = "❌ Error al registrar: " . mysqli_error($conn);
                $tipo_mensaje = "error";
            }
        }
    }
}

// 🔥 OBTENER PRODUCTOS PARA EL SELECT
$productos = mysqli_query($conn, "SELECT codigo_barra, nombre_producto, imagen, estado, cantidad 
    FROM productos WHERE estado IN ('activo', 'inactivo') ORDER BY nombre_producto ASC");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔄 Registrar Conversión - Los Nietos®</title>
    <link rel="icon" href="../../img/LOGIN/icon_24px.png">
    <link rel="stylesheet" href="../../css/Productos-STYLE/conversion2.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* CONTENEDOR PRINCIPAL */
        .conversion-container {
            margin: 120px auto 50px auto;
            max-width: 1200px;
            padding: 40px;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }

        /* HEADER */
        .conversion-header {
            text-align: center;
            padding: 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            margin-bottom: 40px;
            color: white;
        }

        .conversion-header h1 {
            font-family: 'Pacifico', cursive;
            font-size: 42px;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .conversion-header p {
            font-size: 18px;
            opacity: 0.95;
        }

        /* MENSAJES */
        .mensaje {
            padding: 20px 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            font-weight: bold;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .mensaje.success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 2px solid #28a745;
        }

        .mensaje.error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 2px solid #dc3545;
        }

        .mensaje.warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
            color: #856404;
            border: 2px solid #ffc107;
        }

        /* GRID DE PRODUCTOS */
        .productos-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        @media (max-width: 968px) {
            .productos-grid {
                grid-template-columns: 1fr;
            }
        }

        /* TARJETA DE PRODUCTO */
        .producto-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 25px;
            border: 2px solid #dee2e6;
            transition: all 0.3s ease;
        }

        .producto-card:hover {
            border-color: #667eea;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
        }

        .producto-card h3 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .producto-card h3 i {
            font-size: 24px;
        }

        /* SELECT */
        .producto-select {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            font-family: 'Kalam', cursive;
            font-size: 16px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .producto-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 15px rgba(102, 126, 234, 0.3);
        }

        /* VISTA PREVIA DEL PRODUCTO */
        .producto-preview {
            margin-top: 20px;
            background: white;
            border-radius: 12px;
            padding: 20px;
            display: none;
            border: 2px solid #667eea;
        }

        .producto-preview.activo {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .producto-imagen {
            width: 150px;
            height: 150px;
            border-radius: 12px;
            overflow: hidden;
            border: 3px solid #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            flex-shrink: 0;
        }

        .producto-imagen img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            /* <--- CAMBIA A 'CONTAIN' */
            background-color: #fff;
            /* Opcional: Fondo blanco para que se vea limpio */
            padding: 5px;
            /* Opcional: Un pequeño margen para que la imagen "respire" */
            transition: transform 0.3s ease;
        }

        .producto-imagen:hover img {
            transform: scale(1.1);
        }

        .producto-info {
            flex: 1;
        }

        .producto-info h4 {
            color: #1a1a2e;
            font-size: 22px;
            margin-bottom: 10px;
        }

        .producto-info p {
            color: #666;
            font-size: 16px;
            margin-bottom: 8px;
        }

        .producto-info .codigo {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .producto-info .stock {
            font-weight: bold;
            padding: 8px 15px;
            border-radius: 8px;
            display: inline-block;
        }

        .stock-alto {
            background: #d4edda;
            color: #155724;
        }

        .stock-medio {
            background: #fff3cd;
            color: #856404;
        }

        .stock-bajo {
            background: #f8d7da;
            color: #721c24;
        }

        /* FORMULARIO */
        .formulario-conversion {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 35px;
            border-radius: 15px;
            margin-top: 30px;
            border: 2px solid #dee2e6;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            color: #1a1a2e;
            margin-bottom: 10px;
            font-size: 17px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group label i {
            color: #667eea;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            font-family: 'Kalam', cursive;
            font-size: 16px;
            background: white;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 15px rgba(102, 126, 234, 0.3);
        }

        .form-group small {
            display: block;
            margin-top: 8px;
            color: #666;
            font-size: 14px;
            font-style: italic;
        }

        /* BOTONES */
        .botones-conversion {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 35px;
            flex-wrap: wrap;
        }

        .btn-conversion {
            padding: 16px 45px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: bold;
            font-size: 17px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-family: 'Kalam', cursive;
        }

        .btn-guardar {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-guardar:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.5);
        }

        .btn-cancelar {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }

        .btn-cancelar:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.5);
        }

        /* INFO ADICIONAL */
        .info-adicional {
            background: linear-gradient(135deg, #e7f3ff 0%, #d0e8ff 100%);
            padding: 25px;
            border-radius: 12px;
            margin-top: 30px;
            border-left: 5px solid #667eea;
        }

        .info-adicional h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-adicional ul {
            margin: 0;
            padding-left: 25px;
        }

        .info-adicional li {
            margin-bottom: 10px;
            color: #333;
            font-size: 15px;
            line-height: 1.6;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .barra-superior {
                padding: 0 20px;
            }

            .logo {
                font-size: 28px;
            }

            .conversion-container {
                margin: 100px 15px 50px 15px;
                padding: 25px;
            }

            .conversion-header h1 {
                font-size: 32px;
            }

            .producto-imagen {
                width: 120px;
                height: 120px;
            }
        }
    </style>
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

    <!-- CONTENIDO PRINCIPAL -->
    <div class="conversion-container">
        <!-- HEADER -->
        <div class="conversion-header">
            <h1>🔄 Registrar Regla de Conversión</h1>
            <p>Define cómo se desglosan los productos en unidades menores</p>
        </div>

        <!-- MENSAJE -->
        <?php if (!empty($mensaje)): ?>
            <div class="mensaje <?= $tipo_mensaje ?>">
                <i
                    class="bi bi-<?= $tipo_mensaje == 'success' ? 'check-circle' : ($tipo_mensaje == 'error' ? 'x-circle' : 'exclamation-triangle') ?>"></i>
                <span><?= $mensaje ?></span>
            </div>
        <?php endif; ?>

        <!-- FORMULARIO -->
        <form method="POST" id="formConversion">
            <!-- GRID DE PRODUCTOS -->
            <div class="productos-grid">
                <!-- PRODUCTO PADRE -->
                <div class="producto-card">
                    <h3><i class="bi bi-box-seam"></i> Producto Padre (A desglosar)</h3>
                    <select name="producto_padre" id="producto_padre" class="producto-select" required
                        onchange="mostrarPreview('padre')">
                        <option value="">-- Seleccionar Producto Padre --</option>
                        <?php
                        mysqli_data_seek($productos, 0);
                        while ($prod = mysqli_fetch_assoc($productos)):
                            $clase_stock = $prod['cantidad'] >= 50 ? 'stock-alto' : ($prod['cantidad'] >= 20 ? 'stock-medio' : 'stock-bajo');
                            ?>
                            <option value="<?= $prod['codigo_barra'] ?>"
                                data-nombre="<?= htmlspecialchars($prod['nombre_producto']) ?>"
                                data-imagen="<?= $prod['imagen'] ?>" data-estado="<?= $prod['estado'] ?>"
                                data-cantidad="<?= $prod['cantidad'] ?>">
                                <?= htmlspecialchars($prod['nombre_producto']) ?> (<?= $prod['codigo_barra'] ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>

                    <!-- VISTA PREVIA PADRE -->
                    <div id="preview_padre" class="producto-preview">
                        <div class="producto-imagen">
                            <img id="img_padre" src="" alt="Producto Padre">
                        </div>
                        <div class="producto-info">
                            <span class="codigo" id="codigo_padre"></span>
                            <h4 id="nombre_padre"></h4>
                            <p id="estado_padre"></p>
                            <p id="stock_padre" class="stock"></p>
                        </div>
                    </div>
                </div>

                <!-- PRODUCTO HIJO -->
                <div class="producto-card">
                    <h3><i class="bi bi-box"></i> Producto Hijo (Resultado del desglose)</h3>
                    <select name="producto_hijo" id="producto_hijo" class="producto-select" required
                        onchange="mostrarPreview('hijo')">
                        <option value="">-- Seleccionar Producto Hijo --</option>
                        <?php
                        mysqli_data_seek($productos, 0);
                        while ($prod = mysqli_fetch_assoc($productos)):
                            $clase_stock = $prod['cantidad'] >= 50 ? 'stock-alto' : ($prod['cantidad'] >= 20 ? 'stock-medio' : 'stock-bajo');
                            ?>
                            <option value="<?= $prod['codigo_barra'] ?>"
                                data-nombre="<?= htmlspecialchars($prod['nombre_producto']) ?>"
                                data-imagen="<?= $prod['imagen'] ?>" data-estado="<?= $prod['estado'] ?>"
                                data-cantidad="<?= $prod['cantidad'] ?>">
                                <?= htmlspecialchars($prod['nombre_producto']) ?> (<?= $prod['codigo_barra'] ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>

                    <!-- VISTA PREVIA HIJO -->
                    <div id="preview_hijo" class="producto-preview">
                        <div class="producto-imagen">
                            <img id="img_hijo" src="" alt="Producto Hijo">
                        </div>
                        <div class="producto-info">
                            <span class="codigo" id="codigo_hijo"></span>
                            <h4 id="nombre_hijo"></h4>
                            <p id="estado_hijo"></p>
                            <p id="stock_hijo" class="stock"></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FORMULARIO DE CONVERSIÓN -->
            <div class="formulario-conversion">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="bi bi-hash"></i> Factor de Conversión (Cuántos hijos por 1 padre)</label>
                        <input type="number" name="factor_conversion" id="factor_conversion" step="0.01" min="0.01"
                            required placeholder="Ej: 10 (1 padre = 10 hijos)">
                        <small>💡 Ejemplo: Si 1 caja contiene 10 paquetes, ingresa 10</small>
                    </div>

                    <div class="form-group">
                        <label><i class="bi bi-bar-chart"></i> Nivel de Conversión</label>
                        <select name="nivel" id="nivel" required>
                            <option value="1">Nivel 1 (Padre → Hijo 1)</option>
                            <option value="2">Nivel 2 (Hijo 1 → Hijo 2)</option>
                            <option value="3">Nivel 3 (Hijo 2 → Hijo 3)</option>
                        </select>
                        <small>📊 Nivel 1: Caja → Paquete | Nivel 2: Paquete → Unidad individual</small>
                    </div>
                </div>

                <!-- BOTONES -->
                <div class="botones-conversion">
                    <button type="submit" class="btn-conversion btn-guardar">
                        <i class="bi bi-check-circle"></i> Guardar Conversión
                    </button>
                    <a href="CRUD-Product.php" class="btn-conversion btn-cancelar">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </a>
                </div>
            </div>

            <!-- INFORMACIÓN ADICIONAL -->
            <div class="info-adicional">
                <h3><i class="bi bi-info-circle"></i> Información Importante</h3>
                <ul>
                    <li>✅ <strong>Productos inactivos</strong> pueden usarse en conversiones (solo no se pueden vender
                        directamente)</li>
                    <li>✅ Cada producto debe tener su <strong>código de barras único</strong></li>
                    <li>✅ El <strong>factor de conversión</strong> define cuántas unidades hijas contiene 1 unidad padre
                    </li>
                    <li>✅ Puedes tener <strong>múltiples niveles</strong> de conversión (hasta 3 niveles)</li>
                    <li>✅ Solo usuarios <strong>ADMIN</strong> pueden registrar conversiones</li>
                </ul>
            </div>
        </form>
    </div>

    <!-- BARRA INFERIOR -->
    <div class="barra-inferior"></div>

    <!-- SCRIPTS -->
    <script>
        function mostrarPreview(tipo) {
            const select = document.getElementById('producto_' + tipo);
            const preview = document.getElementById('preview_' + tipo);
            const option = select.options[select.selectedIndex];

            if (select.value) {
                const nombre = option.getAttribute('data-nombre');
                const imagen = option.getAttribute('data-imagen');
                const estado = option.getAttribute('data-estado');
                const cantidad = option.getAttribute('data-cantidad');

                // 🔥 IMAGEN - RUTA CORREGIDA
                const rutaImagen = imagen ? '../../img/productos/' + imagen : '../../img/LOGIN/icon_24px.png';
                document.getElementById('img_' + tipo).src = rutaImagen;

                // 🔥 CÓDIGO
                document.getElementById('codigo_' + tipo).textContent = 'Código: ' + select.value;

                // 🔥 NOMBRE
                document.getElementById('nombre_' + tipo).textContent = nombre;

                // 🔥 ESTADO
                document.getElementById('estado_' + tipo).textContent = 'Estado: ' + (estado === 'activo' ? '✅ Activo' : '⚠️ Inactivo');

                // 🔥 STOCK
                const stockElement = document.getElementById('stock_' + tipo);
                const stockClass = cantidad >= 50 ? 'stock-alto' : (cantidad >= 20 ? 'stock-medio' : 'stock-bajo');
                stockElement.className = 'stock ' + stockClass;
                stockElement.textContent = '📦 Stock: ' + cantidad + ' unidades';

                // 🔥 MOSTRAR PREVIEW
                preview.classList.add('activo');
            } else {
                preview.classList.remove('activo');
            }
        }

        // 🔥 VALIDAR QUE PADRE Y HIJO NO SEAN IGUALES
        document.getElementById('formConversion').addEventListener('submit', function (e) {
            const padre = document.getElementById('producto_padre').value;
            const hijo = document.getElementById('producto_hijo').value;

            if (padre === hijo) {
                e.preventDefault();
                alert('❌ El producto padre y hijo no pueden ser el mismo');
                return false;
            }

            // 🔥 CONFIRMACIÓN
            if (!confirm('✅ ¿Confirmar registro de conversión?\n\nEsta regla permitirá desglosar el producto padre en unidades del producto hijo.')) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>

</html>