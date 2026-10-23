<?php
session_start();

// Si no hay sesión, volver al login
if (!isset($_SESSION["user_id"])) {
    header("Location: index.html");
    exit;
}

// Si no es empleado, redirigir
if ($_SESSION["rol"] !== "empleado") {
    header("Location: dashboard_admin.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Empleado</title>
</head>
<body>
    <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION["username"]); ?> 👷</h1>
    <p>Este es el panel para empleados.</p>
    <a href="logout.php">Cerrar sesión</a>
</body>
</html>
