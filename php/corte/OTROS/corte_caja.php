<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../../index.html");
    exit;
}
include("../conexion.php");

// 🔥 DEFINIR VARIABLES DE SESIÓN (AGREGAR ESTO)
$usuario_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// 🔥 VERIFICAR SI YA HAY UN CORTE RECIENTE (últimos 5 segundos)
$corteReciente = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT * FROM cortes_caja 
    WHERE usuario_id = {$_SESSION['user_id']} 
    AND fecha_corte = CURDATE()
    AND TIMESTAMPDIFF(SECOND, fecha_registro, NOW()) < 5
    ORDER BY id DESC LIMIT 1
"));

if ($corteReciente) {
    // Redirigir al historial si hay un corte muy reciente
    header("Location: historial_corte.php?mensaje=corte_reciente");
    exit;
}

// 🔥 VERIFICAR SI USUARIO YA TIENE CORTE HOY
$corteHoy = mysqli_fetch_assoc(mysqli_query($conn, "
SELECT * FROM cortes_caja
WHERE usuario_id = {$_SESSION['user_id']}
AND fecha_corte = CURDATE()
ORDER BY hora_corte DESC LIMIT 1
"));

// 🔥 INICIALIZAR VARIABLES DE EDICIÓN (MOVER ESTO ANTES DEL CÁLCULO)
$modoEdicion = false;
$corteEditar = null;

// 🔥 MODO EDICIÓN
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $editId = (int) $_GET['edit_id'];
    $corteEditar = mysqli_fetch_assoc(mysqli_query($conn, "
SELECT * FROM cortes_caja WHERE id = $editId AND usuario_id = {$_SESSION['user_id']}
"));
    if ($corteEditar) {
        $modoEdicion = true;
    }
}

// 🔥 CALCULAR CAJA INICIAL DEFAULT PARA ESTE CORTE (AHORA $modoEdicion YA EXISTE)
if ($corteHoy && !$modoEdicion) {
    // Segundo corte en adelante - default = efectivo del corte anterior
    $cajaInicialDefault = $corteHoy['total_efectivo_contado'];
    $esPrimerCorte = false;
} else {
    // Primer corte del día - default = 0.00 (usuario ingresa manualmente)
    $cajaInicialDefault = 0.00;
    $esPrimerCorte = true;
}

// 🔥 MODO EDICIÓN
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $editId = (int) $_GET['edit_id'];
    $corteEditar = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT * FROM cortes_caja WHERE id = $editId AND usuario_id = {$_SESSION['user_id']}
    "));
    if ($corteEditar) {
        $modoEdicion = true;
    }
}

// 🔥 CALCULAR VENTAS DEL PERIODO
if ($corteHoy && !$modoEdicion) {
    // Ya hay corte hoy - ventas desde ese corte
    $fechaDesde = $corteHoy['fecha_corte'] . ' ' . $corteHoy['hora_corte'];
    $ventasHoy = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COUNT(*) as total_ventas, COALESCE(SUM(total_venta), 0) as monto_total
        FROM ventas
        WHERE fecha_venta > '$fechaDesde' AND estado = 'completada'
    "));

    $mensajePeriodo = "📍 Ventas desde las " . date('h:i A', strtotime($corteHoy['hora_corte']));
} else {
    // Primer corte del día o edición
    $ventasHoy = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COUNT(*) as total_ventas, COALESCE(SUM(total_venta), 0) as monto_total
        FROM ventas
        WHERE DATE(fecha_venta) = CURDATE() AND estado = 'completada'
    "));

    $mensajePeriodo = "📍 Ventas desde las 00:00 AM";
}

// 🔥 OBTENER COMPRAS DEL PERIODO (CON VALIDACIÓN)
$totalCompras = 0;
$listaCompras = [];

if ($corteHoy && !$modoEdicion) {
    $fechaDesde = $corteHoy['fecha_corte'] . ' ' . $corteHoy['hora_corte'];
    $comprasHoy = mysqli_query($conn, "
        SELECT p.nombre as proveedor, SUM(c.total_compra) as total_comprado
        FROM compras_cabecera c
        JOIN proveedores p ON c.proveedor_id = p.id
        WHERE c.fecha_compra > '$fechaDesde'
        GROUP BY p.id
    ");
} else {
    $comprasHoy = mysqli_query($conn, "
        SELECT p.nombre as proveedor, SUM(c.total_compra) as total_comprado
        FROM compras_cabecera c
        JOIN proveedores p ON c.proveedor_id = p.id
        WHERE DATE(c.fecha_compra) = CURDATE()
        GROUP BY p.id
    ");
}

// 🔥 VALIDAR QUE LA CONSULTA NO FALLÓ
if ($comprasHoy) {
    while ($row = mysqli_fetch_assoc($comprasHoy)) {
        $listaCompras[] = $row;
        $totalCompras += $row['total_comprado'];
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💰
        <?= $modoEdicion ? 'Editar' : 'Nuevo' ?> Corte de Caja - Los Nietos®
    </title>
    <link rel="stylesheet" href="../../css/Corte-STYLE/Corte_Caja.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Kalam:wght@300;400;700&display=swap"
        rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f5f5;
            font-family: 'Kalam', cursive;
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
        }

        .logo {
            font-family: 'Pacifico', cursive;
            font-size: 32px;
        }

        .iconos {
            display: flex;
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
            text-decoration: none;
        }

        .icon-btn i {
            font-size: 26px;
            color: #000;
        }

        .container {
            max-width: 1400px;
            margin: 100px auto;
            padding: 30px;
        }

        .resumen-corte {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card-resumen {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .card-resumen h4 {
            color: #666;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .card-resumen p {
            font-size: 32px;
            font-weight: bold;
        }

        .seccion {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .seccion h2 {
            color: #333;
            margin-bottom: 20px;
            border-bottom: 3px solid #0d6efd;
            padding-bottom: 10px;
        }

        /* CONTEO DE DINERO */
        .grid-billetes {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }

        .input-dinero {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #ddd;
        }

        .input-dinero label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .input-dinero input {
            width: 100%;
            padding: 10px;
            font-size: 18px;
            text-align: center;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        .input-dinero .subtotal {
            display: block;
            margin-top: 8px;
            text-align: center;
            color: #666;
        }

        .validacion {
            display: flex;
            justify-content: space-between;
            padding: 20px;
            background: #e7f3ff;
            border-radius: 10px;
            margin-top: 20px;
        }

        .validacion .monto {
            font-size: 28px;
            font-weight: bold;
        }

        .estado {
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: bold;
        }

        .estado.cuadrado {
            background: #d4edda;
            color: #155724;
        }

        .estado.faltante {
            background: #f8d7da;
            color: #721c24;
        }

        .estado.sobrante {
            background: #fff3cd;
            color: #856404;
        }

        .btn-guardar {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-guardar:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        .alerta-edicion {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>

    <!-- BARRA SUPERIOR -->
    <div class="barra-superior">
        <div class="logo">Los Nietos®</div>
        <div class="iconos">
            <a href="../login/dashboard_admin.php" class="icon-btn">
                <i class="bi bi-house-fill"></i>
                <span class="tooltip">INICIO</span>
            </a>
            <a href="CRUD-Usuarios.php" class="icon-btn">
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
        <h1 style="text-align:center; margin-bottom:30px;">
            💰
            <?= $modoEdicion ? '✏️ Editar' : '🆕 Nuevo' ?> Corte de Caja -
            <?= date('d/m/Y') ?>
        </h1>

        <?php if ($modoEdicion): ?>
            <div class="alerta-edicion">
                ⚠️ <strong>Modo Edición:</strong> Estás modificando el corte #
                <?= $corteEditar['id'] ?> realizado a las
                <?= date('h:i A', strtotime($corteEditar['hora_corte'])) ?>.
                Los datos anteriores se sobrescribirán.
            </div>
        <?php endif; ?>

        <div class="resumen-corte">
            <!-- CAJA INICIAL -->
            <!-- CAJA INICIAL (SIEMPRE EDITABLE) -->
            <div class="card-resumen" style="border:2px solid #0d6efd;">
                <h4>💰 Caja Inicial</h4>
                <input type="number" name="caja_inicial" id="caja_inicial"
                    value="<?= $modoEdicion && $corteEditar ? number_format($corteEditar['caja_inicial'] ?? 0, 2) : number_format($cajaInicialDefault, 2) ?>"
                    step="0.01" min="0"
                    style="width:100%; font-size:24px; text-align:center; border:none; background:transparent; color:#0d6efd; font-weight:bold; font-family:'Kalam', cursive;"
                    onchange="calcularTotal()" placeholder="0.00">
                <?php if (!$esPrimerCorte && !$modoEdicion): ?>
                    <small style="color:#28a745;">💡 Sug: $<?= number_format($cajaInicialDefault, 2) ?> (efectivo del corte
                        anterior)</small>
                <?php else: ?>
                    <small style="color:#666;">Ingresa con cuanto inicias</small>
                <?php endif; ?>
            </div>

            <!-- VENTAS -->
            <div class="card-resumen" style="border:2px solid #28a745;">
                <h4>💰 Ventas</h4>
                <p style="color:#28a745; font-size:24px;" id="ventasDisplay">
                    $<?= number_format($ventasHoy['monto_total'], 2) ?></p>
                <small style="color:#666;">Del sistema</small>
            </div>

            <!-- COMPRAS -->
            <div class="card-resumen" style="border:2px solid #dc3545;">
                <h4>🛒 Compras</h4>
                <p style="color:#dc3545; font-size:24px;" id="comprasDisplay">$<?= number_format($totalCompras, 2) ?>
                </p>
                <small style="color:#666;">A proveedores</small>
            </div>
        </div>

        <form id="formCorteCaja">
            <input type="hidden" name="modo" value="<?= $modoEdicion ? 'editar' : 'crear' ?>">
            <?php if ($modoEdicion): ?>
                <input type="hidden" name="corte_id" value="<?= $corteEditar['id'] ?>">
            <?php endif; ?>

            <div class="seccion">
                <h2>💵 Conteo de Efectivo</h2>
                <!-- BILLETES -->
                <div class="grid-billetes">
                    <div class="input-dinero">
                        <label>🟣 Billete $1000</label>
                        <input type="number" class="cantidad-billete" data-valor="1000" data-tipo="billete" min="0"
                            placeholder="0">
                        <span class="subtotal">$0.00</span>
                    </div>
                    <div class="input-dinero">
                        <label>🔵 Billete $500</label>
                        <input type="number" class="cantidad-billete" data-valor="500" data-tipo="billete" min="0"
                            placeholder="0">
                        <span class="subtotal">$0.00</span>
                    </div>
                    <div class="input-dinero">
                        <label>🟢 Billete $200</label>
                        <input type="number" class="cantidad-billete" data-valor="200" data-tipo="billete" min="0"
                            placeholder="0">
                        <span class="subtotal">$0.00</span>
                    </div>
                    <div class="input-dinero">
                        <label>🟡 Billete $100</label>
                        <input type="number" class="cantidad-billete" data-valor="100" data-tipo="billete" min="0"
                            placeholder="0">
                        <span class="subtotal">$0.00</span>
                    </div>
                    <div class="input-dinero">
                        <label>🟠 Billete $50</label>
                        <input type="number" class="cantidad-billete" data-valor="50" data-tipo="billete" min="0"
                            placeholder="0">
                        <span class="subtotal">$0.00</span>
                    </div>
                    <div class="input-dinero">
                        <label>🔴 Billete $20</label>
                        <input type="number" class="cantidad-billete" data-valor="20" data-tipo="billete" min="0"
                            placeholder="0">
                        <span class="subtotal">$0.00</span>
                    </div>

                    <!-- MONEDAS -->
                    <div class="input-dinero">
                        <label>🪙 Moneda $20</label>
                        <input type="number" class="cantidad-billete" data-valor="20" data-tipo="moneda" min="0"
                            placeholder="0">
                        <span class="subtotal">$0.00</span>
                    </div>
                    <div class="input-dinero">
                        <label>🪙 Moneda $10</label>
                        <input type="number" class="cantidad-billete" data-valor="10" data-tipo="moneda" min="0"
                            placeholder="0">
                        <span class="subtotal">$0.00</span>
                    </div>
                    <div class="input-dinero">
                        <label>🪙 Moneda $5</label>
                        <input type="number" class="cantidad-billete" data-valor="5" data-tipo="moneda" min="0"
                            placeholder="0">
                        <span class="subtotal">$0.00</span>
                    </div>
                    <div class="input-dinero">
                        <label>🪙 Moneda $2</label>
                        <input type="number" class="cantidad-billete" data-valor="2" data-tipo="moneda" min="0"
                            placeholder="0">
                        <span class="subtotal">$0.00</span>
                    </div>
                    <div class="input-dinero">
                        <label>🪙 Moneda $1</label>
                        <input type="number" class="cantidad-billete" data-valor="1" data-tipo="moneda" min="0"
                            placeholder="0">
                        <span class="subtotal">$0.00</span>
                    </div>
                    <div class="input-dinero">
                        <label>🪙 Moneda $0.50</label>
                        <input type="number" class="cantidad-billete" data-valor="0.5" data-tipo="moneda" min="0"
                            step="1" placeholder="0">
                        <span class="subtotal">$0.00</span>
                    </div>
                </div>

                <div class="validacion">
                    <div>
                        <div style="font-size:16px; color:#666;">📋 Total contado:</div>
                        <div class="monto" id="totalContado">$0.00</div>
                    </div>
                    <div>
                        <div style="font-size:16px; color:#666;">📊 Subtotal (Caja Inicial + Ventas - Compras):</div>
                        <div class="monto" id="subtotalDisplay">$0.00</div>
                    </div>
                    <div>
                        <div style="font-size:16px; color:#666;">📈 Diferencia (Conteo - Subtotal):</div>
                        <div class="monto" id="diferencia">$0.00</div>
                        <div class="estado cuadrado" id="estadoDiferencia">CUADRADO</div>
                    </div>
                </div>

                <!-- MENSAJE SI SE TOMÓ DINERO DE VENTAS -->
                <div id="mensajeVentas" style="margin-top:15px; padding:15px; border-radius:10px; display:none;">
                    <strong>💡 Información:</strong> <span id="textoVentas"></span>
                </div>

                <div style="margin-top:20px;">
                    <label style="display:block; font-weight:bold; margin-bottom:8px;">📝 Observaciones:</label>
                    <textarea name="observaciones" rows="3"
                        style="width:100%; padding:12px; border:2px solid #ddd; border-radius:8px;"><?= $modoEdicion ? htmlspecialchars($corteEditar['observaciones'] ?? '') : '' ?></textarea>
                </div>

                <button type="submit" class="btn-guardar" id="btnGuardar" style="margin-top:20px;">
                    💾
                    <?= $modoEdicion ? 'Actualizar Corte' : 'Guardar Corte' ?>
                </button>
            </div>
        </form>
    </div>

    <!-- BARRA INFERIOR -->
    <div class="barra-inferior"></div>

    <script>
        // 🔥 DETECTAR SI ES CORTE ESPECIAL (cruza 2 días)
        function detectarCorteEspecial() {
            const horaInicio = document.getElementById('hora_inicio')?.value || '00:00';
            const horaActual = new Date().toTimeString().substring(0, 5);

            // Si hora actual < hora inicio, es corte especial (cruza madrugada)
            if (horaActual < horaInicio) {
                console.log('⚠️ CORTE ESPECIAL DETECTADO (cruza 2 días)');
                // Aquí podrías mostrar un mensaje o bandera especial
            }
        }

        // Llamar al cargar
        detectarCorteEspecial();

        const totalSistema = <?= $ventasHoy['monto_total'] ?>;
        let detalleBilletes = {};

        // 🔥 CALCULAR TOTALES EN TIEMPO REAL
        document.querySelectorAll('.cantidad-billete').forEach(input => {
            input.addEventListener('input', function () {
                const valor = parseFloat(this.dataset.valor);
                const tipo = this.dataset.tipo; // "billete" o "moneda"
                const cantidad = parseInt(this.value) || 0;
                const subtotal = valor * cantidad;

                this.parentElement.querySelector('.subtotal').textContent = '$' + subtotal.toFixed(2);

                // ✅ CLAVE ÚNICA: "20_billete" o "20_moneda"
                const clave = `${valor}_${tipo}`;
                detalleBilletes[clave] = cantidad;

                calcularTotal();
            });
        });

        function calcularTotal() {
            let totalContado = 0;
            document.querySelectorAll('.cantidad-billete').forEach(input => {
                const valor = parseFloat(input.dataset.valor);
                const cantidad = parseInt(input.value) || 0;
                totalContado += valor * cantidad;
            });
            document.getElementById('totalContado').textContent = '$' + totalContado.toFixed(2);

            // 🔥 OBTENER CAJA INICIAL
            const cajaInicialInput = document.getElementById('caja_inicial');
            const cajaInicial = cajaInicialInput ? parseFloat(cajaInicialInput.value) || 0 : 0;

            // 🔥 OBTENER VENTAS
            const ventas = <?= $ventasHoy['monto_total'] ?>;
            document.getElementById('ventasDisplay').textContent = '$' + ventas.toFixed(2);

            // 🔥 OBTENER COMPRAS
            const compras = <?= $totalCompras ?>;
            document.getElementById('comprasDisplay').textContent = '$' + compras.toFixed(2);

            // 🔥 CALCULAR SUBTOTAL: (Caja Inicial + Ventas) - Compras
            const subtotal = (cajaInicial + ventas) - compras;
            document.getElementById('subtotalDisplay').textContent = '$' + subtotal.toFixed(2);

            // 🔥 CALCULAR DIFERENCIA: Conteo - Subtotal
            const diferencia = totalContado - subtotal;
            const diffElement = document.getElementById('diferencia');
            const estadoElement = document.getElementById('estadoDiferencia');
            const btnGuardar = document.getElementById('btnGuardar');

            diffElement.textContent = (diferencia >= 0 ? '+' : '') + '$' + diferencia.toFixed(2);
            diffElement.style.color = diferencia === 0 ? '#28a745' : (diferencia < 0 ? '#dc3545' : '#ffc107');

            if (Math.abs(diferencia) < 1) {
                estadoElement.textContent = '✅ CUADRADO';
                estadoElement.className = 'estado cuadrado';
            } else if (diferencia < 0) {
                estadoElement.textContent = '❌ FALTANTE';
                estadoElement.className = 'estado faltante';
            } else {
                estadoElement.textContent = '💰 SOBRANTE';
                estadoElement.className = 'estado sobrante';
            }

            // 🔥 MENSAJE SI SE TOMÓ DINERO DE VENTAS
            const mensajeVentas = document.getElementById('mensajeVentas');
            const textoVentas = document.getElementById('textoVentas');
            if (compras > cajaInicial) {
                mensajeVentas.style.display = 'block';
                mensajeVentas.style.background = '#fff3cd';
                mensajeVentas.style.border = '2px solid #ffc107';
                textoVentas.textContent = '⚠️ SÍ se tomó dinero de las ventas para compras ($' + (compras - cajaInicial).toFixed(2) + ')';
            } else {
                mensajeVentas.style.display = 'block';
                mensajeVentas.style.background = '#d4edda';
                mensajeVentas.style.border = '2px solid #28a745';
                textoVentas.textContent = '✅ NO se tomó dinero de las ventas para compras';
            }

            btnGuardar.disabled = false;
        }

        // 🔥 GUARDAR CORTE DE CAJA
        document.getElementById('formCorteCaja').addEventListener('submit', function (e) {
            e.preventDefault();

            // 🔥 PREVENIR DOBLE ENVÍO
            const btnGuardar = document.getElementById('btnGuardar');
            if (btnGuardar.disabled) {
                console.log('⚠️ Botón ya está deshabilitado, evitando doble envío');
                return;
            }

            if (!confirm('✅ ¿Confirmar corte de caja?\nEste registro quedará guardado permanentemente en el historial.')) {
                return;
            }

            // 🔥 DESHABILITAR BOTÓN INMEDIATAMENTE
            btnGuardar.disabled = true;
            const textoOriginal = btnGuardar.innerHTML;
            btnGuardar.innerHTML = '⏳ Procesando...';

            // 🔥 RECOLECTAR DATOS DEL FORMULARIO
            const formData = new FormData(this);
            formData.append('total_ventas_bd', totalSistema);
            formData.append('detalle_billetes', JSON.stringify(detalleBilletes));
            formData.append('caja_inicial', document.getElementById('caja_inicial').value);

            // 🔥 ENVIAR AL SERVIDOR CON TIMEOUT
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 segundos timeout

            fetch('guardar_corte_caja.php', {
                method: 'POST',
                body: formData,
                signal: controller.signal
            })
                .then(response => {
                    clearTimeout(timeoutId);
                    if (!response.ok) {
                        throw new Error('Error HTTP: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert('✅ ' + data.message);
                        window.location.href = 'historial_corte.php';
                    } else {
                        alert('❌ Error: ' + data.message);
                        btnGuardar.disabled = false;
                        btnGuardar.innerHTML = textoOriginal;
                    }
                })
                .catch(error => {
                    clearTimeout(timeoutId);
                    console.error('Error completo:', error);
                    alert('❌ Error de conexión:\n' + error.message + '\n\nRevisa la consola (F12) para más detalles.');
                    btnGuardar.disabled = false;
                    btnGuardar.innerHTML = textoOriginal;
                });
        });
    </script>
</body>

</html>