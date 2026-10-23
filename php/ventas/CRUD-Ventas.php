<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../index.html");
    exit;
}
include("../conexion.php");
include("../login/registrar_actividad.php");
$user_id = $_SESSION["user_id"] ?? 0;
$username = $_SESSION["username"] ?? 'Desconocido';
$rol = $_SESSION["rol"] ?? 'empleado';

/* ============================================
🔥 PROCESAR VENTA (CREAR)
============================================ */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion']) && $_POST['accion'] === 'registrar_venta') {
    // 🔥 CORRECCIÓN PRINCIPAL: Decodificar JSON con validación
    $productos_json = $_POST['productos'] ?? '[]';
    $productos_venta = json_decode($productos_json, true);

    // 🔥 VALIDAR QUE SEA ARRAY
    if (!is_array($productos_venta) || empty($productos_venta)) {
        error_log("Error venta: productos_venta no es array - JSON: " . $productos_json);
        header("Location: CRUD-Ventas.php?error=2");
        exit;
    }

    mysqli_begin_transaction($conn);
    try {
        // Generar folio único
        $folio = 'VTA-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $total_venta = 0;
        $total_compra = 0;

        // 🔥 Calcular totales con validación
        foreach ($productos_venta as $prod) {
            // 🔥 VALIDAR QUE $prod SEA ARRAY
            if (!is_array($prod)) {
                error_log("Error: prod no es array: " . print_r($prod, true));
                continue;
            }
            $total_venta += (float) ($prod['subtotal'] ?? 0);
            $total_compra += (float) ($prod['precio_compra'] ?? 0) * (float) ($prod['cantidad'] ?? 0);
        }

        $ganancia = $total_venta - $total_compra;

        // Insertar venta
        // Obtener datos de pago del formulario
        $metodo_pago = isset($_POST['metodo_pago']) ? $_POST['metodo_pago'] : 'Efectivo';
        $cantidad_recibida = isset($_POST['cantidad_recibida']) ? $_POST['cantidad_recibida'] : $total_venta;
        $cambio = isset($_POST['cambio']) ? $_POST['cambio'] : 0;

        $sql_venta = "INSERT INTO ventas (folio, usuario_id, total_venta, total_compra, ganancia, metodo_pago, cantidad_recibida, cambio, estado, fecha_venta)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completada', NOW())";

        $stmt = mysqli_prepare($conn, $sql_venta);
        mysqli_stmt_bind_param($stmt, "sidddsdd", $folio, $user_id, $total_venta, $total_compra, $ganancia, $metodo_pago, $cantidad_recibida, $cambio);
        mysqli_stmt_execute($stmt);
        $venta_id = mysqli_insert_id($conn);

        // 🔥 Insertar detalle y actualizar inventario
        foreach ($productos_venta as $prod) {
            if (!is_array($prod))
                continue;

            $codigo = $prod['codigo_barra'] ?? '';
            $cantidad_vendida = (float) ($prod['cantidad'] ?? 0);
            $precio_venta = (float) ($prod['precio_venta'] ?? 0);
            $precio_compra = (float) ($prod['precio_compra'] ?? 0);
            $subtotal = (float) ($prod['subtotal'] ?? 0);

            if (empty($codigo) || $cantidad_vendida <= 0)
                continue;

            // 1️⃣ Insertar en detalle_venta
            $sql_detalle = "INSERT INTO detalle_venta (venta_id, codigo_barra, cantidad, precio_venta, precio_compra, subtotal)
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt2 = mysqli_prepare($conn, $sql_detalle);
            mysqli_stmt_bind_param($stmt2, "isdddd", $venta_id, $codigo, $cantidad_vendida, $precio_venta, $precio_compra, $subtotal);
            mysqli_stmt_execute($stmt2);

            // 2️⃣ Obtener datos actuales del producto
            $stmt_prod = mysqli_prepare($conn, "SELECT cantidad, precio_venta FROM productos WHERE codigo_barra = ?");
            mysqli_stmt_bind_param($stmt_prod, "s", $codigo);
            mysqli_stmt_execute($stmt_prod);
            $producto = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_prod));

            $cantidad_actual = (float) $producto['cantidad'];
            $precio_venta_real = (float) $producto['precio_venta']; // Usar el precio real de BD

            // 3️⃣ Calcular nueva cantidad y total_venta (PERMITIR NEGATIVOS)
            $nueva_cantidad = $cantidad_actual - $cantidad_vendida;  // ✅ PERMITE NEGATIVOS
            $nuevo_total_venta = $nueva_cantidad * $precio_venta_real;

            // 4️⃣ Actualizar producto (UNA SOLA VEZ)
            $sql_update = "UPDATE productos 
                   SET cantidad = ?, 
                       total_venta = ? 
                   WHERE codigo_barra = ?";
            $stmt3 = mysqli_prepare($conn, $sql_update);
            mysqli_stmt_bind_param($stmt3, "dds", $nueva_cantidad, $nuevo_total_venta, $codigo);
            mysqli_stmt_execute($stmt3);
            mysqli_stmt_close($stmt3);

            // 5️⃣ Actualizar lotes (FIFO) - tu lógica original está bien ✅
            $cantidad_restante = $cantidad_vendida;
            $sql_lotes = "SELECT id, cantidad FROM lotes WHERE codigo_barra = ? AND cantidad > 0 ORDER BY fecha_ingreso ASC";
            $stmt4 = mysqli_prepare($conn, $sql_lotes);
            mysqli_stmt_bind_param($stmt4, "s", $codigo);
            mysqli_stmt_execute($stmt4);
            $result_lotes = mysqli_stmt_get_result($stmt4);

            if ($result_lotes && mysqli_num_rows($result_lotes) > 0) {
                while ($lote = mysqli_fetch_assoc($result_lotes) && $cantidad_restante > 0) {
                    if (!is_array($lote) || !isset($lote['cantidad'], $lote['id']))
                        continue;

                    $lote_cantidad = (float) $lote['cantidad'];
                    $lote_id = (int) $lote['id'];

                    if ($lote_cantidad >= $cantidad_restante) {
                        $nueva_cantidad_lote = $lote_cantidad - $cantidad_restante;
                        $stmt5 = mysqli_prepare($conn, "UPDATE lotes SET cantidad = ? WHERE id = ?");
                        mysqli_stmt_bind_param($stmt5, "di", $nueva_cantidad_lote, $lote_id);
                        mysqli_stmt_execute($stmt5);
                        mysqli_stmt_close($stmt5);
                        $cantidad_restante = 0;
                    } else {
                        $cantidad_restante -= $lote_cantidad;
                        $stmt6 = mysqli_prepare($conn, "UPDATE lotes SET cantidad = 0 WHERE id = ?");
                        mysqli_stmt_bind_param($stmt6, "i", $lote_id);
                        mysqli_stmt_execute($stmt6);
                        mysqli_stmt_close($stmt6);
                    }
                }
            }
            mysqli_stmt_close($stmt4);
        }

        mysqli_commit($conn);

        // Registrar actividad
        registrar_actividad(
            $conn,
            $user_id,
            $username,
            "CREATE",
            "Venta registrada: Folio $folio, Total: $" . number_format($total_venta, 2),
            "ventas",
            $venta_id,
            [],
            ['folio' => $folio, 'total' => $total_venta]
        );

        header("Location: CRUD-Ventas.php?registrado=1&folio=" . urlencode($folio));
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Error venta: " . $e->getMessage());
        header("Location: CRUD-Ventas.php?error=1");
        exit;
    }
}

// Obtener productos para el selector
$productos = mysqli_query($conn, "SELECT codigo_barra, nombre_producto, precio_venta, cantidad FROM productos WHERE estado = 'activo' ORDER BY nombre_producto ASC");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>• Registrar Venta</title>
    <link rel="icon" href="../../img/LOGIN/icon_24px.png" type="img/INICIO/icon_24px.png" />
    <link rel="stylesheet" href="../../css/Ventas-STYLE/Ventas2.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Estilos específicos para esta página */
        .venta-layout {
            display: flex;
            gap: 20px;
            padding: 0px 5% 20px 5%;
            min-height: calc(100vh - 120px);
            /* 🔥 AJUSTE AUTOMÁTICO */
        }

        /* PANEL IZQUIERDO: PRODUCTOS EN VENTA */
        .panel-izquierdo {
            margin: 100px 0 0 0;
            height: calc(93vh - 140px);
            /* 🔥 ALTURA DINÁMICA */
            min-height: 500px;
            max-height: 100%;
            flex: 1;
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            /* 🔥 PREVENIR DESBORDAMIENTO */
        }

        /*/* PANEL DERECHO: FORMULARIO */
        .panel-derecho {
            margin: 100px 0 0 0;
            width: 400px;
            height: calc(93vh - 140px);
            /* 🔥 ALTURA DINÁMICA */
            min-height: 500px;
            max-height: 100%;
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            /* 🔥 PREVENIR DESBORDAMIENTO */
        }

        /* 🔥 FORMULARIO CON SCROLL SI ES NECESARIO */
        .panel-derecho form {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            /* 🔥 SCROLL EN FORMULARIO */
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #0d6efd;
        }

        .btn-guardar {
            width: 100%;
            padding: 12px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-guardar:hover {
            background: #218838;
        }

        .btn-guardar:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        /* 🔥 LISTA DE PRODUCTOS CON SCROLL AUTOMÁTICO */
        .lista-productos {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            flex: 1;
            /* 🔥 OCUPA ESPACIO DISPONIBLE */
            min-height: 200px;
            overflow-y: auto;
            /* 🔥 SCROLL SOLO CUANDO SEA NECESARIO */
        }

        /* 🔥 INPUT DE CANTIDAD - QUITAR SPINNERS */
        .lista-productos input[type="number"] {
            -moz-appearance: textfield;
        }

        .lista-productos input[type="number"]::-webkit-outer-spin-button,
        .lista-productos input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .lista-productos input[type="number"]:focus {
            outline: none;
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
        }

        /* 🔥 SCROLLBAR PERSONALIZADO */
        .lista-productos::-webkit-scrollbar,
        .panel-derecho form::-webkit-scrollbar {
            width: 8px;
        }

        .lista-productos::-webkit-scrollbar-track,
        .panel-derecho form::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .lista-productos::-webkit-scrollbar-thumb {
            background: #0d6efd;
            border-radius: 4px;
        }

        .lista-productos::-webkit-scrollbar-thumb:hover,
        .panel-derecho form::-webkit-scrollbar-thumb:hover {
            background: #0b5ed7;
        }

        .producto-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
            background: #fff;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: all 0.2s ease;
        }

        .producto-item:hover {
            background: #f0f4ff;
            transform: translateX(5px);
        }

        .producto-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .producto-imagen {
            width: 60px;
            height: 60px;
            object-fit: contain;
            border-radius: 8px;
            border: 1px solid #ddd;
            background: #fff;
            padding: 3px;
        }

        .producto-info {
            flex: 1;
        }

        .producto-info strong {
            display: block;
            color: #333;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .producto-info small {
            color: #666;
            font-size: 12px;
        }

        .producto-cantidad-input {
            width: 60px;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
            font-size: 14px;
        }

        .producto-accion {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .producto-subtotal {
            font-weight: bold;
            color: #0d6efd;
            font-size: 15px;
            min-width: 70px;
            text-align: right;
        }

        .btn-eliminar-producto {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 6px 10px;
            cursor: pointer;
            font-size: 13px;
            transition: 0.2s;
        }

        .btn-eliminar-producto:hover {
            background: #c82333;
            transform: scale(1.1);
        }

        .total-venta {
            font-size: 24px;
            font-weight: bold;
            text-align: right;
            margin-top: 15px;
            padding: 15px;
            background: linear-gradient(135deg, #e7f3ff 0%, #d0e8ff 100%);
            border-radius: 8px;
            border: 2px solid #0d6efd;
        }

        .total-venta #totalVenta {
            color: #0d6efd;
            font-size: 28px;
        }

        /* 🔥 BOTÓN COBRAR MEJORADO */
        .btn-cobrar {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-cobrar:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.6);
        }

        /* 🔥 BOTÓN COBRAR DESHABILITADO */
        .btn-cobrar:disabled {
            background: #6c757d !important;
            cursor: not-allowed;
            box-shadow: none;
            opacity: 0.7;
            animation: none !important;
        }

        /* 🔥 ANIMACIÓN DE PULSO PARA BOTÓN COBRAR */
        @keyframes pulse-cobrar {
            0% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
            }

            70% {
                box-shadow: 0 0 0 15px rgba(40, 167, 69, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
            }
        }

        .btn-cobrar:not(:disabled) {
            animation: pulse-cobrar 2s infinite;
        }

        /* 🔥 BOTÓN VER PRODUCTOS */
        .btn-ver-productos-panel {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
            margin-top: 15px;
        }

        .btn-ver-productos-panel:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .mensaje {
            padding: 15px;
            border-radius: 6px;
            margin: 120px 5% 20px 5%;
            /* 🔥 MÁS MÁRGEN SUPERIOR (120px en vez de 20px) */
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            animation: slideDown 0.5s ease;
        }

        .mensaje.success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 5px solid #28a745;
        }

        .mensaje.error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 5px solid #dc3545;
        }

        /* 🔥 ALERTA DE STOCK EXCEDIDO */
        .alerta-stock-excedido {
            background: #ffe6e6;
            border-left: 4px solid #dc3545;
            padding: 10px 15px;
            margin: -5px 0 10px 0;
            border-radius: 0 6px 6px 0;
            color: #dc3545;
            font-size: 13px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: slideIn 0.3s ease;
        }

        /* 🔥 INPUT CON STOCK EXCEDIDO */
        .input-stock-excedido {
            border-color: #dc3545 !important;
            background: #ffe6e6 !important;
        }

        /* 🔥 ITEM CON STOCK EXCEDIDO */
        .producto-item-stock-excedido {
            background: #fff5f5 !important;
            border: 2px solid #dc3545 !important;
        }

        /* 🔥 ESTILOS PARA MODAL DE COBRO */
        .modal-cobro {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        .modal-cobro-content {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #cantidadRecibida:focus {
            outline: none;
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
        }

        .opcion-pago-btn {
            flex: 1;
            min-width: 100px;
            padding: 12px 15px;
            background: #0d6efd;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .opcion-pago-btn:hover {
            background: #0b5ed7;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
        }

        .opcion-pago-btn:active {
            transform: translateY(0);
        }

        /* 🔥 BOTÓN CONFIRMAR VENTA - ESTADO DESHABILITADO */
        #btnConfirmarVenta:disabled {
            opacity: 0.5 !important;
            background: #6c757d !important;
            cursor: not-allowed;
            box-shadow: none;
            animation: none !important;
        }

        /* 🔥 BOTÓN CONFIRMAR VENTA - ESTADO HABILITADO */
        #btnConfirmarVenta:not(:disabled) {
            opacity: 1 !important;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
            animation: pulse-cobrar 2s infinite;
        }

        #btnConfirmarVenta:not(:disabled):hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.6);
        }







        .btn-volver {
            display: inline-block;
            padding: 10px 20px;
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(108, 117, 125, 0.3);
            text-align: center;
        }

        .btn-volver:hover {
            background: linear-gradient(135deg, #5a6268 0%, #495057 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.4);
            color: #ffffff;
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

    <!-- 🔥 MENSAJES DE ÉXITO/ERROR EN LA PARTE SUPERIOR -->
    <div class="mensajes-container" style="padding: 15px 5%; background: #f5f5f5;">
        <?php if (isset($_GET['registrado']) && $_GET['registrado'] == '1'): ?>
            <div class="mensaje-exito" style="
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        border-left: 5px solid #28a745;
        color: #155724;
        margin: 55px 25px -105px 25px;
        padding: 10px 25px 10px 25px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);
        display: flex;
        align-items: center;
        gap: 15px;
        animation: slideDown 0.5s ease;
    ">
                <i class="bi bi-check-circle-fill" style="font-size: 28px; color: #28a745;"></i>
                <div>
                    <strong style="font-size: 16px; display: block; margin-bottom: 5px;">
                        ✅ Venta registrada correctamente
                    </strong>
                    <?php if (isset($_GET['folio'])): ?>
                        <span style="font-size: 14px;">
                            📋 Folio: <strong style="color: #155724;"><?= htmlspecialchars($_GET['folio']) ?></strong>
                        </span>
                    <?php endif; ?>
                </div>
                <button type="button" onclick="this.parentElement.remove()" style="
            margin-left: auto;
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #155724;
            opacity: 0.6;
            transition: 0.2s;
        " onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.6'">
                    ✕
                </button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="mensaje-error" style="
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        border-left: 5px solid #dc3545;
        color: #721c24;
        margin: 55px 25px -105px 25px;
        padding: 10px 25px 10px 25px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.2);
        display: flex;
        align-items: center;
        gap: 15px;
        animation: slideDown 0.5s ease;
    ">
                <i class="bi bi-exclamation-triangle-fill" style="font-size: 28px; color: #dc3545;"></i>
                <div>
                    <strong style="font-size: 16px; display: block; margin-bottom: 5px;">
                        ❌ Ocurrió un error al registrar la venta
                    </strong>
                    <span style="font-size: 14px;">
                        Por favor verifica los datos e intenta nuevamente
                    </span>
                </div>
                <button type="button" onclick="this.parentElement.remove()" style="
            margin-left: auto;
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #721c24;
            opacity: 0.6;
            transition: 0.2s;
        " onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.6'">
                    ✕
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- 🔥 ANIMACIÓN -->
    <style>
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>

    <!-- LAYOUT DIVIDIDO -->
    <div class="venta-layout">
        <!-- PANEL IZQUIERDO: PRODUCTOS EN ESTA VENTA -->
        <div class="panel-izquierdo">
            <h3 style="margin-bottom: 15px; color: #333; border-bottom: 2px solid #0d6efd; padding-bottom: 10px;">
                🛒 Productos en esta Venta
            </h3>

            <!-- 🔥 LISTA DE PRODUCTOS CON IMÁGENES -->
            <div class="lista-productos" id="listaProductos">
                <p style="color: #666; text-align: center; padding: 50px 0;">
                    📦 No hay productos agregados
                </p>
            </div>

            <input type="hidden" id="productosJSON" name="productos" form="formVenta">

            <!-- 🔥 TOTAL DE LA VENTA -->
            <div class="total-venta" style="margin-top: 15px;">
                Total: $<span id="totalVenta">0.00</span>
            </div>

            <!-- 🔥 BOTÓN COBRAR (Dentro del panel) -->
            <div style="display:flex; gap:10px; margin-top:10px;">
                <span id="totalCobrar" style="font-size: 20px; font-weight: bold; display: none;"></span>
                <button type="button" onclick="abrirModalCobro()" class="btn-cobrar" id="btnCobrar" disabled style="margin-top: 15px; width: 100%; padding: 18px; 
                    background: linear-gradient(135deg, #28a745 0%, #20c997 100%); 
                    color: white; border: none; border-radius: 8px; 
                    font-size: 20px; font-weight: bold; cursor: pointer; 
                    transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4); 
                    display: flex; align-items: center; justify-content: center; gap: 10px;">
                    💳 Cobrar $
                    <span id="totalCobrar">0.00</span>
                </button>
                <a href="historial_ventas.php" class="btn-volver"
                    style="display:block; text-align:center; margin-top:10px;">
                    ← Ver Historial
                </a>
            </div>

        </div>

        <!-- PANEL DERECHO: FORMULARIO DE INGRESO -->
        <div class="panel-derecho">
            <h3 style="margin-bottom: 20px; color: #333; text-align: center;">
                ➕ Agregar Producto
            </h3>

            <!-- 🔥 CONTENEDOR DE IMAGEN DEL PRODUCTO -->
            <div id="contenedorImagenProducto" style="width: 100%; height: 200px; background: #f8f9fa; 
                border: 2px dashed #ddd; border-radius: 10px; 
                margin-bottom: 20px; display: flex; 
                align-items: center; justify-content: center; overflow: hidden;">
                <img id="imagenProductoGrande" src="" alt="Producto" style="max-width: 100%; max-height: 100%; 
                    object-fit: contain; display: none;">
                <span id="textoSinImagen" style="color: #999; font-size: 14px;">
                    📷 La imagen se mostrará aquí
                </span>
            </div>

            <form method="POST" id="formVenta" autocomplete="off">
                <input type="hidden" name="accion" value="registrar_venta">

                <!-- 🔥 BUSCADOR DE PRODUCTOS CON IMÁGENES -->
                <div class="form-group" style="position: relative;">
                    <label>Buscar Producto *</label>
                    <div style="position: relative;">
                        <input type="text" id="buscadorProductos"
                            placeholder="🔍 Buscar por nombre o código de barras..." required autocomplete="off"
                            autofocus style="width: 100%; padding: 12px 40px 12px 15px;
                                border: 2px solid #ddd; border-radius: 8px;
                                font-size: 14px;">
                        <i class="bi bi-search" style="position: absolute; right: 15px; top: 50%; 
                          transform: translateY(-50%); color: #666; 
                          font-size: 18px;"></i>
                    </div>

                    <!-- 🔥 DROPDOWN DE RESULTADOS -->
                    <div id="resultadosBusqueda" style="display: none; position: absolute; top: 100%; 
                        left: 0; right: 0; background: white; 
                        border: 1px solid #ddd; border-radius: 8px; 
                        box-shadow: 0 4px 12px rgba(0,0,0,0.15); 
                        z-index: 1000; max-height: 400px; 
                        overflow-y: auto; margin-top: 5px;">
                    </div>

                    <!-- 🔥 INPUTS OCULTOS -->
                    <input type="hidden" id="codigoProductoSeleccionado">
                    <input type="hidden" id="precioProductoSeleccionado">
                    <input type="hidden" id="stockProductoSeleccionado">
                    <input type="hidden" id="imagenProductoSeleccionado"> <!-- 🔥 NUEVO -->
                </div>

                <div class="form-group">
                    <label>Cantidad *</label>
                    <input type="number" id="cantidadInput" step="1" min="1" value="1" required
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;">
                </div>

                <div class="form-group">
                    <button type="button" class="btn-guardar" onclick="agregarProducto()"
                        style="background: #0d6efd; margin-bottom: 10px;">
                        ➕ Agregar al Carrito
                    </button>
                </div>

                <div style="margin-top: 15px; text-align: center;">
                    <a href="../productos/CRUD-Product.php" class="btn-ver-productos-panel" style="display: inline-flex; align-items: center; gap: 8px;
                      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                      color: white; text-decoration: none;
                      padding: 12px 25px; border-radius: 8px;
                      font-size: 14px; font-weight: bold;
                      box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
                      transition: all 0.3s ease;">
                        <i class="bi bi-box-seam-fill" style="font-size: 16px;"></i>
                        <span>Ver Productos</span>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- BARRA INFERIOR -->
    <div class="barra-inferior"></div>

    <!-- SCRIPTS DE FUNCIONALIDAD -->
    <script>
        // 🔥 VARIABLE GLOBAL - FUERA DEL DOMContentLoaded
        let productosVenta = [];

        // 🔥 FUNCIONES GLOBALES (ACCESIBLES DESDE HTML)
        window.mostrarNotificacion = function (mensaje, tipo = 'success') {
            const notificacion = document.createElement('div');
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
            `;
            notificacion.textContent = mensaje;
            document.body.appendChild(notificacion);
            setTimeout(() => {
                notificacion.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notificacion.remove(), 300);
            }, 3000);
        }

        // 🔥 VALIDAR STOCK DE TODOS LOS PRODUCTOS
        window.validarStockProductos = function () {
            let hayStockExcedido = false;
            productosVenta.forEach((prod, index) => {
                if (prod.cantidad > (prod.stock || 999)) {
                    hayStockExcedido = true;
                    const inputCantidad = document.querySelector(`input[data-index="${index}"]`);
                    if (inputCantidad) {
                        inputCantidad.style.borderColor = '#dc3545';
                        inputCantidad.style.background = '#ffe6e6';
                    }
                }
            });
            return hayStockExcedido;
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
            @keyframes pulse-cobrar {
                0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
                70% { box-shadow: 0 0 0 15px rgba(40, 167, 69, 0); }
                100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
            }
        `;
        document.head.appendChild(style);

        // 🔥 ACTUALIZAR TOTAL GENERAL
        window.actualizarTotalGeneral = function () {
            let total = 0;
            let hayStockExcedido = false;
            productosVenta.forEach((prod, index) => {
                total += prod.subtotal;
                if (prod.cantidad > (prod.stock || 999)) {
                    hayStockExcedido = true;
                }
            });
            document.getElementById('totalVenta').textContent = total.toFixed(2);
            document.getElementById('totalCobrar').textContent = total.toFixed(2);
            const btnCobrar = document.getElementById('btnCobrar');
            // 🔥 PERMITIR COBRAR AUN CON STOCK NEGATIVO, PERO MOSTRAR ADVERTENCIA
            if (hayStockExcedido) {
                btnCobrar.disabled = false;  // ✅ PERMITIR COBRAR
                btnCobrar.style.background = 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)';  // 🔴 ROJO
                btnCobrar.style.cursor = 'pointer';
                btnCobrar.innerHTML = '⚠️ Cobrar con Stock Negativo $' + total.toFixed(2);
                btnCobrar.style.animation = 'pulse-cobrar 2s infinite';
            } else {
                btnCobrar.disabled = false;
                btnCobrar.style.background = 'linear-gradient(135deg, #28a745 0%, #20c997 100%)';
                btnCobrar.style.cursor = 'pointer';
                btnCobrar.innerHTML = '💳 Cobrar $' + total.toFixed(2);
                btnCobrar.style.animation = 'pulse-cobrar 2s infinite';
            }
            document.getElementById('productosJSON').value = JSON.stringify(productosVenta);
        }

        // 🔥 ACTUALIZAR LISTA DE PRODUCTOS
        window.actualizarLista = function () {
            const lista = document.getElementById('listaProductos');
            const totalSpan = document.getElementById('totalVenta');
            const btnCobrar = document.getElementById('btnCobrar');
            const productosJSON = document.getElementById('productosJSON');
            const totalCobrarSpan = document.getElementById('totalCobrar');

            if (!lista || !totalSpan || !btnCobrar || !productosJSON || !totalCobrarSpan) {
                setTimeout(() => window.actualizarLista(), 100);
                return;
            }

            if (productosVenta.length === 0) {
                lista.innerHTML = '<p style="color: #666; text-align: center; padding: 50px 0;">📦 No hay productos agregados</p>';
                totalSpan.textContent = '0.00';
                totalCobrarSpan.textContent = '0.00';
                btnCobrar.disabled = true;
                btnCobrar.style.background = '#6c757d';
                btnCobrar.style.cursor = 'not-allowed';
                btnCobrar.innerHTML = '💳 Cobrar $0.00';
                btnCobrar.style.animation = 'none';
                productosJSON.value = '';
                return;
            }

            let html = '';
            let total = 0;
            let hayStockExcedido = false;

            productosVenta.forEach((prod, index) => {
                total += prod.subtotal;
                const imagenUrl = prod.imagen ? `../../img/PRODUCTOS/${prod.imagen}` : '../../img/PRODUCTOS/sin-imagen.png';
                const stockExcedido = prod.cantidad > (prod.stock || 999);
                if (stockExcedido) hayStockExcedido = true;
                const inputColor = stockExcedido ? '#dc3545' : '#ddd';
                const inputBg = stockExcedido ? '#ffe6e6' : '#fff';

                html += `
                    <div class="producto-item" data-index="${index}"
                    style="display: flex; align-items: center; gap: 15px; padding: 15px;
                    background: ${stockExcedido ? '#fff5f5' : '#fff'};
                    border-radius: 10px; margin-bottom: 10px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                    transition: all 0.2s ease;
                    border: ${stockExcedido ? '2px solid #dc3545' : 'none'};">
                        <img src="${imagenUrl}" alt="${prod.nombre}"
                        style="width: 60px; height: 60px; object-fit: contain;
                        border-radius: 8px; border: 1px solid #ddd;
                        background: #fff; padding: 3px;">
                        <div style="flex: 1;">
                            <div style="font-weight: bold; color: #333; font-size: 15px; margin-bottom: 4px;">
                                ${prod.nombre}
                            </div>
                            <div style="color: #666; font-size: 13px;">
                                $${prod.precio_venta.toFixed(2)} c/u
                            </div>
                            ${stockExcedido ? `
                            <div style="color: #dc3545; font-size: 12px; font-weight: bold; margin-top: 5px;">
                            ⚠️ Stock: ${prod.stock} unidades | Vendiendo: ${prod.cantidad} unidades (Inventario negativo)
                            </div>` : ''}
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <input type="number" min="1" value="${prod.cantidad}"
                            data-index="${index}" onchange="window.actualizarCantidad(this)"
                            onfocus="this.select()"
                            style="width: 70px; padding: 8px; border: 2px solid ${stockExcedido ? '#dc3545' : '#ddd'};
                            border-radius: 6px; text-align: center;
                            font-size: 14px; font-weight: bold; background: ${stockExcedido ? '#ffe6e6' : '#fff'};
                            transition: all 0.2s;">
                        </div>
                        <div style="text-align: right; min-width: 100px;">
                            <div style="font-weight: bold; color: ${stockExcedido ? '#dc3545' : '#0d6efd'};
                            font-size: 16px;" id="subtotal-${index}">
                                $${prod.subtotal.toFixed(2)}
                            </div>
                        </div>
                        <button type="button" onclick="window.eliminarProducto(${index})"
                        style="background: #dc3545; color: white; border: none;
                        border-radius: 6px; padding: 8px 12px;
                        cursor: pointer; font-size: 14px; transition: 0.2s;">
                            🗑️
                        </button>
                    </div>`;
            });

            lista.innerHTML = html;
            totalSpan.textContent = total.toFixed(2);
            totalCobrarSpan.textContent = total.toFixed(2);

            // 🔥 PERMITIR COBRAR AUN CON STOCK NEGATIVO, PERO MOSTRAR ADVERTENCIA
            if (hayStockExcedido) {
                btnCobrar.disabled = false;  // ✅ PERMITIR COBRAR
                btnCobrar.style.background = 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)';  // 🔴 ROJO
                btnCobrar.style.cursor = 'pointer';
                btnCobrar.innerHTML = '⚠️ Cobrar con Stock Negativo $' + total.toFixed(2);
                btnCobrar.style.animation = 'pulse-cobrar 2s infinite';
            } else {
                btnCobrar.disabled = false;
                btnCobrar.style.background = 'linear-gradient(135deg, #28a745 0%, #20c997 100%)';
                btnCobrar.style.cursor = 'pointer';
                btnCobrar.innerHTML = '💳 Cobrar $' + total.toFixed(2);
                btnCobrar.style.animation = 'pulse-cobrar 2s infinite';
            }
            productosJSON.value = JSON.stringify(productosVenta);
        }

        // 🔥 ACTUALIZAR CANTIDAD - CON ALERTA DE STOCK NEGATIVO
        window.actualizarCantidad = function (input) {
            const index = parseInt(input.dataset.index);
            const nuevaCantidad = parseInt(input.value) || 0;
            const stock = parseInt(productosVenta[index].stock) || 999;

            // 🔥 PERMITIR CUALQUIER CANTIDAD, PERO MOSTRAR ALERTA SI EXCEDE STOCK
            if (nuevaCantidad < 1) {
                productosVenta[index].cantidad = 1;
                input.value = 1;
            } else {
                productosVenta[index].cantidad = nuevaCantidad;

                // 🔥 MOSTRAR ALERTA ROJA SI EXCEDE STOCK
                if (nuevaCantidad > stock) {
                    // mostrarNotificacion('⚠️ Stock insuficiente: Hay ' + stock + ' unidades, vendiendo ' + nuevaCantidad + ' (Inventario negativo)', 'danger');
                    input.style.borderColor = '#dc3545';
                    input.style.background = '#ffe6e6';
                } else {
                    input.style.borderColor = '#ddd';
                    input.style.background = '#fff';
                }
            }

            productosVenta[index].subtotal = productosVenta[index].cantidad * productosVenta[index].precio_venta;

            const subtotalElement = document.getElementById(`subtotal-${index}`);
            if (subtotalElement) {
                subtotalElement.textContent = '$' + productosVenta[index].subtotal.toFixed(2);
            }

            window.actualizarTotalGeneral();
        }

        // 🔥 ELIMINAR PRODUCTO
        window.eliminarProducto = function (index) {
            if (confirm('¿Eliminar este producto del carrito?')) {
                productosVenta.splice(index, 1);
                window.actualizarLista();
                // 🔥 AGREGA ESTO AL FINAL
                setTimeout(() => {
                    mantenerFocoBuscador();
                }, 200);
            }
        }

        // 🔥 AGREGAR PRODUCTO
        window.agregarProducto = async function (modoScanner = false) {
            const buscadorInput = document.getElementById('buscadorProductos');
            const codigo = document.getElementById('codigoProductoSeleccionado').value;
            const nombre = buscadorInput.value.split(' (Cód:')[0].trim();
            const precio_venta = parseFloat(document.getElementById('precioProductoSeleccionado').value) || 0;
            const stock = parseInt(document.getElementById('stockProductoSeleccionado').value) || 0;
            const imagen = document.getElementById('imagenProductoSeleccionado').value;
            const cantidad = parseInt(document.getElementById('cantidadInput').value) || 1;

            if (!codigo) {
                window.mostrarNotificacion('⚠️ Selecciona un producto de la lista', 'danger');
                return;
            }

            let precio_compra = 0;
            try {
                const response = await fetch(`obtener_precio_compra.php?codigo=${encodeURIComponent(codigo)}`);
                if (response.ok) {
                    const data = await response.json();
                    precio_compra = parseFloat(data.precio_compra) || 0;
                }
            } catch (err) {
                console.warn('No se pudo obtener precio_compra:', err);
            }

            // 🔥 VERIFICAR SI YA EXISTE - USAR == PARA COMPARACIÓN
            const existente = productosVenta.find(p => p.codigo_barra == codigo);
            const cantidadTotal = existente ? (existente.cantidad + cantidad) : cantidad;

            // 🔥 VALIDAR STOCK - MOSTRAR ALERTA PERO PERMITIR AGREGAR
            if (cantidadTotal > stock) {
                // mostrarNotificacion('⚠️ Stock insuficiente: Hay ' + stock + ' unidades, vendiendo ' + cantidadTotal + ' (Inventario negativo)', 'danger');
            }

            if (existente) {
                // 🔥 SUMAR CANTIDAD AL VALOR ACTUAL (RESPETA EDICIÓN MANUAL)
                existente.cantidad += cantidad;
                existente.subtotal = existente.cantidad * existente.precio_venta;
                mostrarNotificacion('✅ Cantidad actualizada (' + existente.cantidad + ' unidades)', 'success');
            } else {
                productosVenta.push({
                    codigo_barra: codigo,
                    nombre: nombre,
                    cantidad: cantidad,
                    precio_venta: precio_venta,
                    precio_compra: precio_compra,
                    imagen: imagen,
                    stock: stock,
                    subtotal: cantidad * precio_venta
                });
                mostrarNotificacion('✅ Producto agregado', 'success');
            }

            window.actualizarLista();

            document.getElementById('codigoProductoSeleccionado').value = '';
            document.getElementById('precioProductoSeleccionado').value = '';
            document.getElementById('stockProductoSeleccionado').value = '';
            document.getElementById('imagenProductoSeleccionado').value = '';
            document.getElementById('cantidadInput').value = 1;
            buscadorInput.value = '';
            buscadorInput.focus();

            // 🔥 AGREGA ESTO AL FINAL
            setTimeout(() => {
                mantenerFocoBuscador();
            }, 200);
        }

        // 🔥 BUSCAR Y AGREGAR (SCANNER)
        async function buscarYAgregarProducto(term) {
            const buscadorInput = document.getElementById('buscadorProductos');
            try {
                buscadorInput.disabled = true;
                const response = await fetch(`buscar_productos.php?term=${encodeURIComponent(term)}&limit=1`);
                if (!response.ok) throw new Error('Error en la búsqueda');
                const productos = await response.json();

                if (productos.length > 0) {
                    const prod = productos[0];
                    document.getElementById('codigoProductoSeleccionado').value = String(prod.codigo_barra);
                    document.getElementById('precioProductoSeleccionado').value = String(prod.precio_venta);
                    document.getElementById('stockProductoSeleccionado').value = String(prod.cantidad);
                    document.getElementById('imagenProductoSeleccionado').value = prod.imagen || '';
                    buscadorInput.value = `${prod.nombre_producto} (Cód: ${prod.codigo_barra})`;
                    mostrarImagenProducto(prod.codigo_barra, prod.imagen || '');
                    await new Promise(resolve => setTimeout(resolve, 100));
                    await window.agregarProducto(true);
                    buscadorInput.value = '';
                    document.getElementById('codigoProductoSeleccionado').value = '';
                    document.getElementById('precioProductoSeleccionado').value = '';
                    document.getElementById('stockProductoSeleccionado').value = '';
                    document.getElementById('imagenProductoSeleccionado').value = '';
                } else {
                    window.mostrarNotificacion('⚠️ Producto no encontrado: ' + term, 'danger');
                    buscadorInput.value = '';
                }
            } catch (err) {
                console.error('Error al buscar producto:', err);
                window.mostrarNotificacion('⚠️ Producto no encontrado: ' + term, 'danger');
                buscadorInput.value = '';
            } finally {
                buscadorInput.disabled = false;
                buscadorInput.focus();
            }
        }

        // 🔥 MOSTRAR IMAGEN PRODUCTO
        function mostrarImagenProducto(codigo, imagen) {
            const contenedor = document.getElementById('contenedorImagenProducto');
            const imgElement = document.getElementById('imagenProductoGrande');
            const textoSinImagen = document.getElementById('textoSinImagen');

            if (!contenedor || !imgElement || !textoSinImagen) return;

            if (imagen && imagen !== '') {
                imgElement.src = `../../img/PRODUCTOS/${imagen}`;
                imgElement.style.display = 'block';
                textoSinImagen.style.display = 'none';
                contenedor.style.border = '2px solid #0d6efd';
                contenedor.style.background = '#fff';
            } else {
                imgElement.style.display = 'none';
                textoSinImagen.style.display = 'block';
                textoSinImagen.textContent = '📷 Este producto no tiene imagen';
                contenedor.style.border = '2px dashed #ddd';
                contenedor.style.background = '#f8f9fa';
            }
        }

        // 🔥 MANTENER INPUT DE BÚSQUEDA SIEMPRE ENFOCADO
        function mantenerFocoBuscador() {
            const buscador = document.getElementById('buscadorProductos');
            if (buscador && document.activeElement !== buscador) {
                // No forzar foco si hay modales abiertos o se está interactuando con otros elementos
                const modalAbierto = document.querySelector('.modal-cobro[style*="display: flex"]');
                const interactuandoConCarrito = document.activeElement.closest('.producto-item');
                const interactuandoConBotones = document.activeElement.closest('button');

                if (!modalAbierto && !interactuandoConCarrito && !interactuandoConBotones) {
                    buscador.focus();
                }
            }
        }

        // 🔥 DOMContentLoaded - SOLO EVENT LISTENERS
        document.addEventListener('DOMContentLoaded', function () {
            let debounceTimer;
            const buscadorInput = document.getElementById('buscadorProductos');
            const resultadosDiv = document.getElementById('resultadosBusqueda');

            if (!buscadorInput || !resultadosDiv) {
                console.error('❌ Elementos del DOM no encontrados');
                return;
            }

            // BUSCAR MIENTRAS ESCRIBE
            buscadorInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                const term = this.value.trim();
                if (term.length < 1) {
                    resultadosDiv.style.display = 'none';
                    return;
                }
                debounceTimer = setTimeout(() => {
                    fetch(`buscar_productos.php?term=${encodeURIComponent(term)}&limit=20`)
                        .then(r => r.json())
                        .then(productos => {
                            if (productos.length === 0) {
                                resultadosDiv.innerHTML = '<div style="padding: 15px; color: #666; text-align: center;">📦 No se encontraron productos</div>';
                            } else {
                                let html = '';
                                productos.forEach(prod => {
                                    const imagenUrl = prod.imagen ? `../../img/PRODUCTOS/${prod.imagen}` : '../../img/PRODUCTOS/sin-imagen.png';
                                    const stockColor = prod.cantidad <= 10 ? '#dc3545' : '#28a745';
                                    html += `
                            <div class="producto-busqueda-item"
                            data-codigo="${prod.codigo_barra}"
                            data-nombre="${prod.nombre_producto}"
                            data-precio="${prod.precio_venta}"
                            data-stock="${prod.cantidad}"
                            data-imagen="${prod.imagen || ''}"
                            style="display: flex; align-items: center; gap: 12px; padding: 12px 15px;
                            cursor: pointer; transition: background 0.2s; border-bottom: 1px solid #f0f0f0;"
                            onmouseover="this.style.background='#f8f9fa'"
                            onmouseout="this.style.background='white'">
                                <img src="${imagenUrl}" alt="${prod.nombre_producto}"
                                style="width: 50px; height: 50px; object-fit: contain;
                                border-radius: 6px; border: 1px solid #ddd;
                                background: #fff; padding: 2px;">
                                <div style="flex: 1;">
                                    <div style="font-weight: bold; color: #333; margin-bottom: 4px;">
                                        ${prod.nombre_producto}
                                    </div>
                                    <div style="font-size: 12px; color: #666;">
                                        ${prod.marca ? prod.marca + ' • ' : ''}${prod.categoria || ''}
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: bold; color: #0d6efd; font-size: 16px;">
                                        $${prod.precio_venta.toFixed(2)}
                                    </div>
                                    <div style="font-size: 12px; color: ${stockColor}; font-weight: bold;">
                                        📦 ${prod.cantidad} unidades
                                    </div>
                                </div>
                            </div>`;
                                });
                                resultadosDiv.innerHTML = html;
                                document.querySelectorAll('.producto-busqueda-item').forEach(item => {
                                    item.addEventListener('click', function () {
                                        const codigo = this.dataset.codigo;
                                        const nombre = this.dataset.nombre;
                                        const precio = this.dataset.precio;
                                        const stock = this.dataset.stock;
                                        const imagen = this.dataset.imagen || '';
                                        document.getElementById('codigoProductoSeleccionado').value = codigo;
                                        document.getElementById('precioProductoSeleccionado').value = precio;
                                        document.getElementById('stockProductoSeleccionado').value = stock;
                                        document.getElementById('imagenProductoSeleccionado').value = imagen;
                                        buscadorInput.value = `${nombre} (Cód: ${codigo})`;
                                        resultadosDiv.style.display = 'none';
                                        mostrarImagenProducto(codigo, imagen);
                                        document.getElementById('cantidadInput').focus();
                                        document.getElementById('cantidadInput').select();
                                    });
                                });
                            }
                            resultadosDiv.style.display = 'block';
                        })
                        .catch(err => {
                            console.error('Error:', err);
                            resultadosDiv.innerHTML = '<div style="padding:15px;color:#dc3545">❌ Error de conexión</div>';
                            resultadosDiv.style.display = 'block';
                        });
                }, 300);
            });

            // CERRAR RESULTADOS AL HACER CLIC FUERA (PERO MANTENER FOCO)
            document.addEventListener('click', function (e) {
                const modalAbierto = document.querySelector('.modal-cobro[style*="display: flex"]');
                const esEnModal = e.target.closest('.modal-cobro');
                const esEnCarrito = e.target.closest('.producto-item');
                const esEnBoton = e.target.closest('button');
                const esEnInputCantidad = e.target.closest('input[type="number"]');

                // Cerrar resultados si no es en buscador
                if (!e.target.closest('#buscadorProductos') && !e.target.closest('#resultadosBusqueda')) {
                    resultadosDiv.style.display = 'none';
                }

                // 🔥 MANTENER FOCO EXCEPTO EN ELEMENTOS ESPECÍFICOS
                if (!modalAbierto && !esEnModal && !esEnCarrito && !esEnBoton && !esEnInputCantidad) {
                    setTimeout(() => {
                        mantenerFocoBuscador();
                    }, 100);
                }
            });

            // ENTER EN CANTIDAD → AGREGAR
            document.getElementById('cantidadInput').addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    window.agregarProducto();
                }
            });

            // ENTER EN BUSCADOR → BUSCAR Y AGREGAR
            buscadorInput.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const codigo = document.getElementById('codigoProductoSeleccionado').value;
                    if (codigo) {
                        window.agregarProducto();
                    } else {
                        const term = buscadorInput.value.trim();
                        if (term.length >= 1) {
                            buscarYAgregarProducto(term);
                        } else {
                            window.mostrarNotificacion('⚠️ Ingresa un código de barras', 'danger');
                            buscadorInput.focus();
                        }
                    }
                }
            });



            // 🔥 REFOCAR DESPUÉS DE CUALQUIER CLIC (excepto en elementos específicos)
            document.addEventListener('click', function (e) {
                const modalAbierto = document.querySelector('.modal-cobro[style*="display: flex"]');
                const esEnModal = e.target.closest('.modal-cobro');
                const esEnCarrito = e.target.closest('.producto-item');
                const esEnBoton = e.target.closest('button');
                const esEnInputCantidad = e.target.closest('input[type="number"]');

                // Solo refocar si NO estamos en modal, carrito, botones o inputs de cantidad
                if (!modalAbierto && !esEnModal && !esEnCarrito && !esEnBoton && !esEnInputCantidad) {
                    setTimeout(() => {
                        mantenerFocoBuscador();
                    }, 100);
                }
            });
        });

        // 🔥 REFOCAR AUTOMÁTICAMENTE CADA 2 SEGUNDOS (SI NO HAY MODAL ABIERTO)
        setInterval(() => {
            const modalAbierto = document.querySelector('.modal-cobro[style*="display: flex"]');
            const modalTicketAbierto = document.getElementById('modalTicket')?.style.display === 'flex';

            if (!modalAbierto && !modalTicketAbierto) {
                mantenerFocoBuscador();
            }
        }, 2000);

        // 🔥 REFOCAR DESPUÉS DE NUEVA VENTA
        window.nuevaVenta = function () {
            window.location.href = 'CRUD-Ventas.php';
        }
    </script>

    <script>
        // 🔥 ATAJOS DE TECLADO
        document.addEventListener('keydown', function (e) {
            // F2 = Ver Productos
            if (e.key === 'F2') {
                e.preventDefault();
                window.location.href = '../productos/CRUD-Product.php';
            }

            // Ctrl + Enter = Registrar Venta (si hay productos)
            if (e.key === 'Enter' && e.ctrlKey) {
                e.preventDefault();
                const btnRegistrar = document.getElementById('btnRegistrar');
                if (!btnRegistrar.disabled) {
                    document.getElementById('formVenta').submit();
                }
            }
        });

        // ============================================
        // 🔥 FUNCIONES DEL MODAL DE COBRO
        // ============================================

        let totalVentaActual = 0;

        // Abrir modal de cobro
        window.abrirModalCobro = function () {
            totalVentaActual = parseFloat(document.getElementById('totalVenta').textContent) || 0;
            // 🔥 VALIDAR QUE HAYA PRODUCTOS
            const productosJSON = document.getElementById('productosJSON');
            if (productosVenta.length === 0 || productosJSON.value === '' || productosJSON.value === '[]') {
                mostrarNotificacion('⚠️ No hay productos para cobrar', 'danger');
                return;
            }
            if (totalVentaActual <= 0) {
                mostrarNotificacion('⚠️ El total de la venta es $0.00', 'danger');
                return;
            }
            document.getElementById('totalCobrarModal').textContent = '$' + totalVentaActual.toFixed(2);
            document.getElementById('cantidadRecibida').value = '';
            document.getElementById('cambioContainer').style.display = 'none';
            document.getElementById('btnConfirmarVenta').disabled = true;

            // 🔥 RESETEAR ESTILOS DEL BOTÓN
            const btnConfirmar = document.getElementById('btnConfirmarVenta');
            btnConfirmar.style.background = '#6c757d';
            btnConfirmar.style.cursor = 'not-allowed';
            btnConfirmar.style.opacity = '0.5';
            btnConfirmar.style.boxShadow = 'none';

            // Generar opciones de pago
            generarOpcionesPago(totalVentaActual);
            document.getElementById('modalCobro').style.display = 'flex';
            document.getElementById('cantidadRecibida').focus();

            console.log('🛒 Modal abierto - Total:', totalVentaActual);
        }

        // Cerrar modal de cobro
        window.cerrarModalCobro = function () {
            document.getElementById('modalCobro').style.display = 'none';
            setTimeout(() => {
                mantenerFocoBuscador();
            }, 300);
        }

        window.cerrarModalTicket = function () {
            document.getElementById('modalTicket').style.display = 'none';
            setTimeout(() => {
                mantenerFocoBuscador();
            }, 300);
        }

        // Generar opciones de pago rápidas
        function generarOpcionesPago(total) {
            const container = document.getElementById('opcionesPago');
            container.innerHTML = '';

            // Calcular opciones
            const opciones = calcularOpcionesPago(total);

            opciones.forEach(opcion => {
                const btn = document.createElement('button');
                btn.className = 'opcion-pago-btn';
                btn.textContent = '$' + opcion.monto.toLocaleString('es-MX', { minimumFractionDigits: 2 });
                btn.onclick = () => {
                    document.getElementById('cantidadRecibida').value = opcion.monto.toFixed(2);
                    calcularCambio();
                };
                container.appendChild(btn);
            });
        }

        // Calcular opciones de pago inteligentes
        function calcularOpcionesPago(total) {
            const opciones = [];

            // Opción 1: Exacto
            opciones.push({
                monto: total,
                etiqueta: 'Exacto'
            });

            // Opción 2: Billete inmediato superior
            const billetesInmediatos = [10, 20, 50, 100, 200, 500, 1000, 2000];
            let billeteInmediato = billetesInmediatos.find(b => b >= total);
            if (!billeteInmediato) {
                // Si es más de 2000, redondear al siguiente mil
                billeteInmediato = Math.ceil(total / 1000) * 1000;
            }
            if (billeteInmediato > total) {
                opciones.push({
                    monto: billeteInmediato,
                    etiqueta: 'Billete'
                });
            }

            // Opción 3: Billete mayor
            const index = billetesInmediatos.indexOf(billeteInmediato);
            if (index < billetesInmediatos.length - 1) {
                const billeteMayor = billetesInmediatos[index + 1];
                if (billeteMayor > total) {
                    opciones.push({
                        monto: billeteMayor,
                        etiqueta: 'Mayor'
                    });
                }
            }

            // Limitar a 3-4 opciones
            return opciones.slice(0, 4);
        }

        // Calcular cambio
        window.calcularCambio = function () {
            const cantidadRecibida = parseFloat(document.getElementById('cantidadRecibida').value) || 0;
            const cambioContainer = document.getElementById('cambioContainer');
            const cambioMonto = document.getElementById('cambioMonto');
            const btnConfirmar = document.getElementById('btnConfirmarVenta');

            console.log('💰 Total:', totalVentaActual);
            console.log('💵 Recibido:', cantidadRecibida);

            if (cantidadRecibida >= totalVentaActual && cantidadRecibida > 0) {
                const cambio = cantidadRecibida - totalVentaActual;
                cambioMonto.textContent = '$' + cambio.toFixed(2);
                cambioContainer.style.display = 'block';

                // 🔥 HABILITAR BOTÓN
                btnConfirmar.disabled = false;
                btnConfirmar.style.background = 'linear-gradient(135deg, #28a745 0%, #20c997 100%)';
                btnConfirmar.style.cursor = 'pointer';
                btnConfirmar.style.opacity = '1';
                btnConfirmar.style.boxShadow = '0 4px 15px rgba(40, 167, 69, 0.4)';
                btnConfirmar.style.setProperty('background', 'linear-gradient(135deg, #28a745 0%, #20c997 100%)', 'important');

                console.log('✅ Botón HABILITADO');
            } else {
                cambioContainer.style.display = 'none';

                // 🔥 DESHABILITAR BOTÓN
                btnConfirmar.disabled = true;
                btnConfirmar.style.background = '#6c757d';
                btnConfirmar.style.cursor = 'not-allowed';
                btnConfirmar.style.opacity = '0.5';
                btnConfirmar.style.boxShadow = 'none';
                btnConfirmar.style.setProperty('background', '#6c757d', 'important');

                console.log('❌ Botón DESHABILITADO');
            }
        }

        // ============================================
        // 🔥 SELECCIONAR MÉTODO DE PAGO
        // ============================================
        function seleccionarMetodoPago(metodo) {
            const btnEfectivo = document.getElementById('btnEfectivo');
            const btnTransferencia = document.getElementById('btnTransferencia');
            const inputCantidad = document.getElementById('cantidadRecibida');
            const opcionesPago = document.getElementById('opcionesPago');
            const cambioContainer = document.getElementById('cambioContainer');

            if (metodo === 'efectivo') {
                // Estilo Activo Efectivo
                btnEfectivo.style.background = '#0d6efd'; // Azul
                btnTransferencia.style.background = '#6c757d'; // Gris
                inputCantidad.style.borderColor = '#0d6efd';

                // Habilitar input y mostrar opciones
                inputCantidad.disabled = false;
                inputCantidad.value = ''; // Limpiar para que el usuario ingrese
                inputCantidad.focus();

                // Mostrar botones de opciones rápidas (billetes)
                opcionesPago.style.display = 'flex';
                generarOpcionesPago(totalVentaActual); // Regenerar opciones

                // Ocultar cambio hasta que se ingrese monto
                cambioContainer.style.display = 'none';

                // Deshabilitar botón confirmar hasta que haya monto
                document.getElementById('btnConfirmarVenta').disabled = true;
                document.getElementById('btnConfirmarVenta').style.background = '#6c757d';

            } else if (metodo === 'transferencia') {
                // Estilo Activo Transferencia
                btnTransferencia.style.background = '#28a745'; // Verde
                btnEfectivo.style.background = '#6c757d'; // Gris
                inputCantidad.style.borderColor = '#28a745';

                // Lógica Transferencia: El monto es el exacto de la venta
                inputCantidad.value = totalVentaActual.toFixed(2);
                inputCantidad.disabled = true; // Bloquear input porque no se da cambio

                // Ocultar opciones de billetes y cambio
                opcionesPago.style.display = 'none';
                cambioContainer.style.display = 'none';

                // Habilitar botón confirmar inmediatamente
                document.getElementById('btnConfirmarVenta').disabled = false;
                document.getElementById('btnConfirmarVenta').style.background = '#28a745';
            }
        }

        // Confirmar venta
        window.confirmarVenta = function () {
            // 🔥 DETECTAR QUÉ MÉTODO ESTÁ ACTIVO
            const btnEfectivo = document.getElementById('btnEfectivo');
            // Si el botón tiene el color azul (#0d6efd), es efectivo. Si es verde (#28a745), es transferencia.
            const metodoPago = btnEfectivo.style.background.includes('0d6efd') ? 'Efectivo' : 'Transferencia';

            let cantidadRecibida = parseFloat(document.getElementById('cantidadRecibida').value) || 0;
            const cambio = cantidadRecibida - totalVentaActual;

            // 🔥 VALIDACIÓN PARA EFECTIVO
            if (metodoPago === 'Efectivo' && cantidadRecibida < totalVentaActual) {
                mostrarNotificacion('⚠️ El monto recibido es insuficiente', 'danger');
                return;
            }

            // Enviar formulario de venta
            const form = document.getElementById('formVenta');
            const productosJSON = document.getElementById('productosJSON');

            if (productosJSON.value === '' || productosJSON.value === '[]') {
                mostrarNotificacion('⚠️ No hay productos en la venta', 'danger');
                return;
            }

            productosJSON.value = JSON.stringify(productosVenta);

            // 1️⃣ Input MÉTODO PAGO
            const inputMetodoPago = document.createElement('input');
            inputMetodoPago.type = 'hidden';
            inputMetodoPago.name = 'metodo_pago';
            inputMetodoPago.value = metodoPago;
            form.appendChild(inputMetodoPago);

            // 2️⃣ Input CANTIDAD RECIBIDA
            const inputCantidadRecibida = document.createElement('input');
            inputCantidadRecibida.type = 'hidden';
            inputCantidadRecibida.name = 'cantidad_recibida';
            inputCantidadRecibida.value = cantidadRecibida;
            form.appendChild(inputCantidadRecibida);

            // 3️⃣ Input CAMBIO
            const inputCambio = document.createElement('input');
            inputCambio.type = 'hidden';
            inputCambio.name = 'cambio';
            inputCambio.value = cambio;
            form.appendChild(inputCambio);

            cerrarModalCobro();
            form.submit();
        }

        // Mostrar modal de ticket exitoso
        function mostrarModalTicket(folio, total, cambio) {
            document.getElementById('folioVenta').textContent = folio;
            document.getElementById('totalCobradoTicket').textContent = '$' + total.toFixed(2);

            if (cambio > 0) {
                document.getElementById('cambioTicket').textContent = '🔄 Cambio: $' + cambio.toFixed(2);
            } else {
                document.getElementById('cambioTicket').textContent = '💵 Pago exacto';
            }

            document.getElementById('modalTicket').style.display = 'flex';
        }

        // Cerrar modal de ticket
        function cerrarModalTicket() {
            document.getElementById('modalTicket').style.display = 'none';
        }

        // Nueva venta
        function nuevaVenta() {
            window.location.href = 'CRUD-Ventas.php';
        }

        // Cerrar modales con ESC
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                cerrarModalCobro();
                cerrarModalTicket();
            }
        });

        // Cerrar modal al hacer clic fuera
        document.getElementById('modalCobro').addEventListener('click', function (e) {
            if (e.target === this) {
                cerrarModalCobro();
            }
        });

        document.getElementById('modalTicket').addEventListener('click', function (e) {
            if (e.target === this) {
                cerrarModalTicket();
            }
        });
    </script>

    <!-- 🔥 MODAL DE COBRO -->
    <div id="modalCobro" class="modal-cobro"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000; justify-content:center; align-items:center;">
        <div class="modal-cobro-content"
            style="background:#fff; border-radius:12px; padding:30px; max-width:500px; width:90%; box-shadow:0 10px 40px rgba(0,0,0,0.2);">
            <!-- Header -->
            <div
                style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:15px; border-bottom:1px solid #eee;">
                <h3 style="margin:0; color:#333; font-size:20px;">💳 Registrar Venta</h3>
                <button type="button" onclick="cerrarModalCobro()"
                    style="background:none; border:none; font-size:24px; cursor:pointer; color:#666;">✕</button>
            </div>

            <!-- Total a Cobrar -->
            <div style="text-align:center; margin:30px 0;">
                <p style="color:#666; font-size:14px; margin-bottom:10px;">Total a cobrar</p>
                <h1 id="totalCobrarModal" style="font-size:48px; color:#333; margin:0; font-weight:bold;">$0.00</h1>
            </div>

            <!-- Método de Pago -->
            <div class="metodo-pago" style="background:#f8f9fa; border-radius:8px; padding:20px; margin-bottom:20px;">
                <div style="display:flex; gap:10px; margin-bottom:10px;">
                    <button type="button" id="btnEfectivo" onclick="seleccionarMetodoPago('efectivo')"
                        style="flex:1; padding:15px; background:#0d6efd; color:white; border:none; border-radius:8px; font-size:16px; font-weight:bold; cursor:pointer; transition:all 0.3s ease;">
                        💵 Efectivo
                    </button>
                    <button type="button" id="btnTransferencia" onclick="seleccionarMetodoPago('transferencia')"
                        style="flex:1; padding:15px; background:#6c757d; color:white; border:none; border-radius:8px; font-size:16px; font-weight:bold; cursor:pointer; transition:all 0.3s ease;">
                        🏦 Transferencia
                    </button>
                </div>

                <!-- Input de Cantidad -->
                <div style="display:flex; gap:10px; margin-bottom:15px;">
                    <div style="flex:1; position:relative;">
                        <span
                            style="position:absolute; left:15px; top:50%; transform:translateY(-50%); color:#666; font-size:18px;">$</span>
                        <input type="number" id="cantidadRecibida" placeholder="0.00" min="0" step="0.01"
                            style="width:100%; padding:15px 15px 15px 35px; border:2px solid #0d6efd; border-radius:8px; font-size:18px; font-weight:bold; text-align:left;"
                            oninput="calcularCambio(); console.log('Input cambiado:', this.value);">
                    </div>
                </div>

                <!-- Botones de Opciones Rápidas -->
                <div id="opcionesPago" style="display:flex; gap:10px; flex-wrap:wrap;">
                    <!-- Se generan dinámicamente con JS -->
                </div>

                <!-- Cambio -->
                <div id="cambioContainer"
                    style="display:none; background:#d4edda; border-radius:8px; padding:15px; margin-top:15px; text-align:center;">
                    <p style="margin:0; color:#155724; font-size:14px;">🔄 Cambio a devolver:</p>
                    <h2 id="cambioMonto" style="margin:5px 0 0 0; color:#28a745; font-size:32px; font-weight:bold;">
                        $0.00</h2>
                </div>
            </div>

            <!-- Botones de Acción -->
            <div style="display:flex; gap:10px;">
                <button type="button" onclick="cerrarModalCobro()"
                    style="flex:1; padding:15px; background:#6c757d; color:white; border:none; border-radius:8px; font-size:16px; font-weight:bold; cursor:pointer; transition:0.2s;">
                    ❌ Cancelar
                </button>
                <button type="button" onclick="confirmarVenta()" id="btnConfirmarVenta" disabled
                    style="flex:1; padding:15px; background:#6c757d; color:white; border:none; border-radius:8px; font-size:16px; font-weight:bold; cursor:not-allowed; transition:all 0.3s ease;"
                    data-disabled-bg="#6c757d" data-enabled-bg="linear-gradient(135deg, #28a745 0%, #20c997 100%)">
                    ✅ Confirmar Venta
                </button>
            </div>
        </div>
    </div>

    <!-- 🔥 MODAL DE TICKET/COMPROBANTE -->
    <div id="modalTicket" class="modal-cobro"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10001; justify-content:center; align-items:center;">
        <div class="modal-ticket-content"
            style="background:#fff; border-radius:12px; padding:30px; max-width:400px; width:90%; box-shadow:0 10px 40px rgba(0,0,0,0.2); text-align:center;">
            <div
                style="width:80px; height:80px; background:#d4edda; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px auto;">
                <i class="bi bi-check-circle-fill" style="font-size:48px; color:#28a745;"></i>
            </div>
            <h2 style="color:#28a745; margin-bottom:10px;">¡Venta Registrada!</h2>
            <p style="color:#666; margin-bottom:20px;">Folio: <strong id="folioVenta" style="color:#333;"></strong></p>
            <div style="background:#f8f9fa; border-radius:8px; padding:20px; margin-bottom:20px;">
                <p style="margin:5px 0; color:#666;">Total Cobrado:</p>
                <h1 id="totalCobradoTicket" style="color:#333; font-size:36px; margin:10px 0;">$0.00</h1>
                <p id="cambioTicket" style="margin:5px 0; color:#28a745; font-weight:bold;"></p>
            </div>
            <button type="button" onclick="cerrarModalTicket()"
                style="width:100%; padding:15px; background:#0d6efd; color:white; border:none; border-radius:8px; font-size:16px; font-weight:bold; cursor:pointer; transition:0.2s;">
                🖨️ Imprimir Ticket
            </button>
            <button type="button" onclick="nuevaVenta()"
                style="width:100%; padding:15px; background:#28a745; color:white; border:none; border-radius:8px; font-size:16px; font-weight:bold; cursor:pointer; transition:0.2s; margin-top:10px;">
                ➕ Nueva Venta
            </button>
        </div>
    </div>


</body>

</html>