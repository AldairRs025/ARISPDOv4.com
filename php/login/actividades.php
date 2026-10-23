<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../../index.html");
    exit;
}
include("../conexion.php");

// 🔥 FILTROS
$filtro_usuario = $_GET['usuario'] ?? '';
$filtro_accion = $_GET['accion'] ?? '';
$filtro_fecha = $_GET['fecha'] ?? '';

$where = [];
if ($filtro_usuario)
    $where[] = "username = '" . mysqli_real_escape_string($conn, $filtro_usuario) . "'";
if ($filtro_accion)
    $where[] = "accion = '" . mysqli_real_escape_string($conn, $filtro_accion) . "'";
if ($filtro_fecha)
    $where[] = "DATE(fecha) = '" . mysqli_real_escape_string($conn, $filtro_fecha) . "'";

$sql = "SELECT * FROM actividades " . (!empty($where) ? "WHERE " . implode(' AND ', $where) : "") . " ORDER BY fecha DESC LIMIT 100";
$actividades = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>📋 Actividades - Los Nietos®</title>
    <link rel="stylesheet" href="../../css/AdminPanel-STYLE/dash.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .actividades-container {
            margin: 100px 5% 50px;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
        }

        .tabla-actividades {
            width: 100%;
            border-collapse: collapse;
        }

        .tabla-actividades th,
        .tabla-actividades td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .tabla-actividades th {
            background: #333;
            color: white;
        }

        .accion-LOGIN {
            color: #28a745;
            font-weight: bold;
        }

        .accion-LOGOUT {
            color: #17a2b8;
            font-weight: bold;
        }

        .accion-CREATE {
            color: #0d6efd;
            font-weight: bold;
        }

        .accion-UPDATE {
            color: #ffc107;
            font-weight: bold;
        }

        .accion-DELETE {
            color: #dc3545;
            font-weight: bold;
        }

        .filtro-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filtro-form input,
        .filtro-form select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <!-- Sidebar (puedes copiarlo de dashboard_admin.php) -->

    <div class="actividades-container">
        <h1>📋 Registro de Actividades</h1>

        <!-- Filtros -->
        <form method="GET" class="filtro-form">
            <input type="text" name="usuario" placeholder="Usuario" value="<?= htmlspecialchars($filtro_usuario) ?>">
            <select name="accion">
                <option value="">Todas las acciones</option>
                <option value="LOGIN" <?= $filtro_accion == 'LOGIN' ? 'selected' : '' ?>>Login</option>
                <option value="LOGOUT" <?= $filtro_accion == 'LOGOUT' ? 'selected' : '' ?>>Logout</option>
                <option value="CREATE" <?= $filtro_accion == 'CREATE' ? 'selected' : '' ?>>Crear</option>
                <option value="UPDATE" <?= $filtro_accion == 'UPDATE' ? 'selected' : '' ?>>Actualizar</option>
                <option value="DELETE" <?= $filtro_accion == 'DELETE' ? 'selected' : '' ?>>Eliminar</option>
            </select>
            <input type="date" name="fecha" value="<?= htmlspecialchars($filtro_fecha) ?>">
            <button type="submit"
                style="padding: 8px 15px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">Filtrar</button>
            <a href="actividades.php"
                style="padding: 8px 15px; background: #dc3545; color: white; text-decoration: none; border-radius: 4px;">Limpiar</a>
        </form>

        <!-- Tabla -->
        <table class="tabla-actividades">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                    <th>Descripción</th>
                    <th>Tabla</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($act = mysqli_fetch_assoc($actividades)): ?>
                    <tr>
                        <td><?= date('d/m/Y h:i A', strtotime($act['fecha'])) ?></td>
                        <td><?= htmlspecialchars($act['username']) ?></td>
                        <td class="accion-<?= $act['accion'] ?>"><?= $act['accion'] ?></td>
                        <td><?= htmlspecialchars($act['descripcion']) ?></td>
                        <td><?= $act['tabla_afectada'] ?></td>
                        <td><?= $act['ip_address'] ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>

</html>