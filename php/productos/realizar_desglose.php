<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../../index.html");
    exit;
}
include("../conexion.php");
$mensaje = "";
$tipo_mensaje = "";

// 🔥 PROCESAR DESGLOSE
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $producto_padre = mysqli_real_escape_string($conn, $_POST['producto_padre']);
    $cantidad_padre = floatval($_POST['cantidad_padre']);
    $usuario_id = $_SESSION["user_id"];
    $observaciones = mysqli_real_escape_string($conn, $_POST['observaciones']);

    // 🔥 OBTENER CONVERSIONES REGISTRADAS
    $conversiones = [];
    $sql = "SELECT cu.*, p1.nombre_producto as nombre_padre, p2.nombre_producto as nombre_hijo, p1.imagen as imagen_padre, p2.imagen as imagen_hijo
            FROM conversion_unidades cu
            INNER JOIN productos p1 ON cu.producto_padre_codigo = p1.codigo_barra
            INNER JOIN productos p2 ON cu.producto_hijo_codigo = p2.codigo_barra
            WHERE cu.producto_padre_codigo = '$producto_padre' AND cu.activo = 1
            ORDER BY cu.nivel ASC";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $conversiones[] = $row;
    }

    if (empty($conversiones)) {
        $mensaje = "❌ No hay conversiones registradas para este producto";
        $tipo_mensaje = "error";
    } else {
        // 🔥 VERIFICAR STOCK DEL PADRE
        $check_stock = mysqli_query($conn, "SELECT cantidad FROM productos WHERE codigo_barra = '$producto_padre'");
        $stock = mysqli_fetch_assoc($check_stock);
        if ($stock['cantidad'] < $cantidad_padre) {
            $mensaje = "❌ Stock insuficiente. Disponible: " . $stock['cantidad'];
            $tipo_mensaje = "error";
        } else {
            // 🔥 REALIZAR DESGLOSE
            mysqli_begin_transaction($conn);
            $success = true;
            try {
                foreach ($conversiones as $conv) {
    $cantidad_hijo = $cantidad_padre * $conv['factor_conversion'];
    
    // 🔥 1. OBTENER PRECIO DE COMPRA DEL PADRE (Desde tabla LOTES)
    // Buscamos el precio del lote más reciente del producto padre
    $sql_precio_padre = "SELECT precio_compra FROM lotes 
                         WHERE codigo_barra = '{$conv['producto_padre_codigo']}' 
                         ORDER BY id DESC LIMIT 1";
    $res_precio_padre = mysqli_query($conn, $sql_precio_padre);
    $datos_precio_padre = mysqli_fetch_assoc($res_precio_padre);
    
    // Si no hay precio en lotes, usamos 0
    $precio_compra_padre = ($datos_precio_padre) ? floatval($datos_precio_padre['precio_compra']) : 0;

    // 🔥 2. CALCULAR PRECIO DE COMPRA DEL HIJO
    // Fórmula: Precio Padre / Factor de Conversión
    // Ejemplo: Si el padre costó 350 y se divide en 10, el hijo cuesta 35.
    $precio_compra_hijo = ($conv['factor_conversion'] > 0) ? ($precio_compra_padre / $conv['factor_conversion']) : 0;

    // 🔥 3. OBTENER PRECIO DE VENTA DEL HIJO (Para calcular ganancias)
    $sql_precio_venta_hijo = "SELECT precio_venta FROM productos WHERE codigo_barra = '{$conv['producto_hijo_codigo']}'";
    $res_venta = mysqli_query($conn, $sql_precio_venta_hijo);
    $datos_venta = mysqli_fetch_assoc($res_venta);
    $precio_venta_hijo = ($datos_venta) ? floatval($datos_venta['precio_venta']) : 0;

    // 🔥 4. CALCULAR GANANCIAS DEL NUEVO LOTE
    $ganancia_unitaria = $precio_venta_hijo - $precio_compra_hijo;
    $ganancia_lote_total = $ganancia_unitaria * $cantidad_hijo;
    
    // Porcentaje de ganancia (evitamos división por cero)
    $porcentaje_ganancia = ($precio_compra_hijo > 0) ? (($ganancia_unitaria / $precio_compra_hijo) * 100) : 0;

    // 🔥 5. DESCONTAR STOCK DEL PADRE
    $sql_desc = "UPDATE productos SET cantidad = cantidad - $cantidad_padre
                 WHERE codigo_barra = '{$conv['producto_padre_codigo']}'";
    mysqli_query($conn, $sql_desc);
    
    // 🔥 6. AGREGAR STOCK AL HIJO
    $sql_add = "UPDATE productos SET cantidad = cantidad + $cantidad_hijo
                WHERE codigo_barra = '{$conv['producto_hijo_codigo']}'";
    mysqli_query($conn, $sql_add);

    // 🔥 7. INSERTAR NUEVO LOTE EN TABLA LOTES CON EL PRECIO CALCULADO
    $sql_lote = "INSERT INTO lotes 
                 (codigo_barra, cantidad, precio_compra, ganancia_lote, porcentaje_ganancia, proveedor, es_de_compra)
                 VALUES (
                    '{$conv['producto_hijo_codigo']}', 
                    $cantidad_hijo, 
                    $precio_compra_hijo,
                    $ganancia_lote_total,
                    $porcentaje_ganancia,
                    'CONVERSIÓN', 
                    0
                 )";
    mysqli_query($conn, $sql_lote);

    // 🔥 8. ACTUALIZAR TOTAL_COMPRA EN TABLA PRODUCTOS
    // Sumamos al total de compra existente el valor del nuevo lote ingresado
    $valor_nuevo_lote = $cantidad_hijo * $precio_compra_hijo;
    $sql_update_producto = "UPDATE productos 
                            SET total_compra = total_compra + $valor_nuevo_lote
                            WHERE codigo_barra = '{$conv['producto_hijo_codigo']}'";
    mysqli_query($conn, $sql_update_producto);
    
    // 🔥 9. REGISTRAR EN HISTORIAL
    $sql_hist = "INSERT INTO historial_desgloses
                 (producto_padre_codigo, cantidad_padre, producto_hijo_codigo,
                  cantidad_hijo, nivel, usuario_id, observaciones)
                 VALUES ('{$conv['producto_padre_codigo']}', $cantidad_padre,
                         '{$conv['producto_hijo_codigo']}', $cantidad_hijo,
                         {$conv['nivel']}, $usuario_id, '$observaciones')";
    mysqli_query($conn, $sql_hist);
}
                mysqli_commit($conn);
                $mensaje = "✅ Desglose realizado correctamente";
                $tipo_mensaje = "success";

                // 🔥 REGISTRAR ACTIVIDAD
                include("../login/registrar_actividad.php");
                registrar_actividad(
                    $conn,
                    $usuario_id,
                    $_SESSION["username"],
                    "DESGLOSE",
                    "Desglose realizado: $cantidad_padre unidades de {$conversiones[0]['nombre_padre']}",
                    "historial_desgloses",
                    mysqli_insert_id($conn)
                );
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $mensaje = "❌ Error: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    }
}

// 🔥 OBTENER PRODUCTOS CON CONVERSIONES
$productos_conversion = mysqli_query($conn, "SELECT DISTINCT p.codigo_barra, p.nombre_producto, p.imagen, p.cantidad
                                            FROM productos p
                                            INNER JOIN conversion_unidades cu ON p.codigo_barra = cu.producto_padre_codigo
                                            WHERE cu.activo = 1
                                            ORDER BY p.nombre_producto ASC");
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✂️ Realizar Desglose - Los Nietos®</title>
    <link rel="icon" href="../../img/LOGIN/icon_24px.png">
    <link rel="stylesheet" href="../../css/Productos-STYLE/conversion2.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Kalam:wght@300;400;700&family=Pacifico&display=swap"
        rel="stylesheet">
    <style>
        /* 🔥 CONTENEDOR PRINCIPAL */
        .conversion-container {
            margin: 120px auto 50px auto;
            max-width: 1200px;
            padding: 40px;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }

        /* 🔥 HEADER */
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

        /* 🔥 MENSAJES */
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

        /* 🔥 FORMULARIO */
        .formulario-conversion {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 35px;
            border-radius: 15px;
            margin-top: 30px;
            border: 2px solid #dee2e6;
        }

        .form-group {
            margin-bottom: 25px;
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

        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            font-family: 'Kalam', cursive;
            font-size: 16px;
            background: white;
            transition: all 0.3s ease;
        }

        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
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

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        /* 🔥 INFO DE CONVERSIONES */
        .conversion-info {
            background: #fff;
            padding: 25px;
            border-radius: 15px;
            border: 2px solid #667eea;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.15);
        }

        .conversion-info h3 {
            margin-top: 0;
            color: #667eea;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .conversion-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 12px;
            margin-bottom: 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .conversion-item:hover {
            border-color: #667eea;
            transform: translateX(5px);
        }

        .conversion-item img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 10px;
            border: 3px solid #667eea;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .conversion-item .info {
            flex: 1;
        }

        .conversion-item .info strong {
            display: block;
            color: #1a1a2e;
            font-size: 18px;
            margin-bottom: 5px;
        }

        .conversion-item .info small {
            color: #666;
            font-size: 14px;
        }

        .conversion-item .arrow {
            font-size: 28px;
            color: #667eea;
            font-weight: bold;
            margin: 0 10px;
        }

        .conversion-item .factor {
            font-weight: bold;
            color: #764ba2;
            font-size: 20px;
            background: #f0e6ff;
            padding: 8px 15px;
            border-radius: 8px;
        }

        /* 🔥 RESULTADO PREVIEW */
        .resultado-desglose {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            padding: 25px;
            border-radius: 12px;
            border: 2px solid #28a745;
            margin-top: 25px;
        }

        .resultado-desglose h3 {
            margin-top: 0;
            color: #155724;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .resultado-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 15px;
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #28a745;
        }

        .resultado-item strong {
            color: #1a1a2e;
        }

        .resultado-item span {
            color: #28a745;
            font-weight: bold;
            font-size: 18px;
        }

        /* 🔥 BOTONES */
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

        /* 🔥 RESPONSIVE */
        @media (max-width: 768px) {
            .conversion-container {
                margin: 100px 15px 50px 15px;
                padding: 25px;
            }

            .conversion-header h1 {
                font-size: 32px;
            }

            .conversion-item {
                flex-direction: column;
                text-align: center;
            }

            .conversion-item .arrow {
                transform: rotate(90deg);
                margin: 10px 0;
            }

            .botones-conversion {
                flex-direction: column;
            }

            .btn-conversion {
                width: 100%;
                justify-content: center;
            }
        }

        /* 🔥 ESTILOS PARA VALIDACIÓN DE STOCK */
        .input-error {
            border-color: #dc3545 !important;
            /* Borde rojo */
            background-color: #fff5f5 !important;
            /* Fondo muy claro rojizo */
            box-shadow: 0 0 0 4px rgba(220, 53, 69, 0.2) !important;
            /* Sombra suave */
        }

        .error-msg-stock {
            color: #dc3545;
            font-size: 14px;
            font-weight: bold;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            height: 0;
            overflow: hidden;
        }

        .error-msg-stock.visible {
            opacity: 1;
            transform: translateY(0);
            height: auto;
            margin-bottom: 15px;
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
            <a href="../productos/CRUD-Product.php" class="icon-btn">
                <i class="bi bi-box-seam"></i>
                <span class="tooltip">PRODUCTOS</span>
            </a>
            <a href="realizar_desglose.php" class="icon-btn active">
                <i class="bi bi-scissors"></i>
                <span class="tooltip">DESGLOSE</span>
            </a>
            <a href="../login/logout.php" class="icon-btn">
                <i class="bi bi-box-arrow-right"></i>
                <span class="tooltip">SALIR</span>
            </a>
        </div>
    </div>

    <!-- CONTENIDO PRINCIPAL -->
    <div class="conversion-container">
        <!-- HEADER -->
        <div class="conversion-header">
            <h1>✂️ Realizar Desglose de Productos</h1>
            <p>Convierte productos padres en unidades hijas según las reglas registradas</p>
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
        <form class="formulario-conversion" method="POST" id="formDesglose">
            <!-- SELECT PRODUCTO -->
            <div class="form-group">
                <label><i class="bi bi-box-seam"></i> Seleccionar Producto a Desglosar:</label>
                <select name="producto_padre" id="producto_padre" required onchange="cargarConversiones()">
                    <option value="">-- Seleccionar Producto Padre --</option>
                    <?php while ($prod = mysqli_fetch_assoc($productos_conversion)): ?>
                        <option value="<?= $prod['codigo_barra'] ?>" data-stock="<?= $prod['cantidad'] ?>">
                            <?= htmlspecialchars($prod['nombre_producto']) ?> (Stock: <?= $prod['cantidad'] ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- INFO DE CONVERSIONES -->
            <div id="conversiones_info" class="conversion-info" style="display: none;">
                <h3><i class="bi bi-arrow-repeat"></i> Conversiones Registradas:</h3>
                <div id="lista_conversiones"></div>
            </div>

            <!-- CANTIDAD -->
            <div class="form-group">
                <label>🔢 Cantidad a Desglosar:</label>
                <input type="number" name="cantidad_padre" id="cantidad_padre" step="1" min="1" required
                    placeholder="Ej: 2" oninput="validarStockInput()">

                <!-- 🔥 CONTENEDOR PARA EL MENSAJE DE ERROR PERSONALIZADO -->
                <div id="msg_error_stock" class="error-msg-stock">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span id="texto_error_stock"></span>
                </div>

                <small style="color: #666; display: block; margin-top: 5px;">
                    📦 Máximo disponible: <span id="stock_disponible">0</span> unidades
                </small>
            </div>

            <!-- OBSERVACIONES -->
            <div class="form-group">
                <label><i class="bi bi-chat-square-text"></i> Observaciones (Opcional):</label>
                <textarea name="observaciones" rows="3"
                    placeholder="Motivo del desglose, referencia, etc..."></textarea>
            </div>

            <!-- RESULTADO PREVIEW -->
            <div id="resultado_preview" class="resultado-desglose" style="display: none;">
                <h3><i class="bi bi-calculator"></i> Resultado del Desglose:</h3>
                <div id="resultado_detalle"></div>
            </div>

            <!-- BOTONES -->
            <div class="botones-conversion">
                <button type="submit" class="btn-conversion btn-guardar">
                    <i class="bi bi-check-circle"></i> Confirmar Desglose
                </button>
                <a href="../productos/CRUD-Product.php" class="btn-conversion btn-cancelar">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
            </div>
        </form>

        <!-- INFO ADICIONAL -->
        <div class="info-adicional" style="margin-top: 30px;">
            <h3><i class="bi bi-info-circle"></i> Información Importante</h3>
            <ul>
                <li>✅ El stock del <strong>producto padre</strong> se reducirá automáticamente</li>
                <li>✅ El stock del <strong>producto hijo</strong> aumentará según el factor de conversión</li>
                <li>✅ Se registra automáticamente en el <strong>historial de desgloses</strong></li>
                <li>✅ Puedes ver el resultado antes de confirmar el desglose</li>
            </ul>
        </div>
    </div>

    <!-- BARRA INFERIOR -->
    <div class="barra-inferior"></div>

    <!-- SCRIPTS -->
    <script>
        function cargarConversiones() {
            const producto = document.getElementById('producto_padre').value;
            const selectElement = document.getElementById('producto_padre');
            const stock = selectElement.options[selectElement.selectedIndex].getAttribute('data-stock') || 0;

            document.getElementById('stock_disponible').textContent = stock;
            document.getElementById('cantidad_padre').max = stock;
            document.getElementById('cantidad_padre').value = '';
            document.getElementById('resultado_preview').style.display = 'none';

            if (producto) {
                fetch('obtener_conversiones.php?producto=' + producto)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length > 0) {
                            let html = '';
                            data.forEach(conv => {
                                const imgPadre = conv.imagen_padre ? '../../img/productos/' + conv.imagen_padre : '../../../img/LOGIN/icon_24px.png';
                                const imgHijo = conv.imagen_hijo ? '../../img/productos/' + conv.imagen_hijo : '../../../img/LOGIN/icon_24px.png';

                                html += `
                                <div class="conversion-item">
                                    <img src="${imgPadre}" alt="Padre">
                                    <div class="info">
                                        <strong>${conv.nombre_padre}</strong>
                                        <small>Código: ${conv.producto_padre_codigo}</small>
                                    </div>
                                    <div class="arrow">→</div>
                                    <div class="info">
                                        <strong>${conv.nombre_hijo}</strong>
                                        <small>Código: ${conv.producto_hijo_codigo}</small>
                                    </div>
                                    <div class="factor">× ${conv.factor_conversion}</div>
                                </div>`;
                            });
                            document.getElementById('lista_conversiones').innerHTML = html;
                            document.getElementById('conversiones_info').style.display = 'block';
                        } else {
                            document.getElementById('conversiones_info').style.display = 'none';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('conversiones_info').style.display = 'none';
                    });
            } else {
                document.getElementById('conversiones_info').style.display = 'none';
            }
        }

        document.getElementById('cantidad_padre').addEventListener('input', function () {
            const cantidad = parseFloat(this.value) || 0;
            const producto = document.getElementById('producto_padre').value;

            if (producto && cantidad > 0) {
                fetch('obtener_conversiones.php?producto=' + producto)
                    .then(response => response.json())
                    .then(data => {
                        let html = '';
                        data.forEach(conv => {
                            const cantidad_hijo = cantidad * conv.factor_conversion;
                            html += `
                            <div class="resultado-item">
                                <strong>${conv.nombre_hijo}</strong>
                                <span>${cantidad_hijo} unidades</span>
                            </div>`;
                        });
                        document.getElementById('resultado_detalle').innerHTML = html;
                        document.getElementById('resultado_preview').style.display = 'block';
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('resultado_preview').style.display = 'none';
                    });
            } else {
                document.getElementById('resultado_preview').style.display = 'none';
            }
        });

        // 🔥 VALIDACIÓN AL ENVIAR
        document.getElementById('formDesglose').addEventListener('submit', function (e) {
            const cantidad = parseFloat(document.getElementById('cantidad_padre').value);
            const stock = parseFloat(document.getElementById('stock_disponible').textContent);

            if (cantidad > stock) {
                e.preventDefault();
                alert('❌ La cantidad excede el stock disponible');
                return false;
            }

            if (!confirm('✅ ¿Confirmar desglose de productos?\n\nEsta acción modificará los stocks y no se puede deshacer automáticamente.')) {
                e.preventDefault();
                return false;
            }
        });

        // 🔥 FUNCIÓN PARA VALIDAR STOCK EN TIEMPO REAL
        function validarStockInput() {
            const input = document.getElementById('cantidad_padre');
            const valorIngresado = parseFloat(input.value) || 0;
            const maxStock = parseFloat(input.getAttribute('max')) || 0;

            const msgError = document.getElementById('msg_error_stock');
            const textoError = document.getElementById('texto_error_stock');

            // Si el valor ingresado es mayor al máximo permitido
            if (valorIngresado > maxStock) {
                // 🔴 MOSTRAR ERROR
                input.classList.add('input-error');
                textoError.textContent = `⚠️ Solo hay ${maxStock} unidades disponibles en inventario.`;
                msgError.classList.add('visible');

                // Bloquear el botón de envío para que no lo envíen por accidente
                document.querySelector('.btn-guardar').disabled = true;
                document.querySelector('.btn-guardar').style.opacity = '0.5';
                document.querySelector('.btn-guardar').style.cursor = 'not-allowed';
            } else {
                // ✅ QUITAR ERROR
                input.classList.remove('input-error');
                msgError.classList.remove('visible');

                // Desbloquear el botón
                document.querySelector('.btn-guardar').disabled = false;
                document.querySelector('.btn-guardar').style.opacity = '1';
                document.querySelector('.btn-guardar').style.cursor = 'pointer';
            }
        }
    </script>
</body>

</html>