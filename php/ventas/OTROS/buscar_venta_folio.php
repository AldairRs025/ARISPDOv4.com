<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../../index.html");
    exit;
}
include("../conexion.php");

// 🔥 NUEVO: Obtener todos los folios de ventas creados
$folios_query = mysqli_query($conn, "SELECT v.folio, v.fecha_venta, v.total_venta, v.ganancia, v.estado, u.username as vendedor
FROM ventas v
LEFT JOIN usuarios u ON v.usuario_id = u.id
ORDER BY v.fecha_venta DESC");
$folios = [];
while ($row = mysqli_fetch_assoc($folios_query)) {
    $folios[] = $row;
}

$folio_busqueda = $_GET['folio'] ?? '';
$venta = null;
$detalle = [];

if ($folio_busqueda) {
    // Buscar venta
    $sql = "SELECT v.*, u.username as vendedor
    FROM ventas v
    LEFT JOIN usuarios u ON v.usuario_id = u.id
    WHERE v.folio = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $folio_busqueda);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $venta = mysqli_fetch_assoc($result);

    // Buscar detalle
    if ($venta) {
        $sql_detalle = "SELECT d.*, p.nombre_producto
        FROM detalle_venta d
        LEFT JOIN productos p ON d.codigo_barra = p.codigo_barra
        WHERE d.venta_id = ?";
        $stmt_detalle = mysqli_prepare($conn, $sql_detalle);
        mysqli_stmt_bind_param($stmt_detalle, "i", $venta['id']);
        mysqli_stmt_execute($stmt_detalle);
        $detalle = mysqli_stmt_get_result($stmt_detalle);
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>🔍 Buscar Venta por Folio</title>
    <!-- 🔥 MISMO CSS QUE BUSCAR_COMPRA_FOLIO.PHP -->
    <link rel="stylesheet" href="../../css/Ventas-STYLE/BuscarVentaFolio2.css">
    <link rel="icon" href="../../img/LOGIN/icon_24px.png" type="img/INICIO/icon_24px.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- 🔥 FUENTES IDÉNTICAS -->
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Kalam:wght@300;400;700&display=swap"
        rel="stylesheet">
    <style>
        /* ===================================
        🔥 ESTILOS IDÉNTICOS A BUSCAR_COMPRA_FOLIO.PHP
        =================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f5f5;
            font-family: 'Kalam', cursive;
            /* 🔥 MISMA FUENTE */
        }

        /* BARRA SUPERIOR */
        .barra-superior {
            width: 100%;
            height: 60px;
            background: #000;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            position: relative;
            z-index: 1000;
        }

        .logo {
            font-family: 'Pacifico', cursive;
            /* 🔥 MISMA FUENTE LOGO */
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

        .icon-btn i {
            font-size: 26px;
            color: #000;
            transition: all 0.25s ease;
        }

        .icon-btn:hover {
            transform: scale(1.12);
            box-shadow: 0 0 12px rgba(255, 255, 255, 0.7);
        }

        .icon-btn:hover i {
            color: #474747;
            text-shadow: 0 0 6px rgba(0, 0, 0, 0.8);
        }

        .icon-btn {
            position: relative;
        }

        .icon-btn .tooltip {
            position: absolute;
            top: 120%;
            left: 50%;
            transform: translateX(-50%);
            background: #8b8b8b;
            color: #000000;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            z-index: 9999;
        }

        .icon-btn .tooltip::after {
            content: "";
            position: absolute;
            top: -13px;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-bottom-color: #000;
        }

        .icon-btn:hover .tooltip {
            opacity: 1;
        }

        .barra-inferior {
            width: 100%;
            height: 50px;
            background: #333;
            position: fixed;
            bottom: 0;
            left: 0;
        }

        /* CONTENEDOR PRINCIPAL */
        .container {
            margin: 50px auto 50px auto;
            padding: 0;
            max-width: 1200px;
        }

        /* 🔥 TÍTULO PRINCIPAL - MISMA TIPOGRAFÍA */
        .titulo-principal {
            font-family: 'Pacifico', cursive;
            font-size: 42px;
            color: #2d3748;
            text-align: center;
            margin-bottom: 30px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            animation: fadeInDown 0.6s ease;
        }

        /* PANEL DE BÚSQUEDA */
        .panel-busqueda {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .filtro-group {
            margin-bottom: 25px;
        }

        .filtro-group label {
            display: block;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 15px;
        }

        .filtro-group input[type="text"] {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
        }

        .filtro-group input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Kalam', cursive;
            /* 🔥 MISMA FUENTE INPUTS */
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* BOTONES DE BÚSQUEDA */
        .botones-busqueda {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn-buscar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Kalam', cursive;
            /* 🔥 MISMA FUENTE BOTONES */
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-buscar:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-limpiar {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Kalam', cursive;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(245, 87, 108, 0.3);
            text-decoration: none;
        }

        .btn-limpiar:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(245, 87, 108, 0.4);
        }

        .btn-listar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Kalam', cursive;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-listar:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        /* PANEL DEL TICKET */
        .panel-ticket {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            display: none;
        }

        .panel-ticket.activo {
            display: block;
        }

        /* 🔥 TÍTULO DEL TICKET - MISMA TIPOGRAFÍA */
        .titulo-ticket {
            font-family: 'Pacifico', cursive;
            color: #667eea;
            font-size: 32px;
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 4px solid #e2e8f0;
        }

        .info-ticket {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
            padding: 25px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            border-radius: 12px;
            border: 2px solid #e2e8f0;
        }

        .info-ticket div {
            padding: 12px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .info-ticket div:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .info-ticket strong {
            color: #667eea;
            font-weight: 600;
            display: block;
            margin-bottom: 5px;
        }

        /* TABLA DE PRODUCTOS */
        .tabla-productos {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 20px;
            overflow: hidden;
            border-radius: 10px;
        }

        .tabla-productos thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .tabla-productos th {
            padding: 18px 15px;
            text-align: left;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-family: 'Kalam', cursive;
        }

        .tabla-productos td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            color: #2c3e50;
            font-size: 14px;
            font-family: 'Kalam', cursive;
        }

        .tabla-productos tbody tr:hover {
            background: #f8f9fa;
        }

        .tabla-productos tbody tr:last-child td {
            border-bottom: none;
        }

        .ganancia-positiva {
            color: #28a745;
        }

        .ganancia-negativa {
            color: #dc3545;
        }

        /* BOTONES DE ACCIÓN */
        .botones-accion {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-accion {
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Kalam', cursive;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-nueva {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(56, 239, 125, 0.3);
        }

        .btn-nueva:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(56, 239, 125, 0.4);
        }

        .btn-historial {
            background: linear-gradient(135deg, #232526 0%, #414345 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(65, 67, 69, 0.3);
        }

        .btn-historial:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(65, 67, 69, 0.4);
        }

        .btn-editar {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(79, 172, 254, 0.3);
        }

        .btn-editar:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(79, 172, 254, 0.4);
        }

        .btn-eliminar {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(235, 51, 73, 0.3);
        }

        .btn-eliminar:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(235, 51, 73, 0.4);
        }

        /* MENSAJE DE ERROR */
        .mensaje-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            font-weight: 700;
            border-left: 5px solid #dc3545;
            font-family: 'Kalam', cursive;
        }

        .modal-folios {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        .modal-content-folios {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 900px;
            max-height: 80vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        @media (max-width: 768px) {
            .modal-content-folios {
                width: 95%;
                max-height: 90vh;
            }

            .tabla-folios thead th,
            .tabla-folios tbody td {
                padding: 12px 10px;
                font-size: 12px;
            }

            .btn-ver-ticket {
                padding: 6px 12px;
                font-size: 11px;
            }
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: #000000;
            /* ✅ Negro sólido */
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 24px;
            /* font-family: 'Pacifico', cursive; */
            color: #ffffff;
            /* ✅ Blanco */
        }

        .btn-cerrar {
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-cerrar:hover {
            transform: scale(1.2);
        }

        .modal-body {
            padding: 30px;
            overflow-y: auto;
            max-height: calc(80vh - 90px);
        }

        .tabla-folios {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .tabla-folios thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .tabla-folios thead th {
            padding: 18px 15px;
            font-weight: 600;
            text-align: left;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .tabla-folios tbody tr {
            border-bottom: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .tabla-folios tbody tr:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            transform: scale(1.01);
        }

        .tabla-folios tbody tr:last-child {
            border-bottom: none;
        }

        .tabla-folios tbody td {
            padding: 16px 15px;
            color: #2d3748;
            font-size: 14px;
        }

        .btn-ver-ticket {
            display: inline-block;
            padding: 8px 16px;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(17, 153, 142, 0.3);
        }

        .btn-ver-ticket:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(17, 153, 142, 0.4);
        }

        .estado-completada {
            background: #d4edda;
            color: #155724;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
            font-family: 'Kalam', cursive;
        }

        .estado-cancelada {
            background: #f8d7da;
            color: #721c24;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
            font-family: 'Kalam', cursive;
        }

        .estado-pendiente {
            background: #fff3cd;
            color: #856404;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
            font-family: 'Kalam', cursive;
        }

        /* ============================================
        🔥 MODAL EDITAR TICKET - AGREGAR ESTO
        ============================================ */
        .modal-editar-ticket {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            display: none;
            /* 🔥 IMPORTANTE: hidden por defecto */
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        .modal-content-editar {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        .modal-header-editar {
            background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%);
            color: #000;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header-editar h2 {
            margin: 0;
            font-size: 24px;
            font-family: 'Pacifico', cursive;
        }

        .btn-cerrar-modal {
            background: rgba(0, 0, 0, 0.1);
            border: none;
            color: #000;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .btn-cerrar-modal:hover {
            background: rgba(0, 0, 0, 0.2);
            transform: rotate(90deg);
        }

        .modal-body-editar {
            padding: 30px;
            overflow-y: auto;
            max-height: calc(90vh - 150px);
        }

        .info-ticket-editar {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .info-ticket-editar div {
            padding: 10px;
            background: white;
            border-radius: 6px;
        }

        .tabla-editar-ticket {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .tabla-editar-ticket thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .tabla-editar-ticket thead th {
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
        }

        .tabla-editar-ticket tbody tr {
            border-bottom: 1px solid #e2e8f0;
        }

        .tabla-editar-ticket tbody tr:hover {
            background: #f8f9fa;
        }

        .tabla-editar-ticket tbody td {
            padding: 12px 10px;
        }

        .btn-eliminar-producto {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-eliminar-producto:hover {
            background: #c82333;
            transform: scale(1.1);
        }

        .modal-footer-editar {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
        }

        .btn-guardar-cambios {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .btn-guardar-cambios:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
        }

        .btn-cancelar-cambios {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .btn-cancelar-cambios:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.4);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .modal-content-editar {
                width: 95%;
                max-height: 95vh;
            }

            .info-ticket-editar {
                grid-template-columns: 1fr;
            }

            .tabla-editar-ticket {
                font-size: 12px;
            }
        }

        /* ============================================
        🔥 BUSCADOR PRODUCTOS EDITAR TICKET
        ============================================ */
        #resultadosBusquedaEditar {
            max-height: 300px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            margin-top: 5px;
        }

        #infoProductoSeleccionado {
            animation: fadeIn 0.3s ease;
        }

        /* Scrollbar personalizado */
        #resultadosBusquedaEditar::-webkit-scrollbar {
            width: 8px;
        }

        #resultadosBusquedaEditar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        #resultadosBusquedaEditar::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 4px;
        }

        #resultadosBusquedaEditar::-webkit-scrollbar-thumb:hover {
            background: #764ba2;
        }

        .producto-busqueda-item-editar {
            transition: background 0.2s;
            border-bottom: 1px solid #f0f0f0;
        }

        .producto-busqueda-item-editar:last-child {
            border-bottom: none;
        }

        .producto-busqueda-item-editar:hover {
            background: #f8f9fa !important;
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
            <a href="historial_ventas.php" class="icon-btn">
                <i class="bi bi-clock-history"></i>
                <span class="tooltip">HISTORIAL</span>
            </a>
            <a href="logout.php" class="icon-btn">
                <i class="bi bi-box-arrow-right"></i>
                <span class="tooltip">SALIR</span>
            </a>
        </div>
    </div>

    <div class="container">
        <!-- 🔥 TÍTULO PRINCIPAL CON MISMA TIPOGRAFÍA -->
        <h1 class="titulo-principal">
            🔍 Buscar Venta por Folio
        </h1>

        <!-- PANEL DE BÚSQUEDA -->
        <div class="panel-busqueda">
            <form method="GET">
                <div class="filtro-group">
                    <label>Folio de Venta</label>
                    <input type="text" name="folio" class="form-input" placeholder="Ej: VTA-20260314-0001"
                        value="<?= htmlspecialchars($folio_busqueda) ?>" required>
                </div>

                <div class="botones-busqueda">
                    <button type="submit" class="btn-buscar">
                        🔍 Buscar
                    </button>
                    <a href="buscar_venta_folio.php" class="btn-limpiar">
                        🧹 Limpiar
                    </a>
                    <button type="button" class="btn-listar" onclick="mostrarListaFolios()">
                        📋 Ver Todos los Folios
                    </button>
                </div>
            </form>
        </div>

        <!-- PANEL DEL TICKET (Se muestra solo si hay resultados) -->
        <?php if ($venta): ?>
            <div class="panel-ticket activo">
                <!-- 🔥 TÍTULO DEL TICKET CON MISMA TIPOGRAFÍA -->
                <h2 class="titulo-ticket"
                    style="text-align:center; color:black; border-bottom:3px solid #0d6efd; padding-bottom:15px;">
                    📋 Ticket de Venta # <?= htmlspecialchars($venta['folio']) ?>
                </h2>

                <!-- INFORMACIÓN DEL TICKET -->
                <div class="info-ticket"
                    style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin:20px 0; padding:15px; background:#f8f9fa; border-radius:8px;">
                    <div>
                        <strong>👤 Vendedor:</strong>
                        <?= htmlspecialchars($venta['vendedor'] ?? 'N/A') ?>
                    </div>
                    <div>
                        <strong>📅 Fecha y Hora:</strong>
                        <?= date('d/m/Y h:i A', strtotime($venta['fecha_venta'])) ?>
                    </div>
                    <div>
                        <strong>💵 Método de Pago:</strong>
                        <?= htmlspecialchars($venta['metodo_pago']) ?>
                    </div>
                    <div>
                        <strong>🎫 Folio:</strong>
                        <?= htmlspecialchars($venta['folio']) ?>
                    </div>
                    <div>
                        <strong>✅ Estado:</strong>
                        <span class="estado-<?= $venta['estado'] ?>">
                            <?= ucfirst($venta['estado']) ?>
                        </span>
                    </div>
                    <div>
                        <strong>💵 Dinero Recibido:</strong>
                        <?= htmlspecialchars($venta['metodo_pago']) ?>
                    </div>
                    <div>
                        <strong>💵 Cambio:</strong>
                        <?= htmlspecialchars($venta['metodo_pago']) ?>
                    </div>
                </div>

                <!-- TABLA DE PRODUCTOS -->
                <table class="tabla-productos">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>P. Venta</th>
                            <!-- <th>P. Compra</th> -->
                            <th>Subtotal</th>
                            <!-- <th>Ganancias</th> -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_items = 0;
                        $total_ganancias = 0;
                        while ($row = mysqli_fetch_assoc($detalle)):
                            $total_items += $row['cantidad'];
                            // 🔥 CALCULAR GANANCIAS
                            $precio_venta = (float) $row['precio_venta'];
                            // $precio_compra = (float) $row['precio_compra'];
                            $cantidad = (float) $row['cantidad'];
                            // $ganancia_unitaria = $precio_venta - $precio_compra;
                            // $ganancia_total = $ganancia_unitaria * $cantidad;
                            // $porcentaje_ganancia = $precio_compra > 0 ? (($ganancia_unitaria / $precio_compra) * 100) : 0;
                            // 🔥 COLOR SEGÚN GANANCIA
                            // $color_ganancia = $ganancia_total >= 0 ? '#28a745' : '#dc3545';
                            // $clase_ganancia = $ganancia_total >= 0 ? 'ganancia-positiva' : 'ganancia-negativa';
                            // $total_ganancias += $ganancia_total;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($row['codigo_barra']) ?></td>
                                <td><?= htmlspecialchars($row['nombre_producto'] ?? $row['codigo_barra']) ?></td>
                                <td><?= number_format($row['cantidad'], 2) ?></td>
                                <td>$<?= number_format($row['precio_venta'], 2) ?></td>
                                <!-- <td>$<?= number_format($row['precio_compra'], 2) ?></td> -->
                                <td>$<?= number_format($row['subtotal'], 2) ?></td>
                                <!-- <td class="<?= $clase_ganancia ?>" style="text-align:center;">
                                    <strong style="color:<?= $color_ganancia ?>;">
                                        $<?= number_format($ganancia_total, 2) ?>
                                    </strong>
                                    <br>
                                    <small style="color:#6c757d; font-size:11px;">
                                        (<?= number_format($porcentaje_ganancia, 1) ?>%)
                                    </small>
                                </td> -->
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight:bold; background:#e7f3ff;">
                            <td colspan="4" style="text-align:right;">💰 Total Venta:</td>
                            <td style="text-align:center; font-size:18px; color:#667eea;">
                                $<?= number_format($venta['total_venta'], 2) ?></td>
                        </tr>
                        <tr style="font-weight:bold; background:#d4edda;">
                            <td colspan="4" style="text-align:right;">💰 Ganancia Total:</td>
                            <td
                                style="text-align:center; font-size:18px; color:<?= $total_ganancias >= 0 ? '#28a745' : '#dc3545' ?>;">
                                $<?= number_format($total_ganancias, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>

                <!-- BOTONES DE ACCIÓN -->
                <div
                    style="text-align:center; margin-top:30px; display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
                    <a href="CRUD-Ventas.php" class="btn-accion btn-nueva"><i class="bi bi-cart-plus"></i> Nueva Venta</a>
                    <a href="historial_ventas.php" class="btn-accion btn-historial"><i class="bi bi-arrow-left"></i>
                        Historial</a>
                    <!-- 🔥 BOTÓN EDITAR TICKET - ABRE MODAL -->
                    <button type="button" onclick="abrirModalEditarTicket()" class="btn-accion btn-editar">
                        <i class="bi bi-pencil-square"></i> Editar Ticket
                    </button>
                    <button type="button"
                        onclick="eliminarTicket('<?= htmlspecialchars($venta['folio']) ?>', <?= $venta['id'] ?>)"
                        class="btn-accion btn-eliminar">
                        <i class="bi bi-trash"></i> Eliminar Ticket
                    </button>
                </div>
            </div>
        <?php elseif ($folio_busqueda): ?>
            <!-- MENSAJE DE ERROR -->
            <div class="mensaje-error">
                <i class="bi bi-exclamation-triangle"></i>
                No se encontró ninguna venta con el folio: <strong><?= htmlspecialchars($folio_busqueda) ?></strong>
            </div>
        <?php endif; ?>
    </div>

    <!-- BARRA INFERIOR -->
    <div class="barra-inferior"></div>

    <!-- 🔥 MODAL PARA MOSTRAR TODOS LOS FOLIOS -->
    <div id="modalFolios" class="modal-folios" style="display: none;">
        <div class="modal-content-folios">
            <div class="modal-header">
                <h2>📋 Lista de Ventas Registradas</h2>
                <button type="button" class="btn-cerrar" onclick="cerrarModalFolios()">✕</button>
            </div>
            <div class="modal-body">
                <?php if (count($folios) > 0): ?>
                    <table class="tabla-folios">
                        <thead>
                            <tr>
                                <th>Número de Folio</th>
                                <th>Fecha de Venta</th>
                                <th>Vendedor</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($folios as $f): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($f['folio']) ?></strong></td>
                                    <td><?= date('d/m/Y h:i A', strtotime($f['fecha_venta'])) ?></td>
                                    <td><?= htmlspecialchars($f['vendedor'] ?? 'N/A') ?></td>
                                    <td>$<?= number_format($f['total_venta'], 2) ?></td>
                                    <td>
                                        <span class="estado-<?= $f['estado'] ?>">
                                            <?= ucfirst($f['estado']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="buscar_venta_folio.php?folio=<?= urlencode($f['folio']) ?>"
                                            class="btn-ver-ticket">
                                            🔍 Ver Ticket
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="bi bi-inbox" style="font-size: 48px; margin-bottom: 15px;"></i>
                        <p>No hay ventas registradas</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MOSTRAR/OCULTAR MODAL DE FOLIOS -->
    <script>
        function mostrarListaFolios() {
            document.getElementById('modalFolios').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function cerrarModalFolios() {
            document.getElementById('modalFolios').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('modalFolios').addEventListener('click', function (e) {
            if (e.target === this) {
                cerrarModalFolios();
            }
        });

        // Cerrar modal con tecla ESC
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                cerrarModalEditarTicket();
            }
        });
    </script>

    <script>
        console.log('🔥 INICIANDO SCRIPT...');
        console.log('🔍 Venta ID:', <?= $venta['id'] ?? 0 ?>);
        console.log('🔍 Folio:', '<?= htmlspecialchars($venta['folio'] ?? '') ?>');

        // Verificar elementos al cargar
        document.addEventListener('DOMContentLoaded', function () {
            console.log('✅ DOM Cargado');
            console.log('🔍 Buscador:', document.getElementById('buscadorProductosEditar'));
            console.log('🔍 Botón Agregar:', document.querySelector('[onclick="agregarProductoAlTicketEditar()"]'));
            console.log('🔍 Tabla Body:', document.getElementById('tbodyProductosEditar'));
        });

        // ============================================
        // 🔥 ELIMINAR TICKET COMPLETO
        // ============================================
        function eliminarTicket(folio, venta_id) {
            if (confirm('⚠️ ¿ESTÁS SEGURO DE ELIMINAR ESTE TICKET?\n\nFolio: ' + folio + '\n\nEsta acción eliminará:\n• Todos los productos del ticket\n• Se revertirá el inventario (productos y lotes)\n• Esta acción NO se puede deshacer\n\n¿Continuar?')) {
                fetch('eliminar_ticket_venta.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        folio: folio,
                        venta_id: venta_id
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('✅ ' + data.message);
                            window.location.href = 'buscar_venta_folio.php';
                        } else {
                            alert('❌ Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('❌ Error de conexión. Revisa la consola.');
                    });
            }
        }

        // ============================================
        // 🔥 ABRIR/CERRAR MODAL EDITAR TICKET
        // ============================================
        // 🔥 LLAMAR A LA FUNCIÓN CUANDO SE ABRE EL MODAL
        function abrirModalEditarTicket() {
            console.log('🔥 Abriendo modal...');
            const modal = document.getElementById('modalEditarTicket');

            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';

                // 🔥 IMPORTANTE: Inicializar el buscador DESPUÉS de abrir el modal
                setTimeout(() => {
                    inicializarBuscadorEditarTicket();
                }, 100);

                calcularTotalEditarTicket();
                console.log('✅ Modal abierto');
            } else {
                console.error('❌ Modal no encontrado');
            }
        }

        function cerrarModalEditarTicket() {
            console.log('🔥 Cerrando modal...');
            const modal = document.getElementById('modalEditarTicket');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
                console.log('✅ Modal cerrado');
            }
        }

        // ============================================
        // 🔥 BUSCADOR DE PRODUCTOS PARA EDITAR TICKET - CORREGIDO
        // ============================================
        let buscadorInicializado = false;
        function inicializarBuscadorEditarTicket() {
            console.log('🔥 Inicializando buscador...');
            let debounceTimerEditar;
            const buscadorEditar = document.getElementById('buscadorProductosEditar');
            const resultadosEditar = document.getElementById('resultadosBusquedaEditar');

            // Verificar que los elementos existan
            if (!buscadorEditar || !resultadosEditar) {
                console.error('❌ No se encontraron los elementos del buscador');
                return;
            }

            // ❌ ELIMINADO: Evento CLICK que mostraba todos los productos
            // ❌ ELIMINADO: Evento FOCUS que mostraba todos los productos

            // ✅ Evento input - buscar mientras escribe (MÍNIMO 1 CARÁCTER)
            buscadorEditar.addEventListener('input', function () {
                clearTimeout(debounceTimerEditar);
                const term = this.value.trim();
                console.log('✏️ Escribiendo:', term);

                // Si está vacío, ocultar resultados
                if (term.length === 0) {
                    resultadosEditar.style.display = 'none';
                    return;
                }

                resultadosEditar.style.display = 'block';
                resultadosEditar.innerHTML = '<div style="padding:15px; text-align:center;"><i class="bi bi-hourglass-split"></i> Buscando...</div>';

                debounceTimerEditar = setTimeout(() => {
                    cargarProductosEditar(term);
                }, 300);
            });

            // 🔥 Enter en buscador → seleccionar primero disponible
            buscadorEditar.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const term = this.value.trim();
                    if (term.length > 0) {
                        // Seleccionar primer resultado si existe
                        const primerResultado = document.querySelector('.producto-busqueda-item-editar');
                        if (primerResultado) {
                            primerResultado.click();
                        }
                    }
                }
            });

            // 🔥 Cerrar resultados al hacer clic FUERA
            document.addEventListener('click', function (e) {
                const clickedOnSearch = e.target.closest('#buscadorProductosEditar');
                const clickedOnResults = e.target.closest('#resultadosBusquedaEditar');

                if (!clickedOnSearch && !clickedOnResults) {
                    resultadosEditar.style.display = 'none';
                }
            });

            // 🔥 Prevenir cierre al hacer clic en los resultados
            resultadosEditar.addEventListener('click', function (e) {
                e.stopPropagation();
            });

            // 🔥 Cargar productos
            function cargarProductosEditar(term) {
                console.log('🔄 Buscando productos:', term);
                // 🔥 RUTA CORRECTA - misma carpeta
                fetch(`buscar_productos.php?term=${encodeURIComponent(term)}&limit=20`)
                    .then(response => {
                        console.log('📡 Respuesta:', response.status);
                        if (!response.ok) {
                            throw new Error('Error en la respuesta: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(productos => {
                        console.log('📦 Productos encontrados:', productos.length);
                        mostrarResultadosEditar(productos);
                    })
                    .catch(err => {
                        console.error('❌ Error:', err);
                        resultadosEditar.innerHTML = `
                            <div style="padding:15px; color:#dc3545; text-align:center;">
                                ❌ Error al cargar productos<br>
                                <small>${err.message}</small><br>
                                <button onclick="cargarProductosEditar('${term}')" 
                                    style="margin-top:10px; padding:5px 15px; background:#667eea; color:white; border:none; border-radius:4px; cursor:pointer;">
                                    🔄 Reintentar
                                </button>
                            </div>`;
                        resultadosEditar.style.display = 'block';
                    });
            }

            // 🔥 Mostrar resultados
            function mostrarResultadosEditar(productos) {
                console.log('📦 Mostrando resultados:', productos.length);
                if (!productos || productos.length === 0) {
                    resultadosEditar.innerHTML = `
                        <div style="padding:15px; color:#666; text-align:center;">
                            📦 No se encontraron productos<br>
                            <small>Intenta con otro código o nombre</small>
                        </div>`;
                    resultadosEditar.style.display = 'block';
                    return;
                }

                let html = '';
                productos.forEach(prod => {
                    const imagenUrl = prod.imagen && prod.imagen !== '' && prod.imagen !== 'null'
                        ? `../../img/PRODUCTOS/${prod.imagen}`
                        : '../../img/PRODUCTOS/sin-imagen.png';
                    const stockColor = prod.cantidad <= 10 ? '#dc3545' : '#28a745';
                    const stockBajo = prod.cantidad <= 10 ? '⚠️ Stock bajo' : '✅ Disponible';

                    html += `
                        <div class="producto-busqueda-item-editar"
                            data-codigo="${prod.codigo_barra}"
                            data-nombre="${prod.nombre_producto}"
                            data-precio="${prod.precio_venta}"
                            data-stock="${prod.cantidad}"
                            data-imagen="${prod.imagen || ''}"
                            style="display:flex; align-items:center; gap:12px; padding:12px 15px;
                                cursor:pointer; transition:background 0.2s; border-bottom:1px solid #f0f0f0;"
                            onmouseover="this.style.background='#f8f9fa'"
                            onmouseout="this.style.background='white'">
                            <!-- IMAGEN DEL PRODUCTO -->
                            <img src="${imagenUrl}"
                                alt="${prod.nombre_producto}"
                                style="width:50px; height:50px; object-fit:contain; border-radius:6px; border:1px solid #ddd; background:#fff;">
                            <!-- INFORMACIÓN -->
                            <div style="flex:1;">
                                <div style="font-weight:600; color:#333; margin-bottom:4px; font-size:14px;">
                                    ${prod.nombre_producto}
                                </div>
                                <div style="font-size:11px; color:#666;">
                                    <strong>Cód:</strong> ${prod.codigo_barra}
                                </div>
                                <div style="font-size:11px; color:${stockColor}; font-weight:600; margin-top:3px;">
                                    ${stockBajo} - 📦 ${prod.cantidad} unidades
                                </div>
                            </div>
                            <!-- PRECIO -->
                            <div style="text-align:right;">
                                <div style="font-weight:bold; color:#0d6efd; font-size:16px;">
                                    $${prod.precio_venta.toFixed(2)}
                                </div>
                            </div>
                        </div>`;
                });

                resultadosEditar.innerHTML = html;
                resultadosEditar.style.display = 'block';

                // 🔥 Agregar event listeners a los items
                document.querySelectorAll('.producto-busqueda-item-editar').forEach(item => {
                    item.addEventListener('click', function () {
                        console.log('🖱️ Producto seleccionado:', this.dataset.nombre);
                        seleccionarProductoEditar(this);
                    });
                });
            }

            // 🔥 Seleccionar producto
            function seleccionarProductoEditar(item) {
                const codigo = item.dataset.codigo;
                const nombre = item.dataset.nombre;
                const precio = item.dataset.precio;
                const stock = item.dataset.stock;
                const imagen = item.dataset.imagen || '';

                console.log('✅ Producto seleccionado:', { codigo, nombre, precio, stock, imagen });

                // 🔥 Llenar inputs ocultos
                document.getElementById('codigoProductoEditar').value = codigo;
                document.getElementById('precioProductoEditarHidden').value = precio;
                document.getElementById('stockProductoEditarHidden').value = stock;
                document.getElementById('imagenProductoEditarHidden').value = imagen;

                // 🔥 Mostrar información del producto
                mostrarInfoProductoEditar(nombre, precio, stock, imagen);

                // 🔥 Cerrar dropdown
                resultadosEditar.style.display = 'none';
                buscadorEditar.value = `${nombre} (Cód: ${codigo})`;

                // 🔥 Enfocar cantidad
                document.getElementById('cantidadProductoEditar').value = '1';
                document.getElementById('cantidadProductoEditar').focus();
                document.getElementById('cantidadProductoEditar').select();
            }

            // 🔥 Mostrar información del producto seleccionado
            function mostrarInfoProductoEditar(nombre, precio, stock, imagen) {
                const infoDiv = document.getElementById('infoProductoSeleccionado');
                const imgElement = document.getElementById('imagenProductoEditar');
                const nombreElement = document.getElementById('nombreProductoEditar');
                const stockElement = document.getElementById('stockValorEditar');
                const precioElement = document.getElementById('precioProductoEditar');

                // 🔥 IMAGEN
                if (imagen && imagen !== '' && imagen !== 'null') {
                    imgElement.src = `../../img/PRODUCTOS/${imagen}`;
                } else {
                    imgElement.src = '../../img/PRODUCTOS/sin-imagen.png';
                }
                imgElement.style.display = 'block';

                // 🔥 NOMBRE
                nombreElement.textContent = nombre;

                // 🔥 STOCK
                stockElement.textContent = stock;
                stockElement.style.color = stock <= 10 ? '#dc3545' : '#28a745';

                // 🔥 PRECIO
                precioElement.textContent = '$' + parseFloat(precio).toFixed(2);

                // 🔥 MOSTRAR EL DIV
                infoDiv.style.display = 'block';
            }

            console.log('✅ Buscador inicializado correctamente - Solo busca al escribir');
        }

        // 🔥 Agregar producto al ticket
        function agregarProductoAlTicketEditar() {
            const codigo = document.getElementById('codigoProductoEditar').value;
            const nombre = document.getElementById('nombreProductoEditar').textContent;
            const precio = parseFloat(document.getElementById('precioProductoEditarHidden').value) || 0;
            const stock = parseInt(document.getElementById('stockProductoEditarHidden').value) || 0;
            const cantidad = parseInt(document.getElementById('cantidadProductoEditar').value) || 1;

            console.log('🔍 Intentando agregar:', { codigo, nombre, precio, stock, cantidad });

            // 🔥 VALIDAR QUE SE HAYA SELECCIONADO UN PRODUCTO
            if (!codigo || codigo === '' || codigo === '0') {
                alert('⚠️ Primero busca y selecciona un producto de la lista\n\n1. Escribe el código de barras o nombre\n2. Haz CLIC en el producto que aparece\n3. Luego presiona "Agregar"');
                return;
            }

            // 🔥 VALIDAR STOCK
            if (cantidad > stock) {
                alert(`⚠️ No hay suficiente stock.\nDisponible: ${stock}\nSolicitado: ${cantidad}`);
                return;
            }

            // 🔥 VERIFICAR SI YA ESTÁ EN EL TICKET
            const filaExistente = document.querySelector(`#tbodyProductosEditar tr[data-codigo="${codigo}"]`);
            if (filaExistente) {
                if (confirm('⚠️ Este producto ya está en el ticket.\n¿Deseas aumentar la cantidad?')) {
                    const inputCantidad = filaExistente.querySelector('.input-cantidad-editar');
                    const cantidadActual = parseFloat(inputCantidad.value) || 0;
                    const nuevaCantidad = cantidadActual + cantidad;

                    // Validar stock total
                    if (nuevaCantidad > stock) {
                        alert(`⚠️ No hay suficiente stock.\nDisponible: ${stock}\nTotal solicitado: ${nuevaCantidad}`);
                        return;
                    }

                    inputCantidad.value = nuevaCantidad;
                    actualizarFilaEditar(inputCantidad);
                    calcularTotalEditarTicket();
                    alert('✅ Cantidad actualizada correctamente');

                    // Limpiar buscador
                    document.getElementById('codigoProductoEditar').value = '';
                    document.getElementById('buscadorProductosEditar').value = '';
                    document.getElementById('cantidadProductoEditar').value = '1';
                    document.getElementById('infoProductoSeleccionado').style.display = 'none';
                    document.getElementById('buscadorProductosEditar').focus();
                    return;
                } else {
                    return;
                }
            }

            // 🔥 AGREGAR FILA A LA TABLA
            const tbody = document.getElementById('tbodyProductosEditar');
            const nuevaFila = document.createElement('tr');
            const subtotal = cantidad * precio;

            nuevaFila.dataset.codigo = codigo;
            nuevaFila.dataset.id = 'nuevo';
            nuevaFila.innerHTML = `
                <td>${codigo}</td>
                <td>${nombre}</td>
                <td>
                    <input type="number" class="input-cantidad-editar" value="${cantidad}"
                        min="0.01" step="0.01" data-precio="${precio}"
                        style="width:80px; padding:5px; border:1px solid #ddd; border-radius:4px;">
                </td>
                <td>$${precio.toFixed(2)}</td>
                <td class="subtotal-editar">$${(cantidad * precio).toFixed(2)}</td>
                <!-- 🔥 AGREGA ESTO: Input oculto para el precio -->
                <input type="hidden" class="precio-venta-oculto" value="${precio}">
                <td>
                    <button type="button" class="btn-eliminar-producto"
                        onclick="eliminarProductoDelTicket(this, 'nuevo')"
                        title="Eliminar producto">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(nuevaFila);
            calcularTotalEditarTicket();

            // 🔥 LIMPIAR PARA SIGUIENTE PRODUCTO
            document.getElementById('codigoProductoEditar').value = '';
            document.getElementById('buscadorProductosEditar').value = '';
            document.getElementById('cantidadProductoEditar').value = '1';
            document.getElementById('infoProductoSeleccionado').style.display = 'none';
            document.getElementById('buscadorProductosEditar').focus();

            alert('✅ Producto agregado correctamente');
        }

        // Enter en buscador → enfocar cantidad
        if (buscadorEditar) {
            buscadorEditar.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('cantidadProductoEditar').focus();
                    document.getElementById('cantidadProductoEditar').select();
                }
            });
        }

        // Enter en cantidad → agregar producto
        document.getElementById('cantidadProductoEditar').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                agregarProductoAlTicketEditar();
            }
        });

        // ============================================
        // 🔥 ACTUALIZAR SUBTOTALES Y TOTAL
        // ============================================
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.input-cantidad-editar').forEach(input => {
                input.addEventListener('change', function () {
                    actualizarFilaEditar(this);
                    calcularTotalEditarTicket();
                });

                // 🔥 AGREGA ESTO - Actualizar mientras escribe
                input.addEventListener('input', function () {
                    actualizarFilaEditar(this);
                    calcularTotalEditarTicket();
                });
            });
        });

        function actualizarFilaEditar(input) {
            const row = input.closest('tr');
            const precioOculta = row.querySelector('.precio-venta-oculto');
            const precio = parseFloat(precioOculta.value) || 0;
            const cantidad = parseFloat(input.value) || 0;
            const subtotal = cantidad * precio;

            // 🔥 ACTUALIZAR SUBTOTAL
            row.querySelector('.subtotal-editar').textContent = '$' + subtotal.toFixed(2);

            // 🔥 ACTUALIZAR EL DATA-PRECIO TAMBIÉN
            input.dataset.precio = precio;
        }

        function calcularTotalEditarTicket() {
            let total = 0;
            document.querySelectorAll('.subtotal-editar').forEach(cell => {
                const text = cell.textContent.replace('$', '').replace(',', '');
                total += parseFloat(text) || 0;
            });
            document.getElementById('totalEditarTicket').textContent = '$' + total.toFixed(2);
        }

        // ============================================
        // 🔥 ELIMINAR PRODUCTO DEL TICKET
        // ============================================
        function eliminarProductoDelTicket(btn, idDetalle) {
            if (confirm('¿Eliminar este producto del ticket?\n\n⚠️ El stock se regresará al inventario')) {
                const row = btn.closest('tr');
                const codigo = row.dataset.codigo;
                const cantidad = parseFloat(row.querySelector('.input-cantidad-editar').value) || 0;

                // 🔥 REGRESAR STOCK AL INVENTARIO
                fetch('regresar_stock_producto.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        codigo_barra: codigo,
                        cantidad: cantidad,
                        id_detalle: idDetalle
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            row.remove();
                            calcularTotalEditarTicket();
                            alert('✅ Producto eliminado y stock regresado al inventario');
                        } else {
                            alert('❌ Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('❌ Error de conexión');
                    });
            }
        }

        // ============================================
        // 🔥 GUARDAR CAMBIOS DEL TICKET
        // ============================================
        async function guardarCambiosTicket() {
            const productos = [];
            const folio = '<?= htmlspecialchars($venta['folio'] ?? '') ?>';
            const ventaId = <?= $venta['id'] ?? 0 ?>;
            console.log('🔍 Iniciando guardado...');
            console.log('Folio:', folio);
            console.log('Venta ID:', ventaId);
            document.querySelectorAll('#tbodyProductosEditar tr').forEach(row => {
                const codigo = row.dataset.codigo;
                const idDetalle = row.dataset.id;
                const cantidad = parseFloat(row.querySelector('.input-cantidad-editar').value) || 0;
                const precio = parseFloat(row.querySelector('.input-precio-editar')?.value) ||
                    parseFloat(row.querySelector('.precio-venta-oculto')?.value) || 0;
                if (cantidad > 0) {
                    productos.push({
                        id_detalle: idDetalle,
                        codigo: codigo,
                        cantidad: cantidad,
                        precio: precio,
                        subtotal: cantidad * precio
                    });
                }
            });
            console.log('📦 Productos a actualizar:', productos.length);
            if (productos.length === 0) {
                alert('❌ Debe haber al menos un producto en el ticket');
                return;
            }
            if (confirm('¿Confirmar cambios en el ticket?\nSe actualizarán:\n• Cantidades\n• Precios\n• Totales\n• Inventario')) {
                // 🔥 MOSTRAR INDICADOR DE CARGA
                const btnGuardar = document.querySelector('.btn-guardar-cambios');
                const textoOriginal = btnGuardar.innerHTML;
                btnGuardar.disabled = true;
                btnGuardar.innerHTML = '<i class="bi bi-hourglass-split"></i> Guardando...';
                // Enviar datos al servidor
                fetch('actualizar_ticket_venta.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        folio: folio,
                        venta_id: ventaId,
                        productos: productos
                    })
                })
                    .then(response => {
                        console.log('📡 Respuesta del servidor:', response);
                        return response.json();
                    })
                    .then(data => {
                        console.log('📊 Datos recibidos:', data);
                        if (data.success) {
                            alert('✅ ' + data.message);
                            cerrarModalEditarTicket();
                            // Recargar página para ver cambios
                            window.location.reload();
                        } else {
                            alert('❌ Error: ' + data.message);
                            btnGuardar.disabled = false;
                            btnGuardar.innerHTML = textoOriginal;
                        }
                    })
                    .catch(error => {
                        console.error('❌ Error:', error);
                        alert('❌ Error de conexión. Revisa la consola (F12).');
                        btnGuardar.disabled = false;
                        btnGuardar.innerHTML = textoOriginal;
                    });
            }
        }
    </script>

    <!-- 🔥 MODAL PARA EDITAR TICKET -->
    <div id="modalEditarTicket" class="modal-editar-ticket" style="display:none;">
        <div class="modal-content-editar">
            <div class="modal-header-editar">
                <h2>✏️ Editar Ticket <?= htmlspecialchars($venta['folio'] ?? '') ?></h2>
                <button type="button" class="btn-cerrar-modal" onclick="cerrarModalEditarTicket()">✕</button>
            </div>
            <div class="modal-body-editar">
                <!-- Información del Ticket -->
                <div class="info-ticket-editar">
                    <div><strong>👤 Vendedor:</strong> <?= htmlspecialchars($venta['vendedor'] ?? 'N/A') ?></div>
                    <div><strong>📅 Fecha:</strong>
                        <?= date('d/m/Y g:i A', strtotime($venta['fecha_venta'] ?? 'now')) ?></div>
                    <div><strong>🎫 Folio:</strong> <?= htmlspecialchars($venta['folio'] ?? '') ?></div>
                </div>

                <!-- 🔥 INPUTS OCULTOS PARA PRODUCTO -->
                <input type="hidden" id="codigoProductoEditar" value="">
                <input type="hidden" id="precioProductoEditarHidden" value="">
                <input type="hidden" id="stockProductoEditarHidden" value="">
                <input type="hidden" id="imagenProductoEditarHidden" value="">

                <!-- 🔥 BUSCADOR DE PRODUCTOS PARA AGREGAR -->
                <div style="background:#f8f9fa; padding:20px; border-radius:10px; margin-bottom:20px;">
                    <div style="display:flex; gap:15px; align-items:center; flex-wrap:wrap;">
                        <!-- Input de búsqueda -->
                        <div style="flex:1; min-width:300px; position:relative;">
                            <label style="display:block; margin-bottom:5px; font-weight:600;">🔍 Escanear/Buscar
                                Producto:</label>
                            <input type="text" id="buscadorProductosEditar"
                                placeholder="Escanea o escribe el código/nombre..."
                                style="width:100%; padding:12px 40px 12px 15px; border:2px solid #ddd; border-radius:8px; font-size:14px;">
                            <i class="bi bi-search"
                                style="position:absolute; right:15px; top:42px; color:#666; font-size:18px;"></i>
                            <!-- Dropdown de resultados -->
                            <div id="resultadosBusquedaEditar" style="display:none;"></div>
                        </div>
                        <!-- Cantidad -->
                        <div style="width:120px;">
                            <label style="display:block; margin-bottom:5px; font-weight:600;">Cantidad:</label>
                            <input type="number" id="cantidadProductoEditar" value="1" min="1"
                                style="width:100%; padding:12px; border:2px solid #ddd; border-radius:8px; font-size:14px;">
                        </div>
                        <!-- Botón Agregar -->
                        <div>
                            <label style="display:block; margin-bottom:5px; font-weight:600;">&nbsp;</label>
                            <button type="button" onclick="agregarProductoAlTicketEditar()" style="background:linear-gradient(135deg, #28a745 0%, #20c997 100%); color:white;
                            border:none; padding:12px 25px; border-radius:8px; font-weight:600; cursor:pointer;">
                                <i class="bi bi-plus-lg"></i> Agregar
                            </button>
                        </div>
                    </div>
                    <!-- 🔥 IMAGEN Y STOCK DEL PRODUCTO SELECCIONADO -->
                    <div id="infoProductoSeleccionado" style="display:none; margin-top:15px; padding:15px; background:white;
                    border-radius:8px; border-left:4px solid #667eea;">
                        <div style="display:flex; gap:15px; align-items:center;">
                            <img id="imagenProductoEditar" src="" alt="Producto"
                                style="width:80px; height:80px; object-fit:contain; border:1px solid #ddd; border-radius:8px; padding:5px;">
                            <div>
                                <div id="nombreProductoEditar"
                                    style="font-weight:600; font-size:16px; margin-bottom:5px;"></div>
                                <div id="stockProductoEditar" style="color:#28a745; font-weight:600;">
                                    📦 Stock: <span id="stockValorEditar">0</span> unidades
                                </div>
                                <div id="precioProductoEditar" style="color:#667eea; font-weight:600; font-size:18px;">
                                    $0.00
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Productos Editables -->
                <table class="tabla-editar-ticket">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>P. Venta</th>
                            <th>Subtotal</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyProductosEditar">
                        <?php
                        if ($venta && $detalle) {
                            mysqli_data_seek($detalle, 0); // Reiniciar resultado
                            while ($row = mysqli_fetch_assoc($detalle)):
                                ?>
                                <tr data-codigo="<?= $row['codigo_barra'] ?>" data-id="<?= $row['id'] ?>">
                                    <td><?= htmlspecialchars($row['codigo_barra']) ?></td>
                                    <td><?= htmlspecialchars($row['nombre_producto'] ?? $row['codigo_barra']) ?></td>
                                    <td>
                                        <input type="number" class="input-cantidad-editar" value="<?= $row['cantidad'] ?>"
                                            min="0.01" step="0.01" data-precio="<?= $row['precio_venta'] ?>"
                                            style="width:80px; padding:5px; border:1px solid #ddd; border-radius:4px;">
                                    </td>
                                    <td>
                                        $<?= number_format($row['precio_venta'], 2) ?>
                                        <input type="hidden" class="precio-venta-oculto" value="<?= $row['precio_venta'] ?>">
                                    </td>
                                    <td class="subtotal-editar">$<?= number_format($row['subtotal'], 2) ?></td>
                                    <td>
                                        <button type="button" class="btn-eliminar-producto"
                                            onclick="eliminarProductoDelTicket(this, '<?= $row['id'] ?>')"
                                            title="Eliminar producto">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php
                            endwhile;
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight:bold; background:#e7f3ff;">
                            <td colspan="4" style="text-align:right;">Total:</td>
                            <td id="totalEditarTicket">$<?= number_format($venta['total_venta'] ?? 0, 2) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>

                <!-- Botones de Acción -->
                <div class="modal-footer-editar">
                    <button type="button" onclick="guardarCambiosTicket()" class="btn-guardar-cambios">
                        <i class="bi bi-check-lg"></i> Guardar Cambios
                    </button>
                    <button type="button" onclick="cerrarModalEditarTicket()" class="btn-cancelar-cambios">
                        <i class="bi bi-x-lg"></i> Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

</body>

</html>